<?php
// src/Services/Import/ImportLogService.php

namespace App\Services\Logging;

use App\Database\DatabaseManager;
use App\Utilities\Utilities;
use App\Services\Configuration\ConfigService;
use App\Container\ServiceContainer;
use DateInterval;
use DateTime;
use DateTimeZone;
use PDO;
use PDOException;

class LogService
{
    protected DatabaseManager $dbManager;
    protected Utilities $utilities;
    protected ConfigService $configService;
    protected ?\PDO $connection = null;

    public function __construct(
        DatabaseManager $dbManager,
        ?Utilities $utilities = null,
        ?ConfigService $configService = null
    )
    {
        $this->dbManager = $dbManager;
        $this->utilities = $utilities ?? new Utilities($dbManager);
        $this->configService = $configService ?? new ConfigService($dbManager);
    }
    protected function getConnection(): \PDO
    {
        if ($this->connection === null) {
            $this->connection = $this->dbManager->getDefaultConnection();
        }
        return $this->connection;
    }
    /**
     * Format response in standard format or return raw data
     */
    protected function formatResponse(array $result, int $count, bool $isRaw = false): array
    {
        if ($isRaw) {
            return $result;
        }

        return [
            "responseHeader" => [
                "status" => 0
            ],
            "response" => [
                "numFound" => $count,
                "start" => 0,
                "docs" => [
                    $result
                ]
            ]
        ];
    }
    /**
     * @throws \Exception
     */
    public function logException(\Exception $e, $where = "", $tag = ''): void
    {
        // Log full exception details
        error_log($where." - ".$e->__toString(),$tag);
    }

    /**
     * Log changes to variables in the database
     *
     * @param array $data Data to log including table name, old/new values, etc.
     * @return bool Whether the logging was successful
     */
    public function logUpdatedVariable(array $data = []): bool
    {
        // Get the database connection from the DatabaseManager
        $connection = $this->getConnection();
        $filteredTableName = $data['updated_table_name'] !== null ? htmlspecialchars($data['updated_table_name']) : null;
        $filteredUserId = $data['user_id'] !== null ? htmlspecialchars($data['user_id']) : null;
        $filteredOldValue = $data['old_value'] !== null ? htmlspecialchars($data['old_value']) : null;
        $filteredNewValue = $data['new_value'] !== null ? htmlspecialchars($data['new_value']) : null;
        $filteredUpdatedTableId = $data['updated_table_id'] !== null ? htmlspecialchars($data['updated_table_id']) : null;
        $filteredColumnName = $data['column_name'] !== null ? htmlspecialchars($data['column_name']) : null;
        $filteredCommonName = $data['common_name'] !== null ? htmlspecialchars($data['common_name']) : null;
        $insert_stmt = $connection->prepare("INSERT INTO public.logged_updates (updated_table_name, old_value, new_value, user_id, updated_table_id, column_name, common_name, time_updated) VALUES (:filteredTableName, :filteredOldValue, :filteredNewValue, :filteredUserId, :filteredUpdatedTableId, :filteredColumnName, :filteredCommonName, NOW())");
        $insert_stmt->bindValue(":filteredTableName", $filteredTableName);
        $insert_stmt->bindValue(":filteredUserId", $filteredUserId);
        $insert_stmt->bindValue(":filteredOldValue", $filteredOldValue);
        $insert_stmt->bindValue(":filteredNewValue", $filteredNewValue);
        $insert_stmt->bindValue(":filteredUpdatedTableId", $filteredUpdatedTableId);
        $insert_stmt->bindValue(":filteredColumnName", $filteredColumnName);
        $insert_stmt->bindValue(":filteredCommonName", $filteredCommonName);
        // Return true if successful, false otherwise
        return $insert_stmt->execute();
    }

    /**
     * Log a change to a database record
     *
     * @param array $updateArray Base information about the update
     * @param mixed $oldValue The previous value
     * @param mixed $newValue The new value
     * @param string $columnName The name of the column being updated
     * @return void
     */
    public function logChange(array $updateArray, $oldValue, $newValue, string $columnName): void
    {
        // Add the change details to the update array
        $updateArray['old_value'] = $oldValue;
        $updateArray['new_value'] = $newValue;
        $updateArray['column_name'] = $columnName;

        // Delegate to logUpdatedVariable which already follows Approach 2
        $this->logUpdatedVariable($updateArray);
    }

