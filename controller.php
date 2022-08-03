<?php

use DisplayEmailsController as GlobalDisplayEmailsController;

require_once './imap_client.php';
require_once './sqlite_connector.php';

function render_error($status, $msg)
{
    @http_response_code($status);
    die("{'result': 'error', 'error': '$msg'}");
}

class AddPanelController
{
}

class DisplayEmailsController
{
    public static function matches()
    {
        // return !isset($_GET['action']) && !empty($_SERVER['QUERY_STRING'] ?? '');
        return !isset($_GET['action']) && !empty($_GET['address'] ?? '');
    }

    public static function invoke(ImapClient $imapClient, array $config, DatabaseController $databaseClient)
    {
        $address = $_GET['address'] ?? '';
        $filter_network = $_GET["network"] ?? '';
        $filter_from_name = $_GET['from_name'] ?? '';
        $action = $_GET['mode'] ?? '';
        $user = User::parseDomain($address, $config['blocked_usernames']);
        if ($user->isInvalid($config['domains'])) {
            RedirectToRandomAddressController::invoke($imapClient, $config, '');
            return;
        }

        if ($action != 'add_filter') {
            DisplayEmailsController::initialLoad($imapClient, $databaseClient, $config, $user);
            // DisplayEmailsController::render(array(), $config, $user, false);
            return;
        }

        $filter_criteria = '';
        $is_filter_network = true;
        $panel_name = '';
        if (strlen($filter_from_name) > 0) {
            $is_filter_network = false;
            $panel_name = $filter_from_name;
            $filter_criteria = 'FROM "' . $filter_from_name . '"';
        } else if (strlen($filter_network)) {
            $panel_name = $filter_network;
            $filter_criteria = 'FROM "' . $filter_network . ' "';
        }
        // first find existing panel
        $panel_id = 0;
        if ($id = $databaseClient->getPanelIdWithPanelName($panel_name)) {
            $panel_id = $id;
        } else { // insert new panel
            $databaseClient->insertPanelData($panel_name, $user->address, $is_filter_network);
            $inserted_panel_id = $databaseClient->lastInsertedId();
            if ($inserted_panel_id == 0) {
                DisplayEmailsController::render(array(), $config, $user, false);
                return;
            }
            $panel_id = $inserted_panel_id;
        }

        $emails = $imapClient->get_emails($user, $filter_criteria);

        if (!$is_filter_network) {
            $new_emails = array_filter($emails, function ($email) use ($filter_from_name) {
                return $email->fromName == $filter_from_name;
            });
            $emails = $new_emails;
        }
        // insert emails into the database : not overwrite , skip for existing emai id
        $databaseClient->insertEmailData($emails, $user->address, $panel_id);
        // generate rss feed from database or direct emails from gmail
        $databaseClient->generateRssFeed($user->address, $emails, false, $panel_id);
        DisplayEmailsController::initialLoad($imapClient, $databaseClient, $config, $user);
        // get all panel emails from the database ?
        $case = false;
        /*
        if ($case) {
            // read array from xml file
            $file = "rss/" . strtolower(strstr($address, '@', true)) . ".xml";
            if (file_exists($file)) {
                $get = file_get_contents($file);
                $arr = simplexml_load_file($file);
                // Convert into json
                $con = json_encode($arr);

                // // Convert into associative array
                $newArr = json_decode($con, true);
                if (isset($newArr["channel"]["items"]) && count($newArr["channel"]["items"]) > 0) {
                    $itemArray = $newArr["channel"]["items"]["item"];
                    if (array_key_exists("id", $itemArray)) {
                        DisplayEmailsController::render(array($itemArray), $config, $user, $case);
                    } else
                        DisplayEmailsController::render($newArr["channel"]["items"]["item"], $config, $user, $case);
                } else DisplayEmailsController::render(array(), $config, $user, $case);
            } else
                DisplayEmailsController::render(array(), $config, $user, $case);
        } else {
            DisplayEmailsController::render($emails, $config, $user, $case, $filter_from_name, $filter_network, strstr($address, '@', true) . ".xml");
        }
        */
    }

