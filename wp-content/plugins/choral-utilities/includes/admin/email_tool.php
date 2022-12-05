<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

/** Provides Admin Update Emails Tool
 * Admin tool to automate the preparation of
 * Friend and Member email content.  The content
 * is then copied into MailChimp for sending.
 */
class CuEmailTool
{
    private $options, $members = null, $singers = null, $only_members = null;

    public function __construct()
    {
        // Defaults
        $this->options = array(
            'member-inc' => true,
            'singer-inc' => false,
            'member-events' => 30,
            'singer-events' => 7,
            'member-news' => 7,
            'singer-news' => 7,
            'letterhead' => false
        );
    }

    private function init_emails() {
        if ($this->members === null) {
            $this->members = array();
            $this->singers = array();
            $this->only_members = array();
            $users = get_users();
            foreach ($users as $user) {
                $id = $user->data->ID;

                // Be very protective here - to make sure we don't accidentally send
                // emails to the wrong people.  The only other s2 group, which is
                // not included, are "Inactive" - but also filter out any others.
                $group = get_user_field('s2member_access_label', $id);
                if (!in_array($group, array('Member', 'Singer', 'Web Assistant', 'Board'))) {
                    continue;
                }

                // Flag for Non-Singer in Administrative Notes.  This enables marking of
                // Web Assist or Board members who are not participating in the current season.
                $notes = get_user_field('s2member_notes', $id);
                $non_singer = (strpos($notes, "Non-Singer") !== false);

                if ($group != 'Member' && !$non_singer) { // Singers
                    $this->singers[] = $user->data->user_email;
                }

                // All Singers are also Members.  But need to filter
                // out those who have unsubscribed.
                $unsubscribed = get_user_field('foc_unsubscribe', $id);
                if (!$unsubscribed) {
                    $this->members[] = $user->data->user_email;

                    if ($group == 'Member' || $non_singer)
                        $this->only_members[] = $user->data->user_email;
                }
            }
        }
    }

    // ************* CREATE ADMIN TOOLS PAGE **************

    public function create_page()
    {
        add_management_page(
            'Consort Emails',
            'Consort Emails',
            'access_s2member_level3',
            'cu-email-tool',
            array($this, 'render_page'));
    }

    // Render Admin's Email Tools Page
    public function render_page()
    {
        $this->init_emails();
        $this->renderHtml();
        $this->renderJS();
    }

    private function renderHtml()
    {
        // Page Header
        echo '
<h1>Create and Send Emails from Consort Chorale</h1>';

        // Add Articles Form
        echo '
<div class="settings">
    <div class="form">
        <form id="email-pars">
            <table class="email-form">
                <tr>
                    <td colspan="3" class="title">
                        (Optional) Use this form to add News articles and Events
                        to your email. This should be done first because it will replace any text 
                        already in the email. The text can then be edited below before sending.
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td><b>Members</b></td>
                    <td><b>Singers</b></td>
                </tr>
                <tr>
                    <td class="label">Include news and events for: </td>
                    <td><input type="checkbox" name="member-inc" ' . ($this->options['member-inc'] ? 'checked' : '') . '></td>
                    <td><input type="checkbox" name="singer-inc" ' . ($this->options['singer-inc'] ? 'checked' : '') . '></td>
                </tr>
                <tr>
                    <td class="label">Days ahead for upcoming events: </td>
                    <td><input class="data" type="number" name="member-events" value="'. $this->options['member-events'] . '" min="0"></td>
                    <td><input class="data" type="number" name="singer-events" value="'. $this->options['singer-events'] . '" min="0"></td>
                </tr>
                <tr>
                    <td class="label">Days back for recent news: </td>
                    <td><input class="data" type="number" name="member-news" value="'. $this->options['member-news'] . '" min="0"></td>
                    <td><input class="data" type="number" name="singer-news" value="'. $this->options['singer-news'] . '" min="0"></td>
                </tr>
                <tr>
                    <td colspan="3"><input class="button-secondary" type="submit" value="Replace email text with these articles"></td>
                </tr>
            </table>
        </form>
    </div>';

        // Sending Buttons
        $email = wp_get_current_user()->user_email;
        echo '
    <div class="controls">
        <div class="test-form">
            <div>
                <button class="button-primary" onclick="cu_js.sendTest()"> Send to Individuals </button> &nbsp;to: 
            </div>    
            <div class="test-input">
                <input type="text" id="test-emails" value="' . $email . '">
            </div>
        </div>
        Multiple addresses are delimited with commas. (Can also be used to send a test email to yourself before sending to a group.)
        <div>&nbsp;</div>
        <p><button class="button-primary" onclick="cu_js.sendEmail()"> Send to Group </button> &nbsp;bcc: 
            <span id="email-target">' . count($this->members) . ' Members of Consort </span>
            <br>
            Group: <input type="radio" name="group-inc" value="both" checked>All Members &nbsp;
            <input type="radio" name="group-inc" id="radio-btn-singer" value="singers">Current Singers &nbsp;
            <input type="radio" name="group-inc" value="members">Non-Singers
        </p>
        <div id="response-msg" style="display:hidden"></div>
    </div>
</div>';

        // Email
        echo '
<table class="email-body">
    <tr>
        <td class="label">Format: </td>
        <td><input type="checkbox" id="letterhead" name="letterhead" ' . ($this->options['letterhead'] ? 'checked' : '') . '>
            Use full letterhead (including side-panel.)</td>
    </tr>
    <tr>
        <td class="label">Subject: </td>
        <td><input type="text" id="subject"></td>
    </tr>
    <tr>
        <td class="label">Email:</td>
        <td>';

        echo wp_editor('', 'email-content', array(
            'textarea_rows' => 100,
            'teeny' => false,
            'tinymce' => array(
                'content_css' => plugins_url('/../../assets/css/cu_email.css', __FILE__)
            )));

        echo '
        </td>
    </tr>
</table>';
    }

