<?php

namespace App\Services\Imports;

use App\Database\DatabaseManager;
use App\Utilities\Utilities;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use PDO;
use PDOException;
use DateInterval;
use DateTime;
use DateTimeZone;

class ImportClassService {
    protected DatabaseManager $dbManager;
    protected Utilities $utilities;
    protected ConfigService $configService;
    protected LogService $logService;

    public function __construct(
        DatabaseManager $dbManager,
        ?Utilities $utilities = null,
        ?ConfigService $configService = null,
        ?LogService $logService = null
    ) {
        $this->dbManager = $dbManager;

        // Use injected services if provided, otherwise create new instances
        $this->utilities = $utilities ?? new Utilities($dbManager);
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $this->utilities, $this->configService);
    }
    public function importSisClassData($data): array
    {
        $ret = [];
        $campusId = $data['campus_id'];
        $errorArray = [];              // array of error rows
        $newClasses = [];              // Array of new classes
        $rowsProcessed = 0;
        $newClassCount = 0;            // tracks number of classes inserted
        $rowsWithNoImport = 0;
        $bldgResult = null;
        $roomResult = null;

        foreach ($data['data'] as $d) {
            try {
                $importData = 0;
                $d['class_step_complete'] = false;

                // Check to make sure the room and building the class is in exists
                $query = "SELECT id FROM buildings WHERE bldg_num = :bldg_num AND campus_id = :campus LIMIT 1";
                $params = [
                    ':bldg_num' => $d['bldg_num'],
                    ':campus' => $campusId
                ];
                $bldgResult = $this->dbManager->fetchOne($query, $params);

                if ($bldgResult) {
                    $query = "SELECT id, room_population FROM rooms WHERE room_num = :room_num AND building_id = :building_id LIMIT 1";
                    $params = [
                        ':room_num' => $d['room_num'],
                        ':building_id' => $bldgResult['id']
                    ];
                    $roomResult = $this->dbManager->fetchOne($query, $params);

                    $query = "SELECT * FROM class_schedule_data WHERE pp_search_id = :search_id AND class_number_code = :class_code LIMIT 1";
                    $params = [
                        ':search_id' => $d["pp_search_id"],
                        ':class_code' => $d["class_number_code"]
                    ];
                    $result = $this->dbManager->fetchOne($query, $params);

                    // Class Import
                    // If both the room and building exist, and the class add doesn't already exist, add the class
                    if ($bldgResult['id'] && $roomResult['id'] && $result === false) {
                        $r = [];

                        // Determine enrollment total based on room capacity
                        $enrollmentTotal = ($d["enrl_tot"] > $roomResult["room_population"])
                            ? $roomResult["room_population"]
                            : $d['enrl_tot'];

                        $query = "INSERT INTO class_schedule_data (
                            pp_search_id, coursetitle, class_number_code, enrl_tot, facility_id, 
                            bldg_num, bldg_code, room_num, campus, strm, start_date, end_date, 
                            meeting_time_start, meeting_time_end, monday, tuesday, wednesday, 
                            thursday, friday, saturday, sunday
                        ) VALUES (
                            :pp_search_id, :coursetitle, :class_number_code, :enrl_tot, :facility_id,
                            :bldg_num, :bldg_code, :room_num, :campus, :strm, :start_date, :end_date,
                            :meeting_time_start, :meeting_time_end, :monday, :tuesday, :wednesday,
                            :thursday, :friday, :saturday, :sunday
                        )";

                        $params = [
                            ':pp_search_id' => $d['pp_search_id'],
                            ':coursetitle' => $d['coursetitle'],
                            ':class_number_code' => $d['class_number_code'],
                            ':enrl_tot' => $enrollmentTotal,
                            ':facility_id' => $d['facility_id'],
                            ':bldg_num' => $d['bldg_num'],
                            ':bldg_code' => $d['bldg_code'],
                            ':room_num' => $d['room_num'],
                            ':start_date' => $d['start_date'],
                            ':end_date' => $d['end_date'],
                            ':meeting_time_start' => $d['meeting_time_start'],
                            ':meeting_time_end' => $d['meeting_time_end'],
                            ':monday' => $d['monday'],
                            ':tuesday' => $d['tuesday'],
                            ':wednesday' => $d['wednesday'],
                            ':thursday' => $d['thursday'],
                            ':friday' => $d['friday'],
                            ':saturday' => $d['saturday'],
                            ':sunday' => $d['sunday'],
                            ':strm' => $d['strm'],
                            ':campus' => $campusId
                        ];

                        $this->dbManager->execute($query, $params);
                        $newClassCount++;
                        $importData = 1;

                        $r['id'] = $this->dbManager->lastInsertId();
                        $r['pp_search_id'] = $d['pp_search_id'];
                        $r['coursetitle'] = $d['coursetitle'];
                        $r['enrl_tot'] = $enrollmentTotal;
                        $r['facility_id'] = $d['facility_id'];
                        $r['bldg_num'] = $d['bldg_num'];
                        $r['bldg_code'] = $d['bldg_code'];
                        $r['room_num'] = $d['room_num'];
                        $r['start_date'] = $d['start_date'];
                        $r['end_date'] = $d['end_date'];
                        $r['meeting_time_start'] = $d['meeting_time_start'];
                        $r['meeting_time_end'] = $d['meeting_time_end'];
                        $r['monday'] = $d['monday'];
                        $r['tuesday'] = $d['tuesday'];
                        $r['wednesday'] = $d['wednesday'];
                        $r['thursday'] = $d['thursday'];
                        $r['friday'] = $d['friday'];
                        $r['saturday'] = $d['saturday'];
                        $r['sunday'] = $d['sunday'];
                        $r['strm'] = $d['strm'];
                        $r['campus'] = $campusId;

                        $newClasses[] = $r;
                    }

                    if ($importData == 0) {
                        $rowsWithNoImport++;
                    }
                    $rowsProcessed++;
                }
            } catch (\PDOException $e) {
                $d['error_message'] = $e->getMessage();
                $errorArray[] = $d;
            }
        }

        if (!empty($newClasses)) {
            $this->logService->logImports($newClasses, 'classes');
        }

        $ret["totalRowsProcessed"] = $rowsProcessed;
        $ret["totalRowsNoImport"] = $rowsWithNoImport;
        $ret["totalRowsWithErrors"] = count($errorArray);
        $ret["errorArray"] = $errorArray;
        $ret["totalClassesAdded"] = $newClassCount;

        return $ret;
    }

}

