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
 * Class CuOptions
 *
 * CU Options are kept in an associated array, stored as JSON in a single WP option.
 * Options are divided into sections
 */
abstract class CuOptionsBase {

    protected $wp_name = 'plugin_choral_utilities_settings';
    protected $sections;
    protected $options = array();

    public function __construct()
    {
        $this->sections = array('absence', 'rnotes', 'season');

        // register_option($section, $slug, $default value)
        // Slugs must be unique, including across sections
        $this->_register_option('absence', 'primary-rehearsal-dates', '');
        $this->_register_option('absence', 'associate-rehearsal-dates', '');
        $this->_register_option('absence', 'consort-week-start-date', '');
        $this->_register_option('admin', 'current-season-active', false);
        $this->_register_option('admin', 'admin-override-current-season', false);
    }

    protected function _register_option($section, $slug, $default) {
        $this->options[$slug] = array(
            'section' => $section,
            'default' => $default
        );
    }

    /**
     * @return string - user metadata keys
     */
    public function absence_meta_key() { return 'cu_absence_dates'; }

    // Used for accessing the entire set of options
    abstract protected function _get_options();
    abstract protected function _update_options($option);

    public function get_option($slug) {
        if (!isset($this->options[$slug]))
            return false;

        $options = $this->_get_options();
        // Protect against updates in the options fields
        if (!isset($options[$this->options[$slug]['section']]))
            return null;
        $option = $options[$this->options[$slug]['section']];
        return isset($option[$slug]) ? $option[$slug] : null;
    }

    public function update_option($slug, $new_value) {
        if (!isset($this->options[$slug]))
            return false;

        $options = $this->_get_options();
        $options[$this->options[$slug]['section']][$slug] = $new_value;
        return $this->_update_options($options);
    }

    public function get_path($section, $slug) {
        return $this->wp_name . '[' . $section . '][' . $slug . ']';
    }
}


class CuOptions extends CuOptionsBase
{
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
        foreach($this->sections as $section)
            $options[] = $section;

        foreach($this->options as $slug => $option)
            $options[$option['section']][$slug] = $option['default'];

        add_option($this->wp_name, $options);
    }

    public function reset_section_defaults($section) {
        if (!in_array($section, $this->sections))
            return false;

        $options = $this->_get_options();
        foreach($this->options as $slug => $option) {
            if ($option['section'] == $section)
                $options[$option['section']][$slug] = $option['default'];
        }
        return $this->_update_options($options);
    }
}

/**
 * CuDateValidator
 *
 * Common Utility used in admin and frontend to validate Dates.
 */
class CuDateValidator {

    private $callback, $valid;

    /**
     * CuDateValidator constructor.
     * @param null $error_callback to report errors, $errno, $msg
     */
    public function __construct($error_callback = null)
    {
        $this->callback = $error_callback;
    }

    /**
     * @param $date a single date string of the form m/d
     * @return bool true if valid
     */
    public function validate_date($date) {
        $this->valid = true;
        if (preg_match('/^[0-9\/]*$/', $date) !== 1) {
            $this->_add_error(301, 'Invalid characters found.  It should only contain a date - m/d.');
        } else
            $this->_validate($date);
        return $this->valid;
    }

    /**
     * @param $dates comma delimited list of dates m/d,m/d - no spaces
     * @return bool true if valid, false if errors found
     */
    public function validate_dates($dates) {
        $this->valid = true;
        if (preg_match('/^[0-9,\/]*$/', $dates) !== 1) {
            $this->_add_error(302, 'Invalid characters found.  It should only contain dates and commas - m/d,m/d.');
        }
        $dates = explode(',', $dates);
        foreach($dates as $date) {
            $this->_validate($date);
        }
        return $this->valid;
    }

    // validates a single date
    private function _validate($date) {
        $mon_day = explode('/', $date);
        if (count($mon_day) != 2) {
            $this->_add_error(303, '"' . $date . '" is not properly formatted.');
        } else if (!checkdate($mon_day[0], $mon_day[1], date('Y'))) {
            $this->_add_error(304, '"' . $date . '" is not a valid date.');
        }
    }

    // Sends error message, if callback was given
    private function _add_error($errno, $msg) {
        if ($this->callback)
            call_user_func($this->callback, $errno, $msg);
        $this->valid = false;
    }

}