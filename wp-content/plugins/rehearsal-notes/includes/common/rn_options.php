<?php
/**
 * Created by IntelliJ IDEA.
 * User: joncu
 * Date: 4/13/19
 * Time: 3:52 PM
 */
if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

/**
 * Class RnOptions
 *
 * CU Options are kept in an associated array, stored as JSON in a single WP option.
 * Options are divided into sections
 */
abstract class RnOptionsBase {

    const WP_NAME = 'plugin_rehearsal_notes_settings';
    protected $wp_name = RnOptionsBase::WP_NAME;
    protected $options = array();

    public function __construct()
    {
        // register_option($slug, $default value)
        $this->_register_option('song-list', array());
        $this->_register_option('next-id', 1);
    }

    protected function _register_option($slug, $default) {
        $this->options[$slug] = array(
            'default' => $default
        );
    }

    // Used for accessing the entire set of options
    abstract protected function _get_options();
    abstract protected function _update_options($option);

    public function get_option($slug) {
        if (!isset($this->options[$slug]))
            return false;

        $options = $this->_get_options();
        return $options[$slug];
    }

    public function update_option($slug, $new_value) {
        if (!isset($this->options[$slug]))
            return false;

        $options = $this->_get_options();
        $options[$slug] = $new_value;
        return $this->_update_options($options);
    }

    public function reset_to_defaults() {

        $options = $this->_get_options();
        foreach($this->options as $slug => $option) {
            $options[$slug] = $option['default'];
        }
        return $this->_update_options($options);
    }

    public function get_path($slug) {
        return $this->wp_name . '[' . $slug . ']';
    }
}


class RnOptions extends RnOptionsBase
{
    static private $is_admin = -1;

    public function register_setting($page_name, $sanitize_callback) {
        register_setting($page_name, $this->wp_name, $sanitize_callback);
    }

    protected function _get_options()
    {
        return get_option($this->wp_name);
    }

    protected function _update_options($option)
    {
        return update_option($this->wp_name, $option);
    }

    public function add_default_options_to_db() {
        $options = array();

        foreach($this->options as $slug => $option)
            $options[$slug] = $option['default'];

        add_option($this->wp_name, $options);
    }

    // For Uninstall
    public function remove_options() {
        delete_option($this->wp_name);
    }

    // This is a central location - accessible both back & front
    public static function is_admin()
    {
        // WP really messes this up.  Can't even use is_super_admin() because
        // that includes s2member_level4.  We ONLY want those in the administrator role.
        // Plus those who are RNote Admins
        if (self::$is_admin === -1) {
            $positions = false;
            if (function_exists('get_user_field'))  // In case s2member is not active
                $positions = get_user_field('position', get_current_user_id());
            if ($positions === false)
                $positions = array();
            self::$is_admin = in_array('administrator', wp_get_current_user()->roles)
                || in_array('RNote Admin', $positions);
        }
        return self::$is_admin;
    }
}

/**
 * Class RN
 *
 * The Song List array cannot be associative, so this class defines
 * names that identify the array indices.  It is a substitute for
 * JS's lack of associative arrays and PHP's lack of enums.
 */
class RN {
    const ID = 0;
    const NAME = 1;
    const DIR = 2;
    const SM = 3;
    const EM = 4;
    const VP = 5;

    static function renderJS() {
        return 'var rn = ' . json_encode(array(
            'id' => RN::ID,
            'name' => RN::NAME,
            'dir' => RN::DIR,
            'sm' => RN::SM,
            'em' => RN::EM,
            'vp' => RN::VP
        )) . ';';
    }
}

