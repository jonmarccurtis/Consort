<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 9:31 AM
 *
 * Plugin Name: Choral Rehearsal Notes
 * Version: 1.0.0
 * Description: Supports Posting Rehearsal Notes, changes to music provided by Directors, for Singers
 * Author: Jon Curtis, Gallinas Creek Productions
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class ChoralRehearsalNotes {

    public function __construct() {

        // Styles and JS
        add_action('init', array($this, 'init_crn'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));

        // Pages via Shortcodes
        add_shortcode('crn_rehearsal_notes', array($this, 'rehearsal_notes'));
    }

    /********* CSS & JS SUPPORT **********/

    public function init_crn() {
        wp_register_style('crn_style', plugins_url('/assets/css/crn_style.css', __FILE__), false, '1.0.0', 'all');
        wp_register_script('crn_tablesorter', plugins_url('/assets/js/jquery.tablesorter.combined.min.js', __FILE__), array('jquery'), '2.28.4');
    }

    public function enqueue_styles() {
        wp_enqueue_style('crn_style');
    }

    /********* PAGES VIA SHORTCODES **********/

    /**
     * Rehearsal notes
     */
    public function rehearsal_notes($atts) {
        require_once(plugin_dir_path(__FILE__).'/includes/rehearsal_notes.php');
        $crn = new CrnRehearsalNotes($atts);
        return $crn->html();
    }

}

global $crn_choral_rehearsal_notes;
$crn_choral_rehearsal_notes = new ChoralRehearsalNotes();

