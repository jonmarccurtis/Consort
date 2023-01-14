<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 9:31 AM
 *
 * Plugin Name: Consort Utilities
 * Version: 3.2.1
 * Description: Adds Utilities for Choral Groups, including: Membership Roster and Absence Reporting
 * Author: Jon Curtis, Galinas Creek Productions
 *
 * Version 2.2.2 - adds parameters to cu_concert_tickets shortcode, so changes each year do not require coding.
 * Version 2.2.3 - updates member history with 2022
 * Version 2.3.0 - removed dependencies on Caldera Forms (10/22): Snack List, Solo Auditions, Workshop Lunches
 * Version 2.3.1 - updated BoD list on the letterhead in email tool
 * Version 2.3.2 - Fix defect in email tool, to not output <pre>, which doesn't render well in all email clients
 * Version 3.0.0 - REDUCTION, first pass, Remove Email Tool's ability to send emails
 * Version 3.1.0 - REDUCTION, remove all unused parts
 * Version 3.2.0 - REDUCTION, combine with Consort's plugin and rename to Consort Utilities
 * Version 3.2.1 - Added [cu_if_member_not_logged_in]
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class ConsortUtilities {

    public function __construct() {
        global $DOING_AJAX;

        // Styles and JS
        add_action('init', array($this, 'init_cu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));

        // Pages via Shortcodes
        add_shortcode('cu_pay_registration', array($this, 'pay_registration'));
        add_shortcode('cu_concert_tickets', array($this, 'concert_tickets'));
        add_shortcode('cu_lunch_tickets', array($this, 'lunch_tickets'));

        // Admin pages and AJAX callbacks
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_ajax_cu_get_email_content', array($this, 'get_email_content'));

        // Calendar iCal support - Filter Event Manager's event.ics results
        add_filter('em_calendar_template_args', array($this, 'filter_ical_events'));
        add_shortcode('cu_calendar_subscribe', array($this, 'calendar_subscribe'));
        add_action('wp_ajax_cu_get_ical_url', array($this, 'get_ical_url'));
        add_action('wp_ajax_nopriv_cu_get_ical_url', array($this, 'get_ical_url'));

        // Change wording on Event Manager iCal links
        add_filter('em_event_output_placeholder', array($this, 'event_output_placeholder'), 10, 3);

        // Fix Event Manager lists in posts formatting
        add_shortcode('cu_em_fix_lists', array($this, 'cu_em_fix_lists'));
        // Fix formatting of Event excerpts
        add_filter('em_events_output_events', array($this, 'fix_event_excerpt_formatting'));

        // Debugging
        add_shortcode('cu_phpinfo', array($this, 'cu_phpinfo'));

        // Provide shortcode that will log out of Password Protected pages
        add_shortcode('cu_pp_logout', array($this, 'cu_pp_logout'));
        add_action( 'init', array($this, 'cu_do_pp_logout'));

        // shortcode conditionals for member logged in
        add_shortcode('cu_if_member_logged_in', array($this, 'cu_if_member_logged_in'));
        add_shortcode('cu_if_member_not_logged_in', array($this, 'cu_if_member_not_logged_in'));

        if (!$DOING_AJAX) {
            // Login - have "remember me" default to checked
            add_filter( 'login_footer', array($this, 'rememberme_checked'));

            // Modifications after all plugins have loaded
            add_action( 'wp_loaded', array($this, 'load_modifications' ));
        }

    }


    /********* CSS & JS SUPPORT **********/

    public function init_cu() {
        wp_register_style('cu_style', plugins_url('/assets/css/cu_style.css', __FILE__), false, '1.0.0', 'all');
    }

    public function enqueue_styles() {
        wp_enqueue_style('cu_style');
    }

    /********* PAGES VIA SHORTCODES **********/

    /**
     * Member Pay Tuition page
     */
    public function pay_registration() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/pay_registration.php');
        $pay = new CuPayRegistration();
        return $pay->html();
    }

    /********* SEASONAL SHORTCODES **********/

    /**
     * Concert Tickets page
     */
    public function concert_tickets($atts) {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/concert_tickets.php');
        $pay = new CuConcertTickets($atts);
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

    /********* OTHER SHORTCODES **********/

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

    /********* Former CC plugin methods **********/

    /** CU_PP_LOGOUT shortcode
     *
     * Provides a logout link for password protected pages.  Example <a href="[cu_pp_logout]">...
     * This does not affect logged in WP users.
     * This code is borrowed from: https://johnblackbourn.com/wordpress-plugin-logout-password-protected-posts/
     */
    public function cu_pp_logout() {
        return wp_nonce_url( add_query_arg( array( 'action' => 'cu_do_pp_logout' ), site_url( 'wp-login.php', 'login' ) ), 'cu_do_pp_logout' );
    }
    function cu_do_pp_logout() {
        if ( isset( $_REQUEST['action'] ) and ( 'cu_do_pp_logout' == $_REQUEST['action'] ) ) {
            check_admin_referer( 'cu_do_pp_logout' );
            setcookie( 'wp-postpass_' . COOKIEHASH, ' ', time() - 31536000, COOKIEPATH );
            wp_redirect( wp_get_referer() );
            die();
        }
    }

    /**
     * Shortcode conditional for content only to be shown if member logged in
     */
    public function cu_if_member_logged_in($atts, $content) {
        if ( isset( $_COOKIE['wp-postpass_' . COOKIEHASH] ) ) {
            return $content;
        }
        return '';
    }
    public function cu_if_member_not_logged_in($atts, $content) {
        if ( isset( $_COOKIE['wp-postpass_' . COOKIEHASH] ) ) {
            return '';
        }
        return $content;
    }

    /**
     * Reset default for "remember me" checkbox on login to true
     */
    public function rememberme_checked() {
        echo "<script>document.getElementById('rememberme').checked = true;</script>";
    }

    /**
     * This is called once all plugins have been loaded.  It is the time when some of their
     * actions and filters can be removed.
     */
    public function load_modifications() {
        // Remove Photo Gallery Button from classic text editor, as used in Email Tool
        if (class_exists('BWG', false))  // Prevent crash if plugin is deactivated
            remove_action( 'media_buttons', array(BWG::instance(), 'media_button' ) );

        // Remove "Phone (Events Manager)" setting from Admin Profile pages
        remove_filter( 'user_contactmethods', 'EM_People::user_contactmethods', 10);
    }

    /** CU_EM_FIX_LISTS shortcode
     *
     * Event Manager [event-list ] is inconsistent when used on a Page vs a Post.
     * It puts <p> into the items making them 2 line.  This fixes that.  Note, it
     * does not do this if the list is in a page.
     */
    public function cu_em_fix_lists($atts, $content) {
        $fixed = str_replace('<p>', '', $content);
        return str_replace('</p>', '', $fixed);
    }

    /**
     * Replace iCal with "Add to Calendar"
     */
    public function event_output_placeholder($replace, $event, $place_holder) {
        if ($place_holder == '#_EVENTICALLINK') {
            if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'cu_get_email_content') {
                // For emails - don't include the extra stuff
                $replace = str_replace('>iCal<', '>Add to your Calendar<', $replace);
            } else {
                $replace = str_replace('>iCal<', '><span class="cc-ical-link" title="Download/add ICS file for this event to your calendar">ADD TO CALENDAR</span><', $replace);
            }
        }
        return $replace;
    }

    /**
     * Event Excerpts formatting removes line feeds.  The way this fix works is to
     * create an Excerpt here if it has not been filled in.  The created excerpt is
     * temporary and not saved.  This code is derived from EM_Object::output_excerpt()
     * except that it replaces line feeds with <br>.  Since the EM_Event now has an
     * excerpt, it by-passes the Event Manager code that was removing the line feeds.
     */
    public function fix_event_excerpt_formatting($args) {
        if (count($args) != 1)
            return $args;
        else
            $event = $args[0];

        $excerpt_more = apply_filters('em_excerpt_more', ' ' . '[...]');

        if( !empty($event->post_excerpt) ){
            $replace = $event->post_excerpt;
        }else{
            $replace = $event->post_content;
            if ( preg_match('/<!--more(.*?)?-->/', $replace, $matches) ) {
                $content = explode($matches[0], $replace, 2);
                $replace = force_balance_tags($content[0]);
            }
            //shorten content by 55
            $replace = strip_shortcodes( $replace );
            $replace = str_replace(']]>', ']]&gt;', $replace);

            // Don't let <br> & <p> get lost
            $replace = str_replace('</p>', '\n', $replace);
            $replace = str_replace('<br>', '\n', $replace);
            $replace = str_replace('<br />', '\n', $replace);

            $replace = wp_trim_words( $replace, 55, $excerpt_more );

            // Now replace our new line 'tags' with HTML new lines
            $replace = str_replace('\n', '<br>', $replace);
        }
        $args[0]->post_excerpt = $replace;
        return $args;
    }

}

global $cu_consort_utilities;
$cu_consort_utilities = new ConsortUtilities();


