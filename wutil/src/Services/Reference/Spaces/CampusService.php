<?php

namespace App\Services\Reference\Spaces;

use PDO;
use App\Database\DatabaseManager;
use App\Services\Reference\BaseReferenceService;
use App\Utilities\Utilities;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use PDOException;

class CampusService extends BaseReferenceService
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
     * Read one campus by ID
     */
    public function readOneCampus(int $id, bool $isRaw = false, bool $activeOnly = false): array
    {
        if ($activeOnly) {
            $query = "SELECT * FROM campus WHERE id = :id AND active = true LIMIT 1";
        } else {
            $query = "SELECT * FROM campus WHERE id = :id LIMIT 1";
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
                "code" => $row["code"],
                "campus_name" => $row["campus_name"],
                "utc_offset" => $row["utc_offset"],
                "campus_num" => $row["campus_num"],
                "active" => ($row["active"]) ? 1 : 0
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
 * Read one campus by code
 *
 * @param string $code The campus code to look up
 * @param bool $isRaw Whether to return raw data
 * @param bool $activeOnly Whether to only return active campuses
 * @return array The campus data
 */
public function readCampusByCode(string $code, bool $isRaw = false, bool $activeOnly = false): array
{
    // Sanitize the input code
    $sanitizedCode = $this->utilities->sanitizeStringInput($code);

    // If sanitization returns null or empty string, return empty result
    if ($sanitizedCode === null || $sanitizedCode === '') {
        return $this->formatResponse([], 0, $isRaw);
    }
    if ($activeOnly) {
        $query = "SELECT * FROM campus WHERE code = :code AND active = true LIMIT 1";
    } else {
        $query = "SELECT * FROM campus WHERE code = :code LIMIT 1";
    }

    // Get the PDO connection from the database manager
    $connection = $this->dbManager->getDefaultConnection();

    // Prepare and execute the query
    $statement = $connection->prepare($query);
    $statement->bindParam(":code", $code, \PDO::PARAM_STR);
    $statement->execute();

    // Fetch all results as associative arrays
    $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
    $count = count($rows);

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            "id" => $row["id"],
            "code" => $row["code"],
            "campus_name" => $row["campus_name"],
            "utc_offset" => $row["utc_offset"],
            "campus_num" => $row["campus_num"],
            "active" => ($row["active"]) ? 1 : 0
        ];
    }

    return $this->formatResponse($result, $count, $isRaw);
}


    /**
     * Read all campuses
     */
    public function readAllCampuses(bool $isRaw = false, bool $activeOnly = false): array
    {
        if ($activeOnly) {
            $query = "SELECT * FROM campus WHERE active = true";
        } else {
            $query = "SELECT * FROM campus";
        }

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($query);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "code" => $row["code"],
                "campus_name" => $row["campus_name"],
                "utc_offset" => $row["utc_offset"],
                "campus_num" => $row["campus_num"],
                "active" => ($row["active"]) ? 1 : 0
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
 * Add, update or delete a Campus record
 *
 * @param array $data Sanitized data for the Campus record
 * @return bool Success or failure of the operation
 */
public function addUpdCampus(array $data): bool
{
    global $detailedLogging;
    $connection = $this->dbManager->getDefaultConnection();
    $utilities = new Utilities($this->dbManager);
    $inUp = "update";
    $inUpID = "";
    $whatFunction = "addUpdCampus";

    try {
        $connection->beginTransaction();
        $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

        // Extract sanitized data
        $saniUTCOffset = $data['utc_offset'];
        $saniCampusName = $data['campus_name'];
        $saniCode = $data['code'];
        $saniCampus_num = $data['campus_num'];
        $saniId = $data['id'] ?? null;
        $saniDelete = $data['delete'];
        $saniActive = $data['active'];
        $deletion = false;
        $result = false;

        if ($saniId !== null && $saniId != 0) {
            if ($saniDelete == 1) {
                $inUp = "delete";
                if ($detailedLogging) {
                    $this->logService->dataLog($whatFunction." - Prepping Delete for id: " . $saniId, $whatFunction);
                }

                $query = "DELETE FROM campus WHERE id = :id";
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

                $query = "SELECT * FROM campus WHERE id = :id LIMIT 1";
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
                INSERT INTO campus (utc_offset, campus_name, code, campus_num, active)
                VALUES (:utc_offset, :campus_name, :code, :campus_num, :active);
SQL;
                $stmt = $connection->prepare($query);
            } else {
                if ($detailedLogging) {
                    $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                }

                $inUp = "update";
                $query = <<<'SQL'
                UPDATE campus SET
                    utc_offset = :utc_offset,
                    campus_name = :campus_name,
                    code = :code,
                    campus_num = :campus_num,
                    active = :active
                WHERE id = :id;
SQL;
                $stmt = $connection->prepare($query);
                $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                $inUpID = " for id " . $saniId;
            }

            $stmt->bindParam(':utc_offset', $saniUTCOffset, PDO::PARAM_STR);
            $stmt->bindParam(':campus_name', $saniCampusName, PDO::PARAM_STR);
            $stmt->bindParam(':code', $saniCode, PDO::PARAM_STR);
            $stmt->bindParam(':campus_num', $saniCampus_num, PDO::PARAM_STR);
            $stmt->bindParam(':active', $saniActive, PDO::PARAM_BOOL);
            $stmt->execute();

            // Log changes for update operations
            if ($inUp == "update" && $result !== false) {
                $updateArray = [
                    'updated_table_id' => $saniId,
                    'user_id' => 0,
                    'updated_table_name' => 'campus',
                    'common_name' => 'Campuses'
                ];

                $fieldsToCheck = [
                    'utc_offset' => $saniUTCOffset,
                    'campus_name' => $saniCampusName,
                    'code' => $saniCode,
                    'campus_num' => $saniCampus_num,
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
        $this->logService->logException($e, $whatFunction . " - Unexpected error: ", $whatFunction);
        $this->utilities->returnMsg($e->getMessage(), "Error");
        //return false;
    }
    return true;
}


}
