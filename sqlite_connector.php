<?php
require_once './autolink.php';
// Load HTML Purifier
$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('HTML.Nofollow', true);
$purifier_config->set('HTML.ForbiddenElements', array("img"));
$purifier = new HTMLPurifier($purifier_config);

function messageBody($email, $purifier)
{
    global $config;

    // To avoid showing empty mails, first purify the html and plaintext
    // before checking if they are empty.
    $safeHtml = $purifier->purify($email->textHtml);

    $safeText = htmlspecialchars($email->textPlain);
    $safeText = nl2br($safeText);
    $safeText = \AutoLinkExtension::auto_link_text($safeText);

    $hasHtml = strlen(trim($safeHtml)) > 0;
    $hasText = strlen(trim($safeText)) > 0;

    if ($config['prefer_plaintext']) {
        if ($hasText) {
            return $safeText;
        } else {
            return $safeHtml;
        }
    } else {
        if ($hasHtml) {
            return $safeHtml;
        } else {
            return $safeText;
        }
    }
}

class DatabaseController
{
    private $db_name;
    private $table_name;
    private $db;
    private $current_user;
    public function __construct($database_name = "Email.db", $table_name = "emails")
    {
        $this->db_name = $database_name;
        $this->table_name = $table_name;
        $this->createDatabase();
    }
    public function createDatabase()
    {
        // Create a new database, if the file doesn't exist and open it for reading/writing.
        // The extension of the file is arbitrary.
        $this->db = new SQLite3($this->db_name, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);

        // Errors are emitted as warnings by default, enable proper error handling.
        $this->db->enableExceptions(true);
        // Create a table.

        $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->table_name . ' (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            "user_address" VARCHAR,
            "email_id" VARCHAR,
            "safe_email_id" VARCHAR,
            "from_name" VARCHAR,
            "from_address" VARCHAR,
            "subject" VARCHAR,
            "body" VARCHAR,
            "time" DATETIME,
            "panel_id" INTEGER,
            UNIQUE("email_id")
        )');

        $panel_table = "panels";
        