    /**
     * Read logged updates from the database with flexible filtering options
     * Previously was called 'readUpdatedVarsAll()' and 'readUpdatedVars()'
     * @param array $filters Filtering options (type, dateStart, dateEnd)
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array The logged updates matching the criteria
     */
    public function readLoggedUpdates(array $filters = [], bool $isRaw = false): array
    {
        // Get the database connection from the DatabaseManager
        $connection = $this->getConnection();

        // Initialize variables
        $params = [];
        $query = "SELECT * FROM logged_updates";
        $whereConditions = [];

        // Handle type-based filtering
        if (isset($filters['type']) && $filters['type'] !== 'all') {
            $whereConditions[] = "updated_table_name = :type";
            $params[':type'] = $filters['type'];
        }

        // Handle date-based filtering
        if (isset($filters['dateStart'])) {
            $startDate = (new DateTime($filters['dateStart'], new DateTimeZone('America/New_York')))->format('Y-m-d H:i:s.u');
            $params[':dateStart'] = $startDate;

            $endDate = null;
            if (isset($filters['dateEnd']) && $filters['dateEnd'] !== "0-0-0") {
                $endDate = (new DateTime($filters['dateEnd'], new DateTimeZone('America/New_York')));
            } else {
                $endDate = (new DateTime($filters['dateStart'], new DateTimeZone('America/New_York')));
            }

            // Add one day to end date
            $endDate->add(new DateInterval("P1D"));
            $endDate = $endDate->format('Y-m-d H:i:s.u');
            $params[':dateEnd'] = $endDate;

            $whereConditions[] = "time_updated AT TIME ZONE 'America/New_York' > :dateStart";
            $whereConditions[] = "time_updated AT TIME ZONE 'America/New_York' < :dateEnd";
        }

        // Add WHERE clause if conditions exist
        if (!empty($whereConditions)) {
            $query .= " WHERE " . implode(" AND ", $whereConditions);
        }

        // Add ordering and limit
        $query .= " ORDER BY id DESC LIMIT 800";

        // Prepare and execute the query
        $stmt = $connection->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $resStat = $stmt->fetchAll();
        $count = $stmt->rowCount();
        $result = [];

        // Process results
        foreach ($resStat as $row) {
            $res = [
                "id" => $row["id"],
                "old_value" => $row["old_value"],
                "new_value" => $row["new_value"],
                "updated_table_name" => $row["updated_table_name"],
                "user_id" => $row["user_id"],
                "updated_table_id" => $row["updated_table_id"],
                "column_name" => $row["column_name"],
                "common_name" => $row["common_name"]
            ];

            // Convert from UTC to America/New_York
            $originalTimezone = new DateTimeZone('UTC');
            $targetTimezone = new DateTimeZone('America/New_York');

            $incomingTime = $row['time_updated'];
            $datetime = new DateTime($incomingTime, $originalTimezone);
            $datetime->setTimezone($targetTimezone);
            $convertedStarttime = $datetime->format('Y-m-d h:i:s a');

            $res["time_updated"] = $convertedStarttime;

            $result[] = $res;
        }

        // Return raw or formatted data
        if ($isRaw) {
            return $result;
        } else {
            return [
                "responseHeader" => [
                    "status" => 0
                ],
                "response" => [
                    "numFound" => $count,
                    "start" => 0,
                    "docs" => [
                        $result
                    ]
                ]
            ];
        }
    }

    /**
     * Read all logged updates for a specific type or all types
     *
     * @param string $type The type to filter by, or 'all' for all types
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array The logged updates matching the criteria
     */
    public function readLoggedUpdatesByType(string $type = 'all', bool $isRaw = false): array
    {
        return $this->readLoggedUpdates(['type' => $type], $isRaw);
    }

    /**
     * Read logged updates within a date range
     *
     * @param string $dateStart The start date (format: Y-m-d)
     * @param string $dateEnd The end date (format: Y-m-d), or "0-0-0" to use start date + 1 day
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array The logged updates matching the criteria
     */
    public function readLoggedUpdatesByDate(string $dateStart, string $dateEnd = "0-0-0", bool $isRaw = false): array
    {
        return $this->readLoggedUpdates([
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd
        ], $isRaw);
    }

