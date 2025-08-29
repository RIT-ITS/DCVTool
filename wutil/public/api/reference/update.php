<?php
require_once __DIR__ . '/../../../bootstrap.php';

use App\Controllers\Api\Reference\ReferenceDataController;
use App\Container\ServiceContainer;
// Initialize the ServiceContainer
$container = ServiceContainer::getInstance();
$securityService = $container->get('security');
// Check authentication
$userauth = $securityService->checkAuthenticationStatus();
if($userauth){
    $userData = $securityService->checkAuthorized();
} else {
    $userData = null;
}
// Create and execute the controller with just the container
$controller = new ReferenceDataController($container);
$controller->processRequest();