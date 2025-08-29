<?php
// Load the bootstrap file which sets up the application
$dbManager = require_once __DIR__ . '/../bootstrap.php';

require_once(__DIR__ . '/../saml-settings.php');
if (!isset($settingsInfo) || empty($settingsInfo)) {
    error_log('SAML settings not found or empty');
    die('SAML configuration error. Please contact the administrator.');
}
try {
    // Initialize the Auth object with settings
    //if ($settingsInfo) {
        $auth = new OneLogin\Saml2\Auth($settingsInfo);

        $auth->processResponse();
        $samlSessionIndex = $auth->getSessionIndex();
        // SAML session ids have an "_" at the beginning - we must remove that to set the php session id.
        //$samlSessionSubstr = ltrim($samlSessionIndex, '_');
        //session_id($samlSessionSubstr);
        // Get database connection from DatabaseManager
        $security = new App\Security\Security($dbManager);
        $errors = $auth->getErrors();
        if($errors){
            foreach($errors as $error){
                error_log("SAML error: ".$error);
            }
        }
        if ($auth->isAuthenticated()) {
            function _($str)
            {
                return $str;
            }

            if (isset($_SESSION) && isset($_SESSION['AuthNRequestID'])) {
                $requestID = $_SESSION['AuthNRequestID'];
            } else {
                $requestID = null;
            }
            unset($_SESSION['AuthNRequestID']);
            $_SESSION['samlUserdata'] = $auth->getAttributes();
            $_SESSION['samlNameId'] = $auth->getNameId();
            $_SESSION['samlNameIdFormat'] = $auth->getNameIdFormat();
            $_SESSION['samlNameidNameQualifier'] = $auth->getNameIdNameQualifier();
            $_SESSION['samlNameidSPNameQualifier'] = $auth->getNameIdSPNameQualifier();
            $_SESSION['samlSessionIndex'] = $samlSessionIndex;
            $attributes = $_SESSION['samlUserdata'];
            $_SESSION['userid'] = $attributes['urn:oid:0.9.2342.19200300.100.1.1'][0];
            $_SESSION['cn'] = $attributes['urn:oid:2.5.4.42'][0];
            $_SESSION['sn'] = $attributes['urn:oid:2.5.4.4'][0];
            $_SESSION['auth_groups'] = $attributes['urn:oid:1.3.6.1.4.1.4447.1.75'];
            $nameId = $_SESSION['samlNameId'];
            error_log("SAML notice: ".$_SESSION['userid']." has authenticated.");
            if (isset($_POST['RelayState']) && OneLogin\Saml2\Utils::getSelfURL() != $_POST['RelayState']) {
                // To avoid 'Open Redirect' attacks, before execute the
                // redirection confirm the value of $_POST['RelayState'] is a // trusted URL.
                //error_log("SAML RelayState: ".$_POST['RelayState']);
                //error_log("SAML notice: Redirecting ".$_SESSION['userid']." to admin page..");
                //$userdata = $security->checkAuthorized();
                //if($userdata['status'] == 'authorized') {
                    //error_log("User is authorized, sending to admin...");
                if($auth->isAuthenticated()){
                    error_log("SAML notice: ".$_SESSION['userid']." is authenticated. Checking authorizations..");
                    $userdata = $security->checkAuthorized();
                    if($userdata['status'] == 'authorized') {
                        error_log("SAML notice: ".$_SESSION['userid']." is authorized. Sending to Admin..");
                        $auth->redirectTo("/admin/index.php");
                        exit();
                    }
                }
                //header("Location: index.php");

                //} else {
                    //error_log("User is not authorized, sending to main page...");
                    //header("Location: index.php");
                   // exit();
                //}
                //echo("RelayState: ".$_POST['RelayState']);
            } else {
                error_log("SAML No RelayState. Sending to root url.");
                $auth->redirectTo('/');
            }
        } else {
            echo ("<h3> You are not authenticated. <a href='login.php'>Please login here.</a>");
            exit;
        }
    //} else {
    //    // If the file doesn't exist, log an error
    //    error_log('SAML settings not found');
    //    die('SAML configuration error. Please contact the administrator.');
    //}


} catch (OneLogin\Saml2\Error $e) {
    // If there is an error, log it
    error_log("SAML: ".$e->getMessage().". Redirected user to index page.");
    header("Location: /");
    //echo ("<h3> You are not authenticated. <a href='login.php'>Please login here.</a>");

}

/*
echo '<h1>Identified user: '. htmlentities($nameId) .'</h1>';
if (!empty($attributes)) {
    echo '<h2>' . _('User attributes:') . '</h2>';
    echo '<table><thead><th>' . _('Name') . '</th><th>' . _('Values') . '</th></thead><tbody>';
    foreach ($attributes as $attributeFriendlyName => $attributeValues) {
        echo '<tr><td>' . htmlentities($attributeFriendlyName) . '</td><td><ul>';
        foreach ($attributeValues as $attributeValue) {
            echo '<li>' . htmlentities($attributeValue) . '</li>';
        }
        echo '</ul></td></tr>';
    }
    echo '</tbody></table>';
} else {
    echo _('No attributes found.');
}
echo("<br />Post->RelayState ".$_POST['RelayState']);
echo("<br />samlSessionIndex ".$_SESSION['samlSessionIndex']);
echo("<br />samlNameId ".$_SESSION['samlNameId']);
*/