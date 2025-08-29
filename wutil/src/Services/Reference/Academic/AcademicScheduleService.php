<?php

namespace App\Services\Reference\Academic;

use AllowDynamicProperties;
use App\Services\Reference\BaseReferenceService;
use App\Database\DatabaseManager;
use App\Utilities\Utilities;
use App\Services\Reference\Spaces\BuildingService;
use App\Services\Reference\Academic\SemesterService;
use App\Services\Reference\Spaces\CampusService;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use DateTime;
use DateTimeZone;
use Exception;
use PDO;
use PDOException;

class AcademicScheduleService extends BaseReferenceService
{
    private Utilities $utilities;
    protected DatabaseManager $dbManager;
    private SemesterService $semesterService;
    private CampusService $campusService;
    private string $building;
    private DateTimeZone $timezone;
    protected BuildingService $buildingService;
    protected ConfigService $configService;
    protected LogService $logService;

    /**
     * @param Utilities $utilities
     * @param SemesterService $semesterService
     * @param DatabaseManager $dbManager
     * @param BuildingService $buildingService
     * @param CampusService $campusService
     * @param ConfigService|null $configService
     * @param LogService|null $logService
     * @param string $building The building code to filter by
     * @param string $timezone The timezone to use for date calculations
     * @throws \DateInvalidTimeZoneException
     */
    public function __construct(
        Utilities $utilities,
        SemesterService $semesterService,
        DatabaseManager $dbManager,
        BuildingService $buildingService,
        CampusService $campusService,
        ?ConfigService $configService = null,
        ?LogService $logService = null,
        string $building = 'GOL',
        string $timezone = 'America/New_York'
    ) {
        parent::__construct($dbManager);
        $this->utilities = $utilities;
        $this->semesterService = $semesterService;
        $this->building = $building;
        $this->campusService = $campusService;
        $this->timezone = new DateTimeZone($timezone);
        $this->buildingService = $buildingService;

        // Use injected services if provided, otherwise create new instances
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $utilities, $this->configService);
    }


    function querySOLR($baseUrl, $buildingQuery, $idQuery, $rows = 10000, $fields = null) {
        // Base URL
        //$baseUrl = 'https://sissearch.rit.edu/solr/sisClassMeetingPatterns/select';

        // Properly construct the query - the issue is with spaces in the URL
        // We need to encode the entire query string properly

        // Method 1: Construct the query with proper encoding
        if($idQuery != ''){
            $query = '(' . $buildingQuery . ') AND (' . $idQuery . ')';
        } else {
            $query = $buildingQuery;
        }
        $encodedQuery = urlencode($query);
        $url = $baseUrl . '?q=' . $encodedQuery . '&rows=' . $rows;

        // Add field list parameter if specified
        if ($fields !== null) {
            // If $fields is an array, join with commas
            if (is_array($fields)) {
                $fieldList = implode(',', $fields);
            } else {
                $fieldList = $fields;
            }
            $url .= '&fl=' . urlencode($fieldList);
        }

        error_log("querySOLR URL: " . $url);
        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP/SOLR Client');

        // For debugging
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Get verbose debug information
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);

        // Log the actual URL being requested - this is crucial for debugging
        error_log("SOLR Request URL: $url");
        error_log("SOLR Response Code: $httpCode");

        if ($httpCode === 0) {
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            error_log("CURL Connection Error: ($curlErrno) $curlError");
            error_log("CURL Verbose Log: $verboseLog");

            return ['error' => 1, 'message' => "Connection to SOLR failed: $curlError", 'errno' => $curlErrno];
        } else if ($httpCode === 200) {
            error_log('Connection successful. Retrieving Records.');
        }

        // Close cURL session
        curl_close($ch);

        // Return the response
        $data = json_decode($response, true);
        error_log('Available top-level keys: ' . implode(', ', array_keys($data)));
        error_log('Records found: ' . $data['response']['numFound']);
        return $data;
    }


    /**
     * Retrieve class meeting patterns from SIS for a specific semester
     *
     * @param int $chooseSemester The semester to retrieve data for (0 for current)
     * @param bool $detailedLogging Whether to log detailed information
     * @return array The class meeting patterns data
     * @throws Exception
     */
    public function retrieveClassMeetingPatterns(int $chooseSemester = 0, bool $detailedLogging = false): array
    {
        $whatFunction = "retrieveClassMeetingPatterns";
        $detailedLogging = $this->logService->isDetailedLoggingEnabled();
        $this->logService->dataLog("{$whatFunction} - data retrieval starting.", $whatFunction);

        if ($chooseSemester > 0) {
            $semester = $this->utilities->sanitizeIntegerInput($chooseSemester);
        } else {
            $semester = $this->semesterService->determineSemester() ?: 0;
        }
        if ($detailedLogging) {
            $this->logService->dataLog("{$whatFunction} - semester calculated to be {$semester}.", $whatFunction);
        }
        $retArr = [];
        if ($semester > 0) {
            try{
                //$json = json_decode($data, true);
                $json = $this->querySOLR('https://sissearch.rit.edu/solr/sisClassMeetingPatterns/select','building_descrshort:'.$this->building.'*', 'pp_search_id:*-'.$semester.'-*',25000,['pp_search_id','class_number_code','facility_id','building_descrshort','start_dt','end_dt','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']);

                // Check for JSON decode errors
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("JSON decode error: " . json_last_error_msg());
                }
            } catch (Exception $e) {
                if ($detailedLogging) {
                    $this->logService->dataLog($whatFunction." - Error: " . $e->getMessage(), $whatFunction);
                }
                // Handle the error appropriately
                $json = ['error' => true, 'message' => $e->getMessage()];
            }
        } else {
            $this->logService->dataLog("{$whatFunction} - Could not determine semester.", $whatFunction);
            return [
                "response" => [
                    "docs" => [
                        ["no data found."]
                    ]
                ]
            ];
        }

        if (json_last_error() === JSON_ERROR_NONE) {
            $this->logService->dataLog($whatFunction." - JSON Retrieved. Checking top-level keys.");
            $this->logService->dataLog($whatFunction.' - Available top-level keys: ' . implode(', ', array_keys($json)));
            // Check if the expected keys exist in the response
            if (isset($json['response']) && is_array($json['response'])) {
                $resp = $json['response']['docs'] ?? [];
                $numReq = $json['responseheader']['params']['rows'] ?? 0;
                $numFound = $json['response']['numFound'] ?? 0;
                $numStart = $json['response']['start'] ?? 0;
                if ($detailedLogging) {
                    $firstDoc = $json['response']['docs'][0];
                    // Log the entire first document
                    $this->logService->dataLog("First doc in JSON data: " . print_r($firstDoc, true));
                }
            } else {
                $resp = [];
                $numReq = 0;
                $numFound = 0;
                $numStart = 0;
                if ($detailedLogging) {
                    $this->logService->dataLog($whatFunction." - Warning: Expected 'response' key missing in JSON", $whatFunction);
                    $this->logService->dataLog($whatFunction.' - Available top-level keys: ' . implode(', ', array_keys($json)));
                }
            }

            foreach ($resp as $item) {
                if (!empty($item['class_number_code']) && strpos($item['pp_search_id'], '-' . $semester . '-') !== false) {
                    $ppSearchId = $item['pp_search_id'];
                    $classNumCode = $item['class_number_code'];
                    $facilityId = $item['facility_id'];
                    $locInfo = explode("-", $item['facility_id']);

                    $dateStart = null;
                    $dateEnd = null;

                    if (isset($item['meeting_time_start'])) {
                        $timeStart = DateTime::createFromFormat('h:i a', $item['meeting_time_start']);
                        if ($timeStart !== false) {
                            $dateStart = new DateTime($timeStart->format('h:i'), $this->timezone);
                        } else {
                            $dateStart = null;
                            $this->logService->dataLog("{$whatFunction} - DateTime::createFromFormat for 'meeting_time_start' failed for pp_search_id {$ppSearchId}", $whatFunction);
                        }
                    } else {
                        $dateStart = null;
                        $this->logService->dataLog("{$whatFunction} - meeting_time_start was null for pp_search_id {$ppSearchId}", $whatFunction);
                    }

                    if (isset($item['meeting_time_end'])) {
                        $timeEnd = DateTime::createFromFormat('h:i a', $item['meeting_time_end']);
                        if ($timeEnd !== false) {
                            $dateEnd = new DateTime($timeEnd->format('h:i'), $this->timezone);
                        } else {
                            $dateEnd = null;
                            $this->logService->dataLog("{$whatFunction} - DateTime::createFromFormat for 'meeting_time_end' failed for pp_search_id {$ppSearchId}", $whatFunction);
                        }
                    } else {
                        $dateEnd = null;
                        $this->logService->dataLog("{$whatFunction} - meeting_time_end was null for pp_search_id {$ppSearchId}", $whatFunction);
                    }

                    $startDate = $item['start_dt'] ?? null;
                    $endDate = $item['end_dt'] ?? null;
                    $startDateTime = DateTime::createFromFormat('Y-m-d H:i:s.u', $startDate);
                    $endDateTime = DateTime::createFromFormat('Y-m-d H:i:s.u', $endDate);
                    if ($startDateTime === false) {
                        // Get formatting errors
                        $errors = DateTime::getLastErrors();
                        $this->logService->dataLog("DateTime parsing error for start date: " . print_r($errors, true));
                        // Provide a fallback
                        $startDateTime = new DateTime();
                    }

                    if ($endDateTime === false) {
                        // Get formatting errors
                        $errors = DateTime::getLastErrors();
                        $this->logService->dataLog("DateTime parsing error for end date: " . print_r($errors, true));
                        // Provide a fallback
                        $endDateTime = new DateTime();
                    }
                    $formattedStartDate = $startDateTime->format('Y-m-d');
                    $formattedEndDate = $endDateTime->format('Y-m-d');
                    $mon = ($item['Monday'] ?? '') == "Y" ? 1 : 0;
                    $tues = ($item['Tuesday'] ?? '') == "Y" ? 1 : 0;
                    $wed = ($item['Wednesday'] ?? '') == "Y" ? 1 : 0;
                    $thur = ($item['Thursday'] ?? '') == "Y" ? 1 : 0;
                    $fri = ($item['Friday'] ?? '') == "Y" ? 1 : 0;
                    $sat = ($item['Saturday'] ?? '') == "Y" ? 1 : 0;
                    $sun = ($item['Sunday'] ?? '') == "Y" ? 1 : 0;
                    $bldgCode = explode(" ", $item['building_descrshort']);
                    $retArr[] = [
                        "pp_search_id" => $ppSearchId,
                        "class_number_code" => $classNumCode,
                        "facility_id" => $facilityId,
                        "bldg_code" => $bldgCode[0],
                        "bldg_num" => $locInfo[0],
                        "room_num" => $locInfo[1],
                        "meeting_time_start" => $dateStart?->format('h:i:s p') ?? '',
                        "meeting_time_end" => $dateEnd?->format('h:i:s p') ?? '',
                        "start_date" => $formattedStartDate,
                        "end_date" => $formattedEndDate,
                        "mon" => $mon,
                        "tues" => $tues,
                        "wed" => $wed,
                        "thur" => $thur,
                        "fri" => $fri,
                        "sat" => $sat,
                        "sun" => $sun
                    ];
                }
            }

            $topLevelCount = count($retArr);
            $this->logService->dataLog("{$whatFunction} - retrieved {$topLevelCount} classes out of the server tally of {$numFound}. passing data back.", $whatFunction);

            return $retArr;
        } else {
            $this->logService->dataLog("{$whatFunction} - Error: no JSON retrieved. No rows processed.", $whatFunction);
            return [
                "error" => true,
                "message" => $e->getMessage()
            ];
        }
    }

    /**
     * Retrieve class data from the Student Information System
     *
     * @param int $chooseSemester Optional semester code, will determine current semester if not provided
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array Class data or error response
     * @throws Exception
     */
    public function retrieveClassData(int $chooseSemester = 0, bool $isRaw = false): array
    {
        $detailedLogging = $this->logService->isDetailedLoggingEnabled();
        $functionName = "retrieveClassData";
        $this->logService->dataLog("$functionName - data retrieval starting.", $functionName);

        // Determine semester
        if ($chooseSemester > 0) {
            $semester = $this->utilities->sanitizeIntegerInput($chooseSemester);
        } else {
            $semester = $this->semesterService->determineSemester();
        }

        $this->logService->dataLog("$functionName - semester calculated to be $semester.", $functionName);

        $result = [];

        // Retrieve data from JSON feed
        if ($semester > 0) {
            try {
                // Query SOLR for class data
                $json = $this->querySolr(
                    'https://sissearch.rit.edu/solr/mainClassSearch/select',
                    "strm:$semester",
                    '',
                    25000,
                    ['pp_search_id', 'class_number_code', 'course_title_long', 'strm', 'enrl_tot', 'campus']
                );

                // Process response
                if (isset($json['response']) && isset($json['response']['docs'])) {
                    $docs = $json['response']['docs'];
                    $numFound = $json['response']['numFound'];

                    foreach ($docs as $item) {
                        if (!empty($item['class_number_code'])) {
                            $result[$item['pp_search_id']] = [
                                "class_number_code" => $item['class_number_code'],
                                "pp_search_id" => $item['pp_search_id'],
                                "coursetitle" => $item['course_title_long'],
                                "strm" => $item['strm'],
                                "enrl_tot" => $item['enrl_tot'],
                                "campus" => $item['campus']
                            ];
                        }
                    }

                    $totalCount = count(array_keys($result));
                    $this->logService->dataLog("$functionName - retrieved $totalCount classes out of the server tally of $numFound.", $functionName);

                    return $this->formatResponse($result, $totalCount, $isRaw);
                } else {
                    throw new Exception("Invalid response structure from SOLR");
                }
            } catch (Exception $e) {
                $this->logService->dataLog("$functionName - Error: " . $e->getMessage(), $functionName);
                $errorResult = [
                    "error" => true,
                    "message" => "Data retrieval error: " . $e->getMessage()
                ];
                return $this->formatResponse($errorResult, 0, $isRaw);
            }
        } else {
            $this->logService->dataLog("$functionName - Could not determine semester.", $functionName);
            $errorResult = [
                "error" => true,
                "message" => "No data found - could not determine semester"
            ];
            return $this->formatResponse($errorResult, 0, $isRaw);
        }
    }

    /**
     * Retrieve class exam data from the Student Information System
     *
     * @param int $buildingId Building ID to filter exams by
     * @param string $originDateTz Timezone for date/time conversion
     * @param int $chooseSemester Optional semester code, will determine current semester if not provided
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array Exam data or error response
     */
    public function retrieveClassExamData(
        int $buildingId = 0,
        string $originDateTz = "America/New_York",
        int $chooseSemester = 0,
        bool $isRaw = false
    ): array {
        $functionName = "retrieveClassExamData";
        $this->logService->dataLog("$functionName - starting.", $functionName);

        // Validate timezone
        if (!in_array($originDateTz, timezone_identifiers_list())) {
            $originDateTz = "America/New_York";
        }

        // Determine semester
        if ($chooseSemester > 0) {
            $semester = $this->utilities->sanitizeIntegerInput($chooseSemester);
        } else {
            $semester = $this->semesterService->determineSemester();
        }

        $this->logService->dataLog("$functionName - semester calculated to be $semester.", $functionName);

        // Get building data
        $this->logService->dataLog("$functionName - retrieving active building data for building id $buildingId.", $functionName);
        $buildingData = $this->buildingService->readOneBuilding($buildingId, false, true);

        if (isset($buildingData['response']['docs'][0][0])) {
            $buildingCode = $buildingData['response']['docs'][0][0]['facility_code'];
            $buildingNum = $buildingData['response']['docs'][0][0]['bldg_num'];
            $this->logService->dataLog("$functionName - found building facility code: $buildingCode", $functionName);
        } else {
            $buildingCode = 'aaa';
            $buildingNum = '';
            $this->logService->dataLog("$functionName - did not find building facility code so using default: $buildingCode", $functionName);
        }

        $result = [];

        // Retrieve data from JSON feed
        if ($semester > 0) {
            try {
                // Query SOLR for exam data
                $json = $this->querySolr(
                    'https://sissearch.rit.edu/solr/examDays/select',
                    "strm:$semester",
                    "facility_short:$buildingCode*",
                    10000
                );

                // Process response
                if (isset($json['response']) && isset($json['response']['docs'])) {
                    $docs = $json['response']['docs'];
                    $numFound = $json['response']['numFound'];

                    foreach ($docs as $item) {
                        if (!empty($item['pp_search_id']) && strpos($item['pp_search_id'], "-$semester-") !== false) {
                            $tempFac = explode(' ', $item['facility_short']);
                            $facilityId = $buildingNum . "-" . ($tempFac[1] ?? '');

                            // Handle datetime conversion
                            $examDate = $item['exam_dt'];
                            $examStartTime = $item['exam_start_time'];
                            $examEndTime = $item['exam_end_time'];

                            // Convert to UTC
                            $datetimeStart = new DateTime("$examDate $examStartTime", new DateTimeZone($originDateTz));
                            $datetimeStart->setTimezone(new DateTimeZone('UTC'));
                            $dtStart = $datetimeStart->format('Y-m-d H:i:s');

                            $datetimeEnd = new DateTime("$examDate $examEndTime", new DateTimeZone($originDateTz));
                            $datetimeEnd->setTimezone(new DateTimeZone('UTC'));
                            $dtEnd = $datetimeEnd->format('Y-m-d H:i:s');

                            $result[] = [
                                "pp_search_id" => $item['pp_search_id'],
                                "facility_descr" => $item['facility_descr'],
                                "facility_short" => $item['facility_short'],
                                "facility_id" => $facilityId,
                                "exam_start" => $dtStart,
                                "exam_end" => $dtEnd,
                                "strm" => $item['strm'],
                                "campus" => $item['location']
                            ];
                        }
                    }

                    $totalCount = count($result);
                    $this->logService->dataLog("$functionName - retrieved $totalCount exams out of the server tally of $numFound.", $functionName);

                    return $this->formatResponse($result, $totalCount, $isRaw);
                } else {
                    throw new Exception("Invalid response structure from SOLR");
                }
            } catch (Exception $e) {
                $this->logService->dataLog("$functionName - Error: " . $e->getMessage(), $functionName);
                $errorResult = [
                    "error" => true,
                    "message" => "Data retrieval error: " . $e->getMessage()
                ];
                return $this->formatResponse($errorResult, 0, $isRaw);
            }
        } else {
            $this->logService->dataLog("$functionName - Could not determine semester.", $functionName);
            $errorResult = [
                "error" => true,
                "message" => "No data found - could not determine semester"
            ];
            return $this->formatResponse($errorResult, 0, true);
        }
    }

    /**
     * Add or update classes in the database
     *
     * @param int $buildingId Building ID to filter classes by
     * @param int $chooseSemester Optional semester code, will determine current semester if not provided
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array Result of the operation
     */
    public function addUpdateClasses(
        int $buildingId = 0,
        int $chooseSemester = 0,
        bool $isRaw = false
    ): array {
        $functionName = "addUpdateClasses";
        $this->logService->dataLog("$functionName - starting.", $functionName);
        $detailedLogging = $this->logService->isDetailedLoggingEnabled();
        // Load all campuses once at the beginning
        $allCampuses = $this->campusService->readAllCampuses(true, true);
        $campusMap = [];
        // Create a lookup map by code for faster access
        if (isset($allCampuses['response']['docs'][0])) {
            foreach ($allCampuses['response']['docs'][0] as $campus) {
                if (isset($campus['code'])) {
                    $campusMap[$campus['code']] = $campus;
                }
            }
        }
        // Determine semester
        if ($chooseSemester > 0) {
            $semester = $this->utilities->sanitizeIntegerInput($chooseSemester);
        } else {
            $semester = $this->semesterService->determineSemester();
        }

        if ($detailedLogging) {
            $this->logService->dataLog("$functionName - semester calculated to be $semester.", $functionName);
        }

        // Validate semester
        if ($semester <= 0) {
            $this->logService->dataLog("$functionName - invalid semester: $semester", $functionName);
            $errorResult = [
                "error" => true,
                "message" => "Invalid semester"
            ];
            return $this->formatResponse($errorResult, 0, $isRaw);
        }

        try {
            // Get class data
            $classData = $this->retrieveClassData($semester);
            if (empty($classData) || isset($classData['error'])) {
                throw new Exception("Failed to retrieve class data");
            }

            // Get meeting patterns
            $meetingPatterns = $this->retrieveClassMeetingPatterns($buildingId, $semester);
            if (empty($meetingPatterns) || isset($meetingPatterns['error'])) {
                throw new Exception("Failed to retrieve meeting patterns");
            }

            // Index meeting patterns by pp_search_id for easier lookup
            $indexedPatterns = [];
            foreach ($meetingPatterns as $pattern) {
                if (!empty($pattern['pp_search_id'])) {
                    $indexedPatterns[$pattern['pp_search_id']] = $pattern;
                }
            }

            // Get database connection
            $connection = $this->dbManager->getDefaultConnection();

            // Begin transaction
            $connection->beginTransaction();

            // Prepare statements
            $checkStmt = $connection->prepare("SELECT id FROM class_schedule_data WHERE pp_search_id = :pp_search_id");
            $insertStmt = $connection->prepare(
                "INSERT INTO class_schedule_data (
                    pp_search_id, class_number_code, coursetitle, strm, enrl_tot, campus,
                    facility_id, meeting_time_start, meeting_time_end, 
                    monday, tuesday, wednesday, thursday, friday, saturday, sunday
                ) VALUES (
                    :pp_search_id, :class_number_code, :course_title, :strm, :enrl_tot, :campus,
                    :facility_id, :meeting_time_start, :meeting_time_end, 
                    :mon, :tues, :wed, :thurs, :fri, :sat, :sun
                )"
            );
            $updateStmt = $connection->prepare(
                "UPDATE class_schedule_data SET 
                    class_number_code = :class_number_code, 
                    coursetitle = :course_title, 
                    strm = :strm, 
                    enrl_tot = :enrl_tot, 
                    campus = :campus,
                    facility_id = :facility_id, 
                    meeting_time_start = :meeting_time_start, 
                    meeting_time_end = :meeting_time_end, 
                    monday = :mon, 
                    tuesday = :tues, 
                    wednesday = :wed, 
                    thursday = :thurs, 
                    friday = :fri, 
                    saturday = :sat, 
                    sunday = :sun, 
                    last_updated = NOW() 
                WHERE pp_search_id = :pp_search_id"
            );

            $insertCount = 0;
            $updateCount = 0;
            $errorCount = 0;
            $skippedCount = 0;

            // Process each class
            foreach ($classData as $class) {
                if (empty($class['pp_search_id'])) {
                    $skippedCount++;
                    continue;
                }
                $ppSearchId = $class['pp_search_id'];
                // Check if we have meeting pattern data for this class
                if (!isset($indexedPatterns[$ppSearchId])) {
                    if ($detailedLogging) {
                        $this->logService->dataLog("$functionName - skipping class $ppSearchId: no meeting pattern found", $functionName);
                    }
                    $skippedCount++;
                    continue;
                }

                // Combine class data with meeting pattern data
                $pattern = $indexedPatterns[$ppSearchId];
                //$combinedData = array_merge($class, $pattern); //not needed but is conceptually what is happening
                $campus = $class['campus'];
                $campusInfo = $campusMap[$campus] ?? null;
                $campusId = $campusInfo['id'];
                try {
                    // Check if class exists
                    $checkStmt->bindParam(':pp_search_id', $ppSearchId);
                    $checkStmt->execute();
                    $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    $params = [
                        ':pp_search_id' => $ppSearchId,
                        ':class_number_code' => $class['class_number_code'] ?? '',
                        ':course_title' => $class['coursetitle'] ?? '',
                        ':strm' => $class['strm'] ?? $semester,
                        ':enrl_tot' => $class['enrl_tot'] ?? 0,
                        ':campus' => $campusId ?? '',
                        ':facility_id' => $pattern['facility_id'] ?? '',
                        ':meeting_time_start' => $pattern['meeting_time_start'] ?? null,
                        ':meeting_time_end' => $pattern['meeting_time_end'] ?? null,
                        ':mon' => isset($pattern['mon']) ? ($pattern['mon'] ? 1 : 0) : 0,
                        ':tues' => isset($pattern['tues']) ? ($pattern['tues'] ? 1 : 0) : 0,
                        ':wed' => isset($pattern['wed']) ? ($pattern['wed'] ? 1 : 0) : 0,
                        ':thurs' => isset($pattern['thurs']) ? ($pattern['thurs'] ? 1 : 0) : 0,
                        ':fri' => isset($pattern['fri']) ? ($pattern['fri'] ? 1 : 0) : 0,
                        ':sat' => isset($pattern['sat']) ? ($pattern['sat'] ? 1 : 0) : 0,
                        ':sun' => isset($pattern['sun']) ? ($pattern['sun'] ? 1 : 0) : 0
                    ];

                    if ($exists) {
                        // Update existing class
                        $updateStmt->execute($params);
                        $updateCount++;

                        if ($detailedLogging) {
                            $this->logService->dataLog("$functionName - updated class: $ppSearchId", $functionName);
                        }
                    } else {
                        // Insert new class
                        $insertStmt->execute($params);
                        $insertCount++;

                        if ($detailedLogging) {
                            $this->logService->dataLog("$functionName - inserted class: $ppSearchId", $functionName);
                        }
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    $this->logService->dataLog("$functionName - Error processing class $ppSearchId: " . $e->getMessage(), $functionName);
                }
            }

            // Commit transaction
            $connection->commit();

            $result = [
                "success" => true,
                "classes" => [
                    "inserted" => $insertCount,
                    "updated" => $updateCount,
                    "skipped" => $skippedCount,
                    "errors" => $errorCount
                ],
                "semester" => $semester
            ];

            $this->logService->dataLog(
                "$functionName - completed. Classes: $insertCount inserted, $updateCount updated, $skippedCount skipped, $errorCount errors.",
                $functionName
            );

            return $this->formatResponse($result, $insertCount + $updateCount, $isRaw);

        } catch (Exception $e) {
            // Rollback transaction if an error occurred
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }

            $this->logService->dataLog("$functionName - Error: " . $e->getMessage(), $functionName);
            $errorResult = [
                "error" => true,
                "message" => "Failed to add/update classes: " . $e->getMessage()
            ];
            return $this->formatResponse($errorResult, 0, $isRaw);
        }
    }

    /**
     * Add or update exam data in the database
     *
     * @param int $buildingId Building ID to filter exams by
     * @param string $originDateTz Timezone for date/time conversion
     * @param int $chooseSemester Optional semester code, will determine current semester if not provided
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array|bool Result of the operation or false on error
     */
    public function addUpdateExams(
        int $buildingId = 0,
        string $originDateTz = "America/New_York",
        int $chooseSemester = 0,
        bool $isRaw = false
    ): array|bool {
        $functionName = "addUpdateClassExams";
        $this->logService->dataLog("$functionName - starting.", $functionName);
        $detailedLogging = $this->logService->isDetailedLoggingEnabled();
        // Load all campuses once at the beginning
        $allCampuses = $this->campusService->readAllCampuses(true, true);
        $campusMap = [];

        // Create a lookup map by code for faster access
        if (isset($allCampuses['response']['docs'][0])) {
            foreach ($allCampuses['response']['docs'][0] as $campus) {
                if (isset($campus['code'])) {
                    $campusMap[$campus['code']] = $campus;
                }
            }
        }
        // Validate timezone
        if (!in_array($originDateTz, timezone_identifiers_list())) {
            $originDateTz = "America/New_York";
        }

        // Determine semester
        if ($chooseSemester > 0) {
            $semester = $this->utilities->sanitizeIntegerInput($chooseSemester);
        } else {
            $semester = $this->semesterService->determineSemester();
        }

        if ($detailedLogging) {
            $this->logService->dataLog("$functionName - retrieving exam data.", $functionName);
        }

        // Get exam data
        //$classData = $this->retrieveClassExamData($buildingId, $originDateTz, $semester);
        // Get exam data - use isRaw=true to get the raw data array
        $response = $this->retrieveClassExamData($buildingId, $originDateTz, $semester, true);

        // Extract the actual exam data from the response
        $classData = [];
        if (isset($response['data']) && is_array($response['data'])) {
            $classData = $response['data'];
            if ($detailedLogging) {
                $this->logService->dataLog("$functionName - classData array is JSON.", $functionName);
            }
        } else if (is_array($response) && !isset($response['error'])) {
            // If the response is already the data array
            $classData = $response;
        }
        $retArr = [];  // Array for logging purposes

        if (!empty($classData)) {
            if ($detailedLogging) {
                $this->logService->dataLog("$functionName - found exam data. continuing processing.", $functionName);
            }

            try {
                // Get database connection
                $connection = $this->dbManager->getDefaultConnection();
                $connection->beginTransaction();

                // Prepare statements
                $selectStmt = $connection->prepare(
                    "SELECT DISTINCT * FROM exam_schedule_data WHERE pp_search_id = ? ORDER BY versioned DESC LIMIT 1"
                );

                $updateStmt = $connection->prepare(
                    "UPDATE exam_schedule_data SET 
                        pp_search_id = :pp_search_id, 
                        facility_descr = :facility_descr, 
                        facility_id = :facility_id, 
                        dt_start = :dt_start, 
                        dt_end = :dt_end, 
                        campus = :campus, 
                        strm = :strm, 
                        last_updated = NOW(), 
                        versioned = NOW() 
                    WHERE id = :curr_id"
                );

                $updateNoVersionStmt = $connection->prepare(
                    "UPDATE exam_schedule_data SET 
                        pp_search_id = :pp_search_id, 
                        facility_descr = :facility_descr, 
                        facility_id = :facility_id, 
                        dt_start = :dt_start, 
                        dt_end = :dt_end, 
                        campus = :campus, 
                        strm = :strm, 
                        last_updated = NOW() 
                    WHERE id = :curr_id"
                );

                $insertStmt = $connection->prepare(
                    "INSERT INTO exam_schedule_data (
                        pp_search_id, facility_descr, facility_id, dt_start, dt_end, 
                        campus, strm, last_updated, versioned
                    ) VALUES (
                        :pp_search_id, :facility_descr, :facility_id, :dt_start, :dt_end, 
                        :campus, :strm, NOW(), NOW()
                    )"
                );

                $insertCount = 0;
                $updateCount = 0;
                $unchangedCount = 0;

                // Process each exam
                foreach ($classData as $doc) {
                    // Check if all required keys exist before processing
                    if (!isset($doc['pp_search_id']) || !isset($doc['campus']) ||
                        !isset($doc['facility_descr']) || !isset($doc['facility_id']) ||
                        !isset($doc['exam_start']) || !isset($doc['exam_end']) ||
                        !isset($doc['strm'])) {

                        // Log the missing data for debugging
                        if ($detailedLogging) {
                            $this->logService->dataLog("$functionName - Skipping record due to missing required fields: " .
                                print_r($doc, true), $functionName);
                        }
                        continue; // Skip this record
                    }
                    // Extract relevant information
                    $ppSearchId = $doc['pp_search_id'];
                    $campus = $doc['campus'];
                    $facilityDescr = $doc['facility_descr'];
                    $facilityId = $doc['facility_id'];
                    $examStart = $doc['exam_start'];
                    $examEnd = $doc['exam_end'];
                    $strm = $doc['strm'];
                    $campusInfo = $campusMap[$campus] ?? null;
                    if (!$campusInfo) {
                        if ($detailedLogging) {
                            $this->logService->dataLog("$functionName - Campus code '$campus' not found in campus map. Using default campus ID.", $functionName);
                        }
                        $campusId = 1; // Default campus ID if not found
                    } else {
                        $campusId = $campusInfo['id'] ?? 1; // Use ID from map or default to 1 if 'id' key doesn't exist
                    }
                    $campusId = (int)$campusId;
                    if ($campusId <= 0) {
                        $campusId = 1; // Ensure we have a positive integer as fallback
                    }
                    if ($detailedLogging) {
                        $this->logService->dataLog("$functionName - Using campus ID: $campusId for campus code: $campus", $functionName);
                    }
                    // Check if record exists
                    $selectStmt->execute([$ppSearchId]);
                    $row = $selectStmt->fetch(\PDO::FETCH_ASSOC);

                    if ($row) {
                        $currId = $row['id'];

                        // Check if data has changed
                        if (
                            strtotime($row['dt_start']) != strtotime($examStart) ||
                            strtotime($row['dt_end']) != strtotime($examEnd) ||
                            $row['facility_id'] != $facilityId ||
                            $row['facility_descr'] != $facilityDescr ||
                            $row['campus'] != $campus || $row['strm'] != $strm
                        ) {
                            // Data has changed, update with new version
                            $updateStmt->bindValue(":pp_search_id", $ppSearchId);
                            $updateStmt->bindValue(":campus", $campusId, \PDO::PARAM_INT);
                            $updateStmt->bindValue(":facility_id", $facilityId);
                            $updateStmt->bindValue(":facility_descr", $facilityDescr);
                            $updateStmt->bindValue(":dt_start", $examStart);
                            $updateStmt->bindValue(":dt_end", $examEnd);
                            $updateStmt->bindValue(":strm", $strm);
                            $updateStmt->bindValue(":curr_id", $currId);

                            if ($updateStmt->execute()) {
                                $updateCount++;
                                if ($detailedLogging) {
                                    $this->logService->dataLog("$functionName - exam event for pp_search_id: $ppSearchId updated.", $functionName);
                                }
                            }
                        } else {
                            // Data hasn't changed
                            $unchangedCount++;
                            if ($detailedLogging) {
                                $this->logService->dataLog("$functionName - data for pp_search_id: $ppSearchId is the same. update not needed.", $functionName);
                            }
                        }
                    } else {
                        // Insert new record
                        $insertStmt->bindValue(":pp_search_id", $ppSearchId);
                        $insertStmt->bindValue(":campus", $campusId, \PDO::PARAM_INT);
                        $insertStmt->bindValue(":facility_id", $facilityId);
                        $insertStmt->bindValue(":facility_descr", $facilityDescr);
                        $insertStmt->bindValue(":dt_start", $examStart);
                        $insertStmt->bindValue(":dt_end", $examEnd);
                        $insertStmt->bindValue(":strm", $strm);

                        if ($insertStmt->execute()) {
                            $insertCount++;
                            if ($detailedLogging) {
                                $this->logService->dataLog("$functionName - exam event for pp_search_id: $ppSearchId inserted", $functionName);
                            }
                        }
                    }
                }

                // Commit transaction
                $connection->commit();

                if ($detailedLogging) {
                    $this->logService->dataLog("$functionName - committing queries now.", $functionName);
                }

                // Prepare result data
                $result = [
                    "success" => true,
                    "exams" => [
                        "inserted" => $insertCount,
                        "updated" => $updateCount,
                        "unchanged" => $unchangedCount,
                        "total" => count($classData)
                    ],
                    "semester" => $semester
                ];

                if ($detailedLogging) {
                    foreach ($retArr as $item) {
                        $this->logService->dataLog($item, $functionName);
                    }
                }

                $this->logService->dataLog("$functionName - processing complete.", $functionName);

                return $this->formatResponse($result, $insertCount + $updateCount, $isRaw);

            } catch (\Exception $e) {
                // Rollback transaction on error
                if (isset($connection) && $connection->inTransaction()) {
                    $connection->rollBack();
                }

                // Log exception
                $this->logService->logException(
                    $e,
                    "$functionName - data transaction(s) failed. queries rolled back. also the following error was generated: "
                );
                $this->utilities->returnMsg($e->getMessage(), "error");

                //return false;
            }
        } else {
            // No exam data found
            $result = [
                "success" => true,
                "message" => "No exam data found",
                "exams" => [
                    "inserted" => 0,
                    "updated" => 0,
                    "unchanged" => 0,
                    "total" => 0
                ],
                "semester" => $semester
            ];

            $this->logService->dataLog("$functionName - processing complete. No exam data found.", $functionName);

            return $this->formatResponse($result, 0, $isRaw);
        }
    }

}
