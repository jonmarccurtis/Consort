<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 9:31 AM
 *
 * Plugin Name: Choral Utilities
 * Version: 2.2.1
 * Description: Adds Utilities for Choral Groups, including: Membership Roster and Absence Reporting
 * Author: Jon Curtis, Galinas Creek Productions
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class ChoralUtilities {

    public function __construct() {
        // Activation set up DB
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Styles and JS
        add_action('init', array($this, 'init_cu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));

        // Pages via Shortcodes
        add_shortcode('cu_member_list', array($this, 'member_list'));
        add_shortcode('cu_positions_list', array($this, 'positions_list'));
        add_shortcode('cu_member_history', array($this, 'member_history'));
        add_shortcode('cu_singer_roster', array($this, 'singer_roster'));
        add_shortcode('cu_pay_registration', array($this, 'pay_registration'));
        add_shortcode('cu_absence_table', array($this, 'absence_table'));

        // Seasonal Shortcodes
        add_shortcode('cu_concert_tickets', array($this, 'concert_tickets'));
        add_shortcode('cu_lunch_tickets', array($this, 'lunch_tickets'));
        add_shortcode('cu_ws_lunch_list', array($this, 'ws_lunch_list'));
        add_shortcode('cu_solo_auditions', array($this, 'solo_auditions'));

        // Support for Snack Signup page
        add_filter('caldera_forms_render_get_field', array($this, 'snack_signup_dropdown'), 10, 2);
        add_shortcode('cu_snack_list', array($this, 'snack_list'));

        // Other Shortcodes
        add_shortcode('cu_filter', array($this, 'cu_filter'));
        add_shortcode('cu_phpinfo', array($this, 'cu_phpinfo'));

        // Admin pages and AJAX callbacks
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_ajax_cu_get_email_content', array($this, 'get_email_content'));
        add_action('wp_ajax_cu_send_to_group', array($this, 'send_email'));
        add_action('wp_ajax_cu_update_missed_rehearsals', array($this, 'update_missed_rehearsals'));

        // Calendar iCal support - Filter Event Manager's event.ics results
        add_filter('em_calendar_template_args', array($this, 'filter_ical_events'));
        add_shortcode('cu_calendar_subscribe', array($this, 'calendar_subscribe'));
        add_action('wp_ajax_cu_get_ical_url', array($this, 'get_ical_url'));
        add_action('wp_ajax_nopriv_cu_get_ical_url', array($this, 'get_ical_url'));
    }

    /********* ACTIVATION ACTIONS **********/

    public function activate() {
        require_once(plugin_dir_path(__FILE__).'/includes/common/cu_options.php');
        $cu_options = new CuOptions();
        $cu_options->add_default_options_to_db();
    }

    /********* CSS & JS SUPPORT **********/

    public function init_cu() {
        wp_register_style('cu_style', plugins_url('/assets/css/cu_style.css', __FILE__), false, '1.0.0', 'all');
        wp_register_script('cu_tablesorter', plugins_url('/assets/js/jquery.tablesorter.combined.min.js', __FILE__), array('jquery'), '2.28.4');

        // Unused, test code for something similar to the old analytics
        //$this->analytics();
    }

    public function enqueue_styles() {
        wp_enqueue_style('cu_style');
    }

    /********* PAGES VIA SHORTCODES **********/

    /**
     * Member List page
     */
    public function member_list() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/memberlist.php');
        $memberlist = new CuMemberList();
        return $memberlist->html();
    }

    /**
    /**
     * Volunteer Position List page
     */
    public function positions_list() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/positionlist.php');
        $positionlist = new CuPositionList();
        return $positionlist->html();
    }

    /**
     * Member History page
     */
    public function member_history() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/member_history.php');
        $history = new CuMemberHistory();
        return $history->html();
    }

    /**
     * Member Roster page
     */
    public function singer_roster() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/singer_roster.php');
        $roster = new CuSingerRoster();
        return $roster->html();
    }

    /**
     * Member Pay Tuition page
     */
    public function pay_registration() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/pay_registration.php');
        $pay = new CuPayRegistration();
        return $pay->html();
    }

    /**
     * Absence page
     */
    public function absence_table() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/absence_table.php');
        $at = new CuAbsenceTable();
        return $at->html();
    }

    /********* SEASONAL SHORTCODES **********/

    /**
     * Concert Tickets page
     */
    public function concert_tickets() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/concert_tickets.php');
        $pay = new CuConcertTickets();
        return $pay->html();
    }

    /**
     * Fundraiser Lunch Tickets page
     */
    public function lunch_tickets() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/lunch_tickets.php');
        $pay = new CuLunchTickets();
        return $pay->html();
    }

    /**
     * Workshop Lunch List
     */
    public function ws_lunch_list($atts) {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/ws_lunch_list.php');
        $wll = new CuWsLunchList($atts);
        return $wll->html();
    }

    /**
     * Solo Auditions List
     */
    public function solo_auditions($atts) {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/solo_auditions.php');
        $sa = new CuSoloAuditions($atts);
        return $sa->html();
    }

    /**** Support for Snack Signup page ****/

    /**
     * Fill Snack Signup Dropdown (filter callback)
     * Caldera form requirements:
     *    Dropdown slug = snack_signup_dropdown
     *    Name slug = hid_name
     *    static variable 'monday' = date of monday rehearsal
     */
    public function snack_signup_dropdown($field, $form) {
        if ('snack_signup_dropdown' == $field['slug']) {
            require_once(plugin_dir_path(__FILE__).'/includes/frontend/snack_list.php');
            $csl = new CuSnackList();
            $field = $csl->dropdown($field, $form);
        }
        return $field;
    }

    /**
     * Snack List
     * [cu_snack_list id="caldera form id"]
     */
    public function snack_list($atts) {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/snack_list.php');
        $csl = new CuSnackList();
        return $csl->html($atts);
    }


    /********* OTHER SHORTCODES **********/

    /** CU_FILTER shortcode
     *
     * Filter content based on User Position field.
     * Example: [cu_filter position="Members"]<content>[/cu_filter]
     */
    public function cu_filter($atts, $content) {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/filter.php');
        $cond = new CuFilter($atts, $content);
        return $cond->html();
    }

    public function cu_phpinfo() {
        return phpinfo();
    }

    /********* ADMIN PAGES **********/

    public function admin_menu() {
        add_action('admin_enqueue_scripts', array($this, 'add_admin_styles'));

        /**
         * Admin Consort Email Tool page
         */
        require_once(plugin_dir_path(__FILE__).'/includes/admin/email_tool.php');
        $uet = new CuEmailTool();
        $uet->create_page();

        /**
         * Admin Consort Settings page (Absence Sheet & Rehearsal Notes)
         */
        require_once(plugin_dir_path(__FILE__).'/includes/admin/cu_settings.php');
        $ccs = new CuAdminSettings();
        $ccs->create_page();

        /**
         * Admin Consort Analytics page
         */
        require_once(plugin_dir_path(__FILE__).'/includes/admin/analytics.php');
        $ca = new CuAnalytics();
        $ca->create_page();
    }

    /**
     * Shared resources with all Admin pages
     */
    public function add_admin_styles() {
        wp_enqueue_style('cu-admin-style', plugins_url('/assets/css/cu_admin.css', __FILE__));
    }

    /********* AJAX CALLBACKS **********/

    /**
     * AJAX: Admin Consort Email Tool page
     * Get auto-generated news/events content
     */
    public function get_email_content() {
        // Ajax call to get email content
        require_once(plugin_dir_path(__FILE__).'/includes/admin/email_tool.php');
        $uet = new CuEmailTool();
        $uet->return_email_content();
    }

    /**
     * AJAX: Admin Consort Email Tool page
     * Send an email
     */
    public function send_email() {
        // Ajax call to send member/friend email
        require_once(plugin_dir_path(__FILE__).'/includes/admin/email_tool.php');
        $uet = new CuEmailTool();
        $uet->send_email();
    }

    /**
     * AJAX: Absence page
     * Send list of dates to be missed
     */
    public function update_missed_rehearsals() {
        // Ajax call to send list of rehearsals to be missed
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/absence_table.php');
        $uet = new CuAbsenceTable();
        $uet->update_missed_rehearsals();
    }

    /********* iCal SUPPORT **********/

    /**
     * FILTER: for Event Manager's /events.ics URL
     * Determines which calendars' events to send
     */
    public function filter_ical_events($args) {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/ical.php');
        $ccs = new CuCalendarSubscription();
        return $ccs->filter_ical($args);
    }

    /**
     * Subscribe to Calendar form
     */
    public function calendar_subscribe() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/ical.php');
        $ccs = new CuCalendarSubscription();
        return $ccs->html();
    }

    /**
     * AJAX: Calendar subscription URL
     * Generated by PHP so that token encoding/decoding match
     */
    public function get_ical_url() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/ical.php');
        $ccs = new CuCalendarSubscription();
        return $ccs->get_ical_url();
    }


    /********* ANALYTICS **********/

    /**
     * Currently unused experimental ...
     */
    private function analytics() {

        if (!wp_doing_ajax()) {
            $session_id = session_id();
            $ua = $_SERVER['HTTP_USER_AGENT'];
            $ip = $this->getUserIpAddr();
            $page = trim($_SERVER['REQUEST_URI'], '/');
            $user = wp_get_current_user();
            $id = $user->ID;
            if ($id > 0) {
                $name = $user->user_firstname . ' ' . $user->last_name;
                $login = $user->user_login;
                $level = $user->user_level;
                $r = $user->roles[0];
                switch($user->roles[0]) {
                    case 'administrator': $role = 'Admin'; break;
                    case 's2member_level4': $role = 'Board'; break;
                    case 's2member_level3': $role = 'Web Assist'; break;
                    case 's2member_level2': $role = 'Singer'; break;
                    case 's2member_level1': $role = 'Friend'; break;
                    case 'subscriber': $role = 'Inactive'; break;
                    default: $role = 'Unknown: ' . $user->roles[0]; break;
                }
            }
            // Data gathered ... To be Continued, maybe ...
        }

    }
    private function getUserIpAddr() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            //ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //ip pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
}

