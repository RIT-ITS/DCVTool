<?php

declare(strict_types=1);

namespace App\Services\Reference\Spaces;

use App\Database\DatabaseManager;
use App\Services\Logging\LogService;
use App\Services\Reference\BaseReferenceService;
use App\Utilities\Utilities;
use App\Services\Configuration\ConfigService;
use PDO;
use PDOException;

class BuildingService extends BaseReferenceService
{
    protected Utilities $utilities;
    protected ConfigService $configService;
    protected LogService $logService;

    /**
     * @param DatabaseManager $dbManager
     * @param Utilities $utilities
     * @param ConfigService|null $configService
     * @param LogService|null $logService
     */
    public function __construct(
        DatabaseManager $dbManager,
        Utilities $utilities,
        ?ConfigService $configService = null,
        ?LogService $logService = null
    ) {
        parent::__construct($dbManager);
        $this->utilities = $utilities;

        // Use injected services if provided, otherwise create new instances
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $utilities, $this->configService);
    }
    /**
     * Read one building by ID
     */
    public function readOneBuilding(int $id, bool $isRaw = false, bool $activeOnly = false): array
    {
        if ($activeOnly) {
            $query = "SELECT * FROM buildings WHERE id = :id AND active = true LIMIT 1";
        } else {
            $query = "SELECT * FROM buildings WHERE id = :id LIMIT 1";
        }

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
                "bldg_num" => $row["bldg_num"],
                "bldg_name" => $row["bldg_name"],
                "campus_id" => $row["campus_id"],
                "short_desc" => $row["short_desc"],
                "facility_code" => $row["facility_code"],
                "active" => ($row["active"]) ? 1 : 0
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read one building by facility code
     */
    public function readOneBuildbyFacCode(string $faccode, bool $isRaw = false, bool $activeOnly = false): array
    {
        if ($activeOnly) {
            $query = "SELECT * FROM buildings WHERE facility_code = :faccode AND active = true LIMIT 1";
        } else {
            $query = "SELECT * FROM buildings WHERE facility_code = :faccode LIMIT 1";
        }

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($query);
        $statement->bindParam(":faccode", $faccode, \PDO::PARAM_STR);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "bldg_num" => $row["bldg_num"],
                "bldg_name" => $row["bldg_name"],
                "campus_id" => $row["campus_id"],
                "short_desc" => $row["short_desc"],
                "facility_code" => $row["facility_code"],
                "active" => ($row["active"]) ? 1 : 0
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read all buildings by campus ID
     */
    public function readAllBuildings(int $campusId, bool $isRaw = false, bool $activeOnly = false): array
    {
        if ($activeOnly) {
            $query = "SELECT * FROM buildings WHERE campus_id = :campus AND active = true";
        } else {
            $query = "SELECT * FROM buildings WHERE campus_id = :campus";
        }

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($query);
        $statement->bindParam(":campus", $campusId, \PDO::PARAM_INT);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "bldg_num" => $row["bldg_num"],
                "bldg_name" => $row["bldg_name"],
                "campus_id" => $row["campus_id"],
                "short_desc" => $row["short_desc"],
                "facility_code" => $row["facility_code"],
                "active" => ($row["active"]) ? 1 : 0
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }
    /**
    * Read all floors in a building (old method)
    */
    public function readAllBlgFloors(int $buildingId, bool $isRaw = false): array
    {
        $query = "SELECT DISTINCT r.floor_id, f.floor_designation FROM rooms AS r INNER JOIN floors AS f ON r.floor_id = f.id WHERE r.building_id = :building_id";

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($query);
        $statement->bindParam(":building_id", $buildingId, \PDO::PARAM_INT);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                "floor_id" => $row["floor_id"],
                "floor_designation" => $row["floor_designation"]
                // Note: "active" is removed as it's not in the query result
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read all floors in a building (new method)
     */
    public function readAllBlgFloorsNew(int $buildingId, bool $isRaw = false, bool $activeOnly = false): array
    {
        if ($activeOnly) {
            $query = "SELECT * FROM floors AS f WHERE f.buildings_id = :building_id AND active = true";
        } else {
            $query = "SELECT * FROM floors AS f WHERE f.buildings_id = :building_id";
        }

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($query);
        $statement->bindParam(":building_id", $buildingId, \PDO::PARAM_INT);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "floor_designation" => $row["floor_designation"],
                "buildings_id" => $row["buildings_id"],
                "active" => ($row["active"]) ? 1 : 0
            ];
        }
        return $this->formatResponse($result, $count, $isRaw);
    }


    /**
     * Add, update or delete a Floor record
     *
     * @param array $data Sanitized data for the Floor record
     * @return bool Success or failure of the operation
     */
    public function addUpdFloors(array $data): bool
    {
        global $detailedLogging;
        $connection = $this->dbManager->getDefaultConnection();
        $inUp = "update";
        $inUpID = "";
        $whatFunction = "addUpdFloors";

        try {
            $connection->beginTransaction();
            $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

            // Extract sanitized data
            $saniId = $data['id'] ?? null;
            $saniFloorDesignation = $data['floor_designation'];
            $saniBuildingsId = $data['buildings_id'];
            $saniActive = $data['active'];
            $saniDelete = $data['delete'];
            $deletion = false;
            $result = false;

            if ($saniId !== null && $saniId != 0) {
                if ($saniDelete == 1) {
                    $inUp = "delete";
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - Prepping Delete for id: " . $saniId, $whatFunction);
                    }

                    $query = "DELETE FROM floors WHERE id = :id";
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

                    $query = "SELECT * FROM floors WHERE id = :id LIMIT 1";
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
                    INSERT INTO floors (floor_designation, buildings_id, active)
                    VALUES (:floor_designation, :buildings_id, :active);
SQL;
                    $stmt = $connection->prepare($query);
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                    }

                    $inUp = "update";
                    $query = <<<'SQL'
                    UPDATE floors SET
                        floor_designation = :floor_designation,
                        buildings_id = :buildings_id,
                        active = :active
                    WHERE id = :id;
SQL;
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $inUpID = " for id " . $saniId;
                }

                $stmt->bindParam(':floor_designation', $saniFloorDesignation, PDO::PARAM_STR);
                $stmt->bindParam(':buildings_id', $saniBuildingsId, PDO::PARAM_INT);
                $stmt->bindParam(':active', $saniActive, PDO::PARAM_BOOL);
                $stmt->execute();

                // Get building name for logging
                if ($detailedLogging) {
                    $this->logService->dataLog($whatFunction." - We have a buildings_id: " . $saniBuildingsId . " so attempting to select a building name.", $whatFunction);
                }

                $query2 = "SELECT * FROM buildings WHERE id = :id LIMIT 1";
                $stmt2 = $connection->prepare($query2);
                $stmt2->bindParam(':id', $saniBuildingsId, PDO::PARAM_INT);
                $stmt2->execute();
                $buildingResult = $stmt2->fetch(PDO::FETCH_ASSOC);
                $buildingName = $buildingResult['bldg_name'] ?? 'Unknown Building';

                // Log changes for update operations
                if ($inUp == "update" && $result !== false) {
                    $updateArray = [
                        'updated_table_id' => $saniId,
                        'user_id' => 0,
                        'updated_table_name' => 'floors',
                        'common_name' => $buildingName
                    ];

                    $fieldsToCheck = [
                        'buildings_id' => $saniBuildingsId,
                        'floor_designation' => $saniFloorDesignation,
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

    /**
     * Add, update, or delete a building record.
     *
     * @param array $data
     * @return bool
     */
    public function addUpdBuildings(array $data): bool
    {
        global $detailedLogging;
        $whatFunction = "addUpdBuildings";
        $conn = $this->dbManager->getDefaultConnection();
        try {
            $conn->beginTransaction();
            $this->logService->dataLog("$whatFunction - Data transaction(s) starting.", $whatFunction);

            $saniBuildNum = $data['bldg_num'];
            $saniBuildName = $data['bldg_name'];
            $saniCampusId = $data['campus_id'];
            $saniShortDesc = $data['short_desc'];
            $saniFacilityCode = $data['facility_code'];
            $saniActive = $data['active'];
            $saniId = $data['id'] ?? null;
            $saniDelete = $data['delete'];
            $deletion = false;
            $result = false;
            $inUp = "update";

            if ($saniId !== null && $saniId != 0) {
                if ($saniDelete == 1) {
                    $inUp = "delete";
                    if ($detailedLogging) {
                        $this->logService->dataLog("$whatFunction - Prepping to Delete building data for id: $saniId", $whatFunction);
                    }
                    // Delete from buildings
                    $query = "DELETE FROM buildings WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();
                    $count = $stmt->rowCount();

                    // Delete from floors
                    if ($detailedLogging) {
                        $this->logService->dataLog("$whatFunction - Prepping to Delete floor data for id: $saniId", $whatFunction);
                    }
                    $queryFloors = "DELETE FROM floors WHERE buildings_id = :id";
                    $stmt = $conn->prepare($queryFloors);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();

                    // Delete from rooms
                    if ($detailedLogging) {
                        $this->logService->dataLog("$whatFunction - Prepping to Delete room data for id: $saniId", $whatFunction);
                    }
                    $queryRooms = "DELETE FROM rooms WHERE building_id = :id";
                    $stmt = $conn->prepare($queryRooms);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();

                    if ($count > 0) {
                        $this->logService->dataLog("$whatFunction - id $saniId deleted successfully. Total records deleted: $count", $whatFunction);
                        $deletion = true;
                    } else {
                        $this->logService->dataLog("$whatFunction - Error deleting id $saniId", $whatFunction);
                        $deletion = false;
                    }
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog("$whatFunction - We have an id: $saniId so attempting to select a record.", $whatFunction);
                    }
                    $inUp = "update";
                    $query = "SELECT * FROM buildings WHERE id = :id LIMIT 1";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            if ($saniDelete == 0) {
                if ($result === false) {
                    if ($detailedLogging) {
                        $this->logService->dataLog("$whatFunction - We do not have an id, so attempting insert.", $whatFunction);
                    }
                    $inUp = "insert";
                    $query = <<<'SQL'
                        INSERT INTO buildings (bldg_num, bldg_name, campus_id, short_desc, facility_code, active)
                        VALUES (:bldg_num, :bldg_name, :campus_id, :short_desc, :facility_code, :active);
SQL;
                    $stmt = $conn->prepare($query);
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog("$whatFunction - We have an id: $saniId and something needs updating.", $whatFunction);
                    }
                    $inUp = "update";
                    $query = <<<'SQL'
                        UPDATE buildings SET
                            bldg_num = :bldg_num,
                            bldg_name = :bldg_name,
                            campus_id = :campus_id,
                            short_desc = :short_desc,
                            facility_code = :facility_code,
                            active = :active
                        WHERE id = :id;
SQL;
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                }
                $stmt->bindParam(':bldg_num', $saniBuildNum, PDO::PARAM_STR);
                $stmt->bindParam(':bldg_name', $saniBuildName, PDO::PARAM_STR);
                $stmt->bindParam(':campus_id', $saniCampusId, PDO::PARAM_INT);
                $stmt->bindParam(':short_desc', $saniShortDesc, PDO::PARAM_STR);
                $stmt->bindParam(':facility_code', $saniFacilityCode, PDO::PARAM_STR);
                $stmt->bindParam(':active', $saniActive, PDO::PARAM_BOOL);
                $stmt->execute();

                // Logging updates if updating
                $updateArray = [
                    'updated_table_id' => $saniId,
                    'user_id' => 0,
                    'updated_table_name' => 'buildings',
                    'common_name' => 'Buildings'
                ];
                if ($inUp == "update" && $result !== false) {
                    $updateArray['updated_table_id'] = $result['id'];
                    if ($result['bldg_num'] != $saniBuildNum) {
                        $updateArray['old_value'] = $result['bldg_num'];
                        $updateArray['new_value'] = $saniBuildNum;
                        $updateArray['column_name'] = 'bldg_num';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                    if ($result['bldg_name'] != $saniBuildName) {
                        $updateArray['old_value'] = $result['bldg_name'];
                        $updateArray['new_value'] = $saniBuildName;
                        $updateArray['column_name'] = 'bldg_name';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                    if ($result['campus_id'] != $saniCampusId) {
                        $updateArray['old_value'] = $result['campus_id'];
                        $updateArray['new_value'] = $saniCampusId;
                        $updateArray['column_name'] = 'campus_id';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                    if ($result['short_desc'] != $saniShortDesc) {
                        $updateArray['old_value'] = $result['short_desc'];
                        $updateArray['new_value'] = $saniShortDesc;
                        $updateArray['column_name'] = 'short_desc';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                    if ($result['facility_code'] != $saniFacilityCode) {
                        $updateArray['old_value'] = $result['facility_code'];
                        $updateArray['new_value'] = $saniFacilityCode;
                        $updateArray['column_name'] = 'facility_code';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                    if ($result['active'] != $saniActive) {
                        $updateArray['old_value'] = $result['active'];
                        $updateArray['new_value'] = $saniActive;
                        $updateArray['column_name'] = 'active';
                        $this->logService->logUpdatedVariable($updateArray);
                    }
                }

                if ($stmt) {
                    $affectedRows = $stmt->rowCount();
                    $this->logService->dataLog("$whatFunction - $inUp succeeded. It affected $affectedRows row(s) of data.", $whatFunction);
                    if ($detailedLogging) {
                        if ($affectedRows > 0) {
                            $this->logService->dataLog("$whatFunction - Query affected $affectedRows row(s)", $whatFunction);
                        } else {
                            $this->logService->dataLog("$whatFunction - Query did not affect any rows", $whatFunction);
                        }
                    }
                } else {
                    $this->logService->dataLog("$whatFunction - $inUp Query execution failed", $whatFunction);
                }
            }

            $conn->commit();
            if ($detailedLogging) {
                $this->logService->dataLog("$whatFunction - Committing queries now.", $whatFunction);
            }
            if ($saniDelete == 1 && !$deletion) {
                $this->utilities->returnMsg("Data $inUp unsuccessful.", "Failure");
                //return false;
            } else {
                $this->utilities->returnMsg("Data $inUp successful.", "Success");
                //return true;
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $this->logService->logException($e, "$whatFunction - Data transaction(s) failed. Queries rolled back. Also the following error was generated: ", $whatFunction);
            $this->utilities->returnMsg($e->getMessage(), "Error");
            //return false;
        }
    }
}
