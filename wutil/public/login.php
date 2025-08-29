<?php
//ob_start();
require_once __DIR__ . '/../bootstrap.php';
require_once('/var/www/html/saml-settings.php');
use App\Container\ServiceContainer;
$container = ServiceContainer::getInstance();
$securityService = $container->get('security');
$auth = new OneLogin\Saml2\Auth($settingsInfo); // Constructor of the SP, loads settings.php
// and advanced_settings.php
if ($auth->isAuthenticated()) {
    error_log("User is already authenticated, checking Authorization...");
    $userdata = $securityService->checkAuthenticationStatus();
    if($userdata['status'] == 'authorized') {
        error_log("User is authorized, sending to admin...");
        header("Location: admin/index.php");
        exit();
    } else {
        error_log("User is not authorized, sending to main page...");
        header("Location: index.php");
        exit();
    }
} else{
    try {
        error_log("User not authenticated, sending to login");
        $auth->login();
    } catch (\OneLogin\Saml2\Error $e) {
        error_log('Exception: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
    }   // Method that sent the AuthNRequest
}