    private function renderJS()
    {
        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[';

        $js .= '
        function cu_js($) {
            $(document).ready(function () {
                $("#email-pars").submit(function(e) {
                    e.preventDefault();
                    if ($("input[name=\'group-inc\']:checked").val() != "singers"
                        && $("input[name=\'singer-inc\']").is(":checked"))
                    {
                        alert("WARNING: This will add articles intended only for Singers, but the \'Send to\' group includes non-singers.\n\nEither uncheck \'Singers\' here, or change the \'Send to\' group to \'Current Singers\'.");
                        return;
                    }
                    updateContent("updating ...");
                    cu_js.submitForm();
                });
                $("input[name=\'group-inc\']").change(function() {
                    if ($("input[name=\'group-inc\']:checked").val() != "singers")
                    {
                        var content = _getEmailContent();
                        if (content.indexOf("class=\"singer-news") !== -1
                            || content.indexOf("class=\"singer-events") !== -1) 
                        {
                            alert("WARNING: You are expanding the \'Send\' list to include non-Singers while the email contains articles intended only for Singers.\n\nThose articles must be removed first.");
                            $("#radio-btn-singer").prop("checked", true);
                        }
                    } 
                    cu_js.set_count();
                });
                $("input[name=\'only-member-inc\']").change(function() {
                    cu_js.set_count();
                });
            });
            function set_count() {
                switch ($("input[name=\'group-inc\']:checked").val()) {
                case "both":
                    $("#email-target").html("' . count($this->members) . ' Members of Consort");
                    break;
                case "singers":
                    $("#email-target").html("' . count($this->singers) . ' Current Singers");
                    break;
                case "members":
                    $("#email-target").html("' . count($this->only_members) . ' Members only (Non-singers)");
                    break;
                }
            }
            cu_js.set_count = set_count;
            
            function submitForm() {
                var data = { action: "cu_get_email_content" };
                var pars = $("#email-pars").serializeArray();
                for (var i = 0; i < pars.length; i++) {
                    data[pars[i]["name"]] = pars[i]["value"];
                }
                $.post(ajaxurl, data, cu_js.updateContent);
            }
            cu_js.submitForm = submitForm;
            
            function updateContent(contents) {
                var editor = tinymce.get("email-content");
                if (editor && !editor.isHidden()) {
                    editor.setContent(contents);
                } else {
                    $("#email-content").val(contents);
                }
            }
            cu_js.updateContent = updateContent;
            
            function _sendTo(list) {
                showResponse({ success:"pending", data:"Sending emails ..."});
                var data = { 
                     action: "cu_send_to_group",
                     letterhead: $("#letterhead").is(":checked"),
                     target: list,
                     subject: $("#subject").val(),
                     body: _getEmailContent() };
                $.post(ajaxurl, data, cu_js.showResponse);
            }
            
            function _getEmailContent() {
                var body = "";
                var editor = tinymce.get("email-content");
                if (editor && !editor.isHidden()) {
                    body = editor.getContent();
                } else {
                    body = $("#email-content").val();
                }
                return body;
            }
            
            function sendTest() {
                _sendTo($("#test-emails").val());
            }
            cu_js.sendTest = sendTest;
            
            function sendEmail() {
                switch ($("input[name=\'group-inc\']:checked").val()) {
                case "both":
                    var list = "members";
                    var msg = "' . count($this->members) . ' Members!";
                    break;
                case "singers":
                    var list = "singers";
                    var msg = "' . count($this->singers) . ' Current Singers!";
                    break;
                case "members":
                    var list = "only-members";
                    var msg = "' . count($this->only_members) . ' Members only (Non-singers)!";
                    break;
                }
                if (confirm("You are about to send this email to " + msg))
                    _sendTo(list);
                else
                    $("#response-msg").hide();
            }
            cu_js.sendEmail = sendEmail;
            
            function showResponse(response) {
                if (true === response.success) {
                    response.success = "success";
                } else if (false === response.success) {
                    response.success = "error";
                }
                $("#response-msg").attr("class", "cu-" + response.success);
                $("#response-msg").html(response.data);
                $("#response-msg").show();
            }
            cu_js.showResponse = showResponse;
            
        }
        cu_js(jQuery);
        ';

        echo $js.'
        // ]]></script>';
    }

    // ************* AJAX CALLBACK TO FILL CONTENT **************

    // NOTE: when formatting for emails:
    //     CSS styling using classes does not work
    //     <br> gets replaced by <p>&nbsp;</p> - use <div>&nbsp;</div> instead
    //     <p> is evil - adds blank lines where not wanted
    // ALL content must be wrapped in <div> or else the wp_editor
    // will add, and put it inside of <p></p>

    public function return_email_content() {
        $this->getOptions();

        if (!$this->options['member-inc'] && !$this->options['singer-inc']) {
            echo '<pre>[You must include at least one of "Members" or "Singers"]</pre>';
            wp_die();
        }

        if ($this->options['singer-inc']) {
            echo '<h2>Opus 27 News for Singers</h2>';
            $articles = $this->getEvents('singers');
            $articles .= $this->getPosts('singers');
            if (empty($articles))
                echo '<pre>[Search criteria did not find any articles for Singers]</pre>';
            else
                echo $articles;
        }
        if ($this->options['member-inc']) {
            echo '<h2>News about Consort</h2>';
            $articles =  $this->getPosts('consort');
            $articles .= $this->getEvents('consort');
            if (empty($articles))
                echo '<pre>[Search criteria did not find any articles about Consort]</pre>';
            else
                echo $articles;

            echo '<h2>News from other groups</h2>';
            $articles =  $this->getPosts('others');
            $articles .= $this->getEvents('others');
            if (empty($articles))
                echo '<pre>[Search criteria did not find any articles from other groups]</pre>';
            else
                echo $articles;
        }
        wp_die();
    }

    private function getOptions() {
        // member-inc and singer-inc are checkboxes, so they only appear in POST if checked
        $this->options['member-inc'] = false;
        $this->options['singer-inc'] = false;
        if (isset($_POST['member-inc']))
            $this->options['member-inc'] = true;
        if (isset($_POST['singer-inc']))
            $this->options['singer-inc'] = true;

        if (isset($_POST['member-events']))
            $this->options['member-events'] = (int)$_POST['member-events'];
        if (isset($_POST['singer-events']))
            $this->options['singer-events'] = (int)$_POST['singer-events'];
        if (isset($_POST['member-news']))
            $this->options['member-news'] = (int)$_POST['member-news'];
        if (isset($_POST['singer-news']))
            $this->options['singer-news'] = (int)$_POST['singer-news'];
    }

    private function getPosts($target) {
        if ($target == 'singers') {
            $cats = array('singer-news');
            $days = $this->options['singer-news'];
        } else if ($target == 'consort') {
            $cats = array('consort-news');
            $days = $this->options['member-news'];
        } else {
            $cats = array('other-news');
            $days = $this->options['member-news'];
        }
        $date = date('M d, Y', strtotime('-' .$days .' days'));
        // Cannot use Category Name because the query filter works opposite of what
        // we need.  In the hierarchy it returns all the child categories. So 'news'
        // category returns all news items at all levels.
        $args = array(
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'post_status' => 'publish',
            'date_query' => array(array('after' => $date))
        );
        $posts = new WP_Query($args);

        $html = '';
        if($posts->have_posts()) {
            while ($posts->have_posts()) {
                $posts->the_post();

                // This is where we filter categories
                $cat = get_the_category();
                $cat = (is_array($cat) && !empty($cat)) ? $cat[0]->slug : '';
                if (!in_array($cat, $cats))
                    continue;

                $html .= '<div class="' . $cat . ' news-title"><a href="' . get_permalink() .'">' . get_the_title() . '</a></div>';

                if (has_post_thumbnail()) {
                    $url = get_the_post_thumbnail_url(null,'full');
                    $html .= '<div><img style="height:auto;max-width:400px" src="' . $url . '"></div>';
                }

                // Need to get the content directly - to avoid WP messing with its format
                global $post;
                if (!empty($post->post_excerpt))
                    $content = $post->post_excerpt;
                else
                    $content = $post->post_content;
                $content = strip_shortcodes($content);
                $html .= '<div>' . wp_trim_words($content, 50, ' [... <i>click title for more</i>]') . '</div><div>&nbsp;</div>';
            }
            wp_reset_postdata();
        }
        return $html;
    }

    private function getEvents($target)
    {
        $today = date('Y-m-d,', time());
        if ($target == 'singers') {
            $cat = "singer-events";
            $date = date('Y-m-d', strtotime('+' . $this->options['singer-events'] . ' days'));

        } else if ($target == 'consort') {
            $cat = "consort-events";
            $date = date('Y-m-d', strtotime('+' .$this->options['member-events'] . ' days'));

        } else {
            $cat = "other-events";
            $date = date('Y-m-d', strtotime('+' .$this->options['member-events'] . ' days'));
        }

        // Leverage the Event Manager shortcode. Put it into JSON so we can deconstruct each
        // event and reduce them to one entry per event with multiple venues.
        // Note that there is a customization for #_EVENTICALLINK specific to emailing, in
        // chorale_utilities.php
        $event_pars = '
            { "title": "#_EVENTNAME",
              "link": "#_EVENTLINK",
              "venue": [{
                "date": "#_EVENTDATES #_EVENTTIMES"
                {has_location}, "place": "#_LOCATIONNAME, #_LOCATIONTOWN #_LOCATIONSTATE" {/has_location},
                "ical": "#_EVENTICALLINK"
              }],
              {has_image} "image": "#_EVENTIMAGE", {/has_image}
              "excerpt": "#_EVENTEXCERPT" 
            },';
        $event_json = do_shortcode('
            [events_list limit="20" category="' . $cat .'" scope="' . $today . $date .'" 
            format_header="" format_footer=""] ' . $event_pars . ' [/events_list]');

        // Return nothing - if there aren't any events
        if (trim($event_json) == 'No Events')
            return '';

        // Remove line feeds added by the shortcode
        $event_json = '[' . preg_replace('/\s+/', ' ', trim($event_json));

        // JSON doesn't like back-slashes, and Event manager is not escaping them
        // Cannot use addslashes, because we only want to escape backslashes, not quotes.
        $event_json = str_replace('\\','\\\\', $event_json);

        // Replace double quotes found in the #_EVENTLINK  (but not in the #_EVENTIMAGE)
        $event_json = str_replace('href="','href=\'', $event_json);
        $event_json = str_replace('">','\'>', $event_json);

        // Remove the final comma in the array of events, and add the array end.
        $event_json = rtrim($event_json, ',') . ']';

        // Get rid of <p> (added by #_EVENTEXCERPTCUT) - not email friendly
        $event_json = str_replace(array('<p>','</p>'), array('<span>','</span>'), $event_json);

        // Restrict large images
        $event_json = str_replace('<img src', '<img style=\'height:auto;max-width:400px\' src', $event_json);

        // "More" info
        $event_json = str_replace('[&#8230;]', '[&#8230; <i>click title for more</i>]', $event_json);

        $event_list = json_decode($event_json, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            // Disable tags, so they are more readable
            $event_json = str_replace('<img style', 'img style', $event_json);
            $event_json = str_replace('<a href', 'a href', $event_json);
            // Add space between each event
            $event_json = str_replace('},', '},<br><br>', $event_json);

            return '<h1 style="color:red">JSON ERROR: '. json_last_error_msg() .
                '.</h1>An event\'s contents prevented "' . $target . '" events from being processed.<br>' .
                '<i>Note that anchor & img tags have been rendered inoperable by removing their opening bracket.</i><br><br>' .
                '<b style="color:red">SOURCE DATA:</b><br>'. $event_json . '<br><b style="color:red">(End of Source)</b><br><br>';
        }

        // Combine similar events, listing their multiple venues
        $event_redux = array();
        $index = 0;
        foreach($event_list as $event) {
            $found = false;
            foreach ($event_redux as $rIndex => $target) {
                if ($event['title'] == $target['title'] && $event['excerpt'] == $target['excerpt']) {
                    $event_redux[$rIndex]['venue'][] = $event['venue'][0];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $event_redux[$index++] = $event;
            }
        }

        // Now "print" the results
        $events = '';
        foreach($event_redux as $index => $event) {
            $events .= '<div class="' . $cat . ' news-title">' . $event['link'] . '</div>';
            foreach($event['venue'] as $venue) {
                $events .= '<div><b>Date:</b> ' . $venue['date'];
                if (isset($venue['ical'])) {
                    $events .= ' ' . $venue['ical'];
                }
                $events .= '</div>';
                if (isset($venue['place'])) {
                    $events .= '<div><i><b>&nbsp;&nbsp;&nbsp;Place:</b> ' . $venue['place'] . '</i></div>';
                }
            }
            if (isset($event['image'])) {
               $events .= '<div>' . $event['image'] . '</div>';
            }
            $events .= '<div>' . $event['excerpt'] . '</div>';
            $events .= '<div>&nbsp;</div>';
        }
        return $events;
    }

    // ************* AJAX CALLBACK TO SEND EMAIL **************
    // target = 'members', 'friends', or a list of addresses
    //     individual addresses must be WA or higher (spam protection)
    // subject = subject of email
    // body = html of the email body

    public function send_email() {
        $errors = array();
        if (empty($_POST['target'])) {
            $errors[] = 'Missing Test Email address';
        }
        if (empty($_POST['subject'])) {
            $errors[] = 'Missing Subject';
        }
        if (empty($_POST['body'])) {
            $errors[] = 'Email body is empty';
        }
        $count = count($errors);
        if ($count > 0) {
            $msg = $count > 1 ? "ERRORS found: " : "ERROR found: ";
            $msg .= implode(', ', $errors);
            wp_send_json_error($msg);
        }

        $target = $_POST['target'];
        $subject = stripslashes($_POST['subject']);
        $body = stripslashes($_POST['body']);
        $letterhead = !empty($_POST['letterhead']) && $_POST['letterhead'] == 'true';

        $to = array('consortchorale@gmail.com');
        $headers = array();
        if ($target == 'singers') {
            $this->init_emails();
            foreach($this->singers as $email)
                $headers[] = 'Bcc: ' . $email;
        } else if ($target == 'members') {
            $this->init_emails();
            foreach($this->members as $email)
                $headers[] = 'Bcc: ' . $email;
        } else if ($target == 'only-members') {
            $this->init_emails();
            foreach($this->only_members as $email)
                $headers[] = 'Bcc: ' . $email;
        } else {
            // Want to be very careful about accepting random email addresses
            // via the ajax call.
            $targets = explode(',', $target);
            $to = array();
            foreach ($targets as $email) {
                $email = trim($email);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                    wp_send_json_error('ERROR: invalid email address - ' . $email);

                $to[] = $email;
            }
        }

        // DEBUGGING SAFETY NET
        // Uncommenting the following code will effectively disable the "Send Mail"
        // button - returning the list of addresses it would have emailed, instead
        // of actually sending the emails ...
        //
        // Uncomment to only disable Send Mail
        //if (count($headers) > 0)
            // Uncomment to disable
            //wp_send_json_success('WOULD HAVE SENT TO: ' . implode(', ', $to) . ', BCC: ' . implode(', ', $headers));

        if ($letterhead)
            $body = $this->add_letterhead($body);

        // Add the Header and Footer
        $content_header = '<html><head><style>';
        $content_header .= file_get_contents(plugins_url('/../../assets/css/cu_email.css', __FILE__));
        $content_header .= '</style></head><body>';

        $body = $content_header . '<div style="text-align:center">
            <img src="https://www.consortchorale.org/wp-content/uploads/2019/12/CCII-logo-400px.png">
        </div>' .
            $body . '
        <hr>
        <div style="text-align:center;font-size:10px;">
            Consort Chorale Summer Concert<br>
            Sunday, August 13, 2023 with David Dickau<br>
            First Presbyterian Church, 72 Kensington Rd, San Anselmo, California<br>
            <a href="http://www.consortchorale.org">www.consortchorale.org</a><br>
            <br>
            <a title="" style="mso-line-height-rule: exactly;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;" 
                target="_blank" rel="noopener noreferrer" 
                href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&amp;hosted_button_id=24L2GE2SRCPPY">
                <img alt="" style="max-width: 147px;padding-bottom: 0;display: inline !important;vertical-align: bottom;border: 0;
                    height: auto;outline: none;text-decoration: none;-ms-interpolation-mode: bicubic;" class="aolmail_mcnImage" 
                    src="https://www.consortchorale.org/wp-content/uploads/2019/02/PayPal_donate_btn.gif" width="117" align="middle" />
            </a><br>
            Consort Chorale, Inc. is a 501(c)3 charitable organization<br>
            Contributions are tax-deductible to the extent allowed by law, California Tax ID: 81-4094805
        </div></body></html>';
        // Remove newlines
        $body = preg_replace('/\s+/', ' ', $body);

        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        $sent = wp_mail($to, $subject, $body, $headers);
        if ($sent) {
            $count = empty($headers) ? count($to) : count($headers);
            wp_send_json_success('SUCCESS: ' . $count . ' email(s) sent.');
        } else {
            global $phpmailer;
            wp_send_json_error('Mailer returned an error: ' . $phpmailer->ErrorInfo);
        }
    }

    // CC_MOD: Wraps the Consort Letter head around the body.
    // Note - this is hard-coded and must be updated when the official letterhead is updated.
    private function add_letterhead($body) {
        $wrap = '
        <table style="table-layout: fixed; width: 100%;"><tbody><tr>
        <td style="font-family: Georgia; font-size: 10px; color: #800000; width: 125px; text-align:right" valign="top">
            <p><b>Founded in 1994</b></p>
            <p><b> Consort Chorale, Inc.</b><br>P.O. Box 9212<br>San Rafael, CA 94912</p>
            <p>www.consortchorale.org</p>
            <p><b>Board of Directors</b></p>
            <p>Allan Robert Petker<br><i>Artistic Director /<br>Founder</i></p>
            <p>David Raub<br><i>President</i></p>
            <p>Noralee McKersie<br><i>Vice President</i></p>
            <p>Lucinda Ray<br><i>Secretary</i></p>
            <p>Judith Ward<br><i>Treasurer</i></p>
            <p>Jon Curtis<br>Amanda Kreklau<br>Betsy Levine-Proctor</p>
            <p>Ruthann Lovetang<br><i>Past President</i></p>
            <p>Bob Friestad<br><i>Emeritus /<br>Co-Founder</i></p>
            </td>
        <td style="padding-left: 10px;" valign="top">';
        $wrap .= $body;
        $wrap .= '
            </td></tbody></table>';
        return $wrap;
    }
}
