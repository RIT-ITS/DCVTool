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

class ImportEventService {
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

    public function importEvent($data): array
    {
        $conn = $this->dbManager->getDefaultConnection();
        $detailedLogging = $this->configService->getConfigValue('detailedLogging', false);
        $whatFunction = "importEvent";

        $ret = [];
        $log = [];

        try {
            $conn->beginTransaction();
            $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

            $bldgResult = false;
            $roomResult = false;
            $eventResult = false;
            $saniCourseTitle = $data["coursetitle"];
            $saniCampus = $data["campus"];
            $saniBldg = $data["bldg_num"];
            $saniRoom = $data["room_num"];
            $saniStart = $data["datetimeStart"];
            $saniEnd = $data["datetimeEnd"];
            $saniEnrlTot = $data["enrl_tot"];

            if ($detailedLogging) {
                $this->logService->dataLog($whatFunction." - Beginning data validation.", $whatFunction);
            }

            // Check the building
            $query = "SELECT * FROM buildings WHERE bldg_num = :bldg_num AND campus_id = :campus_id AND active = true LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':bldg_num', $saniBldg, PDO::PARAM_STR);
            $stmt->bindParam(':campus_id', $saniCampus, PDO::PARAM_STR);
            $stmt->execute();
            $bldgResult = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check the room
            if ($bldgResult) {
                if ($detailedLogging) {
                    $this->logService->dataLog($whatFunction." - Active building found for ".$saniBldg.", searching for active room.", $whatFunction);
                }

                $query = "SELECT * FROM rooms WHERE building_id = :id AND room_num = :room_num AND active = true LIMIT 1";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':id', $bldgResult["id"], PDO::PARAM_INT);
                $stmt->bindParam(':room_num', $saniRoom, PDO::PARAM_STR);
                $stmt->execute();
                $roomResult = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $ret["outputs"][] = "<strong>Error:</strong> No active building could be found with the number ".$saniBldg;
                $ret["type"] = "error";
                return $ret;
            }

            // If both spaces exist and are active, check to make sure there isn't an event already scheduled for the same room/time
            if ($bldgResult && $roomResult) {
                if ($detailedLogging) {
                    $this->logService->dataLog($whatFunction." - Active room found in ".$bldgResult["bldg_name"]." with the number ".$saniRoom.", searching for events within the same timeframe.", $whatFunction);
                }

                $query = "SELECT * FROM expanded_schedule_data WHERE bldg_num = :bldg_num AND room_number = :room_num AND 
                ((datetime_start AT TIME ZONE 'America/New_york' >= :startDate AND datetime_start AT TIME ZONE 'America/New_york' < :endDate)
                OR (datetime_end AT TIME ZONE 'America/New_york' > :startDateTwo AND datetime_end AT TIME ZONE 'America/New_york' <= :endDateTwo)
                OR (datetime_start AT TIME ZONE 'America/New_york' <= :startDateThree AND datetime_end AT TIME ZONE 'America/New_york' >= :endDateThree))";

                $stmt = $conn->prepare($query);
                $stmt->bindParam(':bldg_num', $saniBldg, PDO::PARAM_STR);
                $stmt->bindParam(':room_num', $saniRoom, PDO::PARAM_STR);
                $stmt->bindParam(':startDate', $saniStart, PDO::PARAM_STR);
                $stmt->bindParam(':endDate', $saniEnd, PDO::PARAM_STR);
                $stmt->bindParam(':startDateTwo', $saniStart, PDO::PARAM_STR);
                $stmt->bindParam(':endDateTwo', $saniEnd, PDO::PARAM_STR);
                $stmt->bindParam(':startDateThree', $saniStart, PDO::PARAM_STR);
                $stmt->bindParam(':endDateThree', $saniEnd, PDO::PARAM_STR);
                $stmt->execute();
                $eventResult = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $ret["outputs"][] = "<strong>Error:</strong> No active room could be found in ".$bldgResult["bldg_name"]." with the number ".$saniRoom;
                $ret["type"] = "error";
                return $ret;
            }

            // If an event is not scheduled in the room within the desired time-frame, add the new event to the schedule
            if ($eventResult) {
                $ret["outputs"][] = "<strong>Error:</strong> ".$eventResult["coursetitle"]. " is already scheduled in ".$roomResult["facility_id"]." for that timeframe.";
                $ret["type"] = "error";
                return $ret;
            } else {
                if ($detailedLogging) {
                    $this->logService->dataLog($whatFunction." - No events in ".$bldgResult["bldg_name"]." room ".$saniRoom." for the desired timeframe. Adding event to schedule.", $whatFunction);
                }

                $query = "SELECT * FROM terms WHERE term_start <= :startDate AND term_end >= :endDate LIMIT 1";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':startDate', $saniStart, PDO::PARAM_STR);
                $stmt->bindParam(':endDate', $saniEnd, PDO::PARAM_STR);
                $stmt->execute();
                $termCode = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($saniEnrlTot > $roomResult["room_population"]) {
                    $saniEnrlTot = $roomResult["room_population"];
                    $ret["outputs"][] = "<strong>Warning:</strong> Event enrollment exceeded the maximum population allowable in the room. Event enrollment truncated to the room's maximum population.";
                }

                $inQuery = "INSERT INTO expanded_schedule_data (pp_search_id, strm, facility_id, class_number_code, coursetitle, enrl_tot, bldg_num, bldg_code, room_number, datetime_start, datetime_end, campus)
                    VALUES (:pp_search_id, :strm, :facility_id, :class_number_code, :coursetitle, :enrl_tot, :bldg_num, :bldg_code, :room_number, :datetime_start, :datetime_end, :campus)";

                $inStmt = $conn->prepare($inQuery);
                $inStmt->bindValue(':pp_search_id', "", PDO::PARAM_STR);
                $inStmt->bindParam(':strm', $termCode['term_code'], PDO::PARAM_STR);
                $facilityId = $saniBldg."-".$saniRoom;
                $inStmt->bindParam(':facility_id', $facilityId, PDO::PARAM_STR);
                $inStmt->bindValue(':class_number_code', "", PDO::PARAM_STR);
                $inStmt->bindParam(':coursetitle', $saniCourseTitle, PDO::PARAM_STR);
                $inStmt->bindParam(':enrl_tot', $saniEnrlTot, PDO::PARAM_INT);
                $inStmt->bindParam(':bldg_num', $saniBldg, PDO::PARAM_STR);
                $inStmt->bindParam(':bldg_code', $bldgResult["facility_code"], PDO::PARAM_STR);
                $inStmt->bindParam(':room_number', $saniRoom, PDO::PARAM_STR);
                $inStmt->bindParam(':datetime_start', $saniStart, PDO::PARAM_STR);
                $inStmt->bindParam(':datetime_end', $saniEnd, PDO::PARAM_STR);
                $inStmt->bindParam(':campus', $saniCampus, PDO::PARAM_STR);
                $inStmt->execute();

                $log["id"] = $conn->lastInsertId();
                $log["strm"] = $termCode['term_code'];
                $log['facility_id'] = $saniBldg."-".$saniRoom;
                $log['coursetitle'] = $saniCourseTitle;
                $log['enrl_tot'] = $saniEnrlTot;
                $log['bldg_num'] = $saniBldg;
                $log['bldg_code'] = $bldgResult["facility_code"];
                $log['room_number'] = $saniRoom;
                $log['datetime_start'] = $saniStart;
                $log['datetime_end'] = $saniEnd;
                $log['campus'] = $saniCampus;

                $this->logService->logImports($log, "events");

                $conn->commit();

                if ($inStmt) {
                    $this->logService->dataLog($whatFunction." - Transaction successful. Event added to Expanded Schedule.", $whatFunction);
                    $ret["outputs"][] = "<strong>Success:</strong> ".$saniCourseTitle. " scheduled in ".$bldgResult["bldg_name"]." in room ".$saniRoom." on ".$data["formattedDate"]. " from ".$data["formattedStart"]." to ".$data["formattedEnd"];
                    $ret["type"] = "ok";
                } else {
                    $this->logService->dataLog($whatFunction." - Transaction failed. Unable to add event to Expanded Schedule.", $whatFunction);
                    $ret["outputs"][] = "<strong>Error:</strong> Unable to add event.";
                    $ret["type"] = "error";
                }
            }

            return $ret;

        } catch (PDOException $e) {
            $this->logService->dataLog($whatFunction . " - Data transaction(s) failed. Queries rolled back. Error: " . $e->getMessage(), $whatFunction);
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $this->utilities->returnMsg($e->getMessage(), "Error");
        }
    }



}

