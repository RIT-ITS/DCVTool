<?php

declare(strict_types=1);

// Include bootstrap file to set up autoloading and environment
require_once __DIR__ . '/../../../bootstrap.php';

use App\Container\ServiceContainer;
use App\Security\Security;
use App\Utilities\UrlService;
use App\Services\Response\JsonResponseService;

// Initialize ServiceContainer
$container = ServiceContainer::getInstance();

// Get core services from container
$dbManager = $container->get('dbManager');
$securityService = new Security($dbManager); // Keep this if Security isn't in the container yet
$urlService = new UrlService(); // Keep this if UrlService isn't in the container yet
$jsonResponse = new JsonResponseService(); // Keep this if JsonResponseService isn't in the container yet
$utilities = $container->get('utilities');
$configService = $container->get('configService');
$logger = $container->get('logService');

// Check authentication
$userauth = $securityService->checkAuthenticationStatus();
if($userauth){
    $userData = $securityService->checkAuthorized();
} else {
    $userData = null;
}

// Get URL information
$urlInfo = $urlService->parseCurrentUrl();
$pathVar = $urlInfo['pathVar'];

$query_q = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_NUMBER_INT); // which query are we doing?
$query_i = filter_input(INPUT_GET, 'i', FILTER_SANITIZE_NUMBER_INT); // are we giving an id?
$query_s = filter_input(INPUT_GET, 's', FILTER_SANITIZE_NUMBER_INT); // starting point for pagination of data
$query_n = filter_input(INPUT_GET, 'n', FILTER_SANITIZE_NUMBER_INT); // how many per page for pagination of data
$query_c = filter_input(INPUT_GET, 'c', FILTER_SANITIZE_NUMBER_INT); // restrict to category
$query_b = filter_input(INPUT_GET, 'b', FILTER_SANITIZE_NUMBER_INT); // restrict to building
$query_f = filter_input(INPUT_GET, 'f', FILTER_SANITIZE_NUMBER_INT); // floor number
$query_d = filter_input(INPUT_GET, 'd', FILTER_SANITIZE_NUMBER_INT); // date query
$query_t = filter_input(INPUT_GET, 't', FILTER_SANITIZE_NUMBER_INT); // date query

