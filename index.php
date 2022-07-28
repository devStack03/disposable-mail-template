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
$strUnique = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR'])) . floor(microtime(true) * 1000);
print_r($strUnique);

function crypto_rand_secure($min, $max)
{
    $range = $max - $min;
    if ($range < 1) return $min; // not so random...
    $log = ceil(log($range, 2));
    $bytes = (int) ($log / 8) + 1; // length in bytes
    $bits = (int) $log + 1; // length in bits
    $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
    do {
        $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
        $rnd = $rnd & $filter; // discard irrelevant bits
    } while ($rnd > $range);
    return $min + $rnd;
}

function getToken($length)
{
    $token = "";
    $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
    $codeAlphabet.= "0123456789";
    $max = strlen($codeAlphabet); // edited

    for ($i=0; $i < $length; $i++) {
        $token .= $codeAlphabet[crypto_rand_secure(0, $max-1)];
    }

    return $token;
}

// echo getToken(10);
/** */
// print_r($ip); exit;
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
