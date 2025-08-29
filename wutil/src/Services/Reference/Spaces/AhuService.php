<?php

namespace App\Services\Reference\Spaces;

use App\Services\Reference\BaseReferenceService;
use App\Database\DatabaseManager;
use App\Services\Logging\LogService;
use App\Utilities\Utilities;
use App\Services\Configuration\ConfigService;
use PDO;
use PDOException;

class AhuService extends BaseReferenceService
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
     * AhuService constructor.
     *
     * @param bool $isRaw
     * @return array
     */
    public function readAllAHUs(bool $isRaw = false): array
    {
        $sql = "SELECT * FROM ahu_data";

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
                "ahu_name" => $row["ahu_name"],
                "ahu_code" => $row["ahu_code"]
            ];
        }
        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Add, update or delete an AHU record
     *
     * @param array $data Sanitized data for the AHU record
     * @return bool Success or failure of the operation
     */
    public function addUpdAHUs(array $data): bool
    {
        global $detailedLogging;
        $connection = $this->dbManager->getDefaultConnection();
        $inUp = "update";
        $whatFunction = "addUpdAHUs";

        try {
            $connection->beginTransaction();
            $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

            // Extract sanitized data
            $saniName = $data["ahu_name"];
            $saniCode = $data["ahu_code"];
            $saniId = $data["id"] ?? null;
            $result = false;
            $deletion = false;

            if ($saniId !== null && $saniId != 0) {
                if ($data['delete'] == 1) {
                    $inUp = "delete";
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - Prepping Delete for id: " . $saniId, $whatFunction);
                    }

                    $query = "DELETE FROM ahu_data WHERE id = :id";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $stmt->execute();
                    $count = $stmt->rowCount();

                    if ($count > 0) {
                        $this->logService->dataLog($whatFunction." - Id " . $saniId . " deleted successfully. Total records deleted: ".$count, $whatFunction);
                        $deletion = true;
                    } else {
                        $this->logService->dataLog($whatFunction." - Error deleting id " . $saniId, $whatFunction);
                        $deletion = false;
                    }
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " so attempting to select a record.", $whatFunction);
                    }

                    $query = "SELECT * FROM ahu_data WHERE id = :id LIMIT 1";
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
                    INSERT INTO ahu_data (ahu_name, ahu_code)
                    VALUES (:ahu_name, :ahu_code);
    SQL;
                    $stmt = $connection->prepare($query);
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                    }

                    $inUp = "update";
                    $query = <<<'SQL'
                    UPDATE ahu_data SET
                        ahu_name = :ahu_name,
                        ahu_code = :ahu_code
                    WHERE id = :id;
    SQL;
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                }

                $stmt->bindParam(':ahu_name', $saniName, PDO::PARAM_STR);
                $stmt->bindParam(':ahu_code', $saniCode, PDO::PARAM_STR);
                $stmt->execute();

                // Log changes for update operations
                if ($inUp == "update" && $result !== false) {
                    $updateArray = [
                        'updated_table_id' => $saniId,
                        'user_id' => 0,
                        'updated_table_name' => 'ahu_data',
                        'common_name' => 'AHU'
                    ];

                    $fieldsToCheck = [
                        'ahu_name' => $saniName,
                        'ahu_code' => $saniCode
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
                    $this->logService->dataLog($whatFunction . " - " . $inUp . " Query execution failed", $whatFunction);
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