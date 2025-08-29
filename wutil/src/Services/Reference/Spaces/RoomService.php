<?php

namespace App\Services\Reference\Spaces;

use PDO;
use PDOException;
use App\Database\DatabaseManager;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use App\Utilities\Utilities;
use App\Services\Reference\BaseReferenceService;
use App\Services\Reference\Academic\UncertaintyService;
use App\Services\Reference\Academic\SemesterService;

class RoomService extends BaseReferenceService
{
    protected ConfigService $configService;
    protected LogService $logService;

    /**
     * @var UncertaintyService
     */
    protected UncertaintyService $uncertaintyService;

    /**
     * @var SemesterService
     */
    protected SemesterService $semesterService;

    protected $zoneService;
    protected Utilities $utilities;

    /**
     * RoomService constructor.
     *
     * @param DatabaseManager $dbManager The database manager
     * @param UncertaintyService $uncertaintyService The uncertainty service
     * @param SemesterService $semesterService The semester service
     * @param Utilities $utilities
     * @param ConfigService|null $configService
     * @param LogService|null $logService
     */
    public function __construct(
        DatabaseManager $dbManager,
        UncertaintyService $uncertaintyService,
        SemesterService $semesterService,
        Utilities $utilities,
        ?ConfigService $configService = null,
        ?LogService $logService = null
    ) {
        parent::__construct($dbManager);
        $this->uncertaintyService = $uncertaintyService;
        $this->semesterService = $semesterService;
        $this->utilities = $utilities;

        // Use injected services if provided, otherwise create new instances
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $utilities, $this->configService);
    }

    // Setter method for ZoneService
    public function setZoneService(ZoneService $zoneService)
    {
        $this->zoneService = $zoneService;
    }

    /**
     * Read one room by ID
     * @throws \Exception
     */
    public function readOneRoom(int $id, bool $isRaw = false): array
    {
        $query = "SELECT * FROM rooms WHERE id = :id LIMIT 1";

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
                "building_id" => $row["building_id"],
                "facility_id" => $row["facility_id"],
                "room_name" => $row["room_name"],
                "room_area" => $row["room_area"],
                "room_population" => $row["room_population"],
                "ash61_cat_id" => $row["ash61_cat_id"],
                "floor_id" => $row["floor_id"],
                "room_num" => $row["room_num"],
                "uncert_amt" => $this->uncertaintyService->updateUncertainty($row['id'], $row['uncert_amt']),
                "rtype_code" => $row['rtype_code'],
                "space_use_name" => $row["space_use_name"],
                "active" => ($row["active"]) ? 1 : 0,
                "reservable" => ($row["reservable"]) ? 1 : 0
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read all rooms for a building or floor
     * @throws \Exception
     */
    public function readAllRooms(int $buildingId, ?int $floorId = 0, bool $isRaw = false, bool $activeOnly = false): array
    {
        if ($activeOnly) {
            if ($floorId > 0) {  // rooms for a floor in the building
                $query = "SELECT * FROM rooms WHERE building_id = :bldg AND floor_id = :flr AND active = true ORDER BY id ASC";
            } else {  // rooms for the whole building
                $query = "SELECT * FROM rooms WHERE building_id = :bldg AND active = true ORDER BY id ASC";
            }
        } else {
            if ($floorId > 0) {  // rooms for a floor in the building
                $query = "SELECT * FROM rooms WHERE building_id = :bldg AND floor_id = :flr ORDER BY id ASC";
            } else {  // rooms for the whole building
                $query = "SELECT * FROM rooms WHERE building_id = :bldg ORDER BY id ASC";
            }
        }

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($query);
        $statement->bindParam(":bldg", $buildingId, \PDO::PARAM_INT);

        if ($floorId > 0) {
            $statement->bindParam(":flr", $floorId, \PDO::PARAM_INT);
        }

        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "building_id" => $row["building_id"],
                "facility_id" => $row["facility_id"],
                "room_name" => $row["room_name"],
                "room_area" => $row["room_area"],
                "room_population" => $row["room_population"],
                "ash61_cat_id" => $row["ash61_cat_id"],
                "floor_id" => $row["floor_id"],
                "room_num" => $row["room_num"],
                "uncert_amt" => $this->uncertaintyService->updateUncertainty($row['id'], $row['uncert_amt']),
                "rtype_code" => $row['rtype_code'],
                "space_use_name" => $row["space_use_name"],
                "active" => ($row["active"]) ? 1 : 0,
                "reservable" => ($row["reservable"]) ? 1 : 0
            ];
        }
        $this->logService->dataLog("Raw room count before formatResponse: " . count($result)." while count var has ".$count, "readAllRooms");
        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Get all rooms with a VAV linked to them
     * @throws \Exception
     */
    public function getRoomsWithVAVs(int $buildingId, bool $isRaw = false): array
    {
        $sql = "SELECT DISTINCT xyz.room_id FROM (room_zone_xref xyz INNER JOIN rooms r ON xyz.room_id = r.id) WHERE r.building_id = :id";

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        $statement->bindValue(":id", $buildingId, \PDO::PARAM_INT);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $roomData = $this->readOneRoom($row['room_id'], true);
            $result[] = $roomData;
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Get all rooms in a building with classes scheduled to be in them
     * Defaults to the ones marked as 'active'
     * @throws \Exception
     */
    public function getRoomsWithClasses(int $buildingId, bool $isRaw = false, bool $activeOnly = false, int $strm = 0): array
    {
        if ($strm > 0) {
            $currentTerm = $strm;
        } else {
            $currentTerm = $this->semesterService->determineSemester();
        }

        if ($activeOnly) {
            $sql = "SELECT DISTINCT r.id, r.facility_id FROM expanded_schedule_data c INNER JOIN rooms r ON r.room_num = c.room_number AND r.building_id = :id AND c.strm = :strm AND r.active = true";
        } else {
            $sql = "SELECT DISTINCT r.id, r.facility_id FROM expanded_schedule_data c INNER JOIN rooms r ON r.room_num = c.room_number AND r.building_id = :id AND c.strm = :strm";
        }

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        $statement->bindValue(":id", $buildingId, \PDO::PARAM_INT);
        $statement->bindValue(":strm", $currentTerm);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $roomData = $this->readOneRoom($row['id'], true);
            $result[] = $roomData;
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Get all rooms in a building with exams scheduled to be in them
     * Defaults to the ones marked as 'active'
     * @throws \Exception
     */
    public function getRoomsWithExams(int $buildingId, bool $isRaw = false, bool $activeOnly = false): array
    {
        $currentTerm = $this->semesterService->determineSemester();

        if ($activeOnly) {
            $sql = "SELECT DISTINCT r.id, r.facility_id, r.room_population, r.room_num, r.building_id FROM exam_schedule_data e INNER JOIN rooms r ON r.facility_id = e.facility_id AND r.building_id = :id AND e.strm = :strm AND r.active = true";
        } else {
            $sql = "SELECT DISTINCT r.id, r.facility_id, r.room_population, r.room_num, r.building_id FROM exam_schedule_data e INNER JOIN rooms r ON r.facility_id = e.facility_id AND r.building_id = :id AND e.strm = :strm";
        }

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        $statement->bindValue(":id", $buildingId, \PDO::PARAM_INT);
        $statement->bindValue(":strm", $currentTerm);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $roomData = $this->readOneRoom($row['id'], true);
            $result[] = $roomData;
        }

        return $this->formatResponse($result, $count, $isRaw);
    }


     /**
     * Get all rooms linked to a zone
     *
     * @param bool $isRaw Whether to return raw data
     * @return array Room and zone ids
     */
    public function getAllRoomZoneXrefs($isRaw = false): array
    {
        $sql = "SELECT * FROM room_zone_xref";
        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();
        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        $statement->execute();
        $resStat = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = $statement->rowCount();
        $result = array();

        foreach ($resStat as $row) {
            $res = array();
            $res["zone_id"] = $row["zone_id"];
            $res["room_id"] = $row["room_id"];
            $res["pr_percent"] = $row["percent"];
            $result[] = $res;
        }
        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Add, update or delete a Room record
     *
     * @param array $data Sanitized data for the Room record
     * @return bool Success or failure of the operation
     */
    public function addUpdRooms(array $data): bool
    {
        global $detailedLogging;
        $connection = $this->dbManager->getDefaultConnection();
        $inUp = "update";
        $inUpID = "";
        $whatFunction = "addUpdRooms";

        try {
            $connection->beginTransaction();
           $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

            // Extract sanitized data
            $saniFacId = $data['facility_id'];
            $saniRoomName = $data['room_name'];
            $saniBldgId = $data['building_id'];
            $saniRoomAr = $data['room_area'];
            $saniAsh61Id = $data['ash61_cat_id'];
            $saniRoomPop = $data['room_population'];
            $saniRoomNum = $data['room_num'];
            $saniFloorId = $data['floor_id'];
            $saniUncertainty = $data['uncert_amt'];
            $saniTypeCode = $data['rtype_code'];
            $saniTypeName = $data['space_use_name'];
            $saniActive = $data["active"];
            $saniReservable = $data["reservable"];
            $saniId = $data['id'] ?? null;
            $saniDelete = $data['delete'];
            $deletion = false;
            $result = false;

            if ($saniId !== null && $saniId != 0) {
                if ($saniDelete == 1) {
                    $inUp = "delete";
                    if ($detailedLogging) {
                       $this->logService->dataLog($whatFunction." - Prepping Delete for id: " . $saniId, $whatFunction);
                    }

                    $query = "DELETE FROM rooms WHERE id = :id";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();
                    $count = $stmt->rowCount();

                    if ($detailedLogging) {
                       $this->logService->dataLog($whatFunction." - Prepping Xref Deletion for id: " . $saniId, $whatFunction);
                    }

                    // Also delete any xrefs
                    $query = "DELETE FROM room_zone_xref WHERE room_id = :id";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();

                    if ($count > 0) {
                       $this->logService->dataLog($whatFunction." - id " . $saniId . " deleted successfully. Total records deleted: ".$count, $whatFunction);
                        $deletion = true;
                    } else {
                       $this->logService->dataLog($whatFunction." - Error deleting id " . $saniId, $whatFunction);
                        $deletion = false;
                    }
                } else {
                    if ($detailedLogging) {
                       $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " so attempting to select a record.", $whatFunction);
                    }

                    $query = "SELECT * FROM rooms WHERE id = :id LIMIT 1";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            if ($data['delete'] == 0) {
                if ($result === false) {
                    if ($detailedLogging) {
                       $this->logService->dataLog($whatFunction." - We do not have an id, so attempting insert.", $whatFunction);
                    }

                    $inUp = "insert";
                    $query = <<<'SQL'
                    INSERT INTO rooms (
                        facility_id, room_name, building_id, room_area, ash61_cat_id, 
                        room_population, room_num, floor_id, uncert_amt, rtype_code, 
                        space_use_name, active, reservable
                    ) VALUES (
                        :facility_id, :room_name, :building_id, :room_area, :ash61_cat_id,
                        :room_population, :room_num, :floor_id, :uncert_amt, :rtype_code,
                        :space_use_name, :active, :reservable
                    );
    SQL;
                    $stmt = $connection->prepare($query);
                } else {
                    if ($detailedLogging) {
                       $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                    }

                    $inUp = "update";
                    $query = <<<'SQL'
                    UPDATE rooms SET
                        facility_id = :facility_id,
                        room_name = :room_name,
                        building_id = :building_id,
                        room_area = :room_area,
                        ash61_cat_id = :ash61_cat_id,
                        room_population = :room_population,
                        room_num = :room_num,
                        floor_id = :floor_id,
                        uncert_amt = :uncert_amt,
                        rtype_code = :rtype_code,
                        space_use_name = :space_use_name,
                        active = :active,
                        reservable = :reservable
                    WHERE id = :id;
    SQL;
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $inUpID = " for id " . $saniId;
                }

                $stmt->bindParam(':facility_id', $saniFacId, \PDO::PARAM_INT);
                $stmt->bindParam(':room_name', $saniRoomName, \PDO::PARAM_STR);
                $stmt->bindParam(':building_id', $saniBldgId, \PDO::PARAM_INT);
                $stmt->bindParam(':room_area', $saniRoomAr, \PDO::PARAM_STR);
                $stmt->bindParam(':ash61_cat_id', $saniAsh61Id, \PDO::PARAM_INT);
                $stmt->bindParam(':room_population', $saniRoomPop, \PDO::PARAM_INT);
                $stmt->bindParam(':room_num', $saniRoomNum, \PDO::PARAM_STR);
                $stmt->bindParam(':floor_id', $saniFloorId, \PDO::PARAM_INT);
                $stmt->bindParam(':uncert_amt', $saniUncertainty, \PDO::PARAM_INT);
                $stmt->bindParam(':rtype_code', $saniTypeCode, \PDO::PARAM_STR);
                $stmt->bindParam(':space_use_name', $saniTypeName, \PDO::PARAM_STR);
                $stmt->bindParam(':active', $saniActive, \PDO::PARAM_BOOL);
                $stmt->bindParam(':reservable', $saniReservable, \PDO::PARAM_BOOL);
                $stmt->execute();

                // Log changes for update operations
                if ($inUp == "update" && $result !== false) {
                    $updateArray = [
                        'updated_table_id' => $result['id'],
                        'user_id' => 0,
                        'updated_table_name' => 'rooms',
                        'common_name' => 'Rooms'
                    ];

                    $fieldsToCheck = [
                        'facility_id' => $saniFacId,
                        'room_name' => $saniRoomName,
                        'building_id' => $saniBldgId,
                        'room_area' => $saniRoomAr,
                        'ash61_cat_id' => $saniAsh61Id,
                        'room_population' => $saniRoomPop,
                        'room_num' => $saniRoomNum,
                        'floor_id' => $saniFloorId,
                        'uncert_amt' => $saniUncertainty,
                        'rtype_code' => $saniTypeCode,
                        'space_use_name' => $saniTypeName,
                        'active' => $saniActive,
                        'reservable' => $saniReservable
                    ];

                    foreach ($fieldsToCheck as $field => $newValue) {
                        if ($result[$field] != $newValue) {
                            $updateArray['old_value'] = $result[$field];
                            $updateArray['new_value'] = $newValue;
                            $updateArray['column_name'] = $field;
                            $this->logService->logUpdatedVariable($updateArray);
                        }
                    }
                }

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
                   $this->logService->dataLog($whatFunction . " - Query execution failed", $whatFunction);
                }
            }

            $connection->commit();

            if ($detailedLogging) {
               $this->logService->dataLog($whatFunction . " - Data transaction(s) successful. Committing queries now.", $whatFunction);
            }

            if ($data['delete'] == 1 && !$deletion) {
                $this->utilities->returnMsg("Data " . $inUp . " unsuccessful.", "Failure");
                //return false;
            } else {
                $this->utilities->returnMsg("Data " . $inUp . " successful.", "Success");
                //return true;
            }
        } catch (\PDOException $e) {
            $connection->rollBack();
            $this->logService->logException($e, $whatFunction . " - Data transaction(s) failed. Queries rolled back. Also the following error was generated: ", $whatFunction);
            $this->utilities->returnMsg($e->getMessage(), "Error");
            //return false;
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logService->logException($e, $whatFunction . " - Unexpected error occurred: ", $whatFunction);
            $this->utilities->returnMsg($e->getMessage(), "Error");
            //return false;
        }
    }

    /**
     * Add, update or delete a Room-Zone cross-reference record
     *
     * @param array $data Sanitized data for the Room-Zone cross-reference
     * @return bool Success or failure of the operation
     */
    public function addUpdXref(array $data): bool
    {
        global $detailedLogging;
        $connection = $this->dbManager->getDefaultConnection();
        $inUp = "update";
        $whatFunction = "addUpdXref";

        try {
            $connection->beginTransaction();
           $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

            // Extract sanitized data
            $saniArea = $data["xref_area"];
            $saniPopulation = $data["xref_population"];
            $saniPercent = $data["pr_percent"];
            $saniId = $data["id"] ?? null;

            if ($detailedLogging) {
               $this->logService->dataLog($whatFunction." - Selecting room id for: " . $data["facility_id"], $whatFunction);
            }

            $query = "SELECT id FROM rooms WHERE facility_id = :facility_id";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(':facility_id', $data["facility_id"], PDO::PARAM_STR);
            $stmt->execute();
            $saniRoomId = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($detailedLogging) {
               $this->logService->dataLog($whatFunction." - Selecting zone id for: " . $data["zone_name"], $whatFunction);
            }

            $query = "SELECT id FROM zones WHERE zone_name = :zone_name";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(':zone_name', $data["zone_name"], PDO::PARAM_STR);
            $stmt->execute();
            $saniZoneId = $stmt->fetch(PDO::FETCH_ASSOC);

            $result = false;
            $deletion = false;

            if ($saniId !== null && $saniId != 0) {
                if ($data['delete'] == 1) {
                    $inUp = "delete";
                    if ($detailedLogging) {
                       $this->logService->dataLog($whatFunction." - Prepping Delete for id: " . $saniId, $whatFunction);
                    }

                    $query = "DELETE FROM room_zone_xref WHERE id = :id";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();
                    $count = $stmt->rowCount();

                    if ($count > 0) {
                       $this->logService->dataLog($whatFunction." - id " . $saniId . " deleted successfully. Total records deleted: ".$count, $whatFunction);
                        $deletion = true;
                    } else {
                       $this->logService->dataLog($whatFunction." - Error deleting id " . $saniId, $whatFunction);
                        $deletion = false;
                    }
                } else {
                    if ($detailedLogging) {
                       $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " so attempting to select a record.", $whatFunction);
                    }

                    $query = "SELECT * FROM room_zone_xref WHERE id = :id LIMIT 1";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            if ($data['delete'] == 0) {
                if ($result === false) {
                    if ($detailedLogging) {
                       $this->logService->dataLog($whatFunction." - We do not have an id, so attempting insert.", $whatFunction);
                    }

                    $inUp = "insert";
                    $query = <<<'SQL'
                    INSERT INTO room_zone_xref (room_id, zone_id, pr_percent, xref_area, xref_population)
                    VALUES (:room_id, :zone_id, :pr_percent, :xref_area, :xref_population);
    SQL;
                    $stmt = $connection->prepare($query);
                } else {
                    if ($detailedLogging) {
                       $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                    }

                    $inUp = "update";
                    $query = <<<'SQL'
                    UPDATE room_zone_xref SET
                        room_id = :room_id,
                        zone_id = :zone_id,
                        pr_percent = :pr_percent,
                        xref_area = :xref_area,
                        xref_population = :xref_population
                    WHERE id = :id;
    SQL;
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                }

                $stmt->bindParam(':zone_id', $saniZoneId[0], PDO::PARAM_INT);
                $stmt->bindParam(':room_id', $saniRoomId[0], PDO::PARAM_INT);
                $stmt->bindParam(':pr_percent', $saniPercent, PDO::PARAM_INT);
                $stmt->bindParam(':xref_area', $saniArea, PDO::PARAM_STR);
                $stmt->bindParam(':xref_population', $saniPopulation, PDO::PARAM_INT);
                $stmt->execute();

                // Log changes for update operations
                if ($inUp == "update" && $result !== false) {
                    $updateArray = [
                        'updated_table_id' => $saniId,
                        'user_id' => 0,
                        'updated_table_name' => 'room_zone_xref',
                        'common_name' => 'Space/Zone'
                    ];

                    $fieldsToCheck = [
                        'zone_id' => $saniZoneId[0],
                        'room_id' => $saniRoomId[0],
                        'pr_percent' => $saniPercent,
                        'xref_area' => $saniArea,
                        'xref_population' => $saniPopulation
                    ];

                    foreach ($fieldsToCheck as $field => $newValue) {
                        if ($result[$field] != $newValue) {
                            $updateArray['old_value'] = $result[$field];
                            $updateArray['new_value'] = $newValue;
                            $updateArray['column_name'] = $field;
                            $this->logService->logUpdatedVariable($updateArray);
                        }
                    }
                }

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
                   $this->logService->dataLog($whatFunction . " - Query execution failed", $whatFunction);
                }
            }

            $connection->commit();

            if ($detailedLogging) {
               $this->logService->dataLog($whatFunction . " - Data transaction(s) successful. Committing queries now.", $whatFunction);
            }

            if ($data['delete'] == 1 && !$deletion) {
                $this->utilities->returnMsg("Data " . $inUp . " unsuccessful.", "Failure");
                //return false;
            } else {
                $this->utilities->returnMsg("Data " . $inUp . " successful.", "Success");
                //return true;
            }
        } catch (PDOException $e) {
            $connection->rollBack();
            $this->logService->logException($e, $whatFunction . " - Data transaction(s) failed. Queries rolled back. Also the following error was generated: ", $whatFunction);
            $this->utilities->returnMsg($e->getMessage(), "Error");
            //return false;
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logService->logException($e, $whatFunction . " - Unexpected error occurred: ", $whatFunction);
            $this->utilities->returnMsg($e->getMessage(), "Error");
            //return false;
        }
    }


}
