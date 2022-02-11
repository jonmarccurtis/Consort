<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

require_once(plugin_dir_path(__FILE__).'/../common/rn_options.php');

/**
 * Class RnSettings
 * Provides Admin Settings page
 * For Absence Sheet and Rehearsal Notes
 */
abstract class RnSettings
{
    protected $options, $page_name, $active_tab;

    // Factory: Returns an instance of RnSettings
    static public function getTab() {
        $tabs = array(
            'songs' => 'Song List',
            'singers' => 'Singer List',
            'reports' => 'Reporting',
            'action' => '');
        if (RnOptions::is_admin())
            $tabs['admin'] = 'Administration';
        $tabs['help'] = 'Help';

        $active_tab = new RnActiveTabField($tabs);

        switch($active_tab->cur_tab()) {
            case 'songs':
                require_once(plugin_dir_path(__FILE__).'/rn_settings_songs.php');
                return new RnSongsTab($active_tab);
            case 'singers':
                require_once(plugin_dir_path(__FILE__).'/rn_settings_singers.php');
                return new RnSingersTab($active_tab);
            case 'reports':
                require_once(plugin_dir_path(__FILE__).'/rn_settings_reports.php');
                return new RnReportsTab($active_tab);
            case 'admin':
                require_once(plugin_dir_path(__FILE__).'/rn_settings_admin.php');
                return new RnAdminTab($active_tab);
            case 'action':
                require_once(plugin_dir_path(__FILE__).'/rn_settings_action.php');
                return new RnActionsTab($active_tab);
            case 'help':
                // TESTING: uninstall_plugin('rehearsal-notes');
                require_once(plugin_dir_path(__FILE__).'/rn_settings_help.php');
                return new RnHelpTab($active_tab);
        }
    }

    protected function __construct($active_tab)
    {
        $this->options = new RnOptions();
        $this->page_name = 'rn-settings';
        $this->active_tab = $active_tab;
    }


    public function add_admin_scripts($page) {
        if ($page == 'settings_page_' . $this->page_name) {
            wp_enqueue_script('rn-settings-script',
                plugins_url('/../../assets/js/rn_settings.js', __FILE__));

            wp_enqueue_style('rn_common',
                plugins_url('/../../assets/css/rn_common.css', __FILE__));
        }
    }

    /**
     * The created list of fields depends on which tab is showing
     * They share a single register_setting() call, and thus a single validation
     * callback.
     */
    public function register_settings_init()
    {
        // register_setting( $option_group, $sanitize_callback )
        $this->options->register_setting($this->page_name,
            array($this, 'rn_settings_validate'));

        $this->register_tab();

        // All tab pages have the hidden tab field for tracking tab in callbacks
        $this->_register_common_section();
    }

    protected function add_section($section, $title) {
        add_settings_section(
            $section,
            $title,
            array($this, 'render_section_info'),
            $this->page_name);
    }

    // Note - having only one callback will not work if ever need more than 1 section
    public function render_section_info() {}

    protected function add_field($section, $field, $args = array()) {
        $args['value'] = $this->options->get_option($field->slug());
        $args['name'] = $this->options->get_path($field->slug());
        add_settings_field(
            $field->field(),
            $field->label(),
            array($field, 'render'),
            $this->page_name,
            $section,
            $args);
    }

    protected function register_tab() {}

    private function _register_common_section()
    {
        $section = 'rn-tab-settings-section';
        add_settings_section(
            $section,
            '',              // HIDDEN
            function() {},
            $this->page_name
        );

        // Hidden Active Tab field - so we know what fields should be present in a POST
        $this->add_field($section, $this->active_tab,
            array('class' => 'sl-header hidden'));
    }

    public function rn_settings_validate($fields) {
        $options = new RnWorkingOptions();
        $input = new RnWorkingOptions($fields);

        $this->validate($options, $input);

        return $options->validated_options();
    }

    // To be overridden by tabs
    protected function validate(&$options, $input) {}

    public function render_page()
    {
        $this->renderHtml();
        $this->renderJS();
    }

    private function renderHtml()
    {
        echo '
        <h1>Rehearsal Notes Settings</h1>
        <h2 class="nav-tab-wrapper">';
        foreach($this->active_tab->tabs() as $slug => $label) {
            if (!empty($label)) {
                echo '
            <a href="?page=' . $this->page_name . '&tab=' . $slug . '" class="nav-tab '
                    . ($this->active_tab->is($slug) ? 'nav-tab-active' : '')
                    . '">' . $label . '</a>';
            }
        }
        echo '
        </h2>';

        $this->render_outer_html();

        echo '
        <form id="rn-settings-form" method="post" action="options.php">';

        settings_fields($this->page_name);
        do_settings_sections($this->page_name);

        $this->render_submit_button();

        echo '
        </form>';
    }

    // Can be overridden by derived classes to place HTML outside of the main form
    protected function render_outer_html() {}

    protected function render_submit_button() {}

