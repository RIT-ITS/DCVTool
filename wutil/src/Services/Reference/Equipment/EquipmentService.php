<?php

namespace App\Services\Reference\Equipment;

use App\Services\Reference\BaseReferenceService;
use App\Database\DatabaseManager;
use App\Utilities\Utilities;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use PDO;
use PDOException;

class EquipmentService extends BaseReferenceService {
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
     * Get equipment map data
     *
     * @param int|null $ptid Optional equipment point ID to filter by
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array Equipment map data
     */
    public function getEquipmentMap(?int $ptid = null, bool $isRaw = false): array
    {
        $whatFunction = "getEquipmentMap";
        $connection = $this->dbManager->getWebCtrlConnection();

        try {
            $sql = "SELECT * FROM equipment_map";
            if ($ptid !== null) {
                $sql .= " WHERE ptid = :ptid";
            }

            $stmt = $connection->prepare($sql);

            if ($ptid !== null) {
                $stmt->bindValue(":ptid", $ptid, PDO::PARAM_INT);
            }

            $stmt->execute();
            $resultSet = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = $stmt->rowCount();

            $result = [];
            foreach ($resultSet as $row) {
                $result[] = [
                    "id" => $row["ptid"],
                    "sysname" => $row["sysname"],
                    "path" => $row["path"],
                    "pointtype" => $row["pointtype"],
                    "uname" => $row["uname"],
                    "description" => $row["description"],
                    "units" => $row["units"],
                    "enabled" => ($row["enabled"]) ? 1 : 0,
                    "enable_path" => $row["enable_path"]
                ];
            }

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
        } catch (PDOException $e) {
            $this->logService->logException($e, "$whatFunction - Database query failed: ", $whatFunction);
            //$this->utilities->returnMsg($e->getMessage(), "Error");
            return [
                "responseHeader" => [
                    "status" => 1,
                    "error" => "Database error"
                ],
                "response" => [
                    "numFound" => 0,
                    "start" => 0,
                    "docs" => []
                ]
            ];
        } catch (\Exception $e) {
            $this->logService->logException($e, "$whatFunction - Unexpected error occurred: ", $whatFunction);
            //$this->utilities->returnMsg($e->getMessage(), "Error");
            return [
                "responseHeader" => [
                    "status" => 1,
                    "error" => "Unexpected error"
                ],
                "response" => [
                    "numFound" => 0,
                    "start" => 0,
                    "docs" => []
                ]
            ];
        }
    }


    /**
     * Add, update or delete an Equipment Map record
     *
     * @param array $data Sanitized data for the Equipment Map record
     * @return bool Success or failure of the operation
     */
    public function addUpdEquipmentMap(array $data): bool
    {
        global $detailedLogging;
        $connection = $this->dbManager->getWebCtrlConnection();
        $inUp = "update";
        $inUpID = "";
        $whatFunction = "addUpdEquipmentMap";

        try {
            $connection->beginTransaction();
            $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

            // Extract sanitized data
            $saniSysname = $data['sysname'];
            $saniPath = $data['path'];
            $saniPointType = $data['pointtype'];
            $saniUname = $data['uname'];
            $saniDescription = $data['description'] !== null ? $data['description'] : null;
            $saniUnits = $data['units'] !== null ? $data['units'] : null;
            $saniEnabled = $data['enabled'] ?? false;
            $saniId = $data['ptid'] ?? null;
            $saniDelete = $data['delete'];
            $deletion = false;
            $result = false;

            if ($saniId !== null && $saniId != 0) {
                if ($saniDelete == 1) {
                    $inUp = "delete";
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - Prepping Delete for id: " . $saniId, $whatFunction);
                    }

                    $query = "DELETE FROM equipment_map WHERE ptid = :id";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);

                    if ($stmt->execute()) {
                        $this->logService->dataLog($whatFunction." - id " . $saniId . " deleted successfully", $whatFunction);
                        $deletion = true;
                    } else {
                        $this->logService->dataLog($whatFunction." - Error Deleting id " . $saniId, $whatFunction);
                        $deletion = false;
                    }
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " so attempting to select a record.", $whatFunction);
                    }

                    $query = "SELECT * FROM equipment_map WHERE ptid = :ptid LIMIT 1";
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':ptid', $saniId, PDO::PARAM_INT);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            if ($data['delete'] == 0) {
                if ($result === false) {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We do not have a ptid, so attempting insert.", $whatFunction);
                    }

                    $inUp = "insert";
                    $query = <<<'SQL'
                    INSERT INTO equipment_map (sysname, path, pointtype, uname, description, units, enabled)
                    VALUES (:sysname, :path, :pointtype, :uname, :description, :units, :enabled);
    SQL;
                    $stmt = $connection->prepare($query);
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have a ptid: " . $saniId . " and something needs updating.", $whatFunction);
                    }

                    $inUp = "update";
                    $query = <<<'SQL'
                    UPDATE equipment_map SET
                        sysname = :sysname,
                        path = :path,
                        pointtype = :pointtype,
                        uname = :uname,
                        description = :description,
                        units = :units,
                        enabled = :enabled
                    WHERE ptid = :ptid;
    SQL;
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':ptid', $saniId, PDO::PARAM_INT);
                }

                $stmt->bindParam(':sysname', $saniSysname, PDO::PARAM_STR);
                $stmt->bindParam(':path', $saniPath, PDO::PARAM_STR);
                $stmt->bindParam(':pointtype', $saniPointType, PDO::PARAM_STR);
                $stmt->bindParam(':uname', $saniUname, PDO::PARAM_STR);
                $stmt->bindParam(':description', $saniDescription, PDO::PARAM_STR);
                $stmt->bindParam(':units', $saniUnits, PDO::PARAM_STR);
                $stmt->bindParam(':enabled', $saniEnabled, PDO::PARAM_STR);
                $stmt->execute();

                // Log changes for update operations
                if ($inUp == "update" && $result !== false) {
                    $updateArray = [
                        'updated_table_id' => $saniId,
                        'user_id' => 0,
                        'updated_table_name' => 'equipment_map',
                        'common_name' => 'Devices'
                    ];

                    $fieldsToCheck = [
                        'sysname' => $saniSysname,
                        'path' => $saniPath,
                        'pointtype' => $saniPointType,
                        'uname' => $saniUname,
                        'description' => $saniDescription,
                        'units' => $saniUnits,
                        'enabled' => $saniEnabled
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
                return false;
            } else {
                $this->utilities->returnMsg("Data " . $inUp . " successful.", "Success");
                return true;
            }
        } catch (PDOException $e) {
            $connection->rollBack();
            $this->logService->logException($e, $whatFunction . " - Data transaction(s) failed. Queries rolled back. Also the following error was generated: ", $whatFunction);
            $this->utilities->returnMsg($e->getMessage(), "Error");
            return false;
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logService->logException($e, $whatFunction . " - Unexpected error occurred: ", $whatFunction);
            $this->utilities->returnMsg($e->getMessage(), "Error");
            return false;
        }
    }


}
