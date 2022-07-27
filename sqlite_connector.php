<?php

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
            UNIQUE("email_id")
        )');
    }

    public function insertEmailData($emails = [], $current_user)
    {
        $this->current_user = $current_user;

        if (count($emails) > 0) {
            foreach ($emails as $email) {
                $email_id = $email->id;
                $safe_email_id = filter_var($email->id, FILTER_VALIDATE_INT);
                $from_name = filter_var($email->fromName, FILTER_SANITIZE_SPECIAL_CHARS);
                $from_address = filter_var($email->fromAddress, FILTER_SANITIZE_SPECIAL_CHARS);
                $time = $email->date;
                $subject = filter_var($email->subject, FILTER_SANITIZE_SPECIAL_CHARS);
                $body = $email->textPlain;
                $this->insertRow($this->current_user, $email_id, $safe_email_id, $from_name, $from_address, $subject, $body, $time);
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
        $time
    ) {
        $statement = $this->db->prepare('INSERT OR IGNORE INTO "' . $this->table_name . '" ("user_address", "email_id", "safe_email_id", "from_name", "from_address", "subject","body", "time") VALUES (:uaddress, :email_id, :safe_email_id, :from_name, :from_address, :subject, :body, :time)');
        $statement->bindValue(':uaddress', $user_address);
        $statement->bindValue(':email_id', $email_id);
        $statement->bindValue(':safe_email_id', $safe_email_id);
        $statement->bindValue(':from_name', $from_name);
        $statement->bindValue(':from_address', $from_address);
        $statement->bindValue(':subject', $subject);
        $statement->bindValue(':body', $body);
        $statement->bindValue(':time', $time);
        $result = $statement->execute(); // you can reuse the statement with different values
        return $result;
    }

    public function generateRssFeed()
    {
        header("Content-type: text/xml");

        echo "<?xml version='1.0' encoding='UTF-8'?>
                <rss version='2.0'>
                <channel>
                <title>w3schools.in | RSS</title>
                <link>https://www.w3schools.in/</link>
                <description>Cloud RSS</description>
                <language>en-us</language>";
        $query = 
        while ($row = mysqli_fetch_array($con, $query)) {
                $title = $row["title"];
                $link = $row["link"];
                $description = $row["description"];

            echo "  <item>
                    <title>$title</title>
                    <link>$link</link>
                    <description>$description</description>
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
