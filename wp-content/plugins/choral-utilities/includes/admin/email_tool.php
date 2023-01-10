<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

/** Provides Consort Emails Tool
 * Admin tool to automate the preparation of
 * Friend and Member email content.  The content
 * is then copied into a mail app (like Consort's Gmail) for sending.
 */
class CuEmailTool
{
    private $options;

    public function __construct()
    {
        // Defaults
        $this->options = array(
            'consort-inc' => true,
            'singer-inc' => true,
            'other-inc' => true,
            'consort-events' => 30,
            'singer-events' => 7,
            'other-events' => 30,
            'consort-news' => 7,
            'singer-news' => 7,
            'other-news' => 7
        );
    }

    // ************* CREATE ADMIN TOOLS PAGE **************

    public function create_page()
    {
        add_management_page(
            'Consort Emails',
            'Consort Emails',
            'email_multiple_users',  // Doesn't really need this, but something is required
            'cu-email-tool',
            array($this, 'render_page'));
    }

    // Render Admin's Email Tools Page
    public function render_page()
    {
        $this->renderHtml();
        $this->renderJS();
    }

    private function renderHtml()
    {
        // Page Header
        echo '
<h1>Create Emails for Consort Chorale</h1>';

        // Add Articles Form
        echo '
<div class="settings">
    <div class="form">
        <form id="email-pars">
            <table class="email-form">
                <tr>
                    <td colspan="4" class="title">
                        Use this form to add posted News articles and Events
                        to your email. This will replace any text 
                        already in the email. Then Copy/Paste it into
                        an email app\'s compose new message.
                        <br>Select which categories to include ...
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td><b>About<br>Consort</b></td>
                    <td><b>Current<br>season</b></td>
                    <td><b>Other<br>groups</b></td>
                </tr>
                <tr>
                    <td class="label">Include news and events for: </td>
                    <td><input type="checkbox" name="consort-inc" ' . ($this->options['consort-inc'] ? 'checked' : '') . '></td>
                    <td><input type="checkbox" name="singer-inc" ' . ($this->options['singer-inc'] ? 'checked' : '') . '></td>
                    <td><input type="checkbox" name="other-inc" ' . ($this->options['other-inc'] ? 'checked' : '') . '></td>
                </tr>
                <tr>
                    <td class="label">Days ahead for upcoming events: </td>
                    <td><input class="data" type="number" name="consort-events" value="'. $this->options['consort-events'] . '" min="0"></td>
                    <td><input class="data" type="number" name="singer-events" value="'. $this->options['singer-events'] . '" min="0"></td>
                    <td><input class="data" type="number" name="other-events" value="'. $this->options['other-events'] . '" min="0"></td>
                </tr>
                <tr>
                    <td class="label">Days back for recent news: </td>
                    <td><input class="data" type="number" name="consort-news" value="'. $this->options['consort-news'] . '" min="0"></td>
                    <td><input class="data" type="number" name="singer-news" value="'. $this->options['singer-news'] . '" min="0"></td>
                    <td><input class="data" type="number" name="other-news" value="'. $this->options['other-news'] . '" min="0"></td>
                </tr>
                <tr>
                    <td colspan="3"><input class="button-secondary" type="submit" value="Replace email text with these articles"></td>
                </tr>
            </table>
        </form>
    </div>
</div>
<div style="padding-right:15px">';

        echo wp_editor('', 'email-content', array(
            'textarea_rows' => 100,
            'teeny' => false,
            'tinymce' => array(
                'content_css' => plugins_url('/../../assets/css/cu_email.css', __FILE__)
            )));

        echo '</div>';

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
                    updateContent("updating ...");
                    cu_js.submitForm();
                });
            });
            
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

        if (!$this->options['consort-inc'] && !$this->options['singer-inc'] && !$this->options['other-inc']) {
            echo '<pre>[You must include at least one of "About Consort", "Other groups," or "Current season"]</pre>';
            wp_die();
        }

        if ($this->options['consort-inc']) {
            echo '<h2>News about Consort</h2>';
            $articles = $this->getPosts('consort');
            $articles .= $this->getEvents('consort');
            if (empty($articles))
                echo '<pre>[Search criteria did not find any articles about Consort]</pre>';
            else
                echo $articles;
        }

        if ($this->options['singer-inc']) {
            echo '<h2>Current season information</h2>';
            $articles = $this->getEvents('singers');
            $articles .= $this->getPosts('singers');
            if (empty($articles))
                echo '<pre>[Search criteria did not find any articles for the Current season]</pre>';
            else
                echo $articles;
        }

        if ($this->options['other-inc']) {
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
        // consort-inc and singer-inc are checkboxes, so they only appear in POST if checked
        $this->options['consort-inc'] = false;
        $this->options['singer-inc'] = false;
        $this->options['other-inc'] = false;
        if (isset($_POST['consort-inc']))
            $this->options['consort-inc'] = true;
        if (isset($_POST['singer-inc']))
            $this->options['singer-inc'] = true;
        if (isset($_POST['other-inc']))
            $this->options['other-inc'] = true;

        if (isset($_POST['consort-events']))
            $this->options['consort-events'] = (int)$_POST['consort-events'];
        if (isset($_POST['singer-events']))
            $this->options['singer-events'] = (int)$_POST['singer-events'];
        if (isset($_POST['other-events']))
            $this->options['other-events'] = (int)$_POST['other-events'];

        if (isset($_POST['consort-news']))
            $this->options['consort-news'] = (int)$_POST['consort-news'];
        if (isset($_POST['singer-news']))
            $this->options['singer-news'] = (int)$_POST['singer-news'];
        if (isset($_POST['other-news']))
            $this->options['other-news'] = (int)$_POST['other-news'];
    }

    private function getPosts($target) {
        if ($target == 'singers') {
            $cats = array('singer-news');
            $days = $this->options['singer-news'];
        } else if ($target == 'consort') {
            $cats = array('consort-news');
            $days = $this->options['consort-news'];
        } else if ($target == 'others') {
            $cats = array('other-news');
            $days = $this->options['other-news'];
        } else {
            return;  // Should be an error
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
            $date = date('Y-m-d', strtotime('+' .$this->options['consort-events'] . ' days'));

        } else {
            $cat = "other-events";
            $date = date('Y-m-d', strtotime('+' .$this->options['other-events'] . ' days'));
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

}
