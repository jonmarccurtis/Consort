<?php
/**
 * User: joncu
 * Date: 2/9/19
 * Time: 9:31 AM
 *
 * Plugin Name: Consort's Site-Plugin
 * Version: 3.0.1
 * Description: Provides a location for PHP changes that will not be overwritten when other plugins or themes are updated.  It also lists changes that must be re-made on update in the following: I-Excel Theme.
 * Author: Jon Curtis
 *
 * Version 2.0.1 - removed dependency on Caldera Forms (10/22)
 * Version 3.0.0 - REDUCTION, remove Admin bar hide, provide password protect log out, Event Manager format fix
 * Version 3.0.1 - REDUCTION, remove references to s2member
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}


/** WP Customizations of other Plugins/Core
 * Put changes needed to support the CCWP website here.
 */
class ConsortCustomizations
{
    public function __construct() {
        $this->add_actions();
    }

    private function add_actions() {
        global $DOING_AJAX;

        // Provide shortcode that will log out of Password Protected pages
        add_shortcode('cu_pp_logout', array($this, 'cu_pp_logout'));
        add_action( 'init', array($this, 'cu_do_pp_logout'));

        // Fix Event Manager lists in posts formatting
        add_shortcode('cu_em_fix_lists', array($this, 'cu_em_fix_lists'));

        // Change wording on Event Manager iCal links
        add_filter('em_event_output_placeholder', array($this, 'event_output_placeholder'), 10, 3);

        // Fix formatting of Event excerpts
        add_filter('em_events_output_events', array($this, 'fix_event_excerpt_formatting'));

        if (!$DOING_AJAX) {
            // Login - have "remember me" default to checked
            add_filter( 'login_footer', array($this, 'rememberme_checked'));

            // Modifications after all plugins have loaded
            add_action( 'wp_loaded', array($this, 'load_modifications' ));
        }
    }

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
        // Remove Photo Gallery Button from front-end text editor
        // TODO: protect against the Photo Gallery plugin not active - blocks Admin loading
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
new ConsortCustomizations();