    /**
     * Log import operations to the database
     *
     * @param array $data The data being imported
     * @param string $type The type of import
     * @return bool Whether the logging was successful
     */
    public function logImports(array $data = [], string $type = ''): bool
    {
        // Get the database connection from the DatabaseManager
        $connection = $this->getConnection();

        // Sanitize inputs
        $filteredData = json_encode($data);
        $filteredType = $this->utilities->sanitizeStringInput($type);

        // Prepare and execute the SQL statement
        $insert_stmt = $connection->prepare("
        INSERT INTO public.import_log (
            import_data, 
            import_type, 
            import_date
        ) VALUES (
            :filteredData, 
            :filteredType, 
            NOW()
        )
    ");

        $insert_stmt->bindValue(":filteredData", $filteredData);
        $insert_stmt->bindValue(":filteredType", $filteredType);

        return $insert_stmt->execute();
    }


    /**
     * Check if detailed logging is enabled in the system configuration
     *
     * @return bool Whether detailed logging is enabled
     */
    public function isDetailedLoggingEnabled(): bool
    {
        // Use instance method
        $configVars = $this->configService->readConfigVars(1, true);

        // Return boolean value directly
        return $configVars['detailedlogging'] === "true";
    }

    /**
     * Check if logging is enabled in the system configuration
     *
     * @return bool Whether logging is enabled
     */
    public function isLoggingEnabled(): bool
    {
        // Use instance method
        $configVars = $this->configService->readConfigVars(0, true);

        // Return boolean value directly
        return $configVars['logging_enabled'] === "true";
    }

    /**
     * Get the configured logging destination
     *
     * @return string The logging destination ('database' or 'stdout')
     */
    public function getLoggingDestination(): string
    {
        // Use instance method
        $configVars = $this->configService->readConfigVars(0, true);

        // Return the destination
        return $configVars['logging_destination'] === "database" ? "database" : "stdout";
    }


    /**
     * Log data to database or stdout
     *
     * @param string $whatToLog The message to log
     * @param string $tag Optional tag for categorizing logs
     * @return void Whether the logging was successful
     * @throws \Exception
     */
    public function dataLog(string $whatToLog = "", string $tag = ''): bool
    {
        // Early return if logging is disabled
        if (!$this->isLoggingEnabled()) {
            return true;
        }
        try {
            // Sanitize inputs
            $filteredLogItem = $this->utilities->sanitizeStringInput($whatToLog);
            $filteredLogTag = $this->utilities->sanitizeStringInput($tag);

            // Get logging destination
            $destination = $this->getLoggingDestination();

            if ($destination === 'database') {
                $connection = $this->getConnection();
                // Get connection only when needed (database logging)

                // Log to database
                $insert_stmt = $connection->prepare("
                INSERT INTO public.function_log (logged_element, logged_date, tag) 
                VALUES (:logged_element, NOW(), :tag)
            ");
                $insert_stmt->bindValue(":logged_element", $filteredLogItem);
                $insert_stmt->bindValue(":tag", $filteredLogTag);
                $insert_stmt->execute();
            } else if ($destination === 'stdout') {
                // Log to STDOUT
                $timestamp = date('Y-m-d H:i:s');
                $logMessage = "[$timestamp]";

                if (!empty($filteredLogTag)) {
                    $logMessage .= " [$filteredLogTag]";
                }

                $logMessage .= ": $filteredLogItem" . PHP_EOL;

                // Write to standard output
                fwrite(STDOUT, $logMessage);
            }

            return true;
        } catch (\Exception $e) {
            // Consider logging the error itself to a fallback location
            error_log("Error in dataLog: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Read logs from the function_log table
     *
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array The logs matching the criteria
     * @throws \Exception If there's an error reading the logs
     */
    public function readLogs(bool $isRaw = false): array
    {
        try {
            // Get the database connection from the DatabaseManager
            $connection = $this->getConnection();

            // Prepare and execute the query
            $query = "SELECT * FROM function_log ORDER BY id DESC LIMIT 500";
            $stmt = $connection->prepare($query);
            $stmt->execute();

            $resStat = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = $stmt->rowCount();
            $result = [];

            // Process results
            foreach ($resStat as $row) {
                $res = [
                    "id" => $row["id"],
                    "logged_element" => $row["logged_element"],
                    "tag" => $row["tag"]
                ];

                // Convert from UTC to America/New_York
                $originalTimezone = new DateTimeZone('UTC');
                $targetTimezone = new DateTimeZone('America/New_York');

                $incomingTime = $row['logged_date'];
                $datetime = new DateTime($incomingTime, $originalTimezone);
                $datetime->setTimezone($targetTimezone);
                $convertedStarttime = $datetime->format('Y-m-d h:i:s a');

                $res["logged_date"] = $convertedStarttime;

                $result[] = $res;
            }

            // Return raw or formatted data
            return $this->formatResponse($result, $count, $isRaw);
        } catch (PDOException $e) {
            $this->logException($e, "Error reading logs");
            throw new \Exception("Failed to read logs: " . $e->getMessage());
        }
    }

    /**
     * Retrieves import logs from the database
     *
     * @param string $type The type of import logs to retrieve, or 'all' for all logs
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array The import logs data
     * @throws \PDOException If a database error occurs
     */
    public function readImportLogs(string $type = 'all', bool $isRaw = false): array
    {
        try {
            $connection = $this->getConnection();

            if ($type === 'all') {
                $query = "SELECT * FROM import_log ORDER BY id DESC LIMIT 800";
                $stmt = $connection->prepare($query);
            } else {
                $query = "SELECT * FROM import_log WHERE import_type = :type ORDER BY id DESC LIMIT 800";
                $stmt = $connection->prepare($query);
                $stmt->bindParam(":type", $type, \PDO::PARAM_STR);
            }

            $stmt->execute();
            $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $count = $stmt->rowCount();
            $result = [];

            foreach ($records as $row) {
                // Create timezone objects
                $originalTimezone = new \DateTimeZone('UTC');
                $targetTimezone = new \DateTimeZone('America/New_York');

                // Convert the timestamp to the target timezone
                $incomingTime = $row['import_date'];
                $datetime = new \DateTime($incomingTime, $originalTimezone);
                $datetime->setTimezone($targetTimezone);
                $convertedStarttime = $datetime->format('Y-m-d h:i:s a');

                $result[] = [
                    "id" => $row["id"],
                    "import_type" => $row["import_type"],
                    "import_data" => json_decode($row["import_data"]),
                    "import_date" => $convertedStarttime
                ];
            }

            // Return raw or formatted data
            return $this->formatResponse($result, $count, $isRaw);
        } catch (\PDOException $e) {
            // Log the error and rethrow
            $this->logException($e,'Database error when retrieving import logs: ' . $e->getMessage());
            throw $e;
        }
    }

}