if (filter_var($query_q, FILTER_VALIDATE_INT)) {
    // include_once ("lib/core.php");
    // query int is valid
    switch ($query_q) {
        //--- Ashrae 6-1 Types
        case 1:
            $ashraeService = $container->get('ashraeService');
            $dataRet = $ashraeService->readAllAshrae61Types();
            $jsonResponse->send($dataRet);
            break;
        //--- Ashrae 6-1 Type Individual Record
        case 2:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            if($var1>0) {
                $ashraeService = $container->get('ashraeService');
                $dataRet = $ashraeService->readOneAshrae61Type($var1);
                $jsonResponse->send($dataRet);
            }
            break;
        //--- Ashrae 6-1 Categories
        case 3:
            // fetch ALL, optionally filter by type
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $ashraeService = $container->get('ashraeService');
            $dataRet = $ashraeService->readAllAshrae61($var1);
            $jsonResponse->send($dataRet);
            break;
        //--- Ashrae 6-1 - Individual Record
        case 4:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            if($var1>0) {
                $ashraeService = $container->get('ashraeService');
                $dataRet = $ashraeService->readOneAshrae61($var1);
                $jsonResponse->send($dataRet);
            }
            break;
        //--- Ashrae 6-4 categories - ALL
        case 5:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $ashraeService = $container->get('ashraeService');
            if ($var1>0) {
                $dataRet = $ashraeService->readOneAshrae64Category($var1);
            } else {
                $dataRet = $ashraeService->readAllAshrae64Cats();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- Ashrae 6-4 - optional id
        case 6:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $ashraeService = $container->get('ashraeService');
            if ($var1>0) {
                $dataRet = $ashraeService->readOneAshrae64($var1);
            } else {
                $dataRet = $ashraeService->readAllAshrae64();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- Rooms query. Querystring: ?q=7&b={building#}&i={id num (optional if you want only one)}
        case 7:
            $var1 = $utilities->sanitizeIntegerInput($query_i); // Room id
            $var2 = $utilities->sanitizeIntegerInput($query_b); // Building id
            $var3 = $utilities->sanitizeIntegerInput($query_f); // Floor id
            $roomService = $container->get('roomService');
            $dataRet = array();
            if ($var1 > 0) { // we have a specific room id so we don't care about floor or building just grab the room
                try {
                    $dataRet = $roomService->readOneRoom($var1, false);
                } catch (Exception $e) {

                }
            } else if ($var2 > 0 && $var3 == 0) { // we have a building id but no floor id
                try {
                    $dataRet = $roomService->readAllRooms($var2);
                } catch (Exception $e) {

                }
            } else if ($var2 > 0 && $var3 > 0) { // we have a building id AND a floor id
                try {
                    $dataRet = $roomService->readAllRooms($var2, $var3);
                } catch (Exception $e) {

                }
            } else {
                $dataRet = array();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- read buildings
        case 8:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $var2 = $utilities->sanitizeIntegerInput($query_c);
            $buildingService = $container->get('buildingService');
            if ($var1 > 0) { // we have a specific id so we don't care about floor or building just grab the room
                $dataRet = $buildingService->readOneBuilding($var1);
            } else if ($var2 > 0) {   // get all the buildings by campus
                $dataRet = $buildingService->readAllBuildings($var2);
            } else {
                $dataRet = array();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- Floor Query  Querystring: ?q=9&i={id num of building}
        case 9:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $buildingService = $container->get('buildingService');
            if ($var1 > 0) { // we have a specific id so we don't care about floor or building just grab the room
                $dataRet = $buildingService->readAllBlgFloorsNew($var1);
            } else {
                $dataRet = array();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- Campus Query
        case 10:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            /** @var \App\Services\Reference\Spaces\CampusService $campusService */
            $campusService = $container->get('campusService');
            if ($var1 > 0) { // we have a specific id so we don't care about floor or building just grab the room
                $dataRet = $campusService->readOneCampus($var1);
            } else {
                $dataRet = $campusService->readAllCampuses();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- SIS Data retrieval
        case 11:
            $var1 = $utilities->sanitizeIntegerInput($query_i); // class id
            /** @var \App\Services\Reference\Academic\ClassService $classService */
            $classService = $container->get('classService');
            if ($var1>0) {
                $dataRet = $classService->readAllClassSchedules($var1);
            } else {
                $dataRet = $classService->readAllClassSchedules();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- Get Semester Term info
        case 12:
            $semesterService = $container->get('semesterService');
            $dataRet = $semesterService->readCurrentSemester();
            $jsonResponse->send($dataRet);
            break;
        //--- Get Uncertainty Table info
        case 13:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            /** @var \App\Services\Reference\Academic\UncertaintyService $uncertaintyService */
            $uncertaintyService = $container->get('uncertaintyService');
            if (filter_var($query_i, FILTER_VALIDATE_INT)) {
                $dataRet = $uncertaintyService->readOneUncertainty($var1, false);
            } else {
                $dataRet = $uncertaintyService->readAllUncertainties();
            }
            $jsonResponse->send($dataRet);
            break;
        //-- Get equipment_map data (from Jordan)
        case 14:
            $equipmentData = $container->get('equipmentService');
            if (isset($var1)) {
                $var1 = $utilities->sanitizeIntegerInput($query_i);
            } else {
                $var1 = 0;
            };
            if ($var1 != 0) {
                $dataRet = $equipmentData->getEquipmentMap($var1);
            } else {
                $dataRet = $equipmentData->getEquipmentMap();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- Get Term Table info
        case 15:
            $var1 = $utilities->sanitizeIntegerInput($query_i); // term id
            $semesterService = $container->get('semesterService');
            if ($var1>0) {
                $dataRet = $semesterService->getTerms($var1);
            } else {
                $dataRet = $semesterService->getTerms();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- NCES Table 4-2
        case 16:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            /** @var \App\Services\Reference\Standards\NcesService $ncesService */
            $ncesService = $container->get('ncesService');
            if ($var1>0) {
                $dataRet = $ncesService->getNces42($var1);
            } else {
                $dataRet = $ncesService->getNces42();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- NCES Categories
        case 17:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            /** @var \App\Services\Reference\Standards\NcesService $ncesService */
            $ncesService = $container->get('ncesService');
            if ($var1>0) {
                $dataRet = $ncesService->getNcesCat($var1);
            } else {
                $dataRet = $ncesService->getNcesCat();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- User data
        case 18:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $userData = $container->get('userService');
            if ($var1>0) {
                $dataRet = $userData->readUsers($var1);
            } else {
                $dataRet = $userData->readUsers('all');
            }
            $jsonResponse->send($dataRet);
            break;
        //--- Read Config variables
        case 19:
            $var1 = $query_i === null ? 'all' : $utilities->sanitizeIntegerInput($query_i); // parameter
            /** @var \App\Services\Configuration\ConfigService $configService */
            $configService = $container->get('configService');
            $dataRet = $configService->readConfigVars((string) $var1, false, $userData);
            $jsonResponse->send($dataRet);
            break;
        //--- log data
        case 20:
            $dataRet = $logger->readLogs();
            $json_result = json_encode($dataRet);
            header("Content-Type: application/json");
            echo $json_result;
            break;
        //--- read logged updates
        case 21:
            $dataRet = $logger->readLoggedUpdates(['type' => 'all']);
            $jsonResponse->send($dataRet);
            exit;
        //--- read Updated Vars
        case 22:
            // first date year
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            // first date month
            $var2 = $utilities->sanitizeIntegerInput(ltrim($query_s, "0"));
            // first date day
            $var3 = $utilities->sanitizeIntegerInput(ltrim($query_n, "0"));
            // second date year
            $var4 = $utilities->sanitizeIntegerInput($query_c);
            // second date month
            $var5 = $utilities->sanitizeIntegerInput(ltrim($query_b, "0"));
            // second date day
            $var6 = $utilities->sanitizeIntegerInput(ltrim($query_f, "0"));
            if ($var1>0 && $var2>0 && $var3>0 && $var4>0 && $var5>0 && $var6>0) {
                $dataRet = $logger->readLoggedUpdates(['dateStart' => $var1 . "-" . $var2 . "-" . $var3, "dateEnd" => $var4 . "-" . $var5 . "-" . $var6]);
            } else {
                $dataRet = array();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- import log data
        case 23:
            $dataRet = $logger->readImportLogs('all');
            $jsonResponse->send($dataRet);
            exit;
        //--- zones by building
        case 24:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $roomService = $container->get('roomService');
            $zoneService = $container->get('zoneService');
            // Set the dependencies after creation
            $roomService->setZoneService($zoneService);
            $zoneService->setRoomService($roomService);
            if ($var1>0) {
                $dataRet = $zoneService->readZonesByBuilding($var1);
            } else {
                $dataRet = $zoneService->readAllZones();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- rooms by linked zone
        case 25:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $roomService = $container->get('roomService');
            $zoneService = $container->get('zoneService');
            // Set the dependencies after creation
            $roomService->setZoneService($zoneService);
            $zoneService->setRoomService($roomService);
            if ($var1 != 0) {
                $dataRet = $zoneService->getRoomsByZone($var1);
            }
            $jsonResponse->send($dataRet);
            break;
        //--- room-zone connections by building
        case 26:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $roomService = $container->get('roomService');
            $zoneService = $container->get('zoneService');
            // Set the dependencies after creation
            $roomService->setZoneService($zoneService);
            $zoneService->setRoomService($roomService);
            if ($var1 != 0) {
                try {
                    $dataRet = $zoneService->getXrefsByBuildingNew($var1);
                } catch (Exception $e) {
                    error_log($e->getMessage());
                }
            }
            $jsonResponse->send($dataRet);
            break;
        //--- AHUs
        case 27:
            $ahuService = $container->get('ahuService');
            $dataRet = $ahuService->readAllAHUs();
            $jsonResponse->send($dataRet);
            break;
        //--- Rooms with VAVs
        case 28:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $roomService = $container->get('roomService');
            $zoneService = $container->get('zoneService');
            // Set the dependencies after creation
            $roomService->setZoneService($zoneService);
            $zoneService->setRoomService($roomService);
            if ($var1 != 0) {
                try {
                    $dataRet = $roomService->getRoomsWithVAVs($var1);
                } catch (Exception $e) {
                    error_log($e->getMessage());
                }
            }
            $jsonResponse->send($dataRet);
            break;
        //--- Rooms with Classes
        case 29:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $var2 = $utilities->sanitizeIntegerInput($query_b);
            $roomService = $container->get('roomService');
            $zoneService = $container->get('zoneService');
            // Set the dependencies after creation
            $roomService->setZoneService($zoneService);
            $zoneService->setRoomService($roomService);
            if ($var1>0 && $var2>0) {
                try {
                    $dataRet = $roomService->getRoomsWithClasses($var1, false, false, $var2);
                } catch (Exception $e) {
                    error_log($e->getMessage());
                }
            }
            $jsonResponse->send($dataRet);
            break;
        //--- Airflow Xref
        case 30:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $airflowData = $container->get('airflowService');
            if ($var1>0) {
                $dataRet = $airflowData->getAirflowXrefData($var1);
            } else {
                $dataRet = array();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- zones with events
        case 31:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $var2 =$utilities->sanitizeIntegerInput($query_b);
            $zoneData = $zoneService = $container->get('zoneService');
            if ($var1>0  && $var2>0) {
                $dataRet = $zoneData->getZonesWithEvents($var1, false, true, $var2);
            } else {
                $dataRet = array();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- airflow event data
        case 32:
            $var1 = $utilities->sanitizeIntegerInput($query_i);
            $var2 = $utilities->sanitizeIntegerInput($query_b);
            $airflowData = $container->get('airflowService');
            if ($var1>0  && $var2>0) {
                $dataRet = $airflowData->getAirflowEventData($var1, $var2);
            } else {
                $dataRet = array();
            }
            $jsonResponse->send($dataRet);
            break;
        //--- read all setpoint write
        case 33:
            /** @var \App\Services\Reference\Hvac\SetpointService $setpointService */
            $setpointService = $container->get('setpointService');
            $dataRet = $setpointService->readSetpointWriteDataAll();
            $jsonResponse->send($dataRet);
            break;
        //--- read setpoint write data
        case 34:
            if (filter_var($query_i, FILTER_VALIDATE_INT)) { // first date year
                $var1 = $query_i;
            } else {
                $var1 = 0;
            }
            if (filter_var(ltrim($query_s, (string)0), FILTER_VALIDATE_INT)) { // first date month
                $var2 = $query_s;
            } else {
                $var2 = 0;
            }
            if (filter_var(ltrim($query_n, (string)0), FILTER_VALIDATE_INT)) { // first date day
                $var3 = $query_n;
            } else {
                $var3 = 0;
            }
            if (filter_var($query_c, FILTER_VALIDATE_INT)) { // second date year
                $var4 = $query_c;
            } else {
                $var4 = 0;
            }
            if (filter_var(ltrim($query_b, (string)0), FILTER_VALIDATE_INT)) { // second date month
                $var5 = $query_b;
            } else {
                $var5 = 0;
            }
            if (filter_var(ltrim($query_f, (string)0), FILTER_VALIDATE_INT)) { // second date day
                $var6 = $query_f;
            } else {
                $var6 = 0;
            }
            /** @var \App\Services\Reference\Hvac\SetpointService $setpointService */
            $setpointService = $container->get('setpointService');
            if(filter_var($query_i, FILTER_VALIDATE_INT) && filter_var(ltrim($query_s, (string)0), FILTER_VALIDATE_INT) && filter_var(ltrim($query_n, (string)0), FILTER_VALIDATE_INT)) {
                $dataRet = $setpointService->readSetpointWriteData($var1."-".$var2."-".$var3, $var4."-".$var5."-".$var6);
            }
            $jsonResponse->send($dataRet);
            break;
        //--- read expanded setpoint data all
        case 35:
            /** @var \App\Services\Reference\Hvac\SetpointService $setpointService */
            $setpointService = $container->get('setpointService');
            $dataRet = $setpointService->readExpandedSetPointWriteDataAll();
            $jsonResponse->send($dataRet);
            break;
        //--- read some expanded setpoint write data
        case 36:
            if (filter_var($query_i, FILTER_VALIDATE_INT)) { // first date year
                $var1 = $query_i;
            } else {
                $var1 = 0;
            }
            if ($query_s && filter_var(ltrim($query_s, (string)0), FILTER_VALIDATE_INT)) { // first date month
                $var2 = $query_s;
            } else {
                $var2 = 0;
            }
            if ($query_n && filter_var(ltrim($query_n, (string)0), FILTER_VALIDATE_INT)) { // first date day
                $var3 = $query_n;
            } else {
                $var3 = 0;
            }
            if ($query_c && filter_var($query_c, FILTER_VALIDATE_INT)) { // second date year
                $var4 = $query_c;
            } else {
                $var4 = 0;
            }
            if ($query_b && filter_var(ltrim($query_b, (string)0), FILTER_VALIDATE_INT)) { // second date month
                $var5 = $query_b;
            } else {
                $var5 = 0;
            }
            if ($query_f && filter_var(ltrim($query_f, (string)0), FILTER_VALIDATE_INT)) { // second date day
                $var6 = $query_f;
            } else {
                $var6 = 0;
            }
            /** @var \App\Services\Reference\Hvac\SetpointService $setpointService */
            $setpointService = $container->get('setpointService');
            if(filter_var($query_i, FILTER_VALIDATE_INT) && filter_var(ltrim($query_s, (string)0), FILTER_VALIDATE_INT) && filter_var(ltrim($query_n, (string)0), FILTER_VALIDATE_INT)) {
                $dataRet = $setpointService->readExpandedSetPointWriteData($var1."-".$var2."-".$var3, $var4."-".$var5."-".$var6);
            }
            $jsonResponse->send($dataRet);
            break;
        //--- read expanded schedule data
        case 37:
            if ($query_s && filter_var($query_s, FILTER_VALIDATE_INT)) { // first date year
                $var1 = $query_s;
            } else {
                $var1 = 0;
            }
            if ($query_n && filter_var(ltrim($query_n, (string)0), FILTER_VALIDATE_INT)) { // first date month
                $var2 = $query_n;
            } else {
                $var2 = 0;
            }
            if ($query_c && filter_var(ltrim($query_c, (string)0), FILTER_VALIDATE_INT)) { // first date day
                $var3 = $query_c;
            } else {
                $var3 = 0;
            }
            if ($query_b && filter_var($query_b, FILTER_VALIDATE_INT)) { // second date year
                $var4 = $query_b;
            } else {
                $var4 = 0;
            }
            if ($query_f && filter_var(ltrim($query_f, (string)0), FILTER_VALIDATE_INT)) { // second date month
                $var5 = $query_f;
            } else {
                $var5 = 0;
            }
            if ($query_d && filter_var(ltrim($query_d, (string)0), FILTER_VALIDATE_INT)) { // second date day
                $var6 = $query_d;
            } else {
                $var6 = 0;
            }
            if (filter_var($query_i, FILTER_VALIDATE_INT)) { // building id
                $var7 = $query_i;
            } else {
                $var7 = 0;
            }
            /** @var \App\Services\Reference\Academic\ClassService $classService */
            $classService = $container->get('classService');
            //$referenceData = new ReferenceTables($conn,$conn2,$var1,$var2,$var3,$var4); // call the reference table
            if(filter_var($query_s, FILTER_VALIDATE_INT) && filter_var(ltrim($query_n, (string)0), FILTER_VALIDATE_INT) && filter_var(ltrim($query_c, (string)0), FILTER_VALIDATE_INT)) {
                $dataRet = $classService->readExpandedScheduleData((int)$var7, $var1."-".$var2."-".$var3, $var4."-".$var5."-".$var6);
            }
            $jsonResponse->send($dataRet);
        }
} else {
    // query is not valid
    $retArr = array("data"=>"No Data.");
    $json_result = json_encode($retArr);
    echo($json_result);

}

