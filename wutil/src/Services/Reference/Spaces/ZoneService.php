<?php

namespace App\Services\Reference\Spaces;

use App\Services\Reference\BaseReferenceService;
use App\Database\DatabaseManager;
use App\Services\Reference\Academic\UncertaintyService;
use App\Services\Reference\Academic\SemesterService;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use App\Utilities\Utilities;
use PDO;
use PDOException;

class ZoneService extends BaseReferenceService
{
    protected UncertaintyService $uncertaintyService;
    protected SemesterService $semesterService;
    protected Utilities $utilities;
    protected ConfigService $configService;
    protected LogService $logService;
    protected RoomService $roomService;

    /**
     * ZoneService constructor.
     *
     * @param DatabaseManager $dbManager The database manager
     * @param UncertaintyService $uncertaintyService The uncertainty service
     * @param Utilities $utilities
     * @param ConfigService|null $configService
     * @param LogService|null $logService
     * @param SemesterService|null $semesterService
     */
    public function __construct(
        DatabaseManager $dbManager,
        UncertaintyService $uncertaintyService,
        Utilities $utilities,
        ?ConfigService $configService = null,
        ?LogService $logService = null,
        ?SemesterService $semesterService = null
    ) {
        parent::__construct($dbManager);
        $this->uncertaintyService = $uncertaintyService;
        $this->utilities = $utilities;

        // Use injected services if provided, otherwise create new instances
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $utilities, $this->configService);
        $this->semesterService = $semesterService;
    }

    // Setter method for RoomService
    public function setRoomService(RoomService $roomService): void
    {
        $this->roomService = $roomService;
    }

    /**
     * Get all zones
     */
    public function readAllZones(bool $isRaw = false): array
    {
        $sql = "SELECT * FROM zones";

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "zone_name" => $row["zone_name"],
                "zone_code" => $row["zone_code"],
                "building_id" => $row["building_id"],
                "ahu_name" => $row["ahu_name"],
                "occ_sensor" => ($row["occ_sensor"]) ? 1 : 0
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Get zones by building ID
     */
    public function readZonesByBuilding(int $id, bool $isRaw = false): array
    {
        $sql = "SELECT * FROM zones WHERE building_id = :id";

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        $statement->bindValue(":id", $id, \PDO::PARAM_INT);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "zone_name" => $row["zone_name"],
                "zone_code" => $row["zone_code"],
                "building_id" => $row["building_id"],
                "ahu_name" => $row["ahu_name"],
                "occ_sensor" => ($row["occ_sensor"]) ? 1 : 0,
                "auto_mode" => ($row["auto_mode"]) ? 1 : 0,
                "active" => ($row["active"]) ? 1 : 0,
                "xrefs" => $this->getRoomsByZone($row["id"], true)
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Get one zone by ID
     */
    public function readOneZone(int $id, bool $isRaw = false): array
    {
        $sql = "SELECT * FROM zones WHERE id = :id";
        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        $statement->bindValue(":id", $id, \PDO::PARAM_INT);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "zone_name" => $row["zone_name"],
                "zone_code" => $row["zone_code"],
                "building_id" => $row["building_id"],
                "ahu_name" => $row["ahu_name"],
                "occ_sensor" => ($row["occ_sensor"]) ? 1 : 0,
                "auto_mode" => ($row["auto_mode"]) ? 1 : 0,
                "active" => ($row["active"]) ? 1 : 0,
                "xrefs" => $this->getRoomsByZone($row["id"], true)
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Get rooms linked to a zone
     *
     * @param int $zoneId Zone ID
     * @param bool $isRaw Whether to return raw data
     * @return array Room facility IDs
     */
    public function getRoomsByZone(int $zoneId, bool $isRaw = false): array
    {
        $sql = "SELECT r.facility_id 
                FROM room_zone_xref xyz 
                INNER JOIN rooms r ON r.id = xyz.room_id 
                WHERE xyz.zone_id = :id";

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();
        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        $statement->bindValue(":id", $zoneId, \PDO::PARAM_INT);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $row["facility_id"];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }


    /**
     * Get one zone by code
     */
    public function readOneZoneByCode(string $code, bool $isRaw = false): array
    {
        $sql = "SELECT * FROM zones WHERE zone_code = :code";

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        $statement->bindValue(":code", $code, \PDO::PARAM_STR);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "zone_name" => $row["zone_name"],
                "zone_code" => $row["zone_code"],
                "building_id" => $row["building_id"],
                "ahu_name" => $row["ahu_name"],
                "occ_sensor" => ($row["occ_sensor"]) ? 1 : 0,
                "auto_mode" => ($row["auto_mode"]) ? 1 : 0,
                "active" => ($row["active"]) ? 1 : 0,
                "xrefs" => $this->getRoomsByZone($row["id"], true)
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Get zone-room cross-references by building
     *
     * @param int $id Building ID
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array Cross-reference data
     * @throws \Exception
     */
    public function getXrefsByBuildingNew(int $id, bool $isRaw = false): array
    {
        $sql = "SELECT r.room_num, r.facility_id, r.uncert_amt, r.room_population, r.room_area, 
                       z.zone_code, z.zone_name, xyz.room_id, xyz.zone_id, xyz.pr_percent, 
                       xyz.xref_area, xyz.xref_population, xyz.id
                FROM ((room_zone_xref xyz
                INNER JOIN zones z ON z.id = xyz.zone_id)
                INNER JOIN rooms r ON r.id = xyz.room_id) 
                WHERE r.building_id = :id";

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        $statement->bindValue(":id", $id, \PDO::PARAM_INT);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "room_num" => $row["room_num"],
                "facility_id" => $row["facility_id"],
                "room_id" => $row["room_id"],
                "room_population" => $row["room_population"],
                "uncert_amt" => $this->uncertaintyService->updateUncertainty($row["room_id"], $row["uncert_amt"]),
                "room_area" => $row["room_area"],
                "zone_id" => $row["zone_id"],
                "zone_code" => $row["zone_code"],
                "zone_name" => $row["zone_name"],
                "pr_percent" => $row["pr_percent"],
                "xref_area" => $row["xref_area"],
                "xref_population" => $row["xref_population"]
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Get zone-room cross-references by zone ID or code
     *
     * @param int|null $id Zone ID (optional)
     * @param string|null $code Zone code (optional)
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array Cross-reference data
     * @throws \Exception
     */
    public function getXrefsByZone(?int $id = null, ?string $code = null, bool $isRaw = false): array
    {
        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Base SQL query
        $baseSql = "SELECT r.room_num, r.facility_id, r.uncert_amt, r.room_population, r.room_area, 
                           z.zone_code, z.zone_name, rzx.room_id, rzx.zone_id, rzx.pr_percent, 
                           rzx.id, rzx.xref_population, a.ppl_oa_rate
                    FROM ((room_zone_xref rzx
                    INNER JOIN zones z ON z.id = rzx.zone_id)
                    INNER JOIN rooms r ON r.id = rzx.room_id
                    INNER JOIN ashrae_6_1 a ON r.ash61_cat_id = a.id)";

        // Prepare statement based on which parameter is provided
        if ($id !== null) {
            $sql = $baseSql . " WHERE z.id = :id";
            $statement = $connection->prepare($sql);
            $statement->bindValue(":id", $id, \PDO::PARAM_INT);
        } elseif ($code !== null) {
            $sql = $baseSql . " WHERE z.zone_code = :code";
            $statement = $connection->prepare($sql);
            $statement->bindValue(":code", $code, \PDO::PARAM_STR);
        } else {
            // If neither parameter is provided, return empty result
            return $this->formatResponse([], 0, $isRaw);
        }

        // Execute the query
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "room_num" => $row["room_num"],
                "facility_id" => $row["facility_id"],
                "room_id" => $row["room_id"],
                "room_population" => $row["room_population"],
                "uncert_amt" => $this->uncertaintyService->updateUncertainty($row["room_id"], $row["uncert_amt"]),
                "room_area" => $row["room_area"],
                "zone_id" => $row["zone_id"],
                "zone_code" => $row["zone_code"],
                "zone_name" => $row["zone_name"],
                "pr_percent" => $row["pr_percent"],
                "xref_population" => $row["xref_population"],
                "ppl_oa_rate" => $row["ppl_oa_rate"]
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }
    // zones by room
    public function getZonesByRoom($id, $isRaw = false): array
    {
        $sql = "SELECT z.zone_code, rzx.zone_id, rzx.xref_population, rzx.pr_percent, z.zone_name, z.active FROM (room_zone_xref rzx INNER JOIN zones z ON z.id = rzx.zone_id) WHERE rzx.room_id = :id";
        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();
        $statement = $connection->prepare($sql);
        $statement->bindValue(":id", $id, \PDO::PARAM_INT);
        $statement->execute();
        $resStat = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = $statement->rowCount();
        $result = array();

        foreach ($resStat as $row) {
            $result[] = array(
                'zone_id' => $row['zone_id'],
                'zone_code' => $row["zone_code"],
                'zone_name' => $row['zone_name'],
                'xref_population' => $row["xref_population"],
                'pr_percent' => $row["pr_percent"],
                'active' => $row['active']
            );
        }
        return $this->formatResponse($result, $count, $isRaw);
    }

    /// get all zones with events
    public function getZonesWithEvents($id, $isRaw = false, $activeOnly = false, $strm = 0): array
    {
        if($strm > 0){
            $currTerm = $strm;
        } else {
            $currTerm = $this->semesterService->determineSemester();
        }
        if($activeOnly){
            $sql = "SELECT DISTINCT z.id FROM (((expanded_schedule_data esd
            INNER JOIN rooms r ON r.facility_id = esd.facility_id)
            INNER JOIN room_zone_xref xyz ON xyz.room_id = r.id)
            INNER JOIN zones z ON z.id = xyz.zone_id) WHERE r.building_id = :id AND r.active = true AND z.active = true AND esd.strm = :strm";
        }
        else{
            $sql = "SELECT DISTINCT z.id FROM (((expanded_schedule_data esd
            INNER JOIN rooms r ON r.facility_id = esd.facility_id)
            INNER JOIN room_zone_xref xyz ON xyz.room_id = r.id)
            INNER JOIN zones z ON z.id = xyz.zone_id) WHERE r.building_id = :id AND esd.strm = :strm";
        }
        $connection = $this->dbManager->getDefaultConnection();
        $statement = $connection->prepare($sql);
        $statement->bindValue(":id", $id, \PDO::PARAM_INT);
        $statement->execute();
        $resStat = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = $statement->rowCount();
        $result = array();

        foreach ($resStat as $row) {
            $res = $this->readOneZone($row['id'], true);
            $result[] = $res;
        }
        return $this->formatResponse($result, $count, $isRaw);

    }

    /**
 * Add, update or delete a Zone record
 *
 * @param array $data Sanitized data for the Zone record
 * @return bool Success or failure of the operation
 */
