<?php
// phpinfo();
// print imap_base64("SU1BUCBleHRlbnNpb24gc2VlbXMgdG8gYmUgaW5zdGFsbGVkLiA=");
// check for common errors
if (version_compare(phpversion(), '7.2', '<')) {
    die("ERROR! The php version isn't high enough, you need at least 7.2 to run this application! But you have: " . phpversion());
}
extension_loaded("imap") || die('ERROR: IMAP extension not loaded. Please see the installation instructions in the README.md');


# load php dependencies:
require_once './vendor/autoload.php';
require_once './config_helper.php';
require_once './sqlite_connector.php';
require_once './User.php';
require_once './imap_client.php';
require_once './controller.php';
load_config();

$databaseClient = new DatabaseController($config['db_name'], $config['table_name']);
$imapClient = new ImapClient($config['imap']['url'], $config['imap']['username'], $config['imap']['password']);
$ip = "";
/** */
if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}
/** */
print_r($ip); exit;
if (DisplayEmailsController::matches()) {
    DisplayEmailsController::invoke($imapClient, $config, $databaseClient);
} elseif (RedirectToAddressController::matches()) {
    RedirectToAddressController::invoke($imapClient, $config);
} elseif (RedirectToRandomAddressController::matches()) {
    RedirectToRandomAddressController::invoke($imapClient, $config);
} elseif (DownloadEmailController::matches()) {
    DownloadEmailController::invoke($imapClient, $config);
} elseif (DeleteEmailController::matches()) {
    DeleteEmailController::invoke($imapClient, $config);
} elseif (HasNewMessagesControllerJson::matches()) {
    HasNewMessagesControllerJson::invoke($imapClient, $config, $databaseClient);
} elseif (GenerateRSSFeedController::matches()){
    GenerateRSSFeedController::invoke($imapClient, $config, $databaseClient);
} else {
    // If requesting the main site, just redirect to a new random mailbox.
    RedirectToRandomAddressController::invoke($imapClient, $config);
}


// delete after each request
$imapClient->delete_old_messages($config['delete_messages_older_than']);
