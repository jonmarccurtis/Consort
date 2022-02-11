<?php
/**
 * User: joncu
 * Date: 4/16/19
 * Time: 9:31 AM
 *
 * Plugin Name: Rehearsal Notes
 * Version: 1.1.0
 * Description: Utility for Chorale groups to create, track, and publish Rehearsal Notes - changes to be made in their music.
 * Author: Jon Curtis
 *
 * v 1.1.0 - adds is_admin to singer's DB.  adds dir_id to notes DB.
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class RehearsalNotes {

    public function __construct() {
        // Activation set up DB
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Styles and JS
        add_action('init', array($this, 'init_rn'));

        // Pages via Shortcodes
        add_shortcode('rn_admin_table', array($this, 'admin_table'));
        add_shortcode('rn_singer_table', array($this, 'singer_table'));
        add_shortcode('rn_printable_table', array($this, 'printable_table'));
        add_shortcode('rn_voice_parts_table', array($this, 'vp_table'));
        add_filter('query_vars', array($this, 'printable_query_vars'));

        // RNote Taker Staff Shortcode
        add_shortcode('rnts_filter', array($this, 'rnts_filter'));

        // RNote Reference Manual Shortcode
        add_shortcode('rn_refman', array($this, 'rn_refman'));

        // Admin pages
        add_action('admin_menu', array($this, 'admin_menu'));

        // AJAX callbacks
        require_once(plugin_dir_path(__FILE__).'/includes/common/ajax_handler.php');
        RnAjaxHandler::add_actions();

        // Website Profile changes
        add_action('profile_update', array($this, 'profile_update'), 10, 1);
        add_action('show_user_profile', array($this, 'customize_profile'), 10, 1); // for your own profile
        add_action('edit_user_profile', array($this, 'customize_profile'), 10, 1); // for editing someone else's
    }

    /********* ACTIVATION ACTIONS **********/

    public function activate() {
        require_once(plugin_dir_path(__FILE__).'/includes/common/rn_options.php');
        $options = new RnOptions();
        $options->add_default_options_to_db();

        require_once(plugin_dir_path(__FILE__).'/includes/common/rn_database.php');
        RnNotesDB::add_table();
        RnSingersDB::add_table();
        RnDoneDB::add_table();
        RnOnlineDB::add_table();
    }

    /********* CSS & JS SUPPORT **********/

    public function init_rn() {
        wp_register_style('rn_common', plugins_url('/assets/css/rn_common.css', __FILE__), false, '1.0.0', 'all');
        wp_register_style('rn_frontend', plugins_url('/assets/css/rn_frontend.css', __FILE__), false, '1.0.0', 'all');
        wp_register_script('rn_tablesorter', plugins_url('/assets/js/jquery.tablesorter.combined.min.js', __FILE__), array('jquery'), '2.28.4');
        wp_register_script('rn_admin_page', plugins_url('/assets/js/rn_admin_page.js', __FILE__), array('jquery'), '1.0');
        wp_register_script('rn_singer_page', plugins_url('/assets/js/rn_singer_page.js', __FILE__), array('jquery'), '1.0');
        wp_register_script('rn_common', plugins_url('/assets/js/rn_common.js', __FILE__), array('jquery'), '1.0');

        // Add Genericons font, used in the front-end Admin page
        wp_register_style( 'genericons', plugins_url('/assets/fonts/genericons.css', __FILE__), false, '2.09', 'all');
    }

    /********* SHORTCODES **********/

    /**
     * Rehearsal Notes Administration Table (frontend)
     */
    public function admin_table() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/admin_page.php');
        $rap = new RnAdminPage();
        return $rap->html();
    }

    /**
     * Rehearsal Notes Singers Table (frontend)
     */
    public function singer_table() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/singer_page.php');
        $rsp = new RnSingerPage();
        return $rsp->html();
    }

    /**
     * Rehearsal Notes Printable Table (frontend)
     */
    public function printable_table() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/printable_page.php');
        $rpp = new RnPrintablePage();
        return $rpp->html();
    }
    // Bad - that this must always be set, even when not on the printable page
    public function printable_query_vars($vars) {
        $vars[] = 'nt';
        return $vars;
    }

    /**
     * Voice Parts Table (frontend)
     */
    public function vp_table() {
        require_once(plugin_dir_path(__FILE__).'/includes/frontend/vp_page.php');
        $rvp = new RnVpPage();
        return $rvp->html();
    }

    /**
     * Conditional for menu display on RNote Taker Staff
     */
    public function rnts_filter($atts, $content) {
        require_once(plugin_dir_path(__FILE__).'/includes/common/rn_database.php');
        $staff = RnSingersDB::get_staff();
        if (in_array(get_current_user_id(), array_keys($staff)))
            return $content;
        return '';
    }

    /********* ADMIN PAGES **********/

    public function admin_menu() {
        add_action('admin_enqueue_scripts', array($this, 'add_admin_styles'));

        /**
         * Admin Rehearsal Notes Settings page
         */
        require_once(plugin_dir_path(__FILE__).'/includes/admin/rn_settings.php');
        $tab = RnSettings::getTab();
        $tab->create_tab();
    }

    /**
     * Shared resources with all Admin pages
     */
    public function add_admin_styles() {
        wp_enqueue_style('rn-admin-style', plugins_url('/assets/css/rn_admin.css', __FILE__));
    }

    /********* PROFILE UPDATES **********/
    public function profile_update($id) {
        require_once(plugin_dir_path(__FILE__).'/includes/admin/wp_profile_settings.php');
        WPProfileSettings::import_singer($id);
    }

    public function customize_profile($user) {
        require_once(plugin_dir_path(__FILE__).'/includes/admin/wp_profile_settings.php');
        WPProfileSettings::customize_profile($user);
    }

    /********* ADMIN & FRONTEND PAGES **********/

    /**
     * RNotes Reference Manual (shortcode)
     */
    public function rn_refman($atts) {
        require_once(plugin_dir_path(__FILE__).'/includes/common/rn_refman.php');
        $man = new RnReferenceManual();
        return $man->html($atts);
    }
}

new RehearsalNotes();

