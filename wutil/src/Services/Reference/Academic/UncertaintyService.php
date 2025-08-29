<?php

namespace App\Services\Reference\Academic;

use App\Services\Reference\BaseReferenceService;
use App\Utilities\Utilities;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use App\Database\DatabaseManager;
class UncertaintyService extends BaseReferenceService
{
    protected Utilities $utilities;
    protected ConfigService $configService;
    protected LogService $logService;
    public function __construct(
        DatabaseManager $dbManager,
        ?Utilities $utilities = null,
        ?ConfigService $configService = null,
        ?LogService $logService = null
    )
    {
        parent::__construct($dbManager);
        // Use injected services if provided, otherwise create new instances
        $this->utilities = $utilities ?? new Utilities($dbManager);
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $this->utilities, $this->configService);
    }

    /**
     * Read one uncertainty by ID
     */
    public function readOneUncertainty(int $id, bool $isRaw = false): array
    {
        $query = "SELECT * FROM uncertainty WHERE id = :id LIMIT 1";
        $result = [];

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($query);
        $statement->bindParam(":id", $id, \PDO::PARAM_INT);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "u_desc" => $row["u_desc"],
                "uncert_amt" => $row["uncert_amt"]
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read one uncertainty based on an ashrae 6-1 id
     */
    public function readOneUncertaintyXref(int $ashrae_id, bool $isRaw = false): array
    {
        $query = "SELECT * FROM uncert_6_1_xref WHERE ashrae_id = :ashrae_id LIMIT 1";
        $result = [];

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($query);
        $statement->bindParam(":ashrae_id", $ashrae_id, \PDO::PARAM_INT);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        foreach ($rows as $row) {
            $result = $this->readOneUncertainty($row["uncert_id"], true);
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read all uncertainties
     */
    public function readAllUncertainties(bool $isRaw = false): array
    {
        $query = "SELECT * FROM uncertainty";
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
            $ash61_cats = $this->getUncertAshraeXrefs($row["id"]);
            $result[] = [
                "id" => $row["id"],
                "u_desc" => $row["u_desc"],
                "uncert_amt" => $row["uncert_amt"],
                "ashrae_61_ids" => $ash61_cats
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Get ashrae/uncert xrefs
     */
    public function getUncertAshraeXrefs(?int $id = null, bool $isRaw = false): array
    {
        $sql = "SELECT ashrae_id FROM uncert_6_1_xref";
        if ($id !== null) {
            $sql .= " WHERE uncert_id = :id";
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
     * Check if the room's uncertainty value is set, otherwise look for a default
     *
     * @param int $roomId The ID of the room
     * @param int|null $uncertaintyValue The current uncertainty value, if any
     * @return int The updated uncertainty value
     * @throws \Exception If there's an error updating the room
     */
    public function updateUncertainty(int $roomId, ?int $uncertaintyValue = null): int
    {
        if ($uncertaintyValue === null) {
            // Get the PDO connection from the database manager
            $connection = $this->dbManager->getDefaultConnection();

            // Get the ashrae category ID for the room
            $query = "SELECT ash61_cat_id FROM rooms WHERE id = :roomId LIMIT 1";
            $statement = $connection->prepare($query);
            $statement->bindParam(':roomId', $roomId, \PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                // Get the uncertainty value for this ashrae category
                $ashraeId = $row['ash61_cat_id'];
                $uncertaintyData = $this->readOneUncertaintyXref($ashraeId, true);

                if (!empty($uncertaintyData)) {
                    $uncertaintyValue = $uncertaintyData[0]['uncert_amt'];

                    // Update the room with the new uncertainty value
                    $query = "UPDATE rooms SET uncert_amt = :uncertaintyValue WHERE id = :roomId";
                    $statement = $connection->prepare($query);
                    $statement->bindParam(':uncertaintyValue', $uncertaintyValue, \PDO::PARAM_INT);
                    $statement->bindParam(':roomId', $roomId, \PDO::PARAM_INT);

                    if (!$statement->execute()) {
                        throw new \Exception("Error while updating room: " . implode(", ", $statement->errorInfo()));
                    }
                } else {
                    $uncertaintyValue = 0;
                }
            } else {
                $uncertaintyValue = 0;
            }
        }

        return $uncertaintyValue;
    }

    /**
     * Add, update or delete an Uncertainty record
     *
     * @param array $data Sanitized data for the Uncertainty record
     * @return bool Success or failure of the operation
     */
    public function addUpdUncertainty(array $data): bool
    {
        global $detailedLogging;
        $connection = $this->dbManager->getDefaultConnection();
        $inUp = "update";
        $inUpID = "";
        $whatFunction = "addUpdUncertainty";

        try {
            $connection->beginTransaction();
            $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

            // Extract sanitized data
            $saniUncertAmt = $data["uncert_amt"];
            $saniUDesc = $data["u_desc"];
            $saniAshraeIds = $data['ashrae_61_ids'];
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

                    $query = "DELETE FROM uncertainty WHERE id = :id";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, \PDO::PARAM_INT);
                    $stmt->execute();
                    $count = $stmt->rowCount();

                    if ($count > 0) {
                        $this->logService->dataLog($whatFunction." - id " . $saniId . " deleted successfully. Total records deleted: ".$count, $whatFunction);
                        $deletion = true;

                        // Reset the default values of the rooms of types that were modified
                        foreach ($saniAshraeIds as $ashId) {
                            $query = "UPDATE rooms SET uncert_amt = NULL WHERE ash61_cat_id = :ashId AND uncert_amt = :amt";
                            $stmt = $connection->prepare($query);
                            $stmt->bindParam(":ashId", $ashId, \PDO::PARAM_INT);
                            $stmt->bindParam(':amt', $saniUncertAmt, \PDO::PARAM_INT);
                            $stmt->execute();
                        }
                    } else {
                        $this->logService->dataLog($whatFunction." - Error deleting id " . $saniId, $whatFunction);
                        $deletion = false;
                    }
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " so attempting to select a record.", $whatFunction);
                    }

                    $query = "SELECT * FROM uncertainty WHERE id = :id LIMIT 1";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, \PDO::PARAM_INT);
                    $stmt->execute();
                    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                }
            }

            if ($data['delete'] == 0) {
                if ($result === false) {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We do not have an id, so attempting insert.", $whatFunction);
                    }

                    $inUp = "insert";
                    $query = <<<'SQL'
                INSERT INTO uncertainty (u_desc, uncert_amt)
                VALUES (:u_desc, :uncert_amt);
SQL;
                    $stmt = $connection->prepare($query);
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                    }

                    $inUp = "update";
                    $query = <<<'SQL'
                UPDATE uncertainty SET
                    u_desc = :u_desc,
                    uncert_amt = :uncert_amt
                WHERE id = :id;
SQL;
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, \PDO::PARAM_INT);
                    $inUpID = " for id " . $saniId;
                }

                $stmt->bindParam(':u_desc', $saniUDesc, \PDO::PARAM_STR);
                $stmt->bindParam(':uncert_amt', $saniUncertAmt, \PDO::PARAM_INT);
                $stmt->execute();

                // Update rooms if this is an update
                if ($inUp == "update" && $result !== false) {
                    foreach ($saniAshraeIds as $ashId) {
                        $query = "UPDATE rooms SET uncert_amt = NULL WHERE ash61_cat_id = :ashId AND uncert_amt = :amt";
                        $stmt = $connection->prepare($query);
                        $stmt->bindParam(":ashId", $ashId, \PDO::PARAM_INT);
                        $stmt->bindParam(':amt', $result["uncert_amt"], \PDO::PARAM_INT);
                        $stmt->execute();
                    }

                    // Log changes for update operations
                    $updateArray = [
                        'updated_table_id' => $saniId,
                        'user_id' => 0,
                        'updated_table_name' => 'uncertainty',
                        'common_name' => 'Uncertainty'
                    ];

                    $fieldsToCheck = [
                        'u_desc' => $saniUDesc,
                        'uncert_amt' => $saniUncertAmt
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

            // Process cross-references
            if ($detailedLogging) {
                $this->logService->dataLog($whatFunction . " - " . $inUp . " Processing Xrefs", $whatFunction);
            }

            // Delete existing xrefs if updating or deleting
            if (($saniDelete == 1 && $deletion) || $inUp == "update") {
                $queryDel = "DELETE FROM uncert_6_1_xref WHERE uncert_id = :id";
                $stmtDel = $connection->prepare($queryDel);
                $stmtDel->bindParam(':id', $saniId, \PDO::PARAM_INT);
                $stmtDel->execute();
            }

            // If it's an insert or update, add xrefs to the table
            if ($inUp == "insert" || $inUp == "update") {
                // If it's an insert, get the most recent row added to the uncertainty table
                if ($saniId === null) {
                    $query = "SELECT MAX(id) FROM uncertainty";
                    $stmt = $connection->prepare($query);
                    $stmt->execute();
                    $res = $stmt->fetch(\PDO::FETCH_NUM);
                    $saniId = $res[0];
                }

                foreach ($saniAshraeIds as $aid) {
                    $query = <<<'SQL'
                INSERT INTO uncert_6_1_xref (ashrae_id, uncert_id)
                VALUES (:ashrae_id, :uncert_id);
SQL;
                    $stmtInsert = $connection->prepare($query);
                    $stmtInsert->bindParam(':ashrae_id', $aid, \PDO::PARAM_INT);
                    $stmtInsert->bindParam(':uncert_id', $saniId, \PDO::PARAM_INT);
                    $stmtInsert->execute();
                }

                $inuptmp = ($inUp == "insert") ? 'insert' : 're-insert';
                if ($detailedLogging) {
                    $this->logService->dataLog($whatFunction . " - Xref Data " . $inuptmp . " complete.", $whatFunction);
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


}
