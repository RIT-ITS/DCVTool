<?php

namespace App\Services\Reference\Academic;

use App\Services\Reference\BaseReferenceService;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use App\Utilities\Utilities;
use App\Database\DatabaseManager;
use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeZone;

class ClassService extends BaseReferenceService
{
    /**
     * @var SemesterService
     */
    protected SemesterService $semesterService;
    private Utilities $utilities;
    protected ConfigService $configService;
    protected LogService $logService;

    /**
     * ClassService constructor.
     *
     * @param DatabaseManager $dbManager The database manager
     * @param SemesterService $semesterService The semester service
     * @param Utilities|null $utilities
     * @param ConfigService|null $configService
     * @param LogService|null $logService
     */
    public function __construct(
        DatabaseManager $dbManager,
        SemesterService $semesterService,
        ?Utilities $utilities = null,
        ?ConfigService $configService = null,
        ?LogService $logService = null
    ) {
        parent::__construct($dbManager);
        $this->semesterService = $semesterService;

        // Use injected services if provided, otherwise create new instances
        $this->utilities = $utilities ?? new Utilities($dbManager);
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $this->utilities, $this->configService);
    }


    /**
     * Read all class schedules
     *
     * @param int $strm Semester term code (optional)
     * @param bool $isRaw Whether to return raw data
     * @param string $bldgcode Building code
     * @return array Class schedule data
     */
    public function readAllClassSchedules(int $strm = 0, bool $isRaw = false, string $bldgcode = 'GOL'): array
    {
        if ($strm > 0) {
            $currTerm = $strm;
        } else {
            $currTerm = $this->semesterService->determineSemester();
        }

        if (isset($currTerm) && $currTerm > 0) {
            $query = "SELECT * FROM class_schedule_data WHERE strm = :strm AND bldg_code = :bldgcode ORDER BY pp_search_id ASC";

            // Get the PDO connection from the database manager
            $connection = $this->dbManager->getDefaultConnection();

            // Prepare and execute the query
            $statement = $connection->prepare($query);
            $statement->bindParam(":strm", $currTerm);
            $statement->bindParam(":bldgcode", $bldgcode);
            $statement->execute();

            // Fetch all results as associative arrays
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $count = count($rows);

            $result = [];
            foreach ($rows as $row) {
                $result[] = [
                    "id" => $row["id"],
                    "pp_search_id" => $row["pp_search_id"],
                    "coursetitle" => $row["coursetitle"],
                    "class_number_code" => $row["class_number_code"],
                    "enrl_tot" => $row["enrl_tot"],
                    "facility_id" => $row["facility_id"],
                    "bldg_num" => $row["bldg_num"],
                    "bldg_code" => $row["bldg_code"],
                    "room_num" => $row["room_num"],
                    "start_date" => $row["start_date"],
                    "end_date" => $row["end_date"],
                    "meeting_time_start" => (new DateTime($row["meeting_time_start"], new DateTimeZone("America/New_York")))->format('h:i:s a'),
                    "meeting_time_end" => (new DateTime($row["meeting_time_end"], new DateTimeZone("America/New_York")))->format('h:i:s a'),
                    "last_updated" => $row["last_updated"],
                    "versioned" => $row["versioned"],
                    "monday" => $row["monday"],
                    "tuesday" => $row["tuesday"],
                    "wednesday" => $row["wednesday"],
                    "thursday" => $row["thursday"],
                    "friday" => $row["friday"],
                    "saturday" => $row["saturday"],
                    "sunday" => $row["sunday"],
                    "campus" => $row["campus"],
                    "strm" => $row["strm"]
                ];
            }

            if ($isRaw) {
                return $result;
            } else {
                return $this->formatResponse($result, $count, false);
            }
        } else {
            $result = [["error" => "data not found"]];
            return $this->formatResponse($result, 0, $isRaw);
        }
    }

    /**
     * Read one class schedule by ID
     *
     * @param int $id Class schedule ID
     * @param bool $isRaw Whether to return raw data
     * @return array Class schedule data
     */
    public function readOneClassSchedule(int $id, bool $isRaw = false): array
    {
        $query = "SELECT * FROM class_schedule_data WHERE id = :id LIMIT 1";

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($query);
        $statement->bindParam(":id", $id, \PDO::PARAM_INT);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "pp_search_id" => $row["pp_search_id"],
                "coursetitle" => $row["coursetitle"],
                "class_number_code" => $row["class_number_code"],
                "enrl_tot" => $row["enrl_tot"],
                "facility_id" => $row["facility_id"],
                "bldg_num" => $row["bldg_num"],
                "bldg_code" => $row["bldg_code"],
                "room_num" => $row["room_num"],
                "start_date" => $row["start_date"],
                "end_date" => $row["end_date"],
                "meeting_time_start" => (new DateTime($row["meeting_time_start"], new DateTimeZone("America/New_York")))->format('h:i:s a'),
                "meeting_time_end" => (new DateTime($row["meeting_time_end"], new DateTimeZone("America/New_York")))->format('h:i:s a'),
                "last_updated" => $row["last_updated"],
                "versioned" => $row["versioned"],
                "monday" => $row["monday"],
                "tuesday" => $row["tuesday"],
                "wednesday" => $row["wednesday"],
                "thursday" => $row["thursday"],
                "friday" => $row["friday"],
                "saturday" => $row["saturday"],
                "sunday" => $row["sunday"],
                "campus" => $row["campus"],
                "strm" => $row["strm"]
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read one class schedule by PeopleSoft ID
     *
     * @param string $ppid PeopleSoft ID
     * @param bool $isRaw Whether to return raw data
     * @return array Class schedule data
     */
    public function readOneClassScheduleByPPID(string $ppid, bool $isRaw = false): array
    {
        $query = "SELECT * FROM class_schedule_data WHERE pp_search_id = :id LIMIT 1";

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($query);
        $statement->bindParam(":id", $ppid, \PDO::PARAM_STR);
        $statement->execute();
        $resStat = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = $statement->rowCount();
        $result = array();
        foreach ($resStat as $row) {
            $result[] = array("id"=>$row["id"],"pp_search_id"=>$row["pp_search_id"],"coursetitle"=>$row["coursetitle"],"class_number_code"=>$row["class_number_code"],"enrl_tot"=>$row["enrl_tot"],"facility_id"=>$row["facility_id"],"bldg_num"=>$row["bldg_num"],"bldg_code"=>$row["bldg_code"],"room_num"=>$row["room_num"],"start_date"=>$row["start_date"],"end_date"=>$row["end_date"],"meeting_time_start"=>$row["meeting_time_start"],"meeting_time_end"=>$row["meeting_time_end"],"last_updated"=>$row["last_updated"],"versioned"=>$row["versioned"],"monday"=>$row["monday"],"tuesday"=>$row["tuesday"],"wednesday"=>$row["wednesday"],"thursday"=>$row["thursday"],"friday"=>$row["friday"],"saturday"=>$row["saturday"],"sunday"=>$row["sunday"],"campus"=>$row["campus"],"strm"=>$row["strm"]);
        }
        return $this->formatResponse($result, $count, $isRaw);
        }

     /**
     * Pull expanded schedule data based on date and building
     *
     * @param int $id Building ID
     * @param string $date1 Start date
     * @param string $date2 End date (optional)
     * @param bool $isRaw Whether to return raw data
     * @return array Expanded schedule data
     */
    public function readExpandedScheduleData(int $id, string $date1, string $date2 = '0-0-0', bool $isRaw = false): array
    {
        try {
            $startDate = (new \DateTime($date1, new \DateTimeZone('America/New_York')))->format('Y-m-d H:i:s.u');

            if ($date2 === '0-0-0') {
                $endDate = (new \DateTime($date1, new \DateTimeZone('America/New_York')))
                    ->add(new \DateInterval('P1D'))
                    ->format('Y-m-d H:i:s.u');
            } else {
                $endDate = (new \DateTime($date2, new \DateTimeZone('America/New_York')))
                    ->add(new \DateInterval('P1D'))
                    ->format('Y-m-d H:i:s.u');
            }

            $query = "SELECT * FROM expanded_schedule_data esd 
                     INNER JOIN buildings b ON b.bldg_num = esd.bldg_num 
                     WHERE b.id = :id 
                     AND esd.datetime_start AT TIME ZONE 'America/New_York' > :dateStart 
                     AND esd.datetime_end AT TIME ZONE 'America/New_York' < :dateEnd 
                     ORDER BY esd.datetime_start DESC";

            // Get the PDO connection from the database manager
            $connection = $this->dbManager->getDefaultConnection();

            // Prepare and execute the query
            $statement = $connection->prepare($query);
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $statement->bindValue(':dateStart', $startDate);
            $statement->bindValue(':dateEnd', $endDate);
            $statement->execute();

            // Fetch all results as associative arrays
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $count = count($rows);

            $result = [];
            foreach ($rows as $row) {
                $result[] = [
                    'id' => $row['id'],
                    'pp_search_id' => $row['pp_search_id'],
                    'strm' => $row['strm'],
                    'facility_id' => $row['facility_id'],
                    'class_number_code' => $row['class_number_code'],
                    'coursetitle' => $row['coursetitle'],
                    'enrl_tot' => $row['enrl_tot'],
                    'datetime_start' => $row['datetime_start'],
                    'datetime_end' => $row['datetime_end'],
                    'campus' => $row['campus']
                ];
            }

            if ($isRaw) {
                return $result;
            } else {
                return $this->formatResponse($result, $count, false);
            }
        } catch (\Exception $e) {
            return [
                'responseHeader' => [
                    'status' => 1,
                    'error' => $e->getMessage()
                ],
                'response' => [
                    'numFound' => 0,
                    'docs' => []
                ]
            ];
        }
    }

    /**
     * Update enrollment total for upcoming class sessions
     *
     * @param string $ppSearchId The pp_search_id of the class
     * @param int $semester The semester to update
     * @param int $newEnrollmentTotal The new enrollment total
     * @return bool True if successful, false on error
     */
    public function updateEnrollmentTotal(string $ppSearchId, int $semester, int $newEnrollmentTotal): bool
    {
        $tag = "updateEnrlTot";

        try {
            $query = "UPDATE expanded_schedule_data 
                      SET enrl_tot = :newenrltot, 
                          last_updated = now() 
                      WHERE pp_search_id = :ppsearchid 
                      AND strm = :strm 
                      AND datetime_start >= now()";

            // Get the database connection from the DatabaseManager
            $conn = $this->dbManager->getDefaultConnection();

            // Prepare and execute the query
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":newenrltot", $newEnrollmentTotal);
            $stmt->bindParam(":ppsearchid", $ppSearchId);
            $stmt->bindParam(":strm", $semester);
            $stmt->execute();

            // Get the number of affected rows
            $rowCount = $stmt->rowCount();

            $this->logService->dataLog(
                "Updated enrollment total to {$newEnrollmentTotal} for {$rowCount} upcoming sessions of class {$ppSearchId} in semester {$semester}.",
                $tag
            );

            return true;

        } catch (\PDOException $e) {
            // Log the exception
            $this->logService->dataLog("Database error: " . $e->getMessage(), $tag);

            // Return false on error
            return false;
        }
    }

    /**
     * Generate an array of dates when a class meets based on its schedule
     *
     * @param array $class The class data containing schedule information
     * @return array Array of meeting dates in Y-m-d format
     */
    public function generateClassDates(array $class): array
    {
        $tag = "generateClassDates";

        try {
            // Validate required fields
            if (!isset($class['start_date']) || !isset($class['end_date'])) {
                $this->logService->dataLog("Missing required date fields for class", $tag);
                return [];
            }

            // Create DateTime objects for start and end dates
            $start = new DateTime($class['start_date']);
            $end = new DateTime($class['end_date']);

            // Create interval and date range
            $interval = new DateInterval('P1D'); // 1 day interval
            $dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));

            $meetingDates = [];

            // Loop through each date in the range
            foreach ($dateRange as $date) {
                $dayOfWeek = strtolower($date->format('l')); // Convert to lowercase (e.g., "monday")

                // Check if class meets on this day
                if (isset($class[$dayOfWeek]) && $class[$dayOfWeek]) {
                    $meetingDates[] = $date->format('Y-m-d');
                }
            }

            // Sort dates chronologically
            usort($meetingDates, function($a, $b) {
                return strtotime($a) <=> strtotime($b);
            });

            $this->logService->dataLog(
                "Generated " . count($meetingDates) . " meeting dates for class between {$class['start_date']} and {$class['end_date']}.",
                $tag
            );

            return $meetingDates;

        } catch (\Exception $e) {
            // Log the exception
            $this->logService->dataLog("Error generating class dates: " . $e->getMessage(), $tag);

            // Return empty array on error
            return [];
        }
    }

    /**
     * Get expanded class data for a specific class and semester
     *
     * @param string $ppSearchId The pp_search_id of the class
     * @param int $semester The semester to check
     * @return array|null Array of expanded class data or null on error
     */
    public function getExpandedClassData(string $ppSearchId, int $semester): ?array
    {
        $tag = "getExpandedClassData";

        try {
            $query = "SELECT enrl_tot 
                  FROM expanded_schedule_data 
                  WHERE pp_search_id = :ppsearchid 
                  AND strm = :strm 
                  AND datetime_start >= now()";

            // Get the database connection from the DatabaseManager
            $conn = $this->dbManager->getDefaultConnection();

            // Prepare and execute the query
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":ppsearchid", $ppSearchId);
            $stmt->bindParam(":strm", $semester);
            $stmt->execute();

            // Fetch all results
            $expandedClassData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->logService->dataLog(
                "Retrieved " . count($expandedClassData) . " expanded class records for pp_search_id {$ppSearchId} in semester {$semester}.",
                $tag
            );

            return $expandedClassData;

        } catch (\PDOException $e) {
            // Log the exception
            error_log("ExpandedClassData Database PDO error: " . $e->getMessage());

            // Return null on error
            return null;
        } catch (\Exception $e) {
            error_log("ExpandedClassData Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Upsert class data for a specific date into the expanded_schedule_data table
     *
     * @param array $class The class data
     * @param string $classDate The date for this class instance in Y-m-d format
     * @param string $sourceServerTZ The timezone of the source server
     * @return bool True if successful, false on error
     */
    public function upsertClassData(array $class, string $classDate, string $sourceServerTZ): bool
    {
        $tag = "upsertClassData";

        try {
            // Create DateTime objects for the class date
            $incomingDT = new \DateTime($classDate);
            $incomingDTToExpanded = $incomingDT->format('Y-m-d');

            // Create DateTime objects for start and end times in the source timezone
            $datetimeStart = new \DateTime(
                $incomingDTToExpanded . ' ' . $class['meeting_time_start'],
                new \DateTimeZone($sourceServerTZ)
            );

            $datetimeEnd = new \DateTime(
                $incomingDTToExpanded . ' ' . $class['meeting_time_end'],
                new \DateTimeZone($sourceServerTZ)
            );

            // Convert to UTC for storage
            $datetimeStart->setTimezone(new \DateTimeZone('UTC'));
            $datetimeEnd->setTimezone(new \DateTimeZone('UTC'));

            // Format datetime strings
            $formattedDTStart = $datetimeStart->format('Y-m-d H:i:sO');
            $formattedDTEnd = $datetimeEnd->format('Y-m-d H:i:sO');

            $this->logService->dataLog(
                "Attempting upsert for pp_search_id: {$class['pp_search_id']} for {$incomingDT->format('l')} {$incomingDT->format('Y-m-d')}.",
                $tag
            );

            // Get database connection
            $conn = $this->dbManager->getDefaultConnection();

            // Prepare the upsert query
            $insertQuery = <<<'SQL'
            INSERT INTO expanded_schedule_data 
                (pp_search_id, strm, facility_id, class_number_code, coursetitle, enrl_tot, bldg_num, bldg_code, room_number, datetime_start, datetime_end, campus, last_updated) 
            VALUES 
                (:pp_search_id, :strm, :facility_id, :class_number_code, :coursetitle, :enrl_tot, :bldg_num, :bldg_code, :room_number, :datetime_start, :datetime_end, :campus, NOW())
            ON CONFLICT (pp_search_id, strm, datetime_start, datetime_end)
            DO UPDATE SET
                facility_id = excluded.facility_id,
                class_number_code = excluded.class_number_code,
                coursetitle = excluded.coursetitle,
                enrl_tot = excluded.enrl_tot,
                bldg_num = excluded.bldg_num,
                bldg_code = excluded.bldg_code,
                room_number = excluded.room_number,
                campus = excluded.campus,
                last_updated = NOW();
            SQL;

            // Prepare parameters
            $params = [
                ':pp_search_id' => $class['pp_search_id'],
                ':strm' => $class['strm'],
                ':facility_id' => $class['facility_id'],
                ':class_number_code' => $class['class_number_code'],
                ':coursetitle' => $class['coursetitle'],
                ':enrl_tot' => $class['enrl_tot'],
                ':bldg_num' => $class['bldg_num'],
                ':bldg_code' => $class['bldg_code'],
                ':room_number' => $class['room_num'],
                ':datetime_start' => $formattedDTStart,
                ':datetime_end' => $formattedDTEnd,
                ':campus' => $class['campus'],
            ];

            // Execute the query
            $stmtUpsert = $conn->prepare($insertQuery);
            $stmtUpsert->execute($params);

            // Get affected rows
            $affectedRowsUpsert = $stmtUpsert->rowCount();

            $this->logService->dataLog(
                "Successfully upserted class data. Affected rows: {$affectedRowsUpsert}",
                $tag
            );

            return true;

        } catch (\PDOException $e) {
            // Log database exception
            $this->logService->dataLog(
                "Database error during upsert: " . $e->getMessage(),
                $tag
            );
            return false;

        } catch (\Exception $e) {
            // Log other exceptions
            $this->logService->dataLog(
                "Error during upsert: " . $e->getMessage(),
                $tag
            );
            return false;
        }
    }


}