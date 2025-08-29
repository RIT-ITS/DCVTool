<?php
require_once __DIR__ . '/../bootstrap.php';
require_once('/var/www/html/saml-settings.php');

// Initialize the Auth object with settings
if ($settingsInfo) {
    // The Auth constructor will load the settings from the default location
    // since your settings file is in the expected path
    $auth = new OneLogin\Saml2\Auth($settingsInfo);
} else {
    // If the file doesn't exist, log an error
    error_log('SAML settings file not found.');
    die('SAML configuration error. Please contact the administrator.');
}
$auth->logout();