<?php

declare(strict_types=1);

namespace App\Services\Reference\Standards;

use App\Services\Reference\BaseReferenceService;
use App\Utilities\Utilities;
use App\Database\DatabaseManager;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use PDO;
use PDOException;

/**
 * Service for handling Ashrae standards reference data
 */
class AshraeService extends BaseReferenceService
{
    protected Utilities $utilities;
    protected ConfigService $configService;
    protected LogService $logService;

    /**
     * @param DatabaseManager $dbManager
     * @param Utilities|null $utilities
     * @param ConfigService|null $configService
     * @param LogService|null $logService
     */
    public function __construct(
        DatabaseManager $dbManager,
        ?Utilities $utilities = null,
        ?ConfigService $configService = null,
        ?LogService $logService = null
    ) {
        parent::__construct($dbManager);

        // Use injected services if provided, otherwise create new instances
        $this->utilities = $utilities ?? new Utilities($dbManager);
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $this->utilities, $this->configService);
    }

    /**
     * Read all Ashrae 6-1 types
     */
    public function readAllAshrae61Types(bool $isRaw = false): array
    {
        $query = "SELECT * FROM ashrae_6_1_types ORDER BY type ASC";
        $result = [];

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($query);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        foreach ($rows as $row) {
            $result[] = ["id" => $row["id"], "type" => $row["type"]];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read one Ashrae 6-1 type by ID
     */
    public function readOneAshrae61Type(int $id, bool $isRaw = false): array
    {
        $query = "SELECT * FROM ashrae_6_1_types WHERE id = :id";
        $result = [];

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare the query
        $statement = $connection->prepare($query);

        // Bind the parameter
        $statement->bindParam(':id', $id, \PDO::PARAM_INT);

        // Execute the query
        $statement->execute();

        // Fetch the result
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $result = ["id" => $row["id"], "type" => $row["type"]];
            $count = 1;
        } else {
            $count = 0;
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read all Ashrae 6-1 data, optionally filtered by type ID
     */
    public function readAllAshrae61(?int $id = null, bool $isRaw = false): array
    {
        $params = [];
        $connection = $this->dbManager->getDefaultConnection();

        if (is_int($id) && $id > 0) {
            $query = "SELECT * FROM ashrae_6_1 WHERE type = :typeId ORDER BY type ASC, category ASC";
            $statement = $connection->prepare($query);
            $statement->bindParam(':typeId', $id, \PDO::PARAM_INT);
        } else {
            $query = "SELECT * FROM ashrae_6_1 ORDER BY type ASC, category ASC";
            $statement = $connection->prepare($query);
        }

        $statement->execute();
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "category" => $row["category"],
                "ok" => (bool)$row["ok"] ? 1 : 0,
                "ppl_oa_rate" => $row["ppl_oa_rate"],
                "area_oa_rate" => $row["area_oa_rate"],
                "occ_density" => $row["occ_density"],
                "occ_stdby_allowed" => (bool)$row["occ_stdby_allowed"] ? 1 : 0,
                "type" => $row["type"],
                "notes" => $row["notes"]
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read one Ashrae 6-1 record by ID
     */
    public function readOneAshrae61(int $id, bool $isRaw = false): array
    {
        $connection = $this->dbManager->getDefaultConnection();
        $query = "SELECT * FROM ashrae_6_1 WHERE id = :id LIMIT 1";
        $statement = $connection->prepare($query);
        $statement->bindParam(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "category" => $row["category"],
                "ok" => (bool)$row["ok"] ? 1 : 0,
                "ppl_oa_rate" => $row["ppl_oa_rate"],
                "area_oa_rate" => $row["area_oa_rate"],
                "occ_density" => $row["occ_density"],
                "occ_stdby_allowed" => (bool)$row["occ_stdby_allowed"] ? 1 : 0,
                "type" => $row["type"],
                "notes" => $row["notes"]
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read all Ashrae 6-4 categories
     */
    public function readAllAshrae64Cats(bool $isRaw = false): array
    {
        $connection = $this->dbManager->getDefaultConnection();
        $query = "SELECT * FROM ashrae_6_4_categories ORDER BY id ASC";
        $statement = $connection->prepare($query);
        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);
        $result = [];

        foreach ($rows as $row) {
            $result[] = ["id" => $row["id"], "name" => $row["category_name"]];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read one Ashrae 6-4 category by ID
     */
    public function readOneAshrae64Category(int $id, bool $isRaw = false): array
    {
        $connection = $this->dbManager->getDefaultConnection();
        $query = "SELECT * FROM ashrae_6_4_categories WHERE id = :id LIMIT 1";
        $statement = $connection->prepare($query);
        $statement->bindParam(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);
        $result = [];

        foreach ($rows as $row) {
            $result[] = ["id" => $row["id"], "name" => $row["category_name"]];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read all Ashrae 6-4 data
     */
    public function readAllAshrae64(bool $isRaw = false): array
    {
        $connection = $this->dbManager->getDefaultConnection();
        $query = "SELECT * FROM ashrae_6_4 ORDER BY id ASC";
        $statement = $connection->prepare($query);
        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "category" => $row["cat"],
                "ez" => $row["ez"],
                "configuration" => $row["configuration"]
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read one Ashrae 6-4 record by ID
     */
    public function readOneAshrae64(int $id, bool $isRaw = false): array
    {
        $connection = $this->dbManager->getDefaultConnection();
        $query = "SELECT * FROM ashrae_6_4 WHERE id = :id LIMIT 1";
        $statement = $connection->prepare($query);
        $statement->bindParam(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "category" => $row["cat"],
                "ez" => $row["ez"],
                "configuration" => $row["configuration"]
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Add, update or delete an ASHRAE 6.1 record
     *
     * @param array $data Sanitized data for the ASHRAE record
     * @return bool Success or failure of the operation
     */
    public function addUpdAshrae61(array $data): bool
    {
        global $detailedLogging;
        $connection = $this->dbManager->getDefaultConnection();
        $inUp = "update";
        $inUpID = "";
        $whatFunction = "addUpdAshrae61";

        try {
            $connection->beginTransaction();
            $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

            // Extract sanitized data
            $saniCategory = $data["category"];
            $saniOk = $data["ok"];
            $saniPpl_oa_rate = $data['ppl_oa_rate'];
            $saniArea_oa_rate = $data['area_oa_rate'];
            $saniOcc_density = $data['occ_density'];
            $saniOcc_stdby_allowed = $data['occ_stdby_allowed'];
            $saniType = $data['type'];
            $saniNotes = $data['notes'];
            $saniId = $data["id"] ?? null;
            $saniDelete = $data['delete'];
            $deletion = false;
            $result = false;

            if ($saniId !== null && $saniId != 0) {
                if ($saniDelete == 1) {
                    $inUp = "delete";
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - Prepping Delete for id: " . $saniId, $whatFunction);
                    }

                    $query = "DELETE FROM ashrae_6_1 WHERE id = :id";
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

                    $query = "SELECT * FROM ashrae_6_1 WHERE id = :id LIMIT 1";
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
                    INSERT INTO ashrae_6_1 (ok, category, ppl_oa_rate, area_oa_rate, occ_density, occ_stdby_allowed, type, notes)
                    VALUES (:ok, :category, :ppl_oa_rate, :area_oa_rate, :occ_density, :occ_stdby_allowed, :type, :notes);
SQL;
                    $stmt = $connection->prepare($query);
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                    }

                    $inUp = "update";
                    $query = <<<'SQL'
                    UPDATE ashrae_6_1 SET
                        ok = :ok,
                        category = :category,
                        ppl_oa_rate = :ppl_oa_rate,
                        area_oa_rate = :area_oa_rate,
                        occ_density = :occ_density,
                        occ_stdby_allowed = :occ_stdby_allowed,
                        type = :type,
                        notes = :notes
                    WHERE id = :id;
SQL;
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $inUpID = " for id " . $saniId;
                }

                $stmt->bindParam(':ok', $saniOk, PDO::PARAM_BOOL);
                $stmt->bindParam(':category', $saniCategory, PDO::PARAM_STR);
                $stmt->bindParam(':ppl_oa_rate', $saniPpl_oa_rate, PDO::PARAM_STR);
                $stmt->bindParam(':area_oa_rate', $saniArea_oa_rate, PDO::PARAM_STR);
                $stmt->bindParam(':occ_density', $saniOcc_density, PDO::PARAM_STR);
                $stmt->bindParam(':occ_stdby_allowed', $saniOcc_stdby_allowed, PDO::PARAM_BOOL);
                $stmt->bindParam(':type', $saniType, PDO::PARAM_STR);
                $stmt->bindParam(':notes', $saniNotes, PDO::PARAM_STR);
                $stmt->execute();

                // Log changes for update operations
                if ($inUp == "update" && $result !== false) {
                    $updateArray = [
                        'updated_table_id' => $saniId,
                        'user_id' => 0,
                        'updated_table_name' => 'ashrae_6_1',
                        'common_name' => 'Ashrae 6-1'
                    ];

                    $fieldsToCheck = [
                        'ok' => $saniOk,
                        'category' => $saniCategory,
                        'ppl_oa_rate' => $saniPpl_oa_rate,
                        'area_oa_rate' => $saniArea_oa_rate,
                        'occ_density' => $saniOcc_density,
                        'occ_stdby_allowed' => $saniOcc_stdby_allowed,
                        'type' => $saniType,
                        'notes' => $saniNotes
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
            } else {
                $this->utilities->returnMsg("Data " . $inUp . " successful.", "Success");
            }
        } catch (PDOException $e) {
            $connection->rollBack();
            $this->logService->logException($e, $whatFunction . " - Data transaction(s) failed. Queries rolled back. Also the following error was generated: ", $whatFunction);
            $this->utilities->returnMsg($e->getMessage(), "Error");
        } catch (\Exception $e) {
        }
        return true;
    }

    /**
     * Add, update or delete an ASHRAE 6.4 record
     *
     * @param array $data Sanitized data for the ASHRAE 6.4 record
     * @return bool Success or failure of the operation
     */
    public function addUpdAshrae64(array $data): bool
    {
        global $detailedLogging;
        $connection = $this->dbManager->getDefaultConnection();
        $utilities = new Utilities($this->dbManager);
        $inUp = "update";
        $inUpID = "";
        $whatFunction = "addUpdAshrae64";

        try {
            $connection->beginTransaction();
            $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

            // Extract sanitized data
            $saniCat = $data['cat'];
            $saniEz = $data['ez'];
            $saniConfiguration = $data['configuration'];
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

                    $query = "DELETE FROM ashrae_6_4 WHERE id = :id";
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

                    $query = "SELECT * FROM ashrae_6_4 WHERE id = :id LIMIT 1";
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
                    INSERT INTO ashrae_6_4 (cat, ez, configuration)
                    VALUES (:cat, :ez, :configuration);
    SQL;
                    $stmt = $connection->prepare($query);
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                    }

                    $inUp = "update";
                    $query = <<<'SQL'
                    UPDATE ashrae_6_4 SET
                        cat = :cat,
                        ez = :ez,
                        configuration = :configuration
                    WHERE id = :id;
    SQL;
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $inUpID = " for id " . $saniId;
                }

                $stmt->bindParam(':cat', $saniCat, PDO::PARAM_STR);
                $stmt->bindParam(':ez', $saniEz, PDO::PARAM_STR);
                $stmt->bindParam(':configuration', $saniConfiguration, PDO::PARAM_STR);
                $stmt->execute();

                // Log changes for update operations
                if ($inUp == "update" && $result !== false) {
                    $updateArray = [
                        'updated_table_id' => $saniId,
                        'user_id' => 0,
                        'updated_table_name' => 'ashrae_6_4',
                        'common_name' => 'Ashrae 6-4'
                    ];

                    $fieldsToCheck = [
                        'cat' => $saniCat,
                        'ez' => $saniEz,
                        'configuration' => $saniConfiguration
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
                $utilities->returnMsg("Data " . $inUp . " unsuccessful.", "Failure");
            } else {
                $utilities->returnMsg("Data " . $inUp . " successful.", "Success");
            }
        } catch (PDOException $e) {
            $connection->rollBack();
            $this->logService->logException($e, $whatFunction . " - Data transaction(s) failed. Queries rolled back. Also the following error was generated: ", $whatFunction);
            $this->utilities->returnMsg($e->getMessage(), "Error");
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logService->logException($e, $whatFunction . " - Unexpected error occurred: ", $whatFunction);
            $this->utilities->returnMsg($e->getMessage(), "Error");
        }
        return true;
    }

    public function getUncertAshraeXrefs($id, $isRaw = false): array
    {
        $connection = $this->dbManager->getDefaultConnection();
        $sql = "SELECT ashrae_id FROM uncert_6_1_xref";
        if($id != null) {
            $sql .= " WHERE uncert_id = :id";
        }
        $stmt = $connection->prepare($sql);
        if($id != null) {
            $stmt->bindValue(":id", $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $resStat = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = $stmt->rowCount();
        $result = array();
        foreach ($resStat as $row) {
            //$result[] = array("ashrae_id"=>$row["ashrae_id"]);
            $result[] = $row["ashrae_id"];
        }
        return $result;
    }

}