    private function renderJS()
    {
        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[
        ';

        $js .= $this->get_js();

        echo $js.'
        // ]]></script>';
    }

    protected function get_js() {}
}

abstract class RnTab extends RnSettings
{
    public function create_tab() {
        // Normal tabs use this.  Action tab overrides it.
        add_action('admin_enqueue_scripts', array($this, 'add_admin_scripts'));
        add_options_page(
            'Rehearsal Notes',
            'Rehearsal Notes',
            'access_s2member_level3',
            $this->page_name,
            array($this, 'render_page'));
        add_action('admin_init', array($this, 'register_settings_init'));

        // Check to see if an action status needs to be shown, this param needs to be unique
        if (isset($_GET['rn_ac_res'])) {
            // Only one currently used is 'updated'
            if ($_GET['rn_ac_res'] == 'session')
                add_action('admin_notices', array($this, 'rn_show_session'));
        }
    }

    public function rn_show_session() {
        @session_start();
        if (isset($_SESSION['rn_ac_res'])) {
            echo '<div class="updated notice is-dismissible">'. $_SESSION['rn_ac_res'] . '</div>';
            unset($_SESSION['rn_ac_res']);
        }
    }

}

class RnWorkingOptions extends RnOptionsBase {

    private $storage;

    public function __construct($src = null)
    {
        // If $src is not provided make a copy of the options from the database
        $this->storage = ($src == null) ? get_option($this->wp_name) : $src;
        parent::__construct();

        // Additional fields, not stored in the database
        $this->_register_option('tab', 'active-tab');
        $this->_register_option('reset', 'none');
    }

    protected function _get_options()
    {
        return $this->storage;
    }

    protected function _update_options($options)
    {
        $this->storage = $options;
    }

    public function validated_options() { return $this->storage; }
}



/** RnField classes
 *
 * RnField (abstract) - base class for fields and actions
 *
 *     RnActiveTabField - tracks which tab is active, for callbacks
 *     RnSingersListField - imports and sets up the list of singers
 *
 *     RnOptionField (abstract) - settings field, stored in WP Options
 *         RnSongListField - holds song list for rehearsal notes
 *
 *     RnAction (abstract) - not part of WP settings, calls back to handle() methods
 *         RnImportAction -
 */


/**
 * Class RnField
 * Base class for all options fields
 */
abstract class RnField
{
    protected $slug, $label, $field;

    public function __construct($slug, $label) {
        $this->slug = $slug;
        $this->label = $label;
        $this->field = 'rn-' . $this->slug . '-field';
    }

    abstract public function render($args);

    public function slug() { return $this->slug; }
    public function label() { return $this->label; }
    public function field() { return $this->field; }
}

/**
 * Class RnActiveTabField
 * Provides a hidden field that indicates which tab was active in AJAX calls
 */
class RnActiveTabField extends RnField
{
    private $tabs;
    private $cur_tab;
    private $error_sent;

    /**
     * RnActiveTabField constructor.
     * @param $tabs array of tabs [slug => label]
     *              first element is the default tab
     */
    public function __construct($tabs)
    {
        $this->error_sent = false;
        $this->tabs = $tabs;
        $default = array_keys($tabs)[0];

        if (isset($_GET['rn_action'])) {
            // This is not really a tab, but when an action is requested the
            // tab does not need to be built, so it is an 'alternative' tab.
            $this->cur_tab = 'action';

        } else if (isset($_POST[RnOptionsBase::WP_NAME]['tab'])) {
            $this->cur_tab = $_POST[RnOptionsBase::WP_NAME]['tab'];

        } else {
            // This setting is only valid for GET requests, and is overridden
            // if calling the is_active methods from a POST.  The difference
            // is determined by whether the $input param is set.
            $this->cur_tab = (isset($_GET['tab'])) ? $_GET['tab'] : $default;

            if (!RnOptions::is_admin() && $this->cur_tab == 'admin')
                $this->cur_tab = $default;

            // We can still clash with other plugins using 'tab' parameter
            if (!in_array($this->cur_tab, array_keys($this->tabs)))
                $this->cur_tab = $default;
        }

        parent::__construct('tab', 'active-tab', 'Active Tab');
    }

    public function render($args)
    {
        echo '
        <input type="hidden" name="' . $args['name'] . '" value="' . $this->cur_tab . '" />';
    }

    public function tabs() {
        return $this->tabs;
    }

    /**
     * @param $tab slug of the tab to check
     * @param $input this is needed in AJAX POST, when the only way to determine
     *               what the current tab is, is by the hidden input field.
     * @return TRUE if it is the active tab.  If this is an AJAX call and the
     *         hidden field is missing, it returns FALSE.
     */
    public function is($tab, $input = null) {
        return $this->cur_tab($input) == $tab;
    }

    public function cur_tab($input = null)
    {
        if ($input == null)
            return $this->cur_tab; // This is a GET request, so we know what the tab is

        // This is a POST request, so find out which tab from the sent fields
        return $input->get_option($this->slug);

        return false;
    }

}

abstract class RnOptionField extends RnField {

    protected $valid, $value;

    public function add_error($errno, $msg) {
        add_settings_error($this->field, $this->slug, 'ERROR in '
            . $this->label . ' = [' . $this->value . ']: ' . $msg, 'error');
        $this->valid = false;
    }

    public function validate(&$options, $input) {
        $this->value = $input->get_option($this->slug);   // Original value for error messages
        $this->valid = true;
        $value = $this->_clean_and_validate($this->value, $options);
        if ($this->valid)
            $options->update_option($this->slug, $value);
    }

    // Return sanitized value.  Calling add_error sets the valid flag to false
    abstract protected function _clean_and_validate($value, &$working_options);
}

