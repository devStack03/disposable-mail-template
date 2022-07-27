<!DOCTYPE html>
<html>
<head>
<style>
body {
    font-family: Arial;
}

.list-form-container {
    background: #F0F0F0;
    border: #e0dfdf 1px solid;
    padding: 20px;
    border-radius: 2px;
}

.column {
    float: left;
    padding: 10px 0px;
}

table {
    width: 100%;    
    background: #FFF;
}

td, th {
    border-bottom: 1px solid #dddddd;
    text-align: left;
    padding: 8px;
    width: auto;
}

.content-div {
    position:relative;
}

.content-div span.column {
    width: 90%;
}

.date {
    position: absolute;
    right: 8px;
    padding: 10px 0px;
}
</style>
<title>Gmail Email Inbox using PHP with IMAP</title>
</head>

<body>
    <h1>Gmail Email Inbox using PHP with IMAP</h1>
    <?php
    if (! function_exists('imap_open')) {
        echo "IMAP is not configured.";
        exit();
    } else {
        ?>
    <div id="listData" class="list-form-container">
            <?php
        
        /* Connecting Gmail server with IMAP */
        $connection = imap_open('{imap.gmail.com:993/imap/ssl}INBOX', 'cebikinsweb@gmail.com', 'vtrrpfhcjitxxxpx') or die('Cannot connect to Gmail: ' . imap_last_error());

        /* Search Emails having the specified keyword in the email subject */
        // $emailData = imap_search($connection, 'SUBJECT "Daily "');
        $emailData = imap_search($connection, 'TO "mail@venlocal.com"');
        
        if (! empty($emailData)) {
            ?>
            <table>
            <?php
            foreach ($emailData as $emailIdent) {
                
                $overview = imap_fetch_overview($connection, $emailIdent, 0);
                $message = imap_fetchbody($connection, $emailIdent, '2');
                
                $messageExcerpt = substr($message, 0, 150);
                $partialMessage = trim(quoted_printable_decode($messageExcerpt)); 
                $date = date("d F, Y", strtotime($overview[0]->date));
                ?>
                <tr>
                        <td style="width:15%;"><span class="column"><?php echo $overview[0]->to; ?></span></td>
                        <td class="content-div"><span class="column"><?php print_r($message);?></span><span class="date"><?php echo $date; ?></span></td>
                </tr>
                <?php
            } // End foreach
            ?>
            </table>
            <?php
        } // end if
        
        imap_close($connection);
    }
    ?>
    </div>
</body>
</html>
