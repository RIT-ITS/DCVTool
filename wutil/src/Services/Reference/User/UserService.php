<?php

declare(strict_types=1);

namespace App\Services\Reference\User;

use App\Services\Reference\BaseReferenceService;
use App\Database\DatabaseManager;
use App\Utilities\Utilities;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use PDO;
use PDOException;

/**
 * Service for handling user-related operations
 */
class UserService extends BaseReferenceService
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
     * Retrieve users from the database
     *
     * @param int|string $userId User ID or 'all' to retrieve all users
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array Array of users or formatted response
     */
    public function readUsers($userId, bool $isRaw = false): array
    {
        $connection = $this->dbManager->getDefaultConnection();

        if ($userId === 'all') {
            $query = "SELECT u.*, r.role_name
                FROM users AS u
                LEFT JOIN user_roles AS r ON u.role = r.id";
            $stmt = $connection->prepare($query);
        } else {
            $query = "SELECT u.*, r.role_name
                FROM users AS u
                LEFT JOIN user_roles AS r ON u.role = r.id
                WHERE u.id = :user_id";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(':user_id', $userId,\PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $count = $stmt->rowCount();
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "first_name" => $row["first_name"],
                "last_name" => $row["last_name"],
                "email" => $row["email"],
                "role_name" => $row["role_name"],
                "role" => $row["role"],
                "uid" => $row["uid"],
            ];
        }

        // Note: readUserRoles() will be implemented later
        // For now, we'll return an empty array
        $userRoles = $this->readUserRoles();

        if ($isRaw) {
            return $result;
        } else {
            // Using the parent formatResponse method with custom additions
            $formattedResponse = $this->formatResponse($result, $count, false);
            $formattedResponse['response']['user_roles'] = $userRoles;
            return $formattedResponse;
        }
    }
    /**
     * Retrieve all user roles from the database
     *
     * @return array Array of user roles with id and role_name
     */
    public function readUserRoles(): array
    {
        $connection = $this->dbManager->getDefaultConnection();

        $query = "SELECT * FROM user_roles";
        $stmt = $connection->prepare($query);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                "id" => $row["id"],
                "role_name" => $row["role_name"]
            ];
        }

        return $result;
    }

    /**
     * Add, update or delete a User record
     *
     * @param array $data Sanitized data for the User record
     * @return bool Success or failure of the operation
     */
    public function addUpdUser(array $data): bool
    {
        global $detailedLogging;
        $connection = $this->dbManager->getDefaultConnection();
        $utilities = new Utilities($this->dbManager);
        $inUp = "update";
        $whatFunction = "addUpdUsers";

        try {
            $connection->beginTransaction();
            $this->logService->dataLog($whatFunction." - Data transaction(s) starting.", $whatFunction);

            // Extract sanitized data
            $saniFirstName = $data["first_name"];
            $saniLastName = $data["last_name"];
            $saniEmail = $data["email"];
            $saniRole = $data["role"];
            $saniUid = $data["uid"];
            $saniId = $data["id"] ?? null;
            $result = false;
            $deletion = false;

            if ($saniId !== null && $saniId != 0) {
                if ($data['delete'] == 1) {
                    $inUp = "delete";
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - Prepping Delete for id: " . $saniId, $whatFunction);
                    }

                    $query = "DELETE FROM users WHERE id = :id";
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

                    $query = "SELECT * FROM users WHERE id = :id LIMIT 1";
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
                    INSERT INTO users (first_name, last_name, email, role, uid)
                    VALUES (:first_name, :last_name, :email, :role, :uid);
    SQL;
                    $stmt = $connection->prepare($query);
                } else {
                    if ($detailedLogging) {
                        $this->logService->dataLog($whatFunction." - We have an id: " . $saniId . " and something needs updating.", $whatFunction);
                    }

                    $inUp = "update";
                    $query = <<<'SQL'
                    UPDATE users SET
                        first_name = :first_name,
                        last_name = :last_name,
                        email = :email,
                        role = :role,
                        uid = :uid
                    WHERE id = :id;
    SQL;
                    $stmt = $connection->prepare($query);
                    $stmt->bindParam(':id', $saniId, PDO::PARAM_INT);
                }

                $stmt->bindParam(':first_name', $saniFirstName, PDO::PARAM_STR);
                $stmt->bindParam(':last_name', $saniLastName, PDO::PARAM_STR);
                $stmt->bindParam(':email', $saniEmail, PDO::PARAM_STR);
                $stmt->bindParam(':role', $saniRole, PDO::PARAM_INT);
                $stmt->bindParam(':uid', $saniUid, PDO::PARAM_STR);
                $stmt->execute();

                // Log changes for update operations
                if ($inUp == "update" && $result !== false) {
                    $updateArray = [
                        'updated_table_id' => $saniId,
                        'user_id' => 0,
                        'updated_table_name' => 'users',
                        'common_name' => 'Users'
                    ];

                    $fieldsToCheck = [
                        'first_name' => $saniFirstName,
                        'last_name' => $saniLastName,
                        'email' => $saniEmail,
                        'role' => $saniRole,
                        'uid' => $saniUid
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
            $utilities->returnMsg($e->getMessage(), "Error");
            return false;
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logService->logException($e, $whatFunction . " - Unexpected error occurred: ", $whatFunction);
            $utilities->returnMsg($e->getMessage(), "Error");
            return false;
        }
    }


}
