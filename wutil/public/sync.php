<?php
declare(strict_types=1);

// 1. Bootstrap the application
// Include your bootstrap file that initializes the DatabaseManager
require_once __DIR__ . '/../bootstrap.php';

// 2. Set up error handling for production
ini_set('display_errors', '0');
error_reporting(E_ALL);

// 3. Initialize the ServiceContainer
use App\Container\ServiceContainer;
$container = ServiceContainer::getInstance();

// 4. Get services from the container
$utilities = $container->get('utilities');
$tokenValidator = $container->get('tokenValidator'); // Assuming this is in the container
$campusService = $container->get('campusService');
$buildingService = $container->get('buildingService');
$semesterService = $container->get('semesterService');
$uncertaintyService = $container->get('uncertaintyService');
$roomService = $container->get('roomService');
$classService = $container->get('classService');
$zoneService = $container->get('zoneService');
$academicScheduleService = $container->get('academicScheduleService');
$transferService = $container->get('transferService');
$responseService = $container->get('jsonResponseService'); // Assuming this is in the container
$urlService = $container->get('urlService'); // Assuming this is in the container

// 5. Create the controller with dependencies
// You could also add the controller to the ServiceContainer if it's used elsewhere
$controller = $container->get('cronJobController');

// 6. Handle the request
$controller->handleRequest($_POST);

// The JsonResponseService will handle sending the response and exiting