        $this->db->query('CREATE TABLE IF NOT EXISTS '.$panel_table.' (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            "user_address" VARCHAR,
            "panel_name" VARCHAR,
            "is_network" INTEGER,
            UNIQUE("panel_name")
        )');
    }

    public function insertPanelData($filter, $current_user, $is_network) {
        $statement = $this->db->prepare('INSERT OR IGNORE INTO panels ("user_address", "panel_name", "is_network") VALUES (:uaddress, :panel_name, :is_network)');
        $statement->bindValue(':uaddress', $current_user);
        $statement->bindValue(':panel_name', $filter);
        $statement->bindValue(':is_network', $is_network);
        $result = $statement->execute(); // you can reuse the statement with different values
        return $result;
    }

    public function lastInsertedId() {
        return $this->db->lastInsertRowID();
    }

    public function getPanelIdWithPanelName($panel_name) {
        $result = $this->db->querySingle('Select id from  panels  where panel_name="' . SQLite3::escapeString($panel_name) . '"');
        if ($result > 0) return $result;
        else 
        return 0;
    }

    public function getAllPanelsWithUser($user_address) {
        $result = $this->db->query('SELECT * FROM panels  WHERE panels.user_address = "'.SQLite3::escapeString($user_address).'"');
        $array = array();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            # code...
            $array[] = $row;
        }
        return $array;
    }

    public function getEmailsOfPanelsWithUser($user_address) {
        $result = $this->db->query('SELECT count(emails.id) as count, panels.* FROM panels LEFT JOIN emails on panels.id = emails.panel_id WHERE panels.user_address = "'.SQLite3::escapeString($user_address).'" GROUP BY panels.id');
        $array = array();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            # code...
            $array[] = $row;
        }
        return $array;
        //SELECT emails.*,  panels.panel_name as panel_name, panels.id as panel_id FROM emails INNER JOIN panels on panels.id = emails.panel_id WHERE panels.user_address = "moflougs62@venlocal.com"
    }

    public function deletePanel($panel_id, $user) {
        $statement = $this->db->prepare("DELETE FROM panels WHERE id=:panel_id");
        $statement->bindValue(':panel_id', $panel_id);
        $result = $statement->execute();
        if ($result) $this->deleteAssociatedEmails($panel_id, $user);
        return $result;        
    }

    public function deleteAssociatedEmails($panel_id, $user) {
        $statement = $this->db->prepare("DELETE FROM emails WHERE panel_id=:panel_id");
        $statement->bindValue(':panel_id', $panel_id);
        $result = $statement->execute();
        return $result;
    }

    public function insertEmailData($emails = [], $current_user, $panel_id = 0)
    {

        // Load HTML Purifier
        $purifier_config = HTMLPurifier_Config::createDefault();
        $purifier_config->set('HTML.Nofollow', true);
        $purifier_config->set('HTML.ForbiddenElements', array("img"));
        $purifier = new HTMLPurifier($purifier_config);
        $this->current_user = $current_user;

        if (count($emails) > 0) {
            foreach ($emails as $email) {
                $email_id = $email->id;
                $safe_email_id = filter_var($email->id, FILTER_VALIDATE_INT);
                $from_name = filter_var($email->fromName, FILTER_SANITIZE_SPECIAL_CHARS);
                $from_address = filter_var($email->fromAddress, FILTER_SANITIZE_SPECIAL_CHARS);
                $time = $email->date;
                $subject = filter_var($email->subject, FILTER_SANITIZE_SPECIAL_CHARS);
                $body = messageBody($email, $purifier);
                $this->insertRow($this->current_user, $email_id, $safe_email_id, $from_name, $from_address, $subject, $body, $time, $panel_id);
            }
        }
    }

    public function insertRow(
        $user_address,
        $email_id,
        $safe_email_id,
        $from_name,
        $from_address,
        $subject,
        $body,
        $time,
        $panel_id
    ) {
        $statement = $this->db->prepare('INSERT OR IGNORE INTO "' . $this->table_name . '" ("user_address", "email_id", "safe_email_id", "from_name", "from_address", "subject","body", "time", "panel_id") VALUES (:uaddress, :email_id, :safe_email_id, :from_name, :from_address, :subject, :body, :time, :panel_id)');
        $statement->bindValue(':uaddress', $user_address);
        $statement->bindValue(':email_id', $email_id);
        $statement->bindValue(':safe_email_id', $safe_email_id);
        $statement->bindValue(':from_name', $from_name);
        $statement->bindValue(':from_address', $from_address);
        $statement->bindValue(':subject', $subject);
        $statement->bindValue(':body', htmlspecialchars($body));
        $statement->bindValue(':time', $time);
        $statement->bindValue(':panel_id', $panel_id);
        $result = $statement->execute(); // you can reuse the statement with different values
        return $result;
    }

    public function generateRssFeed($address, $emails = array(), $is_from_database = true, $panel_id = 0)
    {

        // Load HTML Purifier
        $purifier_config = HTMLPurifier_Config::createDefault();
        $purifier_config->set('HTML.Nofollow', true);
        $purifier_config->set('HTML.ForbiddenElements', array("img"));
        $purifier = new HTMLPurifier($purifier_config);
        if (!isset($address)) return;
        if (!file_exists('rss')) {
            mkdir('rss', 0777, true);
        }
        $file = "rss/" . strstr($address, '@', true) . "_$panel_id.xml";
        if (file_exists($file)) {
            unlink($file);
        }
        $txt = fopen($file, "w") or die("Unable to open file!");

        // print_r('rss feed'); exit;
        $data = "";
        $data = $data . "<?xml version='1.0' encoding='UTF-8'?>
                <rss version='2.0'>
                <channel>
                <title>Social Suite</title>
                <link>https://www.suite.social</link>
                <description>All-in-one Social Management, Marketing, Monitoring, Messaging and Merchant Platform!</description>
                <language>en-us</language>
                <items>";
        $result = array();
        if ($is_from_database) {
            $result = $this->db->query('Select * from "' . $this->table_name . '" where user_address="' . SQLite3::escapeString($address) . '" ORDER BY id DESC');
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $title = $row["subject"];
                $pubDate = $row["time"];
                $email_id = $row["email_id"];
                $from_name = $row["from_name"];
                $from_address = $row["from_address"];
                $description = $row["body"];

                $data = $data . "  <item>
                        <title>$title</title>
                        <link>https://suite.social/s/freelancer-job</link>
                        <id>$email_id</id>
                        <name>$from_name</name>
                        <address>$from_address</address>
                        <description>$description</description>
                        <pubDate>$pubDate</pubDate>
                        </item>";
            }
        } else {
            foreach ($emails as $email) {
                // var_dump(htmlentities($email->textHtml));exit;
                # code...
                $title = filter_var($email->subject, FILTER_SANITIZE_SPECIAL_CHARS);
                $pubDate = $email->date;
                $email_id = $email->id;
                $from_name = filter_var($email->fromName, FILTER_SANITIZE_SPECIAL_CHARS);
                $from_address = filter_var($email->fromAddress, FILTER_SANITIZE_SPECIAL_CHARS);
                $description = htmlspecialchars(messageBody($email, $purifier));

                $data = $data . "  <item>
                        <title>$title</title>
                        <link>https://suite.social/s/freelancer-job</link>
                        <id>$email_id</id>
                        <name>$from_name</name>
                        <address>$from_address</address>
                        <description>$description</description>
                        <pubDate>$pubDate</pubDate>
                        </item>";
            }
        }
        $data = $data . "</items></channel></rss>";
        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $xml = new SimpleXMLElement($data);
        $dom->loadXML($xml->asXML());
        // echo $dom->saveXML();

        fwrite($txt, $dom->saveXML());
        fclose($txt);

        // header('Content-Description: File Transfer');
        // header('Content-Disposition: attachment; filename=' . basename($file));
        // header('Expires: 0');
        // header('Cache-Control: must-revalidate');
        // header('Pragma: public');
        // header('Content-Length: ' . filesize($file));
        // header("Content-Type: text/plain");
        // readfile($file);
    }

    public function getArrivedEmails()
    {
    }

    public function _generateRssFeed()
    {

        // print_r('rss feed'); exit;
        header("Content-type: text/xml");

        echo "<?xml version='1.0' encoding='UTF-8'?>
                <rss version='2.0'>
                <channel>
                <title>Social Suite</title>
                <link>https://www.suite.social</link>
                <description>All-in-one Social Management, Marketing, Monitoring, Messaging and Merchant Platform!</description>
                <language>en-us</language>";

        $result = $this->db->query('Select * from "' . $this->table_name . '"');

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $title = $row["subject"];
            $pubDate = $row["time"];
            // $link = $row["link"];
            $description = $row["body"];

            echo "  <item>
                    <title>$title</title>
                    <link>https://suite.social/s/freelancer-job</link>
                    <description>&lt;a href=&quot;https://suite.social/s/freelancer-job&quot;&gt; &lt;img src=&quot;https://suite.social/images/job.png&quot;&gt;&lt;/a&gt;$description</description>
                    <pubDate>$pubDate</pubDate>
                    </item>";
        }
        echo "</channel></rss>";
    }

    public function insertSampleData()
    {
        // Insert some sample data.
        //
        // INSERTs may seem very slow in SQLite, which happens when not using transactions.
        // It's advisable to wrap related queries in a transaction (BEGIN and COMMIT),
        // even if you don't care about atomicity.
        // If you don't do this, SQLite automatically wraps every single query
        // in a transaction, which slows everything down immensely.

        $this->db->exec('BEGIN');
        $this->db->query('INSERT INTO "' . $this->table_name . '" ("user_id", "url", "time") VALUES (42, "/test", "2017-01-14 10:11:23")');
        $this->db->query('INSERT INTO "' . $this->table_name . '" ("user_id", "url", "time") VALUES (42, "/test2", "2017-01-14 10:11:44")');
        $this->db->exec('COMMIT');

        // Insert potentially unsafe data with a prepared statement.
        // You can do this with named parameters:

        $statement = $this->db->prepare('INSERT INTO "' . $this->table_name . '" ("user_id", "url", "time")
VALUES (:uid, :url, :time)');
        $statement->bindValue(':uid', 1337);
        $statement->bindValue(':url', '/test');
        $statement->bindValue(':time', date('Y-m-d H:i:s'));
        $statement->execute(); // you can reuse the statement with different values


        // Fetch today's visits of user #42.
        // We'll use a prepared statement again, but with numbered parameters this time:

        $statement = $this->db->prepare('SELECT * FROM "' . $this->table_name . '" WHERE "user_id" = ? AND "time" >= ?');
        $statement->bindValue(1, 42);
        $statement->bindValue(2, '2017-01-14');
        $result = $statement->execute();

        echo ("Get the 1st row as an associative array:\n");
        print_r($result->fetchArray(SQLITE3_ASSOC));
        echo ("\n");

        echo ("Get the next row as a numeric array:\n");
        print_r($result->fetchArray(SQLITE3_NUM));
        echo ("\n");

        // If there are no more rows, fetchArray() returns FALSE.

        // free the memory, this in NOT done automatically, while your script is running
        $result->finalize();


        // A useful shorthand for fetching a single row as an associative array.
        // The second parameter means we want all the selected columns.
        //
        // Watch out, this shorthand doesn't support parameter binding, but you can
        // escape the strings instead.
        // Always put the values in SINGLE quotes! Double quotes are used for table
        // and column names (similar to backticks in MySQL).

        $query = 'SELECT * FROM "' . $this->table_name . '" WHERE "url" = \'' .
            SQLite3::escapeString('/test') .
            '\' ORDER BY "id" DESC LIMIT 1';

        $lastVisit = $this->db->querySingle($query, true);

        echo ("Last visit of '/test':\n");
        print_r($lastVisit);
        echo ("\n");


        // Another useful shorthand for retrieving just one value.

        $userCount = $this->db->querySingle('SELECT COUNT(DISTINCT "user_id") FROM "' . $this->table_name . '"');

        echo ("User count: $userCount\n");
        echo ("\n");


        // Finally, close the database.
        // This is done automatically when the script finishes, though.
        $this->db->close();
    }
}

