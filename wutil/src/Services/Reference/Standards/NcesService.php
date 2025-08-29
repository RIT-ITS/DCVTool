<?php

declare(strict_types=1);

namespace App\Services\Reference\Standards;

use App\Services\Reference\BaseReferenceService;
use App\Database\DatabaseManager;
use App\Utilities\Utilities;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use PDO;
use PDOException;

/**
 * Service for handling NCES standards reference data
 */
class NcesService extends BaseReferenceService
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
     * Get NCES 4.2 data
     *
     * @param int|null $id Optional ID to filter results
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array The NCES 4.2 data
     */
    public function getNces42(?int $id = null, bool $isRaw = false): array
    {
        $sql = "SELECT * FROM nces_4_2";
        if ($id !== null) {
            $sql .= " WHERE id = :id";
        }

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        if ($id !== null) {
            $statement->bindValue(":id", $id, \PDO::PARAM_INT);
        }

        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);
        $result = [];

        foreach ($rows as $row) {
            $ashraeIDs = $this->getAshrae61XrefFromNCES($row["id"]);
            $result[] = [
                "id" => $row["id"],
                "code" => $row["code"],
                "space_use_name" => $row["space_use_name"],
                "category_id" => $row["category_id"],
                "ashrae_61_ids" => $ashraeIDs
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Get NCES categories
     *
     * @param int|null $id Optional ID to filter results
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array The NCES categories data
     */
    public function getNcesCat(?int $id = null, bool $isRaw = false): array
    {
        $sql = "SELECT * FROM nces_categories";
        if ($id !== null) {
            $sql .= " WHERE id = :id";
        }

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        if ($id !== null) {
            $statement->bindValue(":id", $id, \PDO::PARAM_INT);
        }

        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "code" => $row["code"],
                "type_name" => $row["type_name"]
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Get ASHRAE 6.1 cross-reference IDs from NCES ID
     *
     * @param int $id The NCES ID to look up
     * @return array Array of ASHRAE IDs related to the NCES ID
     */
    public function getAshrae61XrefFromNCES(int $id): array
    {
        $sql = "SELECT ashrae_id FROM nces_ashrae_xref";
        if ($id !== null) {
            $sql .= " WHERE nces_id = :id";
        }

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        if ($id !== null) {
            $statement->bindValue(":id", $id, \PDO::PARAM_INT);
        }

        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];

        foreach ($rows as $row) {
            $result[] = $row["ashrae_id"];
        }

        return $result;
    }

    /**
     * Get NCES cross-reference IDs from ASHRAE 6.1 ID
     *
     * @param int|null $id The ASHRAE 6.1 ID to look up
     * @return array Array of NCES IDs related to the ASHRAE 6.1 ID
     */
    public function getNcesXrefFromAshrae61(?int $id = null): array
    {
        $sql = "SELECT nces_id FROM nces_ashrae_xref";
        if ($id !== null) {
            $sql .= " WHERE ashrae_id = :id";
        }

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($sql);
        if ($id !== null) {
            $statement->bindValue(":id", $id, \PDO::PARAM_INT);
        }

        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];

        foreach ($rows as $row) {
            $result[] = $row["nces_id"];
        }

        return $result;
    }

    /**
     * Add, update or delete an NCES 4.2 record
     *
     * @param array $data Sanitized data for the NCES 4.2 record
     * @return bool Success or failure of the operation
     */
    public function addUpdNces42(array $data): bool
    {
        global $detailedLogging;
        $connection = $this->dbManager->getDefaultConnection();
        $inUp = "update";
        $inUpID = "";
        $whatFunction = "addUpdNces42";

        try {
            $connection->beginTransaction();
            $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

            // Extract sanitized data
            $saniCode = $data['code'];
            $saniName = $data['space_use_name'];
            $saniCategoryId = $data['category_id'];
            $saniAshraeIds = $data['ashrae_61_ids'];
            $saniId = $data['id'] ?? null;
            $saniDelete = $data['delete'];
            $result = false;
            $deletion = false;

            if ($saniId !== null && $saniId != 0) {
                if ($saniDelete == 1) {
                    $inUp = "delete";
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - Prepping Delete for id: " . $saniId, $whatFunction);
                    }

                    $query = "DELETE FROM nces_4_2 WHERE id = :id";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();
                    $count = $stmt->rowCount();

                    if ($count > 0) {
                        $this->logService->dataLog($whatFunction." - id " . $saniId . " deleted successfully. Total records deleted: ".$count.". Proceeding with delete of xrefs.", $whatFunction);
                        $deletion = true;
                    } else {
                        $this->logService->dataLog($whatFunction." - Error deleting id " . $saniId, $whatFunction);
                        $deletion = false;
                    }
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " so attempting to select a record.", $whatFunction);
                    }

                    $query = "SELECT * FROM nces_4_2 WHERE id = :id LIMIT 1";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            if ($saniDelete == 0) {
                if ($result === false) {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We do not have an id, so attempting insert.", $whatFunction);
                    }

                    $inUp = "insert";
                    $query = <<<'SQL'
                    INSERT INTO nces_4_2 (code, space_use_name, category_id)
                    VALUES (:code, :space_use_name, :category_id);
    SQL;
                    $stmt = $connection->prepare($query);
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                    }

                    $inUp = "update";
                    $query = <<<'SQL'
                    UPDATE nces_4_2 SET
                        code = :code,
                        space_use_name = :space_use_name,
                        category_id = :category_id
                    WHERE id = :id;
    SQL;
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $inUpID = " for id " . $saniId;
                }

                $stmt->bindParam(':code', $saniCode, PDO::PARAM_STR);
                $stmt->bindParam(':space_use_name', $saniName, PDO::PARAM_STR);
                $stmt->bindParam(':category_id', $saniCategoryId, PDO::PARAM_INT);
                $stmt->execute();

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

            // PROCESS ALL XREFS AFTER EVERYTHING ELSE IS DONE
            // Did we delete or update a record? if so, xrefs need to be deleted
            if (($saniDelete == 1 && $deletion) || $inUp == "update") {
                $queryDel = "DELETE FROM nces_ashrae_xref WHERE nces_id = :id";
                $stmtDel = $connection->prepare($queryDel);
                $stmtDel->bindParam(':id', $saniId, PDO::PARAM_INT);
                $stmtDel->execute();
            }

            // If it's an insert or an update, add rows to the table
            if ($inUp == "insert" || $inUp == "update") {
                // If it's an insert, get the most recent row added to the table
                if ($saniId == null) {
                    $query = "SELECT MAX(id) FROM nces_4_2";
                    $stmt = $connection->prepare($query);
                    $stmt->execute();
                    $res = $stmt->fetch(PDO::FETCH_ASSOC);
                    $saniId = $res[0];
                }

                foreach ($saniAshraeIds as $aid) {
                    $query = <<<'SQL'
                    INSERT INTO nces_ashrae_xref (ashrae_id, nces_id)
                    VALUES (:ashrae_id, :nces_id);
    SQL;
                    $stmtInsert = $connection->prepare($query);
                    $stmtInsert->bindParam(':ashrae_id', $aid, PDO::PARAM_INT);
                    $stmtInsert->bindParam(':nces_id', $saniId, PDO::PARAM_INT);
                    $stmtInsert->execute();
                }

                $inuptmp = ($inUp == "insert") ? 'insert' : 're-insert';
                if ($detailedLogging) {
                    $this->logService->dataLog($whatFunction . " - Xref Data ".$inuptmp." complete.", $whatFunction);
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
            $connection->rollBack();
            $this->logService->logException($e, $whatFunction . " - Unexpected error occurred: ", $whatFunction);
            $this->utilities->returnMsg($e->getMessage(), "Error");
        }
        return true;
    }

    /**
     * Add, update or delete an NCES Category record
     *
     * @param array $data Sanitized data for the NCES Category record
     * @return bool Success or failure of the operation
     */
    public function addUpdNcesCats(array $data): bool
    {
        global $detailedLogging;
        $connection = $this->dbManager->getDefaultConnection();
        $utilities = new Utilities($this->dbManager);
        $inUp = "update";
        $inUpID = "";
        $whatFunction = "addUpdNcesCategories";

        try {
            $connection->beginTransaction();
            $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

            // Extract sanitized data
            $saniCode = $data['code'];
            $saniName = $data['type_name'];
            $saniId = $data['id'] ?? null;
            $saniDelete = $data['delete'];
            $result = false;
            $deletion = false;

            if ($saniId !== null && $saniId != 0) {
                if ($saniDelete == 1) {
                    $inUp = "delete";
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - Prepping Delete for id: " . $saniId, $whatFunction);
                    }

                    $query = "DELETE FROM nces_categories WHERE id = :id";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();
                    $count = $stmt->rowCount();

                    if ($count > 0) {
                        $this->logService->dataLog($whatFunction." - id " . $saniId . " deleted successfully. Total records deleted: ".$count.". Proceeding with delete of xrefs.", $whatFunction);
                        $deletion = true;
                    } else {
                        $this->logService->dataLog($whatFunction." - Error deleting id " . $saniId, $whatFunction);
                        $deletion = false;
                    }
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " so attempting to select a record.", $whatFunction);
                    }

                    $query = "SELECT * FROM nces_categories WHERE id = :id LIMIT 1";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            if ($saniDelete == 0) {
                if ($result === false) {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We do not have an id, so attempting insert.", $whatFunction);
                    }

                    $inUp = "insert";
                    $query = <<<'SQL'
                    INSERT INTO nces_categories (code, type_name)
                    VALUES (:code, :type_name);
    SQL;
                    $stmt = $connection->prepare($query);
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                    }

                    $inUp = "update";
                    $query = <<<'SQL'
                    UPDATE nces_categories SET
                        code = :code,
                        type_name = :type_name
                    WHERE id = :id;
    SQL;
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $inUpID = " for id " . $saniId;
                }

                $stmt->bindParam(':code', $saniCode, PDO::PARAM_STR);
                $stmt->bindParam(':type_name', $saniName, PDO::PARAM_STR);
                $stmt->execute();

                // Log changes for update operations
                if ($inUp == "update" && $result !== false) {
                    $updateArray = [
                        'updated_table_id' => $saniId,
                        'user_id' => 0,
                        'updated_table_name' => 'nces_categories',
                        'common_name' => 'NCES Categories'
                    ];

                    $fieldsToCheck = [
                        'code' => $saniCode,
                        'type_name' => $saniName
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


}