public function addUpdZones(array $data): bool
{
    global $detailedLogging;
    $connection = $this->dbManager->getDefaultConnection();
    $inUp = "update";
    $whatFunction = "addUpdZones";

    try {
        $connection->beginTransaction();
        $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

        // Extract sanitized data
        $saniZoneName = $data["zone_name"];
        $saniZoneCode = $data["zone_code"];
        $saniBuildingId = $data['building_id'];
        $saniAHU = $data['ahu_name'];
        $saniOcc = $data['occ_sensor'];
        $saniXrefs = $data['xrefs'];
        $saniActive = $data['active'];
        $saniId = $data["id"] ?? null;
        $pr_default = 0;
        $result = false;
        $deletion = false;

        if ($saniId !== null && $saniId != 0) {
            if ($data['delete'] == 1) {
                $inUp = "delete";
                if ($detailedLogging) {
                    $this->logService->dataLog($whatFunction." - Prepping Delete for id: " . $saniId, $whatFunction);
                }

                $query = "DELETE FROM zones WHERE id = :id";
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

                $query = "SELECT * FROM zones WHERE id = :id LIMIT 1";
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
                INSERT INTO zones (zone_name, zone_code, building_id, ahu_name, occ_sensor, auto_mode, active)
                VALUES (:zone_name, :zone_code, :building_id, :ahu_name, :occ_sensor, :auto_mode, :active);
SQL;
                $stmt = $connection->prepare($query);
            } else {
                if ($detailedLogging) {
                    $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                }

                $inUp = "update";
                $query = <<<'SQL'
                UPDATE zones SET
                    zone_name = :zone_name,
                    zone_code = :zone_code,
                    building_id = :building_id,
                    ahu_name = :ahu_name,
                    occ_sensor = :occ_sensor,
                    auto_mode = :auto_mode,
                    active = :active
                WHERE id = :id;
SQL;
                $stmt = $connection->prepare($query);
                $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
            }

            $stmt->bindParam(':zone_name', $saniZoneName, PDO::PARAM_STR);
            $stmt->bindParam(':zone_code', $saniZoneCode, PDO::PARAM_STR);
            $stmt->bindParam(':building_id', $saniBuildingId, PDO::PARAM_INT);
            $stmt->bindParam(':ahu_name', $saniAHU, PDO::PARAM_STR);
            $stmt->bindParam(':occ_sensor', $saniOcc, PDO::PARAM_INT);
            $stmt->bindParam(':active', $saniActive, PDO::PARAM_INT);

            if (isset($data["auto_mode"])) {
                $stmt->bindParam(':auto_mode', $data["auto_mode"], PDO::PARAM_INT);
            } else {
                $autoMode = $result["auto_mode"] ?? 0;
                $stmt->bindParam(':auto_mode', $autoMode, PDO::PARAM_INT);
            }

            $stmt->execute();

            // Log changes for update operations
            if ($inUp == "update" && $result !== false) {
                $updateArray = [
                    'updated_table_id' => $saniId,
                    'user_id' => 0,
                    'updated_table_name' => 'zones',
                    'common_name' => 'Zones'
                ];

                $fieldsToCheck = [
                    'zone_name' => $saniZoneName,
                    'zone_code' => $saniZoneCode,
                    'building_id' => $saniBuildingId,
                    'ahu_name' => $saniAHU,
                    'occ_sensor' => $saniOcc,
                    'active' => $saniActive
                ];

                foreach ($fieldsToCheck as $field => $newValue) {
                    if ($result[$field] != $newValue) {
                        $updateArray['old_value'] = $result[$field];
                        $updateArray['new_value'] = $newValue;
                        $updateArray['column_name'] = $field;
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                }

                // Handle auto_mode separately since it might not be in the data
                if (isset($data["auto_mode"]) && $result['auto_mode'] != $data["auto_mode"]) {
                    $updateArray['old_value'] = $result['auto_mode'];
                    $updateArray['new_value'] = $data["auto_mode"];
                    $updateArray['column_name'] = 'auto_mode';
                    $this->logService->logUpdatedVariable($updateArray);
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

        if ($detailedLogging) {
            $this->logService->dataLog($whatFunction . " - Processing Xrefs", $whatFunction);
        }

        // Xref Logic
        if ($inUp == "insert") {
            // Get the latest ID from zones
            $query = "SELECT MAX(id) FROM zones";
            $stmt = $connection->prepare($query);
            $stmt->execute();
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            $saniId = $res[0];

            // Loop through xrefs and add them to the xref table
            foreach ($saniXrefs as $xref) {
                $query = "SELECT id FROM rooms WHERE facility_id = :facility_id";
                $stmt = $connection->prepare($query);
                $stmt->bindParam(':facility_id', $xref, PDO::PARAM_STR);
                $stmt->execute();
                $room_id = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($room_id) {
                    $query = <<<'SQL'
                    INSERT INTO room_zone_xref (room_id, zone_id, pr_percent)
                    VALUES (:room_id, :zone_id, :pr_percent);
SQL;
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':zone_id', $saniId, PDO::PARAM_INT);
                    $stmt->bindParam(':room_id', $room_id[0], PDO::PARAM_INT);
                    $stmt->bindParam(':pr_percent', $pr_default, PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
        }

        if ($inUp == 'update') {
            // Check incoming values
            foreach ($saniXrefs as $xref) {
                // Get the linked room's id
                $query = "SELECT id FROM rooms WHERE facility_id = :facility_id";
                $stmt = $connection->prepare($query);
                $stmt->bindParam(':facility_id', $xref, PDO::PARAM_STR);
                $stmt->execute();
                $room_id = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($room_id) {
                    // Check to see if the connection exists in the database
                    $query = "SELECT * FROM room_zone_xref WHERE zone_id = :zone_id AND room_id = :room_id";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':zone_id', $saniId, PDO::PARAM_INT);
                    $stmt->bindParam(':room_id', $room_id[0], PDO::PARAM_INT);
                    $stmt->execute();
                    $xref_check = $stmt->fetch(PDO::FETCH_ASSOC);

                    // If the connection does not exist, create one
                    if (!$xref_check) {
                        $query = <<<'SQL'
                        INSERT INTO room_zone_xref (room_id, zone_id, pr_percent)
                        VALUES (:room_id, :zone_id, :pr_percent);
SQL;
                        $stmt = $connection->prepare($query);
                        $stmt->bindParam(':zone_id', $saniId, PDO::PARAM_INT);
                        $stmt->bindParam(':room_id', $room_id[0], PDO::PARAM_INT);
                        $stmt->bindParam(':pr_percent', $pr_default, PDO::PARAM_INT);
                        $stmt->execute();
                    }
                }
            }

            // Get all currently linked rooms
            $query = "SELECT r.facility_id, r.id FROM (room_zone_xref xyz INNER JOIN rooms r ON r.id = xyz.room_id) WHERE xyz.zone_id = :id";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Check all incoming linked rooms against the current ones
            foreach ($result as $resRef) {
                $wasDeleted = true;
                foreach ($saniXrefs as $xref) {
                    if ($resRef["facility_id"] == $xref) {
                        $wasDeleted = false;
                    }
                }
                if ($wasDeleted) {
                    $query = "DELETE FROM room_zone_xref WHERE zone_id = :zone_id AND room_id = :room_id";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':zone_id', $saniId, PDO::PARAM_INT);
                    $stmt->bindParam(':room_id', $resRef["id"], PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
        }

        if ($inUp == 'delete') {
            $query = "DELETE FROM room_zone_xref WHERE zone_id = :id";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
            $stmt->execute();
        }

        if ($detailedLogging) {
            $this->logService->dataLog($whatFunction . " - Xrefs Processed", $whatFunction);
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
           // return true;
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
