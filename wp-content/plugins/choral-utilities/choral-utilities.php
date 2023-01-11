<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 9:31 AM
 *
 * Plugin Name: Choral Utilities
 * Version: 3.1.0
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
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class ChoralUtilities {

    public function __construct() {

        // Styles and JS
        add_action('init', array($this, 'init_cu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));

        // Pages via Shortcodes
        add_shortcode('cu_pay_registration', array($this, 'pay_registration'));

        // Seasonal Shortcodes
        add_shortcode('cu_concert_tickets', array($this, 'concert_tickets'));
        add_shortcode('cu_lunch_tickets', array($this, 'lunch_tickets'));

        // Other Shortcodes
        add_shortcode('cu_phpinfo', array($this, 'cu_phpinfo')); // assist with debugging

        // Admin pages and AJAX callbacks
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_ajax_cu_get_email_content', array($this, 'get_email_content'));

        // Calendar iCal support - Filter Event Manager's event.ics results
        add_filter('em_calendar_template_args', array($this, 'filter_ical_events'));
        add_shortcode('cu_calendar_subscribe', array($this, 'calendar_subscribe'));
        add_action('wp_ajax_cu_get_ical_url', array($this, 'get_ical_url'));
        add_action('wp_ajax_nopriv_cu_get_ical_url', array($this, 'get_ical_url'));
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

}

global $cu_choral_utilities;
$cu_choral_utilities = new ChoralUtilities();


