<?php
/*
input:

User $user - User object
array $config - config array
array $emails - array of emails
*/

require_once './autolink.php';

// Load HTML Purifier
$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('HTML.Nofollow', true);
$purifier_config->set('HTML.ForbiddenElements', array("img"));
$purifier = new HTMLPurifier($purifier_config);

\Moment\Moment::setLocale($config['locale']);

$mailIds = array_map(function ($mail) {
    return $mail->id;
}, $emails);

$mailIdsJoinedString = filter_var(join('|', $mailIds), FILTER_SANITIZE_SPECIAL_CHARS);

// define bigger renderings here to keep the php sections within the html short.
function niceDate($date)
{
    $m = new \Moment\Moment($date, date_default_timezone_get());
    return $m->calendar();
}

function printMessageBodyAsHtml()
{
}

function printMessageBody($email, $purifier)
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
            echo $safeText;
        } else {
            echo $safeHtml;
        }
    } else {
        if ($hasHtml) {
            echo $safeHtml;
        } else {
            echo $safeText;
        }
    }
}

?>


<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/bootstrap/4.1.1/bootstrap.min.css">
    <link rel="stylesheet" href="assets/fontawesome/v5.0.13/all.css">
    <title><?php
            echo $emails ? "(" . count($emails) . ") " : "";
            echo $user->address ?></title>
    <link rel="stylesheet" href="assets/spinner.css">
    <link rel="stylesheet" href="assets/custom.css">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        .form-inline {
            display: flex;
            flex-flow: row wrap;
            align-items: center;
        }

        .form-inline label {
            margin: 5px 10px 5px 0;
        }

        .form-inline input {
            vertical-align: middle;
            background-color: #fff;
            border: 1px solid #ddd;
        }

        .form-inline button {
            padding: 10px 20px;
            background-color: dodgerblue;
            border: 1px solid #ddd;
            color: white;
            cursor: pointer;
        }

        .form-inline button:hover {
            background-color: royalblue;
        }

        @media (max-width: 800px) {
            .form-inline input {
                margin: 10px 0;
            }

            .form-inline {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
    <script>
        var mailCount = <?php echo count($emails) ?>;

        function handleAdd() {

        }
        setInterval(function() {
            var r = new XMLHttpRequest();
            r.open("GET", "?action=has_new_messages&address=<?php echo $user->address ?>&email_ids=<?php echo $mailIdsJoinedString ?>", true);
            r.onreadystatechange = function() {
                if (r.readyState != 4 || r.status != 200) return;
                if (r.responseText > 0) {
                    console.log("There are", r.responseText, "new mails.");
                    document.getElementById("new-content-avalable").style.display = 'block';

                    // If there are no emails displayed, we can reload the page without losing any state.
                    if (mailCount === 0) {
                        location.reload();
                    }
                }
            };
            r.send();

        }, 150000);
    </script>

</head>

<body>


    <div id="new-content-avalable">
        <div class="alert alert-info alert-fixed" role="alert">
            <strong>New emails</strong> have arrived.

            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                <i class="fas fa-sync"></i>
                Reload!
            </button>

        </div>
        <!-- move the rest of the page a bit down to show all content -->
        <div style="height: 3rem">&nbsp;</div>
    </div>

    <header>
        <div class="container">
            <p class="lead ">
                Your disposable mailbox is ready.
            </p>
            <div class="row" id="address-box-normal">
                <div class="col my-address-block">
                    <span id="my-address"><?php echo $user->address ?></span>&nbsp;<button class="copy-button" data-clipboard-target="#my-address">Copy</button>
                </div>
                <div class="col get-new-address-col">
                    <button type="button" class="btn btn-outline-dark" title="Reload page" onclick="location.reload()">
                        <i class="fas fa-magic"></i> Reload
                    </button>
                </div>
                <div class="col get-new-address-col">
                    <button type="button" class="btn btn-outline-dark" title="Reload page" <?php if (empty($rss_name)) echo "disabled"; ?> onclick='window.open("/rss/<?php echo $rss_name; ?>","_blank")'>
                        <i class="fas fa-magic"></i> Show RSS
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form class="form-inline" id="address-box-edit" action="/" method="GET">
                        <input type="hidden" name="mode" value="add_filter">
                        <input type="hidden" name="address" value="<?php echo $user->address; ?>">
                        <select class="form-control input-group-prepend" aria-label="Default select example" name="network">
                            <option value="" <?php if (empty($filter_network)) echo 'selected="selected"'; ?> disabled>What network</option>
                            <option value="Dev King" <?php if ($filter_network == 'Dev King') echo 'selected="selected"'; ?>>Dev King</option>
                            <option value="mingxi quan" <?php if ($filter_network == 'mingxi quan') echo 'selected="selected"'; ?>>mingxi quan</option>
                            <option value="Facebook">Facebook</option>
                        </select>
                        <input type="text" class="form-control ml-2" placeholder="From name" name="from_name" value="<?php echo $filter_from_name; ?>">
                        <div class="input-group-append ml-2">
                            <button type="submit" class="btn btn-primary">Add</button>
                        </div>
                    </form>
                    <div class="pannel-group">
                        <p>Your Panels:</p>
                        <div class="p-1"> 
                            <span class="badge badge-secondary">Dev King</span>
                            <button type="button" class="btn btn-outline-warning btn-sm">Delete</button>
                        </div>
                        <div class="p-1"> 
                            <span class="badge badge-secondary">mingxi quan</span>
                            <button type="button" class="btn btn-outline-warning btn-sm">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="container">

            <div id="email-list" class="list-group">

                <?php
                foreach ($emails as $email) {
                    $safe_email_id = filter_var($email->id, FILTER_VALIDATE_INT); ?>

                    <a class="list-group-item list-group-item-action email-list-item" data-toggle="collapse" href="#mail-box-<?php echo $email->id ?>" role="button" aria-expanded="false" aria-controls="mail-box-<?php echo $email->id ?>">

                        <div class="media">
                            <button class="btn btn-white open-collapse-button">
                                <i class="fas fa-caret-right expand-button-closed"></i>
                                <i class="fas fa-caret-down expand-button-opened"></i>
                            </button>


                            <div class="media-body">
                                <h6 class="list-group-item-heading"><?php echo filter_var($email->fromName, FILTER_SANITIZE_SPECIAL_CHARS) ?>
                                    <span class="text-muted"><?php echo filter_var($email->fromAddress, FILTER_SANITIZE_SPECIAL_CHARS) ?></span>
                                    <small class="float-right" title="<?php echo $email->date ?>"><?php echo niceDate($email->date) ?></small>
                                </h6>
                                <p class="list-group-item-text text-truncate" style="width: 75%">
                                    <?php echo filter_var($email->subject, FILTER_SANITIZE_SPECIAL_CHARS); ?>
                                </p>
                            </div>
                        </div>
                    </a>


                    <div id="mail-box-<?php echo $email->id ?>" role="tabpanel" aria-labelledby="headingCollapse1" class="card-collapse collapse" aria-expanded="true">
                        <div class="card-body">
                            <div class="card-block email-body">
                                <div class="float-right primary">

                                    <a class="btn btn-outline-success btn-sm" role="button" href="<?php echo "mailto:$email->fromAddress?subject=Re:$email->subject&body=Email%20Body%20Text"; ?>">
                                        Reply
                                    </a>
                                    <a class="btn btn-outline-danger btn-sm" role="button" href="<?php echo "?action=delete_email&email_id=$safe_email_id&address=$user->address" ?>">
                                        Delete
                                    </a>
                                </div>
                                <?php
                                printMessageBody($email, $purifier);
                                // htmlspecialchars_decode($email->textHtml);
                                ?>

                            </div>
                        </div>
                    </div>
                <?php
                } ?>

                <?php
                if (empty($emails)) {
                ?>
                    <div id="empty-mailbox">
                        <p>The mailbox is empty. Checking for new emails automatically. </p>
                        <div class="spinner">
                            <div class="rect1"></div>
                            <div class="rect2"></div>
                            <div class="rect3"></div>
                            <div class="rect4"></div>
                            <div class="rect5"></div>
                        </div>
                    </div>
                <?php
                } ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">


            <!--                <select id="language-selection" class="custom-select" title="Language">-->
            <!--                    <option selected>English</option>-->
            <!--                    <option value="1">Deutsch</option>-->
            <!--                    <option value="2">Two</option>-->
            <!--                    <option value="3">Three</option>-->
            <!--                </select>-->
            <!--                <br>-->

            <small class="text-justify quick-summary">
                This is a disposable mailbox service. Whoever knows your username, can read your emails.
                Emails will be deleted after 30 days.
                <a data-toggle="collapse" href="#about" aria-expanded="false" aria-controls="about">
                    Show Details
                </a>
            </small>
            <div class="card card-body collapse" id="about" style="max-width: 40rem">

                <p class="text-justify">This disposable mailbox keeps your main mailbox clean from spam.</p>

                <p class="text-justify">Just choose an address and use it on websites you don't trust and
                    don't
                    want to use
                    your
                    main email address.
                    Once you are done, you can just forget about the mailbox. All the spam stays here and does
                    not
                    fill up
                    your
                    main mailbox.
                </p>

                <p class="text-justify">
                    You select the address you want to use and received emails will be displayed
                    automatically.
                    There is no registration and no passwords. If you know the address, you can read the
                    emails.
                    <strong>Basically, all emails are public. So don't use it for sensitive data.</strong>


                </p>
            </div>

            <p>
                <small>Powered by
                    <a href="#"><strong>disposable-mailbox</strong></a>
                </small>
            </p>
        </div>
    </footer>


    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="assets/jquery/jquery-3.3.1.slim.min.js"></script>
    <script src="assets/popper.js/1.14.3/umd/popper.min.js"></script>
    <script src="assets/bootstrap/4.1.1/bootstrap.min.js"></script>
    <script src="assets/clipboard.js/clipboard.min.js"></script>

    <script>
        var clipboard = new ClipboardJS('[data-clipboard-target]');
        $(function() {
            $('[data-tooltip="tooltip"]').tooltip();
        });

        /** from https://github.com/twbs/bootstrap/blob/c11132351e3e434f6d4ed72e5a418eb692c6a319/assets/js/src/application.js */
        clipboard.on('success', function(e) {
            $(e.trigger)
                .attr('title', 'Copied!')
                .tooltip('_fixTitle')
                .tooltip('show')
                .tooltip('_fixTitle');
            e.clearSelection();
        });
    </script>

</body>

</html>