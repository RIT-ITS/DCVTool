<?php

declare(strict_types=1);

namespace App\Services\Transfer;

use App\Database\DatabaseManager;
use App\Services\Reference\Academic\ClassService;
use App\Services\Reference\Academic\SemesterService;
use App\Services\Reference\Spaces\BuildingService;
use App\Services\Reference\Spaces\RoomService;
use App\Services\Reference\Spaces\ZoneService;
use App\Utilities\Utilities;
use App\Services\Configuration\ConfigService;
use App\Services\Logging\LogService;
use DateTime;
use DateTimeZone;

/**
 * Service for transferring and transforming data between systems
 */
class TransferService
{
    private Utilities $utilities;
    private ConfigService $configService;
    private SemesterService $semesterService;
    private BuildingService $buildingService;
    private RoomService $roomService;
    private ClassService $classService;
    private DatabaseManager $dbManager;
    private ZoneService $zoneService;
    protected LogService $logService;

    /**
     * Constructor with dependency injection
     *
     * @param Utilities $utilities
     * @param SemesterService $semesterService
     * @param BuildingService $buildingService
     * @param RoomService $roomService
     * @param ClassService $classService
     * @param DatabaseManager $dbManager
     * @param ZoneService $zoneService
     * @param ConfigService|null $configService
     * @param LogService|null $logService
     */
    public function __construct(
        Utilities $utilities,
        SemesterService $semesterService,
        BuildingService $buildingService,
        RoomService $roomService,
        ClassService $classService,
        DatabaseManager $dbManager,
        ZoneService $zoneService,
        ?ConfigService $configService = null,
        ?LogService $logService = null
    ) {
        $this->utilities = $utilities;
        $this->semesterService = $semesterService;
        $this->buildingService = $buildingService;
        $this->roomService = $roomService;
        $this->classService = $classService;
        $this->dbManager = $dbManager;
        $this->zoneService = $zoneService;

        // Use injected services if provided, otherwise create new instances
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $utilities, $this->configService);
    }

    /**
     * Convert SIS data in class_schedule_data table to daily schedules, by building.
     *
     * This method uses the config variable `expandClassesForActiveRoomsOnly` to decide
     * whether to process ALL classes in ALL rooms, or just classes for `active` rooms
     *
     * @param int $buildingId The building ID to process
     * @param int $chooseSemester The semester to process (0 for current/upcoming)
     * @return bool True if successful, false otherwise
     */
    public function convertSemesterScheduleToDaily(int $buildingId = 56, int $chooseSemester = 0): bool
    {
        $tag = "convertSemesterSchedToDaily";
        $this->logService->dataLog("Data transaction(s) starting.", $tag);

        // Determine semester if not provided
        $term = $chooseSemester > 0
            ? $this->utilities->sanitizeIntegerInput($chooseSemester)
            : $this->semesterService->determineSemester();

        if ($term <= 0) {
            $this->logService->dataLog("Invalid semester: {$term}", $tag);
            return false;
        }

        $this->logService->dataLog("Processing data for semester {$term}.", $tag);

        // Get configuration settings
        $configSettings = $this->configService->readConfigVars("3", true);
        $timeZoneConfig = $this->configService->readConfigVars("0", true);

        $detailedLogging = true; // Always use detailed logging for now
        $sourceServerTZ = $timeZoneConfig['SISServerTZ'] ?? 'America/New_York';
        $retrieveActiveOnly = ($configSettings["expandClassesForActiveRoomsOnly"] ?? "off") === "on";

        // Get building information
        $buildingInfo = $this->buildingService->readOneBuilding($buildingId, true, true);

        if (empty($buildingInfo)) {
            $this->logService->dataLog("Building ID {$buildingId} not found.", $tag);
            return false;
        }

        $facilityCode = $buildingInfo[0]['facility_code'] ?? null;

        if (empty($facilityCode)) {
            $this->logService->dataLog("Facility code not found for building ID {$buildingId}.", $tag);
            return false;
        }

        $this->logService->dataLog("Retrieving SIS class data for semester {$term}.", $tag);

        // Retrieve all class schedules for the building
        $classesToProcess = $this->classService->readAllClassSchedules($term, true, $facilityCode);

        if (empty($classesToProcess)) {
            $this->logService->dataLog("No classes found for semester {$term} in building {$buildingId}.", $tag);
            return true; // Return true as this is not an error condition, just no work to do
        }

        // Get recently processed classes to avoid unnecessary updates
        $recentlyProcessedClasses = $this->getRecentlyProcessedClasses($term);

        // Process classes
        $processedClassesCount = 0;
        $processedClassesLimit = 10000;

        foreach ($classesToProcess as $class) {
            // Skip classes that have been processed recently
            if (in_array($class['pp_search_id'], $recentlyProcessedClasses)) {
                if ($detailedLogging) {
                    $this->logService->dataLog("pp_search_id {$class['pp_search_id']} has been processed recently. Skipping.", $tag);
                }
                continue;
            }

            // Process the class
            if ($detailedLogging) {
                $this->logService->dataLog("Calling processClass for {$class['pp_search_id']}.", $tag);
            }

            $classEventsProcessed = $this->processClass($class, $sourceServerTZ);

            // Increment the counter
            $processedClassesCount += $classEventsProcessed;

            // If we have processed enough classes, stop processing
            if ($processedClassesCount >= $processedClassesLimit) {
                $this->logService->dataLog("ProcessClasses called {$processedClassesLimit} times. Stopping.", $tag);
                break;
            }
        }

        $this->logService->dataLog("Processed {$processedClassesCount} class events for semester {$term}.", $tag);
        return true;
    }

    /**
     * Get a list of classes that have been processed recently (within the last 2 hours)
     *
     * @param int $semester The semester to check
     * @return array Array of pp_search_id values for recently processed classes
     */
    public function getRecentlyProcessedClasses(int $semester): array
    {
        $tag = "getRecentlyProcessedClasses";

        try {
            $query = "SELECT pp_search_id 
                      FROM expanded_schedule_data 
                      WHERE strm = :strm 
                      AND last_updated > now() - interval '2 hours' 
                      ORDER BY pp_search_id ASC";

            // Get the database connection from the DatabaseManager
            $conn = $this->dbManager->getDefaultConnection();

            // Prepare and execute the query
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":strm", $semester);
            $stmt->execute();

            // Fetch all results as a simple array of pp_search_id values
            $recentlyProcessedIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // Ensure we always return an array, even if no results
            if (empty($recentlyProcessedIds)) {
                $recentlyProcessedIds = [];
            }

            $this->logService->dataLog("Found " . count($recentlyProcessedIds) . " recently processed classes for semester {$semester}.", $tag);

            return $recentlyProcessedIds;

        } catch (\PDOException $e) {
            // Log the exception
            $this->logService->dataLog("Database error: " . $e->getMessage(), $tag);

            // Return an empty array on error
            return [];
        }
    }

    /**
     * Process a single class into daily schedule events
     *
     * @param array $class The class data to process
     * @param string $sourceServerTZ The timezone of the source server
     * @return int Number of events processed
     */
    public function processClass(array $class, string $sourceServerTZ): int
    {
        $whatFunction = "convertSemesterSchedToDaily-processClass";

        // Retrieve corresponding class data from expanded_schedule_data via ClassService
        $expandedClassData = $this->classService->getExpandedClassData($class['pp_search_id'], $class['strm']);

        // Check if the enrollment total has changed
        $currentEnrlTot = $class['enrl_tot'];
        foreach ($expandedClassData as $expandedClass) {
            if ($currentEnrlTot != $expandedClass['enrl_tot']) {
                if ($this->logService->isDetailedLoggingEnabled()) {
                    $this->logService->dataLog($whatFunction . " - enrl_tot in sis class record differs from existing expanded data...updating for " . $class['pp_search_id'] . ".", $whatFunction);
                }
                $this->classService->updateEnrollmentTotal($class['pp_search_id'], $class['strm'], $currentEnrlTot);
                break;
            }
        }

        // Generate an array of dates that match the days of the week that the class meets
        if ($this->logService->isDetailedLoggingEnabled()) {
            $this->logService->dataLog($whatFunction . " - Calculating class dates for class " . $class['pp_search_id'] . ".", $whatFunction);
        }
        $classDates = $this->classService->generateClassDates($class);

        // Get the last processed date for this class and strm from the progress_tracker table
        $lastProcessed = $this->getLastProcessedDate($class['pp_search_id'], $class['strm']);
        // FIX: Handle null value for $lastProcessed
        if ($lastProcessed === null) {
            // Use a date in the past to process all dates
            $lastProcessedDate = new \DateTime('1970-01-01');
            if ($this->logService->isDetailedLoggingEnabled()) {
                $this->logService->dataLog($whatFunction . " - No last processed date found for " . $class['pp_search_id'] . ". Processing all dates.", $whatFunction);
            }
        } else {
            $lastProcessedDate = new \DateTime($lastProcessed);
        }

        if ($this->logService->isDetailedLoggingEnabled()) {
            $this->logService->dataLog($whatFunction . " - Iterating through dates for class " . $class['pp_search_id'] . ".", $whatFunction);
        }

        $classEventsProcessed = 0;
        $classEventsSkippedCount = 0;
        foreach ($classDates as $classDate) {
            $classDateDT = new \DateTime($classDate);
            if ($classDateDT <= $lastProcessedDate) {
                $classEventsSkippedCount++;
                continue;
            } else {
                $classEventsProcessed++;
            }
            $this->classService->upsertClassData($class, $classDate, $sourceServerTZ);
            $this->updateProgressTracker($class['pp_search_id'], $classDate, $class['strm']);
        }

        if ($this->logService->isDetailedLoggingEnabled()) {
            $this->logService->dataLog(
                $whatFunction . " - Skipped " . $classEventsSkippedCount . " dates for " . $class['pp_search_id'] . " because they were less than " . $lastProcessed . ".",
                $whatFunction
            );
            $this->logService->dataLog(
                $whatFunction . " - Processed " . $classEventsProcessed . " dates for " . $class['pp_search_id'] . ".",
                $whatFunction
            );
        }
        return $classEventsProcessed;
    }

    /**
     * Get the date when a class was last processed
     *
     * @param string $ppSearchId The pp_search_id of the class
     * @param int $semester The semester to check
     * @return string|null The last processed date in Y-m-d format, or null if not found
     */
    public function getLastProcessedDate(string $ppSearchId, int $semester): ?string
    {
        $tag = "getLastProcessedDate";

        try {
            $query = "SELECT MAX(last_updated) as last_processed 
                      FROM expanded_schedule_data 
                      WHERE pp_search_id = :ppsearchid 
                      AND strm = :strm";

            // Get the database connection from the DatabaseManager
            $conn = $this->dbManager->getDefaultConnection();

            // Prepare and execute the query
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":ppsearchid", $ppSearchId);
            $stmt->bindParam(":strm", $semester);
            $stmt->execute();

            // Fetch the result
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && isset($result['last_processed'])) {
                $lastProcessed = $result['last_processed'];

                // Convert to Y-m-d format if not null
                if ($lastProcessed) {
                    $date = new \DateTime($lastProcessed);
                    $formattedDate = $date->format('Y-m-d');

                    $this->logService->dataLog(
                        "Last processed date for class {$ppSearchId} in semester {$semester} is {$formattedDate}.",
                        $tag
                    );

                    return $formattedDate;
                }
            }

            $this->logService->dataLog(
                "No processing history found for class {$ppSearchId} in semester {$semester}.",
                $tag
            );

            return null;

        } catch (\PDOException $e) {
            // Log the exception
            $this->logService->dataLog("Database error: " . $e->getMessage(), $tag);

            // Return null on error
            return null;
        } catch (\Exception $e) {
            // Log other exceptions (like DateTime errors)
            $this->logService->dataLog("Error processing date: " . $e->getMessage(), $tag);

            // Return null on error
            return null;
        }
    }


    /**
     * Update the progress tracker for a class
     *
     * @param string $ppSearchId The pp_search_id of the class
     * @param string $lastProcessedDate The date when the class was last processed
     * @param int $semester The semester
     * @return bool True if successful, false on error
     */
    public function updateProgressTracker(string $ppSearchId, string $lastProcessedDate, int $semester): bool
    {
        $tag = "updateProgressTracker";

        try {
            // Get database connection
            $conn = $this->dbManager->getDefaultConnection();

            // Prepare the upsert query
            $upsertQuery = "
                INSERT INTO class_expanded_progress (pp_search_id, last_processed, strm) 
                VALUES (:pp_search_id, :last_processed_date, :strm)
                ON CONFLICT (pp_search_id, strm) 
                DO UPDATE SET last_processed = :last_processed_date;
            ";

            // Prepare parameters
            $params = [
                ':pp_search_id' => $ppSearchId,
                ':last_processed_date' => $lastProcessedDate,
                ':strm' => $semester
            ];

            // Execute the query
            $stmtUpsert = $conn->prepare($upsertQuery);
            $stmtUpsert->execute($params);

            // Get affected rows
            $affectedRows = $stmtUpsert->rowCount();

            $this->logService->dataLog(
                "Updated progress tracker for class {$ppSearchId} in semester {$semester}. Affected rows: {$affectedRows}",
                $tag
            );

            return true;

        } catch (\PDOException $e) {
            // Log database exception
            $this->logService->dataLog(
                "Database error updating progress tracker: " . $e->getMessage(),
                $tag
            );
            return false;

        } catch (\Exception $e) {
            // Log other exceptions
            $this->logService->dataLog(
                "Error updating progress tracker: " . $e->getMessage(),
                $tag
            );
            return false;
        }
    }

    /**
     * Converts exam schedule data to expanded format
     *
     * @param int $buildingId Building ID to process
     * @param int $chooseSemester Optional semester to process (uses current semester if not provided)
     * @return bool Success status
     * @throws \Exception
     */
    public function convertExamScheduleToExpanded(int $buildingId = 0, int $chooseSemester = 0): bool
    {
        $whatFunction = "convertExamSchedToDaily";
        $logThis = $this->logService->dataLog(...);
        $logThis($whatFunction . " - Data transaction(s) starting.", $whatFunction);
        // Get configuration
        $confArr = $this->configService->readConfigVars("all", true);
        // Get building data
        $buildingData = $this->buildingService->readOneBuilding(56, true); // return raw, active only
        $buildingNum = $buildingData[0]['bldg_num'];
        $buildingFacilityCode = $buildingData[0]['facility_code'];
        $detailedLogging = $confArr['detailedlogging'];
        if ($detailedLogging) {
            $logThis($whatFunction . " - Building found - facility_code: " . $buildingFacilityCode . " bldg_num: " . $buildingNum . "...", $whatFunction);
        }



        $retrieveActiveOnly = ($confArr["expandClassesForActiveRoomsOnly"] == "on");

        // Determine semester
        if ($chooseSemester > 0) {
            $term = $this->utilities->sanitizeIntegerInput($chooseSemester);
        } else {
            $term = $this->semesterService->determineSemester() ?: 0;
        }

        if ($detailedLogging) {
            $logThis($whatFunction . " - Semester is " . $term . ". Starting processing.", $whatFunction);
        }

        // Retrieve rooms with exams
        $roomArray = $this->roomService->getRoomsWithExams($buildingId, false, false);
        $roomsWithExamsFound = $roomArray["response"]["numFound"];
        $roomArrActual = $roomArray["response"]["docs"][0];

        if ($detailedLogging) {
            $logThis($whatFunction . " - getRoomsWithExams found " . $roomsWithExamsFound . " results for term " . $term . " in building id " . $buildingId . ". retrieveActiveOnly is " . $confArr["expandClassesForActiveRoomsOnly"] . ".", $whatFunction);
        }
        $conn = $this->dbManager->getDefaultConnection();
        // Process each room
        foreach ($roomArrActual as $room) {
            $roomNumber = $room[0]['room_num'] ?? 0;
            $roomPopulation = $room[0]['room_population'] ?? 0;
            $roomFacilityID = $room[0]['facility_id'] ?? '';

            if ($term > 0) {
                $conn->beginTransaction();
                try {
                    $queryCount = 'SELECT COUNT(*) FROM exam_schedule_data WHERE strm = :term AND facility_id = :facid';
                    $stmtCount = $conn->prepare($queryCount);
                    $stmtCount->bindParam(':term', $term);
                    $stmtCount->bindParam(':facid', $roomFacilityID);
                    $stmtCount->execute();

                    if ($detailedLogging) {
                        $logThis($whatFunction . " - Data retrieved...found " . $stmtCount->fetchColumn() . " exam record(s) for facility_id " . $roomFacilityID . " for term " . $term, $whatFunction);
                    }

                    // Fetch exam schedule data
                    $stmtExamQuery = 'SELECT id, pp_search_id, strm, facility_descr, facility_id, dt_start, dt_end, campus 
                                     FROM exam_schedule_data 
                                     WHERE strm = :term AND facility_id = :facid 
                                     ORDER BY dt_start';
                    $stmtExamRes = $conn->prepare($stmtExamQuery);
                    $stmtExamRes->bindParam(':term', $term);
                    $stmtExamRes->bindParam(':facid', $roomFacilityID);
                    $stmtExamRes->execute();
                    $stmtExamResults = $stmtExamRes->fetchAll(\PDO::FETCH_ASSOC);

                    // Process data and insert into expanded_schedule_data
                    foreach ($stmtExamResults as $exam) {
                        $startDate = new \DateTime($exam['dt_start']);
                        $endDate = new \DateTime($exam['dt_end']);

                        // Convert to UTC
                        $startDate->setTimezone(new \DateTimeZone('UTC'));
                        $endDate->setTimezone(new \DateTimeZone('UTC'));

                        // Format dates
                        $formattedDTStart = $startDate->format('Y-m-d H:i:sO');
                        $formattedDTSDate = $startDate->format('Y-m-d');
                        $formattedDTEnd = $endDate->format('Y-m-d H:i:sO');
                        $formattedDTEDate = $endDate->format('Y-m-d');

                        if ($detailedLogging) {
                            $logThis($whatFunction . " - Checking record for pp_search_id: " . $exam['pp_search_id'] . " for " . $formattedDTStart . " to see if it already exists.", $whatFunction);
                        }
                        // Fetch course info
                        $courseInfoQuery = 'SELECT DISTINCT coursetitle, enrl_tot, pp_search_id, facility_id, bldg_num, bldg_code, class_number_code, room_num 
                                           FROM class_schedule_data 
                                           WHERE pp_search_id = :pp_search_id AND strm = :strm 
                                           LIMIT 1';
                        $stmtCourseInfo = $conn->prepare($courseInfoQuery);
                        $stmtCourseInfo->bindParam(':pp_search_id', $exam['pp_search_id']);
                        $stmtCourseInfo->bindParam(':strm', $exam['strm']);
                        $stmtCourseInfo->execute();
                        $courseInfoResult = $stmtCourseInfo->fetch();
                        $checkClassCount = $stmtCourseInfo->rowCount();
                        if ($detailedLogging && $checkClassCount > 0) {
                            $logThis($whatFunction . " - Class Select query retrieved " . $checkClassCount . " result(s).", $whatFunction);
                        }
                        $courseClassNumberCode = $courseInfoResult['class_number_code'] ?? '';
                        $courseClassTitle = $courseInfoResult['coursetitle'] ?? '';

                        $upsertQuery = <<<'SQL'
                        INSERT INTO expanded_schedule_data (
                            pp_search_id, strm, facility_id, class_number_code, coursetitle, enrl_tot, 
                            bldg_num, bldg_code, room_number, datetime_start, datetime_end, campus
                        ) VALUES (
                            :pp_search_id, :strm, :facility_id, :class_number_code, :coursetitle, :enrl_tot, 
                            :bldg_num, :bldg_code, :room_num, :datetime_start, :datetime_end, :campus
                        )
                        ON CONFLICT (pp_search_id, strm, datetime_start, datetime_end) DO UPDATE SET
                            facility_id = EXCLUDED.facility_id,
                            class_number_code = EXCLUDED.class_number_code,
                            coursetitle = EXCLUDED.coursetitle,
                            enrl_tot = EXCLUDED.enrl_tot,
                            bldg_num = EXCLUDED.bldg_num,
                            bldg_code = EXCLUDED.bldg_code,
                            room_number = EXCLUDED.room_number,
                            datetime_start = EXCLUDED.datetime_start,
                            datetime_end = EXCLUDED.datetime_end,
                            campus = EXCLUDED.campus;
                        SQL;

                        $stmtUpsert = $conn->prepare($upsertQuery);
                        $stmtUpsert->bindParam(':pp_search_id', $exam['pp_search_id']);
                        $stmtUpsert->bindParam(':strm', $term);
                        $stmtUpsert->bindParam(':facility_id', $roomFacilityID);
                        $stmtUpsert->bindParam(':class_number_code', $courseClassNumberCode);
                        $stmtUpsert->bindParam(':coursetitle', $courseClassTitle);
                        $stmtUpsert->bindParam(':enrl_tot', $roomPopulation);
                        $stmtUpsert->bindParam(':bldg_num', $buildingNum);
                        $stmtUpsert->bindParam(':bldg_code', $buildingFacilityCode);
                        $stmtUpsert->bindParam(':room_num', $roomNumber);
                        $stmtUpsert->bindParam(':datetime_start', $formattedDTStart);
                        $stmtUpsert->bindParam(':datetime_end', $formattedDTEnd);
                        $stmtUpsert->bindParam(':campus', $exam['campus']);
                        if ($stmtUpsert->execute()) {
                            if ($detailedLogging) {
                                $affectedRowsUpdate = $stmtUpsert->rowCount();
                                $logThis($whatFunction . " - Upsert for pp_search_id: " . $exam['pp_search_id'] . " in bldg: " . $buildingFacilityCode . ", room: " . $roomNumber . " affected " . $affectedRowsUpdate . " records.", $whatFunction);
                                $logThis($whatFunction . " - Upsert for pp_search_id: " . $exam['pp_search_id'] . " - All values: id: " . $exam['id'] . " strm: " . $term . " facility_id: " . $roomFacilityID . " class_number_code: " . $courseClassNumberCode . " classtitle: " . $courseClassTitle . " room_population: " . $roomPopulation . " bldg_num: " . $buildingNum . " bldg_code: " . $buildingFacilityCode . " room_num: " . $roomNumber . " datetime_start: " . $formattedDTStart . " datetime_end: " . $formattedDTEnd . " campus: " . $exam['campus'], $whatFunction);
                            }
                        } else {
                            $errorInfo = $stmtUpsert->errorInfo();
                            $errorString = implode(" - ", $errorInfo);
                            $logThis($whatFunction . " - Upsert for pp_search_id: " . $exam['pp_search_id'] . ". Error encountered: " . $errorString, $whatFunction);
                        }
                    }
                    $conn->commit();
                    if ($detailedLogging) {
                        $logThis($whatFunction . " - Committing queries now.", $whatFunction);
                    }
                } catch (\PDOException $e) {
                    // Roll back the transaction
                    $conn->rollback();

                    // Log the error
                    error_log($whatFunction . " - Data transaction(s) failed. Queries rolled back. Error: " . $e->getMessage());

                    return false;
                }
            } else {
                $logThis($whatFunction . " - function could not run. Term value not supplied.", $whatFunction);
                return false;
            }
        }
        return true;
    }

    /**
     * Transfer class data to the setpoint_write table
     *
     * @param array $inputArr Array of documents to transfer
     * @param int $daysFromToday Number of days from today
     * @return bool Success status
     */
    public function transferClasses(array $inputArr, int $daysFromToday = 0): bool
    {
        $whatFunction = "transferClasses";

        // Get configuration for detailed logging
        $confArr = $this->configService->readConfigVars("all", true);
        $detailedLogging = $confArr['detailedlogging'] ?? false;

        // Get WebCtrl database connection
        $conn2 = $this->dbManager->getWebCtrlConnection();

        $this->logService->dataLog($whatFunction . " - Transfer started for day " . $daysFromToday, $whatFunction);

        /*
         * incoming array format for above example:
         * START:
         * [effectivetime] => {date} 13:00:00-5
         * [uname] => "/RIT/70-VAV-337/RpPz"
         * [pv] => 44
         *
         * END:
         * [effectivetime] => {date} 13:50:00-5
         * [uname] => "/RIT/70-VAV-337/RpPz"
         * [pv] => 0
         *
         * Write data ONLY if "dispatched" = false
         */

        // Select existing ones to see if there is an update to the Pv value
        $select_stmt = $conn2->prepare("SELECT DISTINCT effectivetime, uname, pv, dispatched 
                                       FROM public.setpoint_write 
                                       WHERE effectivetime = TIMEZONE('UTC',:effectivetime) 
                                       AND uname = :uname 
                                       LIMIT 1");

        // Update query for Pv value of existing docs that have a different Pv
        $update_stmt = $conn2->prepare("UPDATE public.setpoint_write 
                                       SET pv = :pv 
                                       WHERE effectivetime = TIMEZONE('UTC',:effectivetime) 
                                       AND uname = :uname 
                                       AND dispatched = false");

        // Insert query for any docs not present
        $insert_stmt = $conn2->prepare("INSERT INTO public.setpoint_write (effectivetime, uname, pv) 
                                       VALUES (TIMEZONE('UTC', :effectivetime), :uname, :pv)");

        foreach ($inputArr as $doc) {
            // Check each doc vs db
            $select_stmt->bindValue(":effectivetime", $doc["effectivetime"]);
            $select_stmt->bindValue(":uname", $doc["uname"]);
            $select_stmt->execute();
            $row = $select_stmt->fetch();

            // Process the results of the SELECT query
            if ($row) {
                if ($row["pv"] != $doc["pv"] && !$row["dispatched"]) {
                    // The pv's don't match. Update the record for that effectivetime and uname.
                    $update_stmt->bindValue(":effectivetime", $doc["effectivetime"]);
                    $update_stmt->bindValue(":uname", $doc["uname"]);
                    $update_stmt->bindValue(":pv", $doc["pv"]);
                    $update_stmt->execute();
                    $count = $update_stmt->rowCount();

                    if ($count > 0) {
                        if ($detailedLogging) {
                            $this->logService->dataLog(
                                $whatFunction . " - Data...update_stmt for uname: " . $doc["uname"] .
                                ", effectivetime: " . $doc["effectivetime"] . " with pv: " . $doc["pv"],
                                $whatFunction
                            );
                        }
                    } else {
                        if ($detailedLogging) {
                            $this->logService->dataLog(
                                $whatFunction . " - Data...update_stmt for uname: " . $doc["uname"] .
                                ", effectivetime: " . $doc["effectivetime"] . " failed.",
                                $whatFunction
                            );
                        }
                    }
                } else {
                    if ($detailedLogging) {
                        $dstatus = ($row['dispatched']) ? "true" : "false";
                        $this->logService->dataLog(
                            $whatFunction . " - Data...update for uname: " . $doc["uname"] .
                            " at " . $doc["effectivetime"] . " with status dispatched (" . $dstatus . ") not needed.",
                            $whatFunction
                        );
                    }
                }
            } else {
                // If no row exists, then it's a new record so insert it
                $insert_stmt->bindValue(":effectivetime", $doc["effectivetime"]);
                $insert_stmt->bindValue(":uname", $doc["uname"]);
                $insert_stmt->bindValue(":pv", $doc["pv"]);
                $insert_stmt->execute();
                $count = $insert_stmt->rowCount();

                if ($count > 0) {
                    if ($detailedLogging) {
                        $this->logService->dataLog(
                            $whatFunction . " - Data...insert_stmt fired for uname: " . $doc["uname"] .
                            " at " . $doc["effectivetime"] . " with pv: " . $doc["pv"],
                            $whatFunction
                        );
                    }
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog(
                            $whatFunction . " - Data...insert_stmt for uname: " . $doc["uname"] .
                            " at time:" . $doc["effectivetime"] . " failed.",
                            $whatFunction
                        );
                    }
                }
            }
        }

        $this->logService->dataLog($whatFunction . " - Transfer complete for day " . $daysFromToday, $whatFunction);
        return true;
    }

    /**
     * Add or update a record in the setpoint_write table
     *
     * @param array|null $data Data to add or update
     * @return bool Success status
     */
    public function addUpdSetpointWrite(?array $data): bool
    {
        $whatFunction = "addUpdSetpointWrite";

        // Get configuration
        $confArr = $this->configService->readConfigVars("all", true);
        $detailedLogging = $confArr['detailedlogging'] ?? false;
        $unamePreString = $confArr['unamePreString'] ?? '';
        $unamePostString = $confArr['unamePostString'] ?? '';

        // Get WebCtrl database connection
        $conn2 = $this->dbManager->getWebCtrlConnection();

        $inUp = "update";
        $inUpID = "";

        try {
            $conn2->beginTransaction();
            $this->logService->dataLog($whatFunction . " - Data transaction(s) starting.", $whatFunction);

            // Extract data from input array
            $incomingEffectiveTime = $data['effectivetime'];
            $incomingUname = $data['uname'];
            $incomingPv = $data['pv'];
            $incomingId = $data['id'] ?? null;
            $incomingDeleteReq = $data['delete'] ?? 0;
            $fullUname = $unamePreString . $incomingUname . $unamePostString;

            // Perform timezone conversion for datetime comparison with WebCTRL
            // Original datetime and timezone
            $originalTimezone = new \DateTimeZone('UTC');
            // Target timezone
            $targetTimezone = new \DateTimeZone('America/New_York');
            // Create a DateTime object with the original datetime and timezone
            $datetime = new \DateTime($incomingEffectiveTime, $originalTimezone);
            // Convert the datetime to the target timezone
            $datetime->setTimezone($targetTimezone);
            // Format the datetime to the desired format that matches what WebCTRL uses
            $convertedDatetime = $datetime->format('Y-m-d H:i:s.u');

            $deletion = false;
            $result = false;
            $inUp = "update";

            if ($incomingId !== null && $incomingId != 0) {
                if ($incomingDeleteReq == 1) {
                    // Delete a record by id
                    $inUp = "delete";
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction . " - Prepping Delete for id: " . $incomingId, $whatFunction);
                    }

                    $query = "DELETE FROM setpoint_write WHERE id = :id";
                    $stmt = $conn2->prepare($query);
                    $stmt->bindParam(':id', $incomingId);
                    $stmt->execute();
                    $count = $stmt->rowCount();

                    if ($count > 0) {
                        $this->logService->dataLog($whatFunction . " - id " . $incomingId . " deleted successfully. Total records deleted: " . $count, $whatFunction);
                        $deletion = true;
                    } else {
                        $this->logService->dataLog($whatFunction . " - Error deleting id " . $incomingId, $whatFunction);
                        $deletion = false;
                    }
                } else {
                    // We are not deleting, but we are selecting a record by id
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction . " - We have an id: " . $incomingId . " so attempting to select a record.", $whatFunction);
                    }

                    $query = "SELECT * FROM setpoint_write WHERE id = :id LIMIT 1";
                    $stmt = $conn2->prepare($query);
                    $stmt->bindParam(':id', $incomingId);
                    $stmt->execute();
                    $result = $stmt->fetch();
                }
            }

            if ($incomingDeleteReq == 0) {
                if ($result === false) {
                    // We didn't have a selection via id, but do we have a match for uname and time?
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction . " - We did not have a selection via id...attempting record match via datetime and uname.", $whatFunction);
                    }

                    // Now compare $convertedDatetime with db version
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction . " - datetime: " . $incomingEffectiveTime . " converted to WebCTRLs date format in New York Time: " . $convertedDatetime . " for comparison to db.", $whatFunction);
                    }

                    // We are selecting a record by uname and effective time
                    $query = "SELECT * FROM setpoint_write WHERE uname = :uname AND effectivetime = :effectivetime LIMIT 1";
                    $stmt = $conn2->prepare($query);
                    $stmt->bindParam(':uname', $fullUname);
                    $stmt->bindParam(':effectivetime', $convertedDatetime);
                    $stmt->execute();
                    $result = $stmt->fetch();

                    if ($result !== false) {
                        $incomingId = $result['id'];
                    }
                }

                // Check for $result again, since we attempted a selection again
                if ($result === false) {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction . " - We do not have a record when selecting via uname and datetime, so attempting insert.", $whatFunction);
                    }

                    $inUp = "insert";
                    $query = "INSERT INTO setpoint_write (effectivetime, uname, pv, dispatched) 
                              VALUES (:effectivetime, :uname, :pv, false)";
                    $stmt = $conn2->prepare($query);
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction . " - We have an id: " . $incomingId . " and something needs updating.", $whatFunction);
                    }

                    $inUp = "update";
                    $query = "UPDATE setpoint_write SET 
                              effectivetime = :effectivetime, 
                              uname = :uname, 
                              pv = :pv 
                              WHERE id = :id";
                    $stmt = $conn2->prepare($query);
                    $stmt->bindParam(':id', $result['id']);
                    $inUpID = " for id " . $result['id'];
                }

                $stmt->bindParam(':effectivetime', $convertedDatetime);
                $stmt->bindParam(':uname', $fullUname);
                $stmt->bindParam(':pv', $incomingPv);
                $stmt->execute();

                if ($stmt) {
                    $affectedRows = $stmt->rowCount();
                    $this->logService->dataLog($whatFunction . " - " . $inUp . " succeeded. It affected " . $affectedRows . " row(s) of data.", $whatFunction);

                    if ($detailedLogging) {
                        if ($affectedRows > 0) {
                            $this->logService->dataLog($whatFunction . " - Query affected " . $affectedRows . " row(s)", $whatFunction);
                        } else {
                            $this->logService->dataLog($whatFunction . " - Query did not affect any rows", $whatFunction);
                        }
                    }
                } else {
                    $this->logService->dataLog($whatFunction . "- Query " . $inUp . " failed", $whatFunction);
                }
            }

            $conn2->commit();

            if ($detailedLogging) {
                $this->logService->dataLog($whatFunction . " - Committing queries now.", $whatFunction);
            }

            // If this was supposed to be a delete and the deletion did NOT happen, return false
            if ($incomingDeleteReq == 1 && !$deletion) {
                return false;
            } else {
                // Otherwise return true
                return true;
            }
        } catch (\PDOException $e) {
            $conn2->rollback();
            error_log($whatFunction . " - Data transaction(s) failed. Queries rolled back. Error: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Add or update a setpoint expanded record
     *
     * @param array $data The data to add or update
     * @return bool Success or failure
     */
    public function addUpdSetpointExpanded(array $data): bool
    {
        $conn = $this->dbManager->getDefaultConnection();
        $whatFunction = "addUpdSetpointExpanded";
        $inUp = "update";
        // Get configuration
        $confArr = $this->configService->readConfigVars("all", true);
        $detailedLogging = $confArr['detailedlogging'] ?? false;

        try {
            $conn->beginTransaction();
            $this->logService->dataLog($whatFunction . " - Data transaction(s) starting.", $whatFunction);

            $saniZoneName = $data["zone_name"];
            $saniZoneId = $data["zone_id"];
            $saniFacilityId = $data["facility_id"];
            $saniCourseTitle = $data["coursetitle"];
            $saniEnrlTot = $data["enrl_tot"];
            $saniClassCode = $data["class_number_code"];
            $saniPv = $data["pv"];
            $saniEffectiveTime = $data["effectivetime"];
            $saniId = $data["id"] ?? null;
            $incomingDeleteReq = array_key_exists('delete', $data) ? $data['delete'] : 0;

            // Original datetime and timezone
            $originalTimezone = new \DateTimeZone('UTC');
            // Target timezone
            $targetTimezone = new \DateTimeZone('America/New_York');
            // Create a DateTime object with the original datetime and timezone
            $datetime = new \DateTime($data["effectivetime"], $originalTimezone);
            // Convert the datetime to the target timezone
            $datetime->setTimezone($targetTimezone);
            // Format the datetime to the desired format that matches what WebCTRL uses
            $convertedDatetime = $datetime->format('Y-m-d H:i:s.u');

            $result = false;
            $deletion = false;

            if ($saniId !== null && $saniId != 0) {
                if ($incomingDeleteReq == 1) {
                    $inUp = "delete";
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction . " - Prepping Delete for id: " . $saniId, $whatFunction);
                    }
                    $query = "DELETE FROM setpoint_expanded WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $saniId);
                    $stmt->execute();
                    $count = $stmt->rowCount();
                    if ($count > 0) {
                        $this->logService->dataLog($whatFunction . " - id " . $saniId . " deleted successfully. Total records deleted: " . $count, $whatFunction);
                        $deletion = true;
                    } else {
                        $this->logService->dataLog($whatFunction . " - Error deleting id " . $saniId, $whatFunction);
                        $deletion = false;
                    }
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction . " - We have an id: " . $saniId . " so attempting to select a record.", $whatFunction);
                    }
                    $query = "SELECT * FROM setpoint_expanded WHERE id = :id LIMIT 1";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $saniId);
                    $stmt->execute();
                    $result = $stmt->fetch();
                }
            }

            if ($incomingDeleteReq == 0) {
                if ($result === false) {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction . " - We do not have an id, so attempting insert.", $whatFunction);
                    }
                    $inUp = "insert";
                    $query = <<<'SQL'
                    INSERT INTO setpoint_expanded (zone_name, facility_id, coursetitle, enrl_tot, pv, effectivetime, zone_id, class_number_code)
                    VALUES (:zone_name, :facility_id, :coursetitle, :enrl_tot, :pv, :effectivetime, :zone_id, :class_number_code)
                    ON CONFLICT (zone_name, effectivetime) DO UPDATE SET
                    facility_id = EXCLUDED.facility_id,
                    coursetitle = EXCLUDED.coursetitle,
                    enrl_tot = EXCLUDED.enrl_tot,
                    pv = EXCLUDED.pv,
                    zone_id = EXCLUDED.zone_id,
                    class_number_code = EXCLUDED.class_number_code;
                    SQL;
                    $stmt = $conn->prepare($query);
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction . " - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                    }
                    $inUp = "update";
                    $query = <<<'SQL'
                    UPDATE setpoint_expanded SET
                        zone_name = :zone_name,
                        facility_id = :facility_id,
                        coursetitle = :coursetitle,
                        enrl_tot = :enrl_tot,
                        pv = :pv,
                        effectivetime = :effectivetime,
                        zone_id = :zone_id,
                        class_number_code = :class_number_code
                    WHERE id = :id;
                    SQL;
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $saniId);
                }

                $stmt->bindParam(':zone_name', $saniZoneName);
                $stmt->bindParam(':facility_id', $saniFacilityId);
                $stmt->bindParam(':coursetitle', $saniCourseTitle);
                $stmt->bindParam(':enrl_tot', $saniEnrlTot);
                $stmt->bindParam(':pv', $saniPv);
                $stmt->bindParam(':effectivetime', $convertedDatetime);
                $stmt->bindParam(':zone_id', $saniZoneId);
                $stmt->bindParam(':class_number_code', $saniClassCode);
                $stmt->execute();

                $updateArray = [];
                $updateArray['updated_table_id'] = $saniId;
                $updateArray['user_id'] = 0;
                $updateArray['updated_table_name'] = 'setpoint_expanded';
                $updateArray['common_name'] = 'Setpoint Expanded';

                if ($inUp == "update") {
                    if ($result['zone_name'] != $saniZoneName) {
                        $updateArray['old_value'] = $result['zone_name'];
                        $updateArray['new_value'] = $saniZoneName;
                        $updateArray['column_name'] = 'zone_name';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                    if ($result['facility_id'] != $saniFacilityId) {
                        $updateArray['old_value'] = $result['facility_id'];
                        $updateArray['new_value'] = $saniFacilityId;
                        $updateArray['column_name'] = 'facility_id';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                    if ($result['coursetitle'] != $saniCourseTitle) {
                        $updateArray['old_value'] = $result['coursetitle'];
                        $updateArray['new_value'] = $saniCourseTitle;
                        $updateArray['column_name'] = 'coursetitle';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                    if ($result['enrl_tot'] != $saniEnrlTot) {
                        $updateArray['old_value'] = $result['enrl_tot'];
                        $updateArray['new_value'] = $saniEnrlTot;
                        $updateArray['column_name'] = 'enrl_tot';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                    if ($result['pv'] != $saniPv) {
                        $updateArray['old_value'] = $result['pv'];
                        $updateArray['new_value'] = $saniPv;
                        $updateArray['column_name'] = 'pv';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                    if ($result['effectivetime'] != $saniEffectiveTime) {
                        $updateArray['old_value'] = $result['effectivetime'];
                        $updateArray['new_value'] = $saniEffectiveTime;
                        $updateArray['column_name'] = 'effectivetime';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                    if ($result['zone_id'] != $saniZoneId) {
                        $updateArray['old_value'] = $result['zone_id'];
                        $updateArray['new_value'] = $saniZoneId;
                        $updateArray['column_name'] = 'zone_id';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                    if ($result['class_number_code'] != $saniClassCode) {
                        $updateArray['old_value'] = $result['class_number_code'];
                        $updateArray['new_value'] = $saniClassCode;
                        $updateArray['column_name'] = 'class_number_code';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                }

                if ($stmt) {
                    $affectedRows = $stmt->rowCount();
                    $this->logService->dataLog($whatFunction . " - " . $inUp . " succeeded. It affected " . $affectedRows . " row(s) of data.", $whatFunction);

                    if ($affectedRows > 0) {
                        if ($detailedLogging) {
                            $this->logService->dataLog("addUpdSetpointExpanded - Query affected " . $affectedRows . " row(s)", $whatFunction);
                        }
                    } else {
                        if ($detailedLogging) {
                            $this->logService->dataLog("addUpdSetpointExpanded - Query did not affect any rows", $whatFunction);
                        }
                    }
                } else {
                    $this->logService->dataLog($whatFunction . " - Query execution failed", $whatFunction);
                }
            }

            $conn->commit();

            if ($detailedLogging) {
                $this->logService->dataLog($whatFunction . " - Data transaction(s) successful. Committing queries now.", $whatFunction);
            }

            if ($incomingDeleteReq == 1 && !$deletion) {
                return false;
            } else {
                return true;
            }
        } catch (\Exception $e) {
            $conn->rollback();
            $this->logService->logException($e, $whatFunction . " - Data transaction(s) failed. Queries rolled back. Also the following error was generated: ");
            $this->utilities->returnMsg($e->getMessage(), "Error");
        }
    }

    /**
     * Transfer schedule data to setpoint tables for a specific building and time period
     *
     * @param int $building_id The building identifier.
     * @param int $daysFromToday Number of days from now to include.
     * @return bool                 True on success, false on failure.
     * @throws \Exception
     */
    public function transferToSetpoint(int $building_id = 0, int $daysFromToday = 1): bool
    {
        $whatFunction = "transferToSetpoint";
        $conn = $this->dbManager->getDefaultConnection();
        $conn2 = $this->dbManager->getWebCtrlConnection();
        // Get configuration
        $confArr = $this->configService->readConfigVars("all", true);
        $detailedLogging = $confArr['detailedlogging'] ?? false;
        // Log transfer start
        $this->logService->dataLog("$whatFunction - Transfer starting up.", $whatFunction);

        // Get current semester from the SemesterService.
        $term = $this->semesterService->determineSemester() ?? 0;

        // Get building info via BuildingService.
        $buildingInfo = $this->buildingService->readOneBuilding(56, true);
        if (!empty($buildingInfo[0]['active']) && $detailedLogging) {
            $this->logService->dataLog("$whatFunction - found building " . $buildingInfo[0]['bldg_name'] . ". Starting processing.", $whatFunction);
        }

        // Get room information via RoomService.
        $roomInfo = $this->roomService->readAllRooms(56, 0, true, false);
        $roomCount = count($roomInfo);
        if ($detailedLogging) {
            $firstFacId = $roomInfo[0]['facility_id'] ?? 'N/A';
            $this->logService->dataLog("$whatFunction - readAllRooms() for id $building_id found $roomCount rooms. Creating array of facility ids. First facID: $firstFacId", $whatFunction);
        }

        // Create an associative array of facility_id to room data.
        $facilityIdToData = [];
        foreach ($roomInfo as $room) {
            // Skip entries without a facility_id
            if (!isset($room['facility_id'])) {
                continue;
            }
            $facilityIdToData[$room['facility_id']] = [
                'id'            => $room['id'] ?? null,
                'uncert_amt'    => $room['uncert_amt'] ?? 0,
                'ash61_cat_id'  => $room['ash61_cat_id'] ?? null,
                'is_active'     => $room['active'] ?? 0
            ];
            //$this->logService->dataLog("$whatFunction - facilityIdToData array ".$room['facility_id']." is id: ".$room['id'].", uncert_amt: ".$room['uncert_amt']." ash61_cat_id: ".$room['ash61_cat_id']." is_active: ".$room['active'], $whatFunction);

        }

        // Format datetime values in UTC.
        $currDTStart = new DateTime('now', new DateTimeZone('UTC'));
        $currDTStartFormatted = $currDTStart->format('Y-m-d H:i:sO');
        if ($detailedLogging) {
            $this->logService->dataLog("$whatFunction - Formatted start datetime: $currDTStartFormatted", $whatFunction);
        }

        $dateXDaysFromNow = new DateTime('now', new DateTimeZone('UTC'));
        $dateXDaysFromNow->modify('+' . $daysFromToday . ' day');
        $dateXDaysFromNowFormatted = $dateXDaysFromNow->format('Y-m-d H:i:sO');
        if ($detailedLogging) {
            $this->logService->dataLog("$whatFunction - Formatted end datetime: $dateXDaysFromNowFormatted", $whatFunction);
        }

        // Build and prepare the query.
        if ($detailedLogging) {
            $this->logService->dataLog("$whatFunction - Creating expanded_schedule_data query.", $whatFunction);
        }
        $query = "SELECT * FROM expanded_schedule_data
                  WHERE bldg_num = :bldgnum 
                    AND datetime_start >= :currentDate 
                    AND datetime_start <= :dateXDaysFromNow 
                  ORDER BY datetime_start";

        $stmt = $conn->prepare($query);

        if ($detailedLogging) {
            $this->logService->dataLog(
                "$whatFunction - adding parameters to expanded_schedule_data query - :bldgnum "
                . $buildingInfo[0]['bldg_num'] . ", :currentDate $currDTStartFormatted, :dateXDaysFromNow $dateXDaysFromNowFormatted",
                $whatFunction
            );
        }
        $stmt->bindParam(':bldgnum', $buildingInfo[0]['bldg_num']);
        $stmt->bindParam(':currentDate', $currDTStartFormatted);
        $stmt->bindParam(':dateXDaysFromNow', $dateXDaysFromNowFormatted);

        if ($detailedLogging) {
            $this->logService->dataLog("$whatFunction - Executing the expanded_schedule_data query.", $whatFunction);
        }

        try {
            $stmt->execute();
            $schedCount = 0;
            $missingFacIDs = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $schedCount++;
                if ($detailedLogging) {
                    $this->logService->dataLog("$whatFunction - Processing data for scheduled event in facility_id " . $row["facility_id"] . ".", $whatFunction);
                }
                $eventFacID = $row["facility_id"];

                if (isset($facilityIdToData[$eventFacID])) {
                    $facData = $facilityIdToData[$eventFacID];
                    $roomId = $facData['id'];
                    $isRoomActive = $facData['is_active'] > 0;

                    if ($detailedLogging) {
                        $this->logService->dataLog("$whatFunction - Processing data for scheduled event in facility_id $eventFacID with room_id $roomId and active status of $isRoomActive", $whatFunction);
                    }

                    if ($isRoomActive) {
                        // Retrieve zones for the given room.
                        $zones = $this->zoneService->getZonesByRoom($roomId, true);
                        if ($detailedLogging) {
                            $this->logService->dataLog("$whatFunction - Retrieving zones. Found " . count($zones) . " elements.", $whatFunction);
                        }

                        if (count($zones) > 0) {
                            if ($detailedLogging) {
                                $this->logService->dataLog("$whatFunction - Found " . count($zones) . " zone(s) associated with room $eventFacID.", $whatFunction);
                            }
                            foreach ($zones as $zone) {
                                if ($zone['active']) {
                                    $zoneDynRpPzArr = [];
                                    $zoneMaxRpPzArr = [];
                                    if ($detailedLogging) {
                                        $this->logService->dataLog("$whatFunction - Retrieving xrefs for zone: " . $zone['zone_id'] . ".", $whatFunction);
                                    }
                                    // Get the xref data for this zone.
                                    $zBRResults = $this->zoneService->getXrefsByZone($zone["zone_id"], null, true);
                                    if ($detailedLogging) {
                                        $this->logService->dataLog("$whatFunction - Found " . count($zBRResults) . " xrefs for zone: " . $zone['zone_id'] . " (" . $zone['zone_name'] . "). Iterating through them.", $whatFunction);
                                    }
                                    foreach ($zBRResults as $zBR) {
                                        // Calculation for dynamic and maximum room proportional zone value.
                                        $tempDynRpPz = (($row['enrl_tot'] + $zBR['uncert_amt']) * $zBR['pr_percent']) * $zBR['ppl_oa_rate'];
                                        $tempMaxRpPz = $zBR['xref_population'] * $zBR['ppl_oa_rate'];
                                        if ($detailedLogging) {
                                            $this->logService->dataLog(
                                                "$whatFunction - Performing calculations on values for zone: " . $zone['zone_name'] . " (" . $zone['zone_code'] .
                                                ") - enrl_tot: " . $row['enrl_tot'] . ", uncert_amt: " . $zBR['uncert_amt'] .
                                                ", pr_percent: " . $zBR['pr_percent'] . ", ppl_oa_rate: " . $zBR['ppl_oa_rate'],
                                                $whatFunction
                                            );
                                            $this->logService->dataLog(
                                                "$whatFunction - Dynamic RpPz calc: $tempDynRpPz; Max RpPz calc: $tempMaxRpPz",
                                                $whatFunction
                                            );
                                        }
                                        $zoneDynRpPzArr[] = $tempDynRpPz;
                                        $zoneMaxRpPzArr[] = $tempMaxRpPz;
                                    }
                                    $zoneDynRpPz = array_sum($zoneDynRpPzArr);
                                    $zoneMaxRpPz = array_sum($zoneMaxRpPzArr);
                                    if ($detailedLogging) {
                                        $this->logService->dataLog("$whatFunction - Zone " . $zone['zone_name'] . " (" . $zone['zone_code'] . ") total Dynamic RpPz: $zoneDynRpPz", $whatFunction);
                                        $this->logService->dataLog("$whatFunction - Zone " . $zone['zone_name'] . " (" . $zone['zone_code'] . ") total Max RpPz: $zoneMaxRpPz", $whatFunction);
                                    }
                                    $zoneRpPz = ($zoneDynRpPz < $zoneMaxRpPz) ? $zoneDynRpPz : $zoneMaxRpPz;
                                    if ($detailedLogging) {
                                        $this->logService->dataLog("$whatFunction - Final calculated RpPz for zone " . $zone['zone_name'] . " (" . $zone['zone_code'] . "): $zoneRpPz", $whatFunction);
                                    }

                                    // Prepare start and end data arrays (note: conversion to America/New_York is handled in setpoint_write if needed)
                                    $startData = [
                                        'effectivetime' => $row['datetime_start'],
                                        'uname'        => $zone['zone_code'],
                                        'pv'           => $zoneRpPz,
                                        'delete'       => 0
                                    ];
                                    $endData = [
                                        'effectivetime' => $row['datetime_end'],
                                        'uname'        => $zone['zone_code'],
                                        'pv'           => 0,
                                        'delete'       => 0
                                    ];

                                    if ($detailedLogging) {
                                        $this->logService->dataLog("$whatFunction - Sanitizing setpoint data before insert.", $whatFunction);
                                    }
                                    $saniStart = $this->utilities->sanitizeSetpointData($startData);
                                    $saniEnd   = $this->utilities->sanitizeSetpointData($endData);

                                    if ($detailedLogging) {
                                        $this->logService->dataLog(
                                            "$whatFunction - Sending start data for zone " . $zone['zone_name'] . " (" . $zone['zone_code'] .
                                            "). Pv: $zoneRpPz for UTC datetime " . $startData['effectivetime'] .
                                            " (sanitized: " . $saniStart['effectivetime'] . ").",
                                            $whatFunction
                                        );
                                    }
                                    $insUpdSetpointStart = $this->addUpdSetpointWrite($saniStart);

                                    if ($detailedLogging) {
                                        $this->logService->dataLog(
                                            "$whatFunction - Sending end data for zone " . $zone['zone_name'] . " (" . $zone['zone_code'] .
                                            "). Pv: 0 for UTC datetime " . $endData['effectivetime'] .
                                            " (sanitized: " . $saniEnd['effectivetime'] . ").",
                                            $whatFunction
                                        );
                                    }
                                    $insUpdSetpointEnd = $this->addUpdSetpointWrite($saniEnd);

                                    if ($insUpdSetpointStart && $insUpdSetpointEnd) {
                                        if ($detailedLogging) {
                                            $this->logService->dataLog("$whatFunction - Data for zone " . $zone['zone_name'] . " (" . $zone['zone_code'] . ") transferred to setpoint_write successfully!", $whatFunction);
                                            $this->logService->dataLog(
                                                "$whatFunction - Data array start: " . $zone['zone_name'] . ", " . $row['facility_id'] . ", " . $row['coursetitle'] . ", " . $row['class_number_code'] . ", " . $row['enrl_tot'] . ", $zoneRpPz, " . $saniStart['effectivetime'] . ", " . $zone["zone_id"],
                                                $whatFunction
                                            );
                                            $this->logService->dataLog(
                                                "$whatFunction - Data array end: " . $zone['zone_name'] . ", " . $row['facility_id'] . ", " . $row['coursetitle'] . ", " . $row['class_number_code'] . ", " . $row['enrl_tot'] . ", $zoneRpPz, " . $saniEnd['effectivetime'] . ", " . $zone["zone_id"],
                                                $whatFunction
                                            );
                                        }
                                        $insUpdSetpointExStart = $this->addUpdSetpointExpanded([
                                            'zone_name'      => $zone['zone_name'],
                                            'facility_id'    => $row['facility_id'],
                                            'coursetitle'    => $row['coursetitle'],
                                            'class_number_code' => $row['class_number_code'],
                                            'enrl_tot'       => $row['enrl_tot'],
                                            'pv'             => $zoneRpPz,
                                            'effectivetime'  => $saniStart['effectivetime'],
                                            'zone_id'        => $zone["zone_id"]
                                        ]);
                                        $insUpdSetpointExEnd = $this->addUpdSetpointExpanded([
                                            'zone_name'      => $zone['zone_name'],
                                            'facility_id'    => $row['facility_id'],
                                            'coursetitle'    => $row['coursetitle'],
                                            'class_number_code' => $row['class_number_code'],
                                            'enrl_tot'       => $row['enrl_tot'],
                                            'pv'             => 0,
                                            'effectivetime'  => $saniEnd['effectivetime'],
                                            'zone_id'        => $zone["zone_id"]
                                        ]);
                                        if ($detailedLogging) {
                                            $this->logService->dataLog("$whatFunction - Queries sent to setpoint_expanded: $insUpdSetpointExStart - $insUpdSetpointExEnd", $whatFunction);
                                        }
                                        if (!$insUpdSetpointExStart || !$insUpdSetpointExEnd) {
                                            $this->logService->dataLog("$whatFunction - error sending data to setpoint_expanded. Start Data: $insUpdSetpointExStart, End Data: $insUpdSetpointExEnd", $whatFunction);
                                        }
                                    } else {
                                        $this->logService->dataLog("$whatFunction - NOTE: Data for zone " . $zone['zone_name'] . " (" . $zone['zone_code'] . ") was NOT transferred to setpoint_write.", $whatFunction);
                                    }
                                } // end if zone active
                            } // end foreach zones
                        } else {
                            if ($detailedLogging) {
                                $this->logService->dataLog("$whatFunction - No zones found.", $whatFunction);
                            }
                        }
                    } else {
                        if ($detailedLogging) {
                            $this->logService->dataLog("$whatFunction - Facility_id $eventFacID is marked inactive. Skipping.", $whatFunction);
                        }
                    }
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog("$whatFunction - No matching facility_id found for: $eventFacID", $whatFunction);
                        $missingFacIDs[$eventFacID] = $eventFacID;
                    }
                }
            }
            $this->logService->dataLog("$whatFunction - Processing complete. Data for $schedCount events transferred to setpoint_write.", $whatFunction);
            // Sending missing rooms to error_log so an alert can be set
            if(!empty($missingFacIDs)){
                ksort($missingFacIDs);
                foreach ($missingFacIDs as $missingFacID) {
                    error_log($whatFunction." - No matching facility_id found for: ".$missingFacID);
                }
            }
            return true;
        } catch (\PDOException $e) {
            error_log("$whatFunction - Data transaction(s) failed. Queries rolled back. Error: " . $e->getMessage());
            $this->utilities->returnMsg($e->getMessage(), "Error");
            return false;
        }
    }

}