global $cu_choral_utilities;
$cu_choral_utilities = new ChoralUtilities();


/** Control of Current Season
 * The following will enable turning off the Singer Menu
 * section of the website when outside of the Current Season.
 * Note - optionally it can be kept visible to Admin.
 */
class CurrentSeason
{
    /**
     * Used by Events manager in events-manager/templates/forms/event/categories-public.php
     */
    public static function is_current_season() {
        require_once(plugin_dir_path(__FILE__).'/includes/common/cu_options.php');
        $options = new CuOptions();
        return (
            $options->get_option('current-season-active')
            || (current_user_can('administrator')
                && $options->get_option('admin-override-current-season')));
    }

    // This cannot be initialized in the ctor, as WP's current_user_can() is not loaded yet.
    private static $season_on = null;
    private function init() {
        if (self::$season_on === null)
            self::$season_on = self::is_current_season();
    }

    public function __construct() {
        add_shortcode('if_current_season', array($this, 'current_season'));
        add_shortcode('if_not_current_season', array($this, 'not_current_season'));
        add_filter('ubermenu_display_item', array($this, 'current_season_menu_filter'), 20, 6 );
    }

    // CurrentSeason shortcodes are used on the News Index page
    public function current_season($atts, $content = null) {
        $this->init();
        return self::$season_on ? do_shortcode($content) : '';
    }

    public function not_current_season($atts, $content = null) {
        $this->init();
        return self::$season_on ? '' : do_shortcode($content);
    }

    // The menus themselves are filtered here.  The # is found at the top of the Uber menu form.
    public function current_season_menu_filter( $display_on , $walker , $element , $max_depth, $depth, $args ){
        $this->init();

        switch ($element->ID) {
            case 2546:  // Singers menu and its children
            case 2558:  // Admin => Edit Rehearsal Notes
            case 2654:  // News & Events => Detailed News Lists => News for Singers
                if (self::$season_on)
                    $res = $display_on;
                else
                    $res = false;
                break;

            default:
                $res = $display_on;
        }
        return $res;
    }
}
new CurrentSeason();
