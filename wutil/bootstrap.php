<?php
// bootstrap.php - Application initialization

// Load the Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$siteTitle = "DCVTool";
// Load environment variables (if using .env file)
// If you're using vlucas/phpdotenv package:
// $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
// $dotenv->load();

// Load database configuration
$dbConfig = require __DIR__ . '/Config/Database.php';

// Initialize the DatabaseManager with configuration
//$dbManager = App\Database\DatabaseManager::getInstance($dbConfig);

// Set error reporting based on environment
$environment = getenv('APP_ENV') ?: 'production';
if ($environment === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // Already taken care of in Dockerfile
    //error_reporting(0);
    //ini_set('display_errors', 0);
}
// Import the ServiceContainer class
use App\Container\ServiceContainer;
// Initialize the container
$container = ServiceContainer::getInstance();

// Pre-register the database manager
$dbManager = $container->get('dbManager');

// Keep the global $utilities for backward compatibility
$utilities = $container->get('utilities');
$configService = $container->get('configService');
$logService = $container->get('logService');
$semesterService = $container->get('semesterService');

// You can define global helper functions here if needed
function app_path($path = '') {
    return __DIR__ . ($path ? DIRECTORY_SEPARATOR . $path : $path);
}

function config($key = null, $default = null) {
    static $config = [];

    if ($key === null) {
        return $config;
    }

    return $config[$key] ?? $default;
}

// Helper function to get services from the container
function app($service = null) {
    $container = ServiceContainer::getInstance();
    return $service ? $container->get($service) : $container;
}

// Return the DatabaseManager instance so it can be used in the including file if needed
return $dbManager;
