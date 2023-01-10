<?php
/**
 * User: joncu
 * Date: 2/9/19
 * Time: 9:31 AM
 *
 * Plugin Name: Consort's Site-Plugin
 * Version: 3.0.0
 * Description: Provides a location for PHP changes that will not be overwritten when other plugins or themes are updated.  It also lists changes that must be re-made on update in the following: I-Excel Theme.
 * Author: Jon Curtis
 *
 * Version 2.0.1 - removed dependency on Caldera Forms (10/22)
 * Version 3.0.0 - REDUCTION, remove Admin bar hide, provide password protect log out, Event Manager format fix
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

        // WP Admin Bar suppression
        // CC_REDUCTION don't need this anymore.  Those who are Editors will need
        // the Admin bar to log off and move back-forth between dashboard and front end.
        //  add_action('after_setup_theme', array($this, 'remove_admin_bar'));

        // Provide login item for the Menu
        add_shortcode('cu_login_url', array($this, 'cu_login_url'));

        // Provide shortcode that will log out of Password Protected pages
        add_shortcode('cu_pp_logout', array($this, 'cu_pp_logout'));
        add_action( 'init', array($this, 'cu_do_pp_logout'));

        // New Member Registration & Profile fields display: s2Member Plugin
        add_action('ws_plugin__s2member_during_profile_during_fields_during_custom_fields_display',
            array($this,'filter_profile_fields'), 10, 3);

        // Transfer s2Member field(s) to Capabilities
        add_action('ws_plugin__s2member_after_users_list_update_cols',
            array($this, 'transfer_to_capabilities'), 10, 2);

        // Fix Event Manager lists in posts formatting
        add_shortcode('cu_em_fix_lists', array($this, 'cu_em_fix_lists'));

        // Change wording on Event Manager iCal links
        add_filter('em_event_output_placeholder', array($this, 'event_output_placeholder'), 10, 3);

        // Display all users as possible post Authors
        add_filter('rest_user_query', array($this, 'rest_user_query'), 10, 2);
        add_filter('wp_dropdown_users_args', array($this, 'dropdown_users_args'), 10, 2);

        // Fix formatting of Event excerpts
        add_filter('em_events_output_events', array($this, 'fix_event_excerpt_formatting'));

        if (!$DOING_AJAX) {
            // Access Restricted Page - Login Redirecting
            add_action('template_redirect', array($this, 'access_login_redirect'));

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


    /** CU_LOGIN_URL shortcode
     *
     * Returns the login URL with redirect back to the current page.
     * Needed by the Login Menu item
     */
    public function cu_login_url() {
        return wp_login_url($_SERVER['REQUEST_URI']);
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

    /**
     * Overrides the POST EDIT Author's dropdown to show all who are able to edit news.
     * This method is called via the WP REST API.
     *
     * This filter is necessary because WP has a 5+ year old bug that they refuse to fix.
     * The Author dropdown for posts is based on the "Author" role, rather than user
     * capabilities.  Since CC is using s2member custom roles, even if the role is given
     * the ability to edit posts, unless the user is of the role "Author" they do not
     * appear in the dropdown.  One solution tried, via AAM, was to enable multiple
     * roles per user.  But this causes a number of side-effect defects.
     *
     * This only affects the Admin page for POST edit and new.  The quick-edit on the
     * Post list page uses wp_dropdown_user_args, but only calls it twice, rather than
     * for each post.  Thus, it is not possible to control who can edit which post.  We
     * could give the ability to set any author for any post, but that risks setting
     * the author to someone who does not have access to that level of post.
     *
     * @param $args
     * @param $request
     * @return mixed
     */
    public function rest_user_query($args, $request) {

        // Only modify the user list if this query comes during editing
        // one of the 4 types of CC News Posts.  Since this is a REST call,
        // the workaround is to check the HTTP REFERER.  It should contain
        // ?post=#&action=edit.  We can then use the post ID to find out
        // who is able to be an author.
        //
        // The one exception is a New Post.  For it, we enable all users.
        // One problem with this is that is/when the category is changed,
        // the Author dropdown does not get updated until the page is
        // refreshed.

        $ref = $request->get_header('referer');
        if (!empty($ref)) {
            $query_str = parse_url($ref, PHP_URL_QUERY);
            parse_str($query_str, $params);
            if (isset($params['action']) && 'edit' == $params['action']) {
                if (isset($params['post'])) {
                    $post_id = $params['post'];
                    $cats = get_the_category($post_id);
                    if (1 == count($cats)) { // We only allow one category per post
                        $cat = $cats[0]->to_array();
                        if (isset($cat['cat_ID'])) {
                            $args = $this->adjust_users($args, $cat['cat_ID']);
                        }
                    }
                }
            } else {  // This might be a new post
                if ('post-new.php' == basename($ref)) {
                    $args['who'] = '';  // Up to the user to know who can edit the category
                }
            }
        }
        return $args;
    }

    /**
     * Adjust the Event Author dropdown in the Edit Event Admin page.  Same problem as
     * with Posts.  It is not based on Capabilities, but rather on Role.
     *
     * @param $args
     * @param $r
     * @return mixed
     */
    public function dropdown_users_args($args, $r) {
        if ('post_author_override' == $r['name']) {
            if ('event' == get_post_type()) {
                $post_id = get_the_ID();
                $cats = get_the_terms($post_id, 'event-categories');
                if (false !== $cats) {
                    if (1 == count($cats)) { // We only allow one category per event
                        $cat = $cats[0]->to_array();
                        if (isset($cat['term_id'])) {
                            // Offset ID with 1000 to distinguish from Post IDs
                            $args = $this->adjust_users($args, $cat['term_id'] + 1000);
                        }
                    }
                } else {  // This is a new event
                    $args['who'] = '';  // Up to the user to know who can edit the category
                }
            }
        }
        return $args;
    }

    private function adjust_users($args, $cat_id)
    {
        // Event Categories are offset by 1000
        // CC_MOD: Hardcoded to the CC Category IDs!
        switch ($cat_id) {
            case 2:     // News about Other Groups
            case 1007:  // Other Events
            case 9:     // News about Consort
            case 1010:  // Consort Events
                $args['who'] = '';  // Cancel Who == Authors
                // Number defaults to 100.  Should be enough for Singers and Board,
                // But we might exceed this for full membership someday.
                $args['number'] = 150; // Everyone
                break;

            case 3:     // News for Singers
            case 1008:  // Singer Events
                $args['who'] = '';  // Replace Who with Role__in
                $args['role__in'] = array('s2member_level2', 's2member_level3', 's2member_level4', 'administrator');
                break;

            case 11:   // Board News
            case 1012: // Board Events
                $args['who'] = '';  // Replace Who with Role__in
                $args['role__in'] = array('s2member_level4', 'administrator');
                break;

            case 4:    // Past Concerts
            case 1:    // Uncategorized Posts
            default:
                break;  // take no action
        }
        return $args;
    }


    // Removes the Admin bar across the top of the site - for all but Admins
    public function remove_admin_bar()
    {
        if (!current_user_can('administrator') && !is_admin()) {
            show_admin_bar(false);
        }
    }

    // Remove profile fields from the profile page
    public function filter_profile_fields($default, $vars)
    {
        // Don't show some profile fields in front-end profile ...
        return in_array($vars['field_id_class'], ['position']) ? false : $default;
    }

    /** transfer_to_capabilities
     *
     * WP has a built-in system of capabilities that can be applied to roles or
     * to individual users.  These can then be used in PHP, shortcodes, and other
     * places, like UberMenu, as conditionals to control website content.
     *
     * s2member does support custom capabilities, but they can only be set via
     * s2member PayPal buttons.  (They are to provide things like access to videos,
     * music, site content, based on a customer paying for the access.)
     *
     * This method provides a way to use the value of s2member custom profile fields
     * to set or clear custom capabilities for that user.
     */
    public function transfer_to_capabilities($vars)
    {
        $id = $vars['user_id'];
        $fields = $vars['fields'];

        // s2member custom fields => WP custom capabilities - map
        // grant = boolean - based on custom field values
        // name = custom capability name, use prefic cc_
        $caps = array(
            // Only those with Membership position can view the Opus registration list
            array(
                'grant' => isset($fields['position']) && in_array('Membership', $fields['position']),
                'name' => 'cc_access_registration_list'
            ),
            // Note Takers && Directors can edit rehearsal notes
            array(
                'grant' => isset($fields['position']) && (in_array('Note Taker', $fields['position'])
                                                          || in_array('Artistic Director', $fields['position'])),
                'name' => 'rn_can_edit_rnotes'
            )
        );

        foreach($caps as $cap) {
            // Its a database update, so only do it if its changing
            if ($cap['grant'] != user_can($id, $cap['name'])) {
                $user = new WP_User($id);
                if ($cap['grant'])
                    $user->add_cap($cap['name'], true);
                else
                    $user->remove_cap($cap['name']);
            }
        }
    }

    /**
     * When a user does something like click a link in an email, but
     * is not logged in, they are taken to the "restricted" page.  This
     * intercepts that page loading.  If the user is not logged in,
     * it presents them with the login and redirect to the page they
     * wanted.  If they are already logged in, it means that they do
     * not have a high enough access level to see the page, so they
     * continue to the restricted page.
     *
     * Unfortunately WP does not provide a way to limit this call only
     * to the page we care about.
     */
    public function access_login_redirect() {
        if (is_page('restricted')) {
            if (!is_user_logged_in()) {
                if (!empty($_REQUEST['_s2member_vars'])) { // s2member MOP vars
                    @list($restriction_type, $requirement_type, $requirement_type_value,
                        $seeking_type, $seeking_type_value, $seeking_uri)
                        = explode("..", stripslashes((string)$_REQUEST["_s2member_vars"]));

                    $target_uri = '';
                    if (!empty($seeking_uri)) {
                        $target_uri = base64_decode($seeking_uri);
                    }
                    wp_redirect(wp_login_url(esc_url($target_uri)));
                    die;
                }
            }
        }
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

