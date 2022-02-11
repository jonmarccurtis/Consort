<?php
/**
 * User: joncu
 * Date: 4/16/19
 * Time: 9:31 AM
 *
 * Plugin Name: Rehearsal Notes
 * Version: 1.0.0
 * Description: Utility for Chorale groups to create, track, and publish Rehearsal Notes - changes to be made in their music.
 * Author: Jon Curtis
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

abstract class RnAjaxHandler
{
    /**
     * Add AJAX callbacks here:
     *
     * @var array - indexed by 'action'
     *     'n_par' is the nonce parameter name
     *     'handler' is the name of a derived class
     */
    protected static $ajax = array(
        'rn_get_rnote' => array('n_par' => 'rn_gt', 'handler' => 'RnAdminPage'),
        'rn_save_rnote' => array('n_par' => 'rn_sv', 'handler' => 'RnAdminPage'),
        'rn_move_rnote' => array('n_par' => 'rn_mv', 'handler' => 'RnAdminPage'),
        'rn_history_rnote' => array('n_par' => 'rn_hi', 'handler' => 'RnAdminPage'),
        'rn_delete_rnote' => array('n_par' => 'rn_del', 'handler' => 'RnAdminPage'),
        'rn_heartbeat' => array('n_par' => 'rn_hb', 'handler' => 'RnAdminPage'),
        'rn_sef_save' => array('n_par' => 'sef_sv', 'handler' => 'SingerEditForm'),
        'rn_sef_vps_save' => array('n_par' => 'sef_vps', 'handler' => 'SingerEditForm'),
        'rn_singer_heartbeat' => array('n_par' => 'rn_shb', 'handler' => 'RnSingerPage'),
        'rn_singer_save_question' => array('n_par' => 'rn_ssv', 'handler' => 'RnSingerPage'),
        'rn_singer_track_questions' => array('n_par' => 'rn_str', 'handler' => 'RnSingerPage'),
        'rn_singer_sef_save' => array('n_par' => 'rn_ssef', 'handler' => 'RnSingerPage'),
        'rn_singer_done' => array('n_par' => 'rn_sdn', 'handler' => 'RnSingerPage'),
        'rn_print_done' => array('n_par' => 'rn_pdn', 'handler' => 'RnPrintablePage'),
        'rn_table_settings' => array('n_par' => 'rn_tset', 'handler' => 'RnTable'),
    );

    // What to include, relative to this static class file
    protected static $src_file = array(
        'RnAdminPage' => '../frontend/admin_page.php',
        'RnSingerPage' => '../frontend/singer_page.php',
        'SingerEditForm' => 'singer_edit_form.php',
        'RnPrintablePage' => '../frontend/printable_page.php',
        'RnTable' => 'rn_table.php'
    );

    // Derived classes must implement these and call get_ajax_JS()
    abstract protected function check_user_can_handle_ajax();
    abstract protected function do_ajax_request($action);


    // Called from RehearsalNotes ctor
    public static function add_actions()
    {
        foreach (self::$ajax as $action => $ajax) {
            add_action('wp_ajax_' . $action, array('RnAjaxHandler', 'handle_ajax_request'));
        }
    }

    public static function handle_ajax_request()
    {
        if (!isset($_POST['action'])) // Don't think WP would let this happen
            self::send_fatal_error(1025);
        $action = $_POST['action'];
        if (!isset(self::$ajax[$action]))
            self::send_fatal_error(1026);

        $ajax = self::$ajax[$action];
        require_once(plugin_dir_path(__FILE__) . self::$src_file[$ajax['handler']]);

        $id = get_current_user_id();
        if (!check_ajax_referer($action . $id, $ajax['n_par'], false))
            self::send_user_error('Connection timed out. Try page refresh.');

        $handler = new $ajax['handler']();
        $handler->check_user_can_handle_ajax();
        $handler->do_ajax_request($action);
    }

    protected static function send_fatal_error($code) {
        wp_send_json_error(array('code' => $code));
    }
    protected static function send_user_error($msg) {
        wp_send_json_error(array('code' => 0, 'msg' => $msg));
    }

    // Passes the nonce(s) to JS.  The request is assembled in
    // rn_common.js send_request()
    protected function get_ajax_JS()
    {
        $handler = get_class($this);
        $id = get_current_user_id();
        $nonced = array();
        foreach (self::$ajax as $action => $ajax) {
            // RnTable is part of RnSingerPage and RnAdminPage
            if ($ajax['handler'] == $handler || $ajax['handler'] == 'RnTable') {
                $ajax['action'] = $action;
                $ajax['n_val'] = wp_create_nonce($action . $id);
                unset($ajax['handler']);
                $nonced[] = $ajax;
            }
        }
        return '
            var rn_req = ' . json_encode($nonced) . ';';
    }

}
