<?php
require_once __DIR__ . '/../bootstrap.php';
require_once('/var/www/html/saml-settings.php');
// Or use the following if installing the toolkit via Composer


//use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Error;

try {
    $auth = new OneLogin\Saml2\Auth($settingsInfo);
    $settings = $auth->getSettings();
    // Now we only validate SP settings
    //$settings = new Settings($settingsInfo, true);
    $metadata = $settings->getSPMetadata(true);
    $errors = $settings->validateMetadata($metadata);
    if (empty($errors)) {
        header('Content-Type: text/xml');
        echo $metadata;
    } else {
        throw new Error(
            'Invalid SP metadata: '.implode(', ', $errors),
            Error::METADATA_SP_INVALID
        );
    }
} catch (Exception $e) {
    echo $e->getMessage();
}