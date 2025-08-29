<?php
// src/Services/Configuration/ConfigService.php

namespace App\Services\Configuration;

use App\Services\Logging\LogService;
use PDO;
use PDOException;
use App\Utilities\Utilities;
use App\Database\DatabaseManager;
use App\Services\Reference\Academic\SemesterService;
use App\Container\ServiceContainer;
class ConfigService
{
    protected DatabaseManager $dbManager;
    protected PDO $conn;
    protected Utilities $utilities;
    protected ?SemesterService $semesterService = null;
    protected ?LogService $logService = null;

    // Static property to hold the cache
    private static array $configCache = [];

    // Static instance for singleton pattern
    private static ?self $instance = null;

    // Get singleton instance - updated to use container
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $container = ServiceContainer::getInstance();
            self::$instance = $container->get('configService');
        }
        return self::$instance;
    }

    public function __construct(
        DatabaseManager $dbManager,
        ?Utilities $utilities = null,
        ?LogService $logService = null,
        ?SemesterService $semesterService = null
    ) {
        $this->dbManager = $dbManager;
        $this->conn = $dbManager->getDefaultConnection();
        $this->utilities = $utilities ?? new Utilities($dbManager);

        // These are now optional and can be set later
        $this->logService = $logService;
        $this->semesterService = $semesterService;
    }

    /**
     * Set the LogService after construction
     */
    public function setLogService(LogService $logService): void
    {
        $this->logService = $logService;
    }

    /**
     * Set the SemesterService after construction
     */
    public function setSemesterService(SemesterService $semesterService): void
    {
        $this->semesterService = $semesterService;
    }
    /**
     * Read configuration variables from the database
     *
     * @param string $type The scope of configuration to retrieve ('all' or specific scope)
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array Configuration variables
     */
    public function readConfigVars(string $type, bool $isRaw = false, ?array $userData = null): array
    {
        // Use cache key based on type
        $cacheKey = "config_" . $type;
        if (isset(self::$configCache[$cacheKey]) && !empty(self::$configCache[$cacheKey])) {
            $result = self::$configCache[$cacheKey];
        } else {
            $conn = $this->dbManager->getDefaultConnection();

            if ($type == 'all') {
                $query = "SELECT * FROM config";
                $stmt = $conn->prepare($query);
            } else {
                $query = "SELECT * FROM config AS c WHERE c.config_scope = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $type);
            }

            $stmt->execute();
            $resStat = $stmt->fetchAll();
            $count = $stmt->rowCount();
            $result = [];

            foreach ($resStat as $row) {
                $result[$row["config_key"]] = $row["config_value"];
            }

            // Cache the results
            self::$configCache[$cacheKey] = $result;
        }

        // Use the injected SemesterService if available
        if ($this->semesterService) {
            $strm = $this->semesterService->determineSemester(true);
        } else {
            // Fallback using container
            $container = ServiceContainer::getInstance();
            $semesterService = $container->get('semesterService');
            $strm = $semesterService->determineSemester(true);
        }

        // Get data about the current user. Only pull if the session is fully formed
        //$userDat = 0;
        //if (isset($_SESSION) && count($_SESSION) > 1) {
        //    $security = new \App\Security\Security($this->dbManager);
        //    $userDat = $security->checkAuthorized(false);
        //}

        $result["current_term"] = $strm;
        if ($userData !== null) {
            $result["current_user"] = $userData;
        }

        if ($isRaw) {
            return $result;
        } else {
            $dataArr = [
                "responseHeader" => [
                    "status" => 0
                ],
                "response" => [
                    "numFound" => count($result) - 2, // Subtract the two added fields
                    "start" => 0,
                    "docs" => $result,
                ]
            ];
            return $dataArr;
        }
    }

    /**
     * Static wrapper for readConfigVars for backward compatibility
     */
    public static function readConfigVarsStatic(string $type, bool $isRaw = false, ?array $userData = null): array
    {
        $container = ServiceContainer::getInstance();
        $configService = $container->get('configService');
        return $configService->readConfigVars($type, $isRaw, $userData);
    }

    /**
     * Get a specific configuration value
     *
     * @param string $key The configuration key
     * @param mixed $default Default value if key not found
     * @return mixed The configuration value or default
     */
    public function getConfigValue(string $key, $default = null)
    {
        // Try to get from all configs
        $configs = $this->readConfigVars('all', true);

        return $configs[$key] ?? $default;
    }


    /**
     * Add or update a configuration variable.
     *
     * @param array $data
     * @return bool
     */
    public function addUpdConfigVars(array $data): bool
    {
        global $detailedLogging;
        $whatFunction = "addUpdConfigVars";
        try {
            $this->conn->beginTransaction();
            // Use instance logService if available
            if ($this->logService) {
                $this->logService->dataLog("$whatFunction - Data transaction(s) starting.", $whatFunction);
            } else {
                // Fallback to container
                $container = ServiceContainer::getInstance();
                $logService = $container->get('logService');
                $logService->dataLog("$whatFunction - Data transaction(s) starting.", $whatFunction);
            }
            $saniId = $data['id'] ?? null;
            $saniKey = $data['config_key'];
            $saniValue = $data['config_value'];
            $saniScope = $data['config_scope'];

            // Check if record exists
            $query = "SELECT * FROM config WHERE config_key = :k";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':k', $saniKey, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                if ($detailedLogging) {
                    if ($this->logService) {
                        $this->logService->dataLog("$whatFunction - Updating existing config for key: $saniKey", $whatFunction);
                    } else {
                        // Fallback to container
                        $container = ServiceContainer::getInstance();
                        $logService = $container->get('logService');
                        $logService->dataLog("$whatFunction - Updating existing config for key: $saniKey", $whatFunction);
                    }
                }
                $inUp = "update";
                $query = "UPDATE config SET config_scope = :config_scope, config_value = :config_value WHERE config_key = :config_key";
            } else {
                if ($detailedLogging) {
                    if ($this->logService) {
                        $this->logService->dataLog("$whatFunction - Inserting new config for key: $saniKey", $whatFunction);
                    } else {
                        // Fallback to container
                        $container = ServiceContainer::getInstance();
                        $logService = $container->get('logService');
                        $logService->dataLog("$whatFunction - Inserting new config for key: $saniKey", $whatFunction);
                    }
                }
                $inUp = "insert";
                $query = "INSERT INTO config (config_key, config_value, config_scope) VALUES (:config_key, :config_value, :config_scope)";
            }

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':config_key', $saniKey, PDO::PARAM_STR);
            $stmt->bindParam(':config_value', $saniValue, PDO::PARAM_STR);
            $stmt->bindParam(':config_scope', $saniScope, PDO::PARAM_STR);
            $stmt->execute();

            // Logging updates if updating
            if ($inUp === "update" && $result) {
                $updateArray = [
                    'updated_table_id' => $result['id'],
                    'user_id' => 0,
                    'updated_table_name' => 'config',
                    'common_name' => 'Settings'
                ];
                if ($result['config_key'] !== $saniKey) {
                    $updateArray['old_value'] = $result['config_key'];
                    $updateArray['new_value'] = $saniKey;
                    $updateArray['column_name'] = 'config_key';
                    if ($this->logService) {
                        $this->logService->logUpdatedVariable($updateArray);
                    } else {
                        // Fallback to container
                        $container = ServiceContainer::getInstance();
                        $logService = $container->get('logService');
                        $logService->logUpdatedVariable($updateArray);
                    }
                }
                if ($result['config_value'] !== $saniValue) {
                    $updateArray['old_value'] = $result['config_value'];
                    $updateArray['new_value'] = $saniValue;
                    $updateArray['column_name'] = 'config_value';
                    if ($this->logService) {
                        $this->logService->logUpdatedVariable($updateArray);
                    } else {
                        // Fallback to container
                        $container = ServiceContainer::getInstance();
                        $logService = $container->get('logService');
                        $logService->logUpdatedVariable($updateArray);
                    }
                }
                if ($result['config_scope'] !== $saniScope) {
                    $updateArray['old_value'] = $result['config_scope'];
                    $updateArray['new_value'] = $saniScope;
                    $updateArray['column_name'] = 'config_scope';
                    if ($this->logService) {
                        $this->logService->logUpdatedVariable($updateArray);
                    } else {
                        // Fallback to container
                        $container = ServiceContainer::getInstance();
                        $logService = $container->get('logService');
                        $logService->logUpdatedVariable($updateArray);
                    }
                }
            }

            $affectedRows = $stmt->rowCount();
            if ($this->logService) {
                $this->logService->dataLog("$whatFunction - $inUp succeeded. It affected $affectedRows row(s) of data.", $whatFunction);
            } else {
                // Fallback to container
                $container = ServiceContainer::getInstance();
                $logService = $container->get('logService');
                $logService->dataLog("$whatFunction - $inUp succeeded. It affected $affectedRows row(s) of data.", $whatFunction);
            }

            $this->conn->commit();
            if ($detailedLogging) {
                if ($this->logService) {
                    $this->logService->dataLog("$whatFunction - Data transaction(s) successful. Committing queries now.", $whatFunction);
                } else {
                    // Fallback to container
                    $container = ServiceContainer::getInstance();
                    $logService = $container->get('logService');
                    $logService->dataLog("$whatFunction - Data transaction(s) successful. Committing queries now.", $whatFunction);
                }
            }
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            if ($this->logService) {
                $this->logService->logException($e,"$whatFunction - Data transaction(s) failed. Queries rolled back. Also the following error was generated", );
            } else {
                // Fallback to container
                $container = ServiceContainer::getInstance();
                $logService = $container->get('logService');
                $logService->logException($e,"$whatFunction - Data transaction(s) failed. Queries rolled back. Also the following error was generated", );
            }
            $this->utilities->returnMsg($e->getMessage(), "Error");
            //return false;
        }
    }
}