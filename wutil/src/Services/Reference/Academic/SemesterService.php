<?php

namespace App\Services\Reference\Academic;

use App\Services\Reference\BaseReferenceService;
use App\Database\DatabaseManager;
use App\Utilities\Utilities;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use PDO;
use PDOException;

/**
 * Service for handling university semester-related reference data
 */
class SemesterService extends BaseReferenceService
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

        // Use provided services or create them if not provided
        $this->utilities = $utilities ?? new Utilities($dbManager);
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $this->utilities, $this->configService);
    }
    /**
     * Read current semester information
     */
    public function readCurrentSemester(bool $isRaw = false): array
    {
        $query = "SELECT * FROM terms WHERE DATE(NOW()) BETWEEN term_start AND term_end LIMIT 1";
        $result = [];

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare and execute the query
        $statement = $connection->prepare($query);
        $statement->execute();

        // Fetch all results as associative arrays
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $count = count($rows);

        if ($count > 0) {
            foreach ($rows as $row) {
                $result[] = [
                    "id" => $row["id"],
                    "term_name" => $row["term_name"],
                    "term_start_ts" => $row["term_start"],
                    "term_end_ts" => $row["term_end"],
                    "term_code" => $row["term_code"]
                ];
            }
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Read next semester information
     */
    public function readNextSemester(bool $isRaw = false): array
    {
        $query = "SELECT * FROM terms WHERE NOW() < term_start AND NOW() < term_end ORDER BY term_start ASC LIMIT 1";
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
            $result[] = [
                "id" => $row["id"],
                "term_name" => $row["term_name"],
                "term_start_ts" => $row["term_start"],
                "term_end_ts" => $row["term_end"],
                "term_code" => $row["term_code"]
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Determine current or upcoming semester code
     */
    public function determineSemester(): int
    {
        $currSemester = $this->readCurrentSemester();

        if ($currSemester["response"]["numFound"] > 0) {
            return $currSemester["response"]["docs"][0][0]["term_code"];
        }

        $nextSemester = $this->readNextSemester();
        return $nextSemester["response"]["docs"][0][0]["term_code"];
    }

    /**
     * Get terms information
     *
     * @param int|null $id Optional term ID to filter by
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array Term data
     */
    public function getTerms(?int $id = null, bool $isRaw = false): array
    {
        $sql = "SELECT * FROM terms";

        if ($id !== null) {
            $sql .= " WHERE id = :id ORDER BY term_start";
        } else {
            $sql .= " ORDER BY term_start";
        }

        // Get the PDO connection from the database manager
        $connection = $this->dbManager->getDefaultConnection();

        // Prepare the query
        $statement = $connection->prepare($sql);

        // Bind parameters if needed
        if ($id !== null) {
            $statement->bindValue(":id", $id, \PDO::PARAM_INT);
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
                "term_name" => $row["term_name"],
                "term_start" => $row["term_start"],
                "term_end" => $row["term_end"],
                "term_code" => $row["term_code"]
            ];
        }

        return $this->formatResponse($result, $count, $isRaw);
    }

    /**
     * Add, update or delete a Term record
     *
     * @param array $data Sanitized data for the Term record
     * @return bool Success or failure of the operation
     */
    public function addUpdTerm(array $data): bool
    {
        global $detailedLogging;
        $connection = $this->dbManager->getDefaultConnection();
        $inUp = "update";
        $inUpID = "";
        $whatFunction = "addUpdTerm";

        try {
            $connection->beginTransaction();
            $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

            // Extract sanitized data
            $saniTermName = $data['term_name'];
            $saniTermStart = $data['term_start'];
            $saniTermEnd = $data['term_end'];
            $saniTermCode = $data['term_code'];
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

                    $query = "DELETE FROM terms WHERE id = :id";
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

                    $query = "SELECT * FROM terms WHERE id = :id LIMIT 1";
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
                    INSERT INTO terms (term_name, term_start, term_end, term_code)
                    VALUES (:term_name, :term_start, :term_end, :term_code);
    SQL;
                    $stmt = $connection->prepare($query);
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                    }

                    $inUp = "update";
                    $query = <<<'SQL'
                    UPDATE terms SET
                        term_name = :term_name,
                        term_start = :term_start,
                        term_end = :term_end,
                        term_code = :term_code
                    WHERE id = :id;
    SQL;
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                    $inUpID = " for id " . $saniId;
                }

                $stmt->bindParam(':term_name', $saniTermName, PDO::PARAM_STR);
                $stmt->bindParam(':term_start', $saniTermStart, PDO::PARAM_STR);
                $stmt->bindParam(':term_end', $saniTermEnd, PDO::PARAM_STR);
                $stmt->bindParam(':term_code', $saniTermCode, PDO::PARAM_STR);
                $stmt->execute();

                // Log changes for update operations
                if ($inUp == "update" && $result !== false) {
                    $updateArray = [
                        'updated_table_id' => $saniId,
                        'user_id' => 0,
                        'updated_table_name' => 'terms',
                        'common_name' => 'Terms'
                    ];

                    $fieldsToCheck = [
                        'term_name' => $saniTermName,
                        'term_start' => $saniTermStart,
                        'term_end' => $saniTermEnd,
                        'term_code' => $saniTermCode
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
            $connection->rollBack();
            $this->logService->logException($e, $whatFunction . " - Unexpected error occurred: ", $whatFunction);
            $this->utilities->returnMsg($e->getMessage(), "Error");
        }
        return true;
    }


}