    public static function initialLoad($imapClient, $databaseClient, $config, $user)
    {

        $panels = $databaseClient->getEmailsOfPanelsWithUser($user->address);
        
        // get emails from sqlite database when initial rendering
        $new_panels = array();
        foreach ($panels as $_panel) {
            # code...
            $panel = (object)$_panel;
            $filter_criteria = '';
            $is_filter_network = $panel->is_network;
            $filter_criteria = 'FROM "' . $panel->panel_name . '"';

            $emails = $imapClient->get_emails($user, $filter_criteria);
            if (!$is_filter_network) {
                $new_emails = array_filter($emails, function ($email) use ($panel) {
                    return $email->fromName == $panel->panel_name;
                });
                $emails = $new_emails;
            }

            $panel->emails = $emails ?? [];
            // insert emails into the database : not overwrite , skip for existing emai id
            $databaseClient->insertEmailData($emails, $user->address, $panel->id);
            // generate rss feed from database or direct emails from gmail
            $databaseClient->generateRssFeed($user->address, $emails, false, $panel->id);
            $new_panels[] = $panel;
        }

        DisplayEmailsController::_render($new_panels, $config, $user);
    }

    public static function _render($panels, $config, $user)
    {
        // variables that have to be defined here for frontend template: $emails, $config
        require "frontend.template_2.php";
    }

    public static function render($emails, $config, $user, $option, $filter_from_name = '', $filter_network = '', $rss_name = '')
    {
        // variables that have to be defined here for frontend template: $emails, $config
        if ($option)
            require "frontend.template.php";
        else
            require "frontend.template_2.php";
    }

    public static function rssFeedName()
    {
    }

    public static function xml2array($xmlObject, $out = array())
    {
        foreach ((array) $xmlObject as $index => $node)
            $out[$index] = (is_object($node)) ? DisplayEmailsController::xml2array($node) : $node;

        return $out;
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
        header("location: ?address=$address");
    }
}

class RedirectToRandomAddressController
{
    public static function matches()
    {
        return ($_GET['action'] ?? null) === 'random';
    }

    public static function invoke(ImapClient $imapClient, array $config, $ip)
    {
        $address = User::get_random_address($config['domains'], $ip);
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
        // $databaseClient->insertEmailData($emails, $user->address);
        // $databaseClient->generateRssFeed($user->address);

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

class DeletePanelController
{
    public static function matches()
    {
        return ($_GET['action'] ?? null) === "delete_panel"
            && isset($_GET['panel_id'])
            && isset($_GET['address']);
    }

    public static function invoke(ImapClient $imapClient, DatabaseController $databaseClient, array $config)
    {
        $panel_id = $_GET['panel_id'];
        $address = $_GET['address'];

        $user = User::parseDomain($address, $config['blocked_usernames']);
        if ($user->isInvalid($config['domains'])) {
            RedirectToRandomAddressController::invoke($imapClient, $config, '');
            return;
        }

        $delete_panel_id = filter_var($panel_id, FILTER_SANITIZE_NUMBER_INT);
        if ($databaseClient->deletePanel($delete_panel_id, $user)) {
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

class xml_utils
{

    /*object to array mapper */
    public static function objectToArray($object)
    {
        if (!is_object($object) && !is_array($object)) {
            return $object;
        }
        if (is_object($object)) {
            $object = get_object_vars($object);
        }
        return array_map('objectToArray', $object);
    }

    /* xml DOM loader*/
    public static function xml_to_array($xmlstr)
    {
        $doc = new DOMDocument();
        $doc->loadXML($xmlstr);
        return xml_utils::dom_to_array($doc->documentElement);
    }

    /* recursive XMl to array parser */
    public static function dom_to_array($node)
    {
        $output = array();
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;
            case XML_ELEMENT_NODE:
                for ($i = 0, $m = $node->childNodes->length; $i < $m; $i++) {
                    $child = $node->childNodes->item($i);
                    $v = xml_utils::dom_to_array($child);
                    if (isset($child->tagName)) {
                        $t = xml_utils::ConvertTypes($child->tagName);
                        if (!isset($output[$t])) {
                            $output[$t] = array();
                        }
                        $output[$t][] = $v;
                    } elseif ($v) {
                        $output = (string) $v;
                    }
                }
                if (is_array($output)) {
                    if ($node->attributes->length) {
                        $a = array();
                        foreach ($node->attributes as $attrName => $attrNode) {
                            $a[$attrName] = xml_utils::ConvertTypes($attrNode->value);
                        }
                        $output['@attr'] = $a;
                    }
                    foreach ($output as $t => $v) {
                        if (is_array($v) && count($v) == 1 && $t != '@attr') {
                            $output[$t] = $v[0];
                        }
                    }
                }
                break;
        }
        return $output;
    }

    /* elements converter */
    public static function ConvertTypes($org)
    {
        if (is_numeric($org)) {
            $val = floatval($org);
        } else {
            if ($org === 'true') {
                $val = true;
            } else if ($org === 'false') {
                $val = false;
            } else {
                if ($org === '') {
                    $val = null;
                } else {
                    $val = $org;
                }
            }
        }
        return $val;
    }
}
