<?php
// src/Database/DatabaseManager.php
namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

class DatabaseManager
{
    private static ?DatabaseManager $instance = null;
    private array $connections = [];
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function getInstance(array $config = null): self
    {
        if (self::$instance === null) {
            if ($config === null) {
                throw new RuntimeException('Configuration must be provided when creating the first instance');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function connection(string $name = 'default'): PDO
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        if (!isset($this->config['connections'][$name])) {
            throw new RuntimeException("Database configuration for '{$name}' not found");
        }

        return $this->makeConnection($name);
    }

    private function makeConnection(string $name): PDO
    {
        $config = $this->config['connections'][$name];

        try {
            $dsn = "{$config['driver']}:host={$config['host']};port={$config['port']};dbname={$config['database']}";
            $conn = new PDO($dsn, $config['username'], $config['password']);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $this->connections[$name] = $conn;
            return $conn;
        } catch (PDOException $e) {
            throw new RuntimeException("Failed to connect to database '{$name}': " . $e->getMessage());
        }
    }
    public function getConnection(): PDO
    {
        return $this->connection('default');
    }
    public function getDefaultConnection(): PDO
    {
        return $this->connection('default');
    }

    public function getWebCtrlConnection(): PDO
    {
        return $this->connection('webctrl');
    }

    public function getWebCtrlMainConnection(): PDO
    {
        return $this->connection('webctrl_main');
    }
}
