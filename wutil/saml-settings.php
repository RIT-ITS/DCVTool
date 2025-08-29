<?php

$settings_json = getenv('SAML_SETTINGS'); // Note: Changed hyphen to underscore
if ($settings_json === false) {
    throw new Exception("SAML_SETTINGS environment variable not found");
}

$settingsInfo = json_decode($settings_json, true);
if ($settingsInfo === null && json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception("Failed to parse SAML settings JSON: " . json_last_error_msg());
}

// Make sure the variable is globally accessible
global $settingsInfo;