function sqlite_open($location, $mode)
{
    $handle = new SQLite3($location);
    return $handle;
}
function sqlite_query($dbhandle, $query)
{
    $array['dbhandle'] = $dbhandle;
    $array['query'] = $query;
    $result = $dbhandle->query($query);
    return $result;
}
function sqlite_fetch_array(&$result, $type)
{
    #Get Columns
    $i = 0;
    while ($result->columnName($i)) {
        $columns[] = $result->columnName($i);
        $i++;
    }

    $resx = $result->fetchArray(SQLITE3_ASSOC);
    return $resx;
}

function create_table()
{
    $db = new SQLite3('test.db');

    $db->exec("CREATE TABLE cars(id INTEGER PRIMARY KEY, name TEXT, price INT)");
    $db->exec("INSERT INTO cars(name, price) VALUES('Audi', 52642)");
    $db->exec("INSERT INTO cars(name, price) VALUES('Mercedes', 57127)");
    $db->exec("INSERT INTO cars(name, price) VALUES('Skoda', 9000)");
    $db->exec("INSERT INTO cars(name, price) VALUES('Volvo', 29000)");
    $db->exec("INSERT INTO cars(name, price) VALUES('Bentley', 350000)");
    $db->exec("INSERT INTO cars(name, price) VALUES('Citroen', 21000)");
    $db->exec("INSERT INTO cars(name, price) VALUES('Hummer', 41400)");
    $db->exec("INSERT INTO cars(name, price) VALUES('Volkswagen', 21600)");
}
