<?php
/* This demo shows you how to add a simple signature to an existing PDF
 * document through the QuoVadis Signing and Validation Service.
 */
date_default_timezone_set('Europe/Berlin');
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

// require the autoload class from Composer
require_once('../vendor/autoload.php');

if (file_exists('credentials.php')) {
    // The vars are defined in this file for privacy reason.
    require('credentials.php');
} else {
    // The account id (get in contact with info.ch@quovadisglobal.com to get demo credentials)
    $accountId = '';
    $clientId = '';

    // The password
    $password = '';
    $profile = 'Default';
    $pin = '';
}

// URL to the WSDL file
$wsdl = 'https://services.sealsignportal.com/sealsign/ws/BrokerClient?wsdl';

// Options for the SoapClient instance
$clientOptions = array(
    'stream_context' => stream_context_create(array(
        'ssl' => array(
            'verify_peer' => true,
            'cafile' => __DIR__ . '/cacert.pem',
            'peer_name' => 'services.sealsignportal.com'
        )
    )
));

// create a HTTP writer
$writer = new SetaPDF_Core_Writer_Http('QuoVadis-Signed.pdf');
// let's get the document
$document = SetaPDF_Core_Document::loadByFilename('files/Laboratory-Report.pdf', $writer);

// now let's create a signer instance
$signer = new SetaPDF_Signer($document);
$signer->setAllowSignatureContentLengthChange(false);
$signer->setSignatureContentLength(32750); // standard size by QuoVadis

// set some signature properies
$signer->setLocation($_SERVER['SERVER_NAME']);
$signer->setContactInfo('+01 2345 67890123');
$signer->setReason('testing...');

// create an QuoVadis module instance
$module = new SetaPDF_Signer_QuoVadis_Module($wsdl, $accountId, $password, $clientId, $pin, $profile, $clientOptions);
// login to QuoVadis system
$module->login();

// sign the document with the use of the module
$signer->sign($module);

// logout
$module->logout();