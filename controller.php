<?php

require_once './imap_client.php';
require_once './sqlite_connector.php';

function render_error($status, $msg)
{
    @http_response_code($status);
    die("{'result': 'error', 'error': '$msg'}");
}

class DisplayEmailsController
{
    public static function matches()
    {
        return !isset($_GET['action']) && !empty($_SERVER['QUERY_STRING'] ?? '');
    }

    public static function invoke(ImapClient $imapClient, array $config, DatabaseController $databaseClient)
    {
        $address = $_SERVER['QUERY_STRING'] ?? '';

        $user = User::parseDomain($address, $config['blocked_usernames']);
        if ($user->isInvalid($config['domains'])) {
            RedirectToRandomAddressController::invoke($imapClient, $config, '');
            return;
        }

        $emails = $imapClient->get_emails($user);
        $databaseClient->insertEmailData($emails, $user->address);
        $databaseClient->generateRssFeed($user->address);

        // read array from xml file
        $file = "rss/" . strstr($address, '@', true) . ".xml";
        $get = file_get_contents($file);
        $arr = simplexml_load_string($get);
        // Convert into json
        $con = json_encode($arr);

        // // Convert into associative array
        $newArr = json_decode($con, true);

        // var_dump($newArr["channel"]["item"]); exit;
        DisplayEmailsController::render($newArr["channel"]["item"], $config, $user);
    }

    public static function render($emails, $config, $user)
    {
        // variables that have to be defined here for frontend template: $emails, $config
        require "frontend.template.php";
    }
}

class RedirectToAddressController
{
    public static function matches()
    {
        return ($_GET['action'] ?? null) === "redirect"
            && isset($_POST['username'])
            && isset($_POST['domain']);
    }

    public static function invoke(ImapClient $imapClient, array $config)
    {
        $user = User::parseUsernameAndDomain($_POST['username'], $_POST['domain'], $config['blocked_usernames']);
        RedirectToAddressController::render($user->username . "@" . $user->domain);
    }

    public static function render($address)
    {
        header("location: ?$address");
    }
}

class RedirectToRandomAddressController
{
    public static function matches()
    {
        return ($_GET['action'] ?? null) === 'random';
    }

    public static function invoke(ImapClient $imapClient, array $config)
    {
        $address = User::get_random_address($config['domains']);
        RedirectToAddressController::render($address);
    }
}

class HasNewMessagesControllerJson
{
    public static function matches()
    {
        return ($_GET['action'] ?? null) === "has_new_messages"
            && isset($_GET['email_ids'])
            && isset($_GET['address']);
    }


    public static function invoke(ImapClient $imapClient, array $config, DatabaseController $databaseClient)
    {
        $email_ids = $_GET['email_ids'];
        $address = $_GET['address'];

        $user = User::parseDomain($address, $config['blocked_usernames']);
        if ($user->isInvalid($config['domains'])) {
            render_error(400, "invalid email address");
        }

        $emails = $imapClient->get_emails($user);
        
        $knownMailIds = explode('|', $email_ids);
        $newMailIds = array_map(function ($mail) {
            return $mail->id;
        }, $emails);

        $onlyNewMailIds = array_diff($newMailIds, $knownMailIds);
        // $newMails = array_filter( $emails, function ($mail) use ($onlyNewMailIds){
        //     if (array_key_exists($mail->id, $onlyNewMailIds))
        //         return $mail;
        // }, ARRAY_FILTER_USE_KEY);

        // foreach ($emails as $key => $value) {
        // foreach ($emails as $email) {
        //     # code...
        //     if (in_array($email->id, $knownMailIds))
        //         unset($email);
        // }
        // print_r($emails);
        $databaseClient->insertEmailData($emails, $user->address);
        $databaseClient->generateRssFeed($user->address);

        HasNewMessagesControllerJson::render(count($onlyNewMailIds));
    }

    public static function render($counter)
    {
        header('Content-Type: application/json');
        print json_encode($counter);
    }
}

class DownloadEmailController
{
    public static function matches()
    {
        return ($_GET['action'] ?? null) === "download_email"
            && isset($_GET['email_id'])
            && isset($_GET['address']);
    }


    public static function invoke(ImapClient $imapClient, array $config)
    {
        $email_id = $_GET['email_id'];
        $address = $_GET['address'];

        $user = User::parseDomain($address, $config['blocked_usernames']);
        if ($user->isInvalid($config['domains'])) {
            RedirectToRandomAddressController::invoke($imapClient, $config, '');
            return;
        }

        $download_email_id = filter_var($email_id, FILTER_SANITIZE_NUMBER_INT);
        $full_email = $imapClient->load_one_email_fully($download_email_id, $user);
        if ($full_email !== null) {
            $filename = $user->address . "-" . $download_email_id . ".eml";
            DownloadEmailController::renderDownloadEmailAsRfc822($full_email, $filename);
        } else {
            render_error(404, 'download error: invalid username/mailid combination');
        }
    }

    public static function renderDownloadEmailAsRfc822($full_email, $filename)
    {
        header("Content-Type: message/rfc822; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        print $full_email;
    }
}

class DeleteEmailController
{
    public static function matches()
    {
        return ($_GET['action'] ?? null) === "delete_email"
            && isset($_GET['email_id'])
            && isset($_GET['address']);
    }

    public static function invoke(ImapClient $imapClient, array $config)
    {
        $email_id = $_GET['email_id'];
        $address = $_GET['address'];

        $user = User::parseDomain($address, $config['blocked_usernames']);
        if ($user->isInvalid($config['domains'])) {
            RedirectToRandomAddressController::invoke($imapClient, $config, '');
            return;
        }

        $delete_email_id = filter_var($email_id, FILTER_SANITIZE_NUMBER_INT);
        if ($imapClient->delete_email($delete_email_id, $user)) {
            RedirectToAddressController::render($address);
        } else {
            render_error(404, 'delete error: invalid username/mailid combination');
        }
    }
}

class GenerateRSSFeedController
{
    public static function matches()
    {
        return ($_GET['action'] ?? null) === "generate_rss_feed" && isset($_GET['address']);
    }

    public static function invoke(ImapClient $imapClient, array $config, DatabaseController $databaseClient)
    {
        if (!isset($_GET['address'])) return;
        $databaseClient->generateRssFeed($_GET['address']);
    }
}
