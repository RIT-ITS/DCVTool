<?php
namespace App\Security;

use App\Database\DatabaseManager;
use App\Utilities\Utilities;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use PDO;
use PDOException;

class Security
{
    private DatabaseManager $dbManager;
    private Utilities $utilities;
    private ConfigService $configService;
    public LogService $logService;
    /**
     * Constructor with DatabaseManager injection
     */
    public function __construct(
        DatabaseManager $dbManager,
        Utilities $utilities = null,
        ConfigService $configService = null,
        LogService $logService = null
    )
    {
        $this->dbManager = $dbManager;
        $this->utilities = $utilities ?? new Utilities($dbManager);
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $this->utilities, $this->configService);
    }

    /**
     * Check if user is authenticated
     */
    public function checkAuthenticationStatus(bool $performRedirect = true): array
    {
        // Get database manager instance
        $dbManager = DatabaseManager::getInstance();

        $detailedSecurityLogging = false;

        $whatFunction = "checkAuthStatus";
        $isValid = false; // assume false - e.g. not authenticated

        if ($detailedSecurityLogging) {
            $this->logService->dataLog($whatFunction . " - starting session checks.", $whatFunction);
        }

        $userdata['status'] = "not authorized";

        // Check required SAML session variables and only set $isValid to true if ALL apply
        if (isset($_SESSION['samlUserdata'])) {
            if (isset($_SESSION['samlNameId'])) {
                if (isset($_SESSION['samlSessionIndex'])) {
                    if (isset($_SESSION['userid'])) {
                        if ($detailedSecurityLogging) {
                            $this->logService->dataLog($whatFunction . " - userid detected. Checking Authorizations.", $whatFunction);
                        }
                        $userdata = self::checkAuthorized();
                        $isValid = true;
                    } else {
                        if ($detailedSecurityLogging) {
                            $this->logService->dataLog($whatFunction . " - userid not detected.", $whatFunction);
                        }
                    }
                } else {
                    if ($detailedSecurityLogging) {
                        $this->logService->dataLog($whatFunction . " - samlSessionIndex not detected.", $whatFunction);
                    }
                }
            } else {
                if ($detailedSecurityLogging) {
                    $this->logService->dataLog($whatFunction . " - samlNameId not detected or not equal to decrypted value.", $whatFunction);
                }
            }
        } else {
            if ($detailedSecurityLogging) {
                $this->logService->dataLog($whatFunction . " - samlUserdata not detected.", $whatFunction);
            }
        }

        if (!$isValid) {
            // Get the current script filename
            $currentScript = basename($_SERVER['SCRIPT_FILENAME']);

            // If the current script is json.php, return empty JSON instead of redirecting
            if ($currentScript === 'json.php') {
                if ($detailedSecurityLogging) {
                    $this->logService->dataLog($whatFunction . " - Authentication failed in json.php, returning empty JSON.", $whatFunction);
                }
                // Return empty array which will be converted to {} when JSON encoded
                return [];
            } else if ($performRedirect) {
                // For other scripts, redirect to login
                header("Location: /");
                exit; // Added exit to prevent further execution
            }
        }

        return $userdata;
    }

    /**
     * Check if user is authorized
     */
    public function checkAuthorized(): array
    {
        global $userRole; // Keep this global if needed across the application

        // Get database manager instance
        $dbManager = DatabaseManager::getInstance();
        $conn = $dbManager->getDefaultConnection();

        $detailedSecurityAuthLogging = true;


        $whatFunction = "checkAuthStatus";
        $thisuid = $this->utilities->sanitizeStringInput($_SESSION['userid']);
        $result = [];

        $query = "SELECT u.*, r.role_name
            FROM users AS u
            LEFT JOIN user_roles AS r ON u.role = r.id
            WHERE u.uid = :uid LIMIT 1";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':uid', $thisuid);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            if ($detailedSecurityAuthLogging) {
                $this->logService->dataLog($whatFunction . " - user " . $result['uid'] . " authorized with role " . $result['role_name'], $whatFunction);
            }
            $result['status'] = "authorized";
            $userRole = $result['role'];
            return $result;
        } else {
            return ['status' => "not authorized"];
        }
    }

    /**
     * Non-static version of checkAuthorized for instance use
     */
    public function isUserAuthorized(): array
    {
        return self::checkAuthorized();
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $roleName): bool
    {
        if (!isset($_SESSION['userid'])) {
            return false;
        }

        $conn = $this->dbManager->getDefaultConnection();
        $userId = $conn->quote($_SESSION['userid']);
        $roleNameSafe = $conn->quote($roleName);

        $query = "SELECT COUNT(*) 
                 FROM users u
                 JOIN user_roles r ON u.role = r.id
                 WHERE u.uid = {$userId} AND r.role_name = {$roleNameSafe}";

        $count = $conn->query($query)->fetchColumn();
        return $count > 0;
    }

    /**
     * Get current user data
     */
    public function getCurrentUser(): ?array
    {
        if (!isset($_SESSION['userid'])) {
            return null;
        }

        $conn = $this->dbManager->getDefaultConnection();
        $userId = $conn->quote($_SESSION['userid']);
        $query = "SELECT u.*, r.role_name
                 FROM users u
                 LEFT JOIN user_roles r ON u.role = r.id
                 WHERE u.uid = {$userId}";

        $result = $conn->query($query)->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Example of using a different connection
     */
    public function getWebCtrlUserData(): ?array
    {
        if (!isset($_SESSION['userid'])) {
            return null;
        }

        // Get the WebCTRL connection instead of default
        $conn = $this->dbManager->getWebCtrlConnection();
        $userId = $conn->quote($_SESSION['userid']);

        $query = "SELECT * FROM webctrl_users WHERE uid = {$userId}";
        $result = $conn->query($query)->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}
