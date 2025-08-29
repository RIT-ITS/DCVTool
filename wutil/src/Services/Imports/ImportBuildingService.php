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

class ImportBuildingService {

    private DatabaseManager $dbManager;
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
    public function importBuildingRoomData($data): array {
        $pdo = $this->dbManager->getDefaultConnection();

        $ret = [];
        $campusId = $data['campus_id'];
        $buildingNumberArray = [];    //tracks buildings that have been checked/added
        $errorArray = [];              //array of error rows
        $floorArray = [];              //tracks floors that have been checked/added
        $newFloors = [];
        $newBuildings = [];
        $newRooms = [];
        $rowsProcessed = 0;
        $newBuildingCount = 0;        //tracks number of buildings inserted
        $newFloorCount = 0;           //tracks number of floors inserted
        $newRoomCount = 0;
        $rowsWithNoImport = 0;

        foreach($data['data'] as $d){
            try{
                $importData = 0;
                $d['building_step_complete'] = false;
                $d['floor_step_complete'] = false;
                $d['room_step_complete'] = false;
                /*
                    BUILDING INSERT
                    track the buildings checked in $buildingNumberArray based on the key being the table id of the building
                    To prevent too many DB queries.
                */
                $bldgNum = $d['bldg_num'];
                $buildingId = 0;
                if(!in_array($bldgNum, $buildingNumberArray)){
                    $query = "SELECT * FROM buildings WHERE bldg_num = :bldg_num AND campus_id = :campus_id LIMIT 1";
                    $stmt = $pdo->prepare($query);
                    $stmt->bindValue(':bldg_num', $bldgNum, PDO::PARAM_STR);
                    $stmt->bindValue(':campus_id', $campusId, PDO::PARAM_INT);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if($result === false) {
                        $r = [];
                        $query = "INSERT INTO buildings (bldg_num, bldg_name, campus_id, facility_code, short_desc)
                        VALUES (:bldg_num, :bldg_name, :campus_id, :facility_code, :short_desc)";
                        $stmt = $pdo->prepare($query);
                        $stmt->bindValue(':bldg_num', $bldgNum, PDO::PARAM_STR);
                        $stmt->bindValue(':bldg_name', $d['bldg_name'], PDO::PARAM_STR);
                        $stmt->bindValue(':campus_id', $campusId, PDO::PARAM_INT);
                        $stmt->bindValue(':short_desc', $d['short_desc'], PDO::PARAM_STR);
                        $stmt->bindValue(':facility_code', $d['facility_code'], PDO::PARAM_STR);
                        $stmt->execute();

                        $buildingId = $pdo->lastInsertId();
                        $newBuildingCount++;
                        $importData = 1;
                        $r['bldg_num'] = $bldgNum;
                        $r['id'] = $buildingId;
                        $r['campus_id'] = $campusId;
                        $r['bldg_name'] = $d['bldg_name'];
                        $r['facility_code'] = $d['facility_code'];
                        $newBuildings[] = $r;
                    }else{
                        $buildingId = $result['id'];
                    }
                    $buildingNumberArray[$buildingId] = $bldgNum;
                }else{
                    $buildingId = array_search($bldgNum, $buildingNumberArray);
                }
                $d['building_step_complete'] = true;

                /*
                   FLOORS INSERT
                   track the floors checked in $floorArray based on the key being the table id of the floor
                   To prevent too many DB queries.
                   floorArray[building_id][floor_id] = floor_designation
                */
                $floorDesignation = $d['floor_designation'];
                $floorId = $d['floor_id'];
                if (!isset($floorArray[$buildingId]) || !is_array($floorArray[$buildingId])) {
                    $floorArray[$buildingId] = [];
                }
                if(!in_array($floorDesignation, $floorArray[$buildingId])){
                    $query = "SELECT * FROM floors WHERE floor_designation = :floor_designation AND buildings_id = :buildings_id LIMIT 1";
                    $stmt = $pdo->prepare($query);
                    $stmt->bindValue(':floor_designation', $floorDesignation, PDO::PARAM_STR);
                    $stmt->bindValue(':buildings_id', $buildingId, PDO::PARAM_INT);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if($result === false) {
                        $r = [];
                        $query = "INSERT INTO floors (floor_designation, buildings_id) 
                        VALUES (:floor_designation, :buildings_id)";
                        $stmt = $pdo->prepare($query);
                        $stmt->bindValue(':floor_designation', $floorDesignation, PDO::PARAM_STR);
                        $stmt->bindValue(':buildings_id', $buildingId, PDO::PARAM_INT);
                        $stmt->execute();
                        $floorId = $pdo->lastInsertId();
                        $newFloorCount++;
                        $importData = 1;
                        $r['floor_designation'] = $floorDesignation;
                        $r['id'] = $buildingId;
                        $r['campus_id'] = $campusId;
                        $r['buildings_id'] = $buildingId;
                        $newFloors[] = $r;
                    }else{
                        $floorId = $result['id'];
                    }
                    $floorArray[$buildingId][$floorId] = $floorDesignation;
                }else{
                    $floorId = array_search($floorDesignation, $floorArray[$buildingId]);
                }
                $d['floor_step_complete'] = true;

                /*
                   ROOMS INSERT
                */
                $roomNumb = $d['room_num'];

                $query = "SELECT * FROM rooms WHERE room_num = :room_num AND building_id = :building_id LIMIT 1";
                $stmt = $pdo->prepare($query);
                $stmt->bindValue(':room_num', $roomNumb, PDO::PARAM_STR);
                $stmt->bindValue(':building_id', $buildingId, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if($result === false) {
                    $query = "SELECT ashrae_id FROM nces_ashrae_xref WHERE nces_id = (SELECT MIN(id) FROM nces_4_2 WHERE code = :rtype_code LIMIT 1) LIMIT 1";
                    $stmt = $pdo->prepare($query);
                    $stmt->bindValue(':rtype_code', $d['rtype_code'], PDO::PARAM_STR);
                    $stmt->execute();
                    $fetchResult = $stmt->fetch(PDO::FETCH_NUM);
                    $rm_ash_cat = $fetchResult[0] ?? null;

                    if($d['space_use_name'] == null){
                        $query = "SELECT space_use_name FROM nces_4_2 WHERE code = :rtype_code";
                        $stmt = $pdo->prepare($query);
                        $stmt->bindValue(':rtype_code', $d['rtype_code'], PDO::PARAM_STR);
                        $stmt->execute();
                        $fetchResult = $stmt->fetch(PDO::FETCH_NUM);
                        $rm_space_type = $fetchResult[0] ?? null;
                    }
                    else{
                        $rm_space_type = $d['space_use_name'];
                    }

                    $r = [];
                    $query = "INSERT INTO rooms (facility_id, room_name, building_id, room_area, ash61_cat_id, room_population, uncert_amt, room_num, floor_id, rtype_code, space_use_name, active, reservable) 
                    VALUES (:facility_id, :room_name, :building_id, :room_area, :ash61_cat_id, :room_population, :uncert_amt, :room_num, :floor_id, :rtype_code, :space_use_name, :active, :reservable)";
                    $stmt = $pdo->prepare($query);
                    $stmt->bindValue(':facility_id', $d['facility_id'], PDO::PARAM_STR);
                    $stmt->bindValue(':room_name', $d['room_name'], PDO::PARAM_STR);
                    $stmt->bindValue(':building_id', $buildingId, PDO::PARAM_INT);
                    $stmt->bindValue(':room_area', $d['room_area'], PDO::PARAM_STR);
                    $stmt->bindValue(':ash61_cat_id', $rm_ash_cat, PDO::PARAM_INT);
                    $stmt->bindValue(':room_population', $d['room_population'], PDO::PARAM_INT);
                    $stmt->bindValue(':room_num', $roomNumb, PDO::PARAM_STR);
                    $stmt->bindValue(':floor_id', $floorId, PDO::PARAM_INT);
                    $stmt->bindValue(':rtype_code', $d['rtype_code'], PDO::PARAM_STR);
                    $stmt->bindValue(':space_use_name', $rm_space_type, PDO::PARAM_STR);
                    $stmt->bindValue(':active', $d['active'], PDO::PARAM_INT);
                    $stmt->bindValue(':reservable', $d['reservable'], PDO::PARAM_INT);
                    $stmt->bindValue(':uncert_amt', $d['uncert_amt'], PDO::PARAM_STR);
                    $stmt->execute();
                    $newRoomCount++;
                    $importData = 1;
                    $r['facility_id'] = $d['facility_id'];
                    $r['room_name'] = $d['room_name'];
                    $r['id'] = $pdo->lastInsertId();
                    $r['campus_id'] = $campusId;
                    $r['building_id'] = $buildingId;
                    $r['room_area'] = $d['room_area'];
                    $r['ash61_cat_id'] = $rm_ash_cat;
                    $r['room_population'] = $d['room_population'];
                    $r['room_num'] = $roomNumb;
                    $r['floor_id'] = $floorId;
                    $r['rtype_code'] = $d['rtype_code'];
                    $r['space_use_name'] = $rm_space_type;
                    $r['active'] = $d['active'];
                    $r['reservable'] = $d['reservable'];
                    $r['uncert_amt'] = $d['uncert_amt'];
                    $newRooms[] = $r;
                }

                $d['room_step_complete'] = true;

                if($importData == 0){
                    $rowsWithNoImport++;
                }
                $rowsProcessed++;
            }catch(PDOException $e) {
                $d['error_message'] = $e->getMessage();
                $errorArray[] = $d;
            }
        }

        if(!empty($newRooms)){
            $this->logService->logImports($newRooms, 'rooms');
        }
        if(!empty($newFloors)){
            $this->logService->logImports($newFloors, 'floors');
        }
        if(!empty($newBuildings)){
            $this->logService->logImports($newBuildings, 'buildings');
        }

        $ret["totalRowsProcessed"] = $rowsProcessed;
        $ret["totalRowsNoImport"] = $rowsWithNoImport;
        $ret["totalRowsWithErrors"] = count($errorArray);
        $ret["errorArray"] = $errorArray;
        $ret["totalBuildingsAdded"] = $newBuildingCount;
        $ret["totalFloorsAdded"] = $newFloorCount;
        $ret["totalRoomsAdded"] = $newRoomCount;

        return $ret;
    }
}

