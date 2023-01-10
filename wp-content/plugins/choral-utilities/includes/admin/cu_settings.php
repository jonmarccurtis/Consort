<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

require_once(plugin_dir_path(__FILE__).'/../common/cu_options.php');

/**
 * Class CuAdminSettings
 * Provides Admin Settings page
 * For Absence Sheet and Rehearsal Notes
 */
class CuAdminSettings
{
    private $options;

    private $page_name;
    private $active_tab;
    private $actions;

    private $primary_rehearsals;
    private $assoc_rehearsals;
    private $consort_week;
    private $current_season;
    private $admin_override;
    private $test_family;

    public function __construct()
    {
        $this->options = new CuOptions();

        $this->page_name = 'cu-settings';

        $tabs = array();
        if (CuAdminSettings::is_admin())
            $tabs['admin'] = 'Administration';
        $tabs['absence'] = 'Absence Page';
        $this->active_tab = new CuActiveTabField($tabs);

        $this->actions = array(
            'reset_absence' => new CuResetAbsenceAction(
                'admin',
                'reset-absence-page',
                'Reset Absence Page',
                $this->options)
        );

        // Field provider classes
        $this->primary_rehearsals = new CuDateField(
            'absence',
            'primary-rehearsal-dates',
            'Primary Rehearsal Dates');
        $this->assoc_rehearsals = new CuDateField(
            'absence',
            'associate-rehearsal-dates',
            'Associate Rehearsal Dates');
        $this->consort_week = new CuDateField(
            'absence',
            'consort-week-start-date',
            'Consort Week Start Date', true);

        $this->current_season = new CuCheckboxField(
            'admin',
            'current-season-active',
            'Current Season',
            'Turn on Current Season view');
        $this->admin_override = new CuCheckboxField(
            'admin',
            'admin-override-current-season',
            'Current Season Admin',
            'Override Current Season for Administrators');

        $this->test_family = array(
            'board' => new CuTestFamilyField('admin', 'test-board', 'Test Board', 'tboard'),
            'web' => new CuTestFamilyField('admin', 'test-web', 'Test Web Assistant', 'tweb'),
            'singer' => new CuTestFamilyField('admin', 'test-singer', 'Test Singer', 'tsinger'),
            'member' => new CuTestFamilyField('admin', 'test-member', 'Test Member', 'tmember'),
            'admin' => new CuTestFamilyField('admin', 'test-admin', 'Test Admin', 'tccboda'));

    }

    public static function is_admin() {
        // WP really messes this up.  Can't even use is_super_admin() because
        // that includes s2member_level4.  We ONLY want those in the administrator role.
        // return in_array('administrator', wp_get_current_user()->roles);
        //
        // Update 3/20 - in order to enable BOD members to activate the emergency Admin
        // member, "Test Admin", this page has to be accessible to all board members.
        // It is possible for a non-Admin (BOD member) to activate Test Admin.
        return count(array_intersect(array('s2member_level4', 'administrator'),
                wp_get_current_user()->roles)) >= 1;
    }

    public function create_page()
    {
        add_action('admin_enqueue_scripts', array($this, 'add_admin_scripts'));
        add_options_page(
            'Chorale Utilities',
            'Chorale Utilities',
            'access_s2member_level3',
            $this->page_name,
            array($this, 'render_page'));
        add_action('admin_init', array($this, 'register_settings_init'));

        // Check to see if an action status needs to be shown, param needs to be unique
        if (isset($_GET['cu_ar'])) {
            // Only one currently used is 'updated'
            if ($_GET['cu_ar'] == 'updated')
                add_action('admin_notices', array($this, 'show_updated'));
        }
    }

    public function add_admin_scripts($page) {
        if ($page == 'settings_page_' . $this->page_name) {
            wp_enqueue_script('cu-settings-script',
                plugins_url('/../../assets/js/cu_settings.js', __FILE__));
        }
    }

    /**
     * The created list of fields depends on which tab is showing
     * They share a single register_setting() call, and thus a single validation
     * callback.  Otherwise 'rnotes' was causing a WP 'Option page not found' error
     */
    public function register_settings_init()
    {
        // Check to see if this is a CuAction callback
        foreach($this->actions as $action)
            $action->handle();

        // register_setting( $option_group, $sanitize_callback )
        $this->options->register_setting($this->page_name,
            array($this, 'cu_settings_validate'));

        switch ($this->active_tab->cur_tab()) {
            case 'absence':
                $this->_register_absence_tab();
                break;
            case 'admin':
                $this->_register_admin_tab();
                break;
            default:
                break;
        }

        // All tab pages have the hidden tab field for tracking tab in callbacks
        $this->_register_common_section();
    }

    public function show_updated() {
        echo '<div class="updated notice is-dismissible"><p><b>Data has been reset.</b></p></div>';
    }

    private function _add_field($section, $field, $args = array()) {
        $args['value'] = $this->options->get_option($field->slug());
        $args['name'] = $this->options->get_path($field->section(), $field->slug());
        add_settings_field(
            $field->field(),
            $field->label(),
            array($field, 'render'),
            $this->page_name,
            $section,
            $args);
    }

    // ABSENCE TAB PAGE
    private function _register_absence_tab () {
        $section = 'cu-absence-settings-section';
        add_settings_section(
            $section,
            'Report Absence Page',
            array($this, 'render_absence_section_info'),
            $this->page_name
        );

        $this->_add_field($section, $this->primary_rehearsals);
        $this->_add_field($section, $this->assoc_rehearsals);
        $this->_add_field($section, $this->consort_week,
            array('desc' => 'Enter date for Monday of Consort Week.'));
    }

    private function _register_admin_tab() {
        // Redundant - the tab itself is already restricted
        if (CuAdminSettings::is_admin()) {
            $section = 'cu-admin-settings-section';
            add_settings_section(
                $section,
                'Administrative Actions',
                array($this, 'render_admin_section_info'),
                $this->page_name
            );
            $this->_add_field($section, $this->actions['reset_absence']);

            $section2 = 'cu-season-section';
            add_settings_section(
                $section2,
                'Current Season Settings',
                array($this, 'render_season_section_info'),
                $this->page_name
            );
            $this->_add_field($section2, $this->current_season);
            $this->_add_field($section2, $this->admin_override);

            $section3 = 'cu-test-family-section';
            add_settings_section(
                $section3,
                'Test Family Settings',
                array($this, 'render_test_family_section_info'),
                $this->page_name
            );
            foreach ($this->test_family as $member) {
                $this->_add_field($section3, $member);
            }
        }
    }

    private function _register_common_section()
    {
        $section = 'cu-tab-settings-section';
        add_settings_section(
            $section,
            '',              // HIDDEN
            function() {},
            $this->page_name
        );

        // Hidden Active Tab field - so we know what fields should be present in a POST
        $this->_add_field($section, $this->active_tab,
            array('class' => 'sl-header hidden'));
    }

    public function render_absence_section_info() {
        echo 'Enter rehearsal dates as a comma delimited list - m/d only. For example: 5/29,6/3,6/10.';
    }

    public function render_admin_section_info() {
        echo 'These actions completely remove all related data from the database. 
            They should only be used at the end of the season, and after the data has been saved (TBD).';
    }

    public function render_season_section_info() {
        echo 'In addition to turning the season off/on below you must also:<br>&nbsp; &nbsp; ADD/REMOVE "3" IN 
            Dashboard => Frontier => Post Settings => Exclude Categories<br>
            This blocks the ability to post news to the Singer News category (ID=3).
            (But existing Singer News postings will still show up under "My News Articles",
            just as "Past Concerts" continues to show up under Admin\'s articles.)';
    }

    public function render_test_family_section_info() {
        echo 'Activate the Test Family members by setting switching their roles between "Inactive"
            and their respective testing roles.<br>
            <em>Warning: Leaving any of these users active can be a security risk.  They can also
            appear in lists and emails.<br>Do not leave active except when actually using the account.</em>';
    }

    public function cu_settings_validate($fields) {
        $options = new CuWorkingOptions();
        $input = new CuWorkingOptions($fields);

        if ($this->active_tab->is('absence', $input)) {
            $this->primary_rehearsals->validate($options, $input);
            $this->assoc_rehearsals->validate($options, $input);
            $this->consort_week->validate($options, $input);

        } else if ($this->active_tab->is('admin', $input)) {
            $this->current_season->validate($options, $input);
            $this->admin_override->validate($options, $input);
            foreach($this->test_family as $member)
                $member->handle($input);
        }

        return $options->validated_options();
    }

    public function render_page()
    {
        $this->renderHtml();
        $this->renderJS();
    }

    private function renderHtml()
    {
        echo '
        <h1>Chorale Utilities Settings</h1>
        <h2 class="nav-tab-wrapper">';
        foreach($this->active_tab->tabs() as $slug => $label) {
            echo '
            <a href="?page=' . $this->page_name . '&tab=' . $slug . '" class="nav-tab '
                . ($this->active_tab->is($slug) ? 'nav-tab-active' : '')
                . '">' . $label . '</a>';
        }
        echo '
        </h2>
        
        <form id="cu-settings-form" method="post" action="options.php">';

        settings_fields($this->page_name);
        do_settings_sections($this->page_name);

        submit_button('Save Changes');

        echo '
        </form>';
    }

    private function renderJS()
    {
        /*
        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[
        ';

        switch ($this->active_tab->cur_tab()) {
            default:
                break;
        }

        echo $js.'
        // ]]></script>';
        */
    }
}

class CuWorkingOptions extends CuOptionsBase {

    private $storage;

    public function __construct($src = null)
    {
        // If $src is not provided make a copy of the options from the database
        $this->storage = ($src == null) ? get_option($this->wp_name) : $src;
        parent::__construct();

        // Additional fields, not stored in the database
        $this->_register_option('tab', 'active-tab', 'absence');
        $this->_register_option('admin', 'test-board', false);
        $this->_register_option('admin', 'test-web', false);
        $this->_register_option('admin', 'test-singer', false);
        $this->_register_option('admin', 'test-member', false);
        $this->_register_option('admin', 'test-admin', false);
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

/** CuField classes
 *
 * CuField (abstract) - base class for fields and actions
 *
 *     CuActiveTabField - tracks which tab is active, for callbacks
 *
 *     CuOptionField (abstract) - settings field, stored in the database
 *         CuDateField - fields containing m/d dates
 *         CuCheckBoxField - fields containing a checkbox
 *
 *     CuAction (abstract) - not part of WP settings, calls back to handle() methods
 *         CuResetAbsenceAction - clears absence data
 *
 *     CuTestFamilyField - checkbox for Test Family role setting
 */


/**
 * Class CuField
 * Base class for all options fields
 */
abstract class CuField
{
    protected $section, $slug, $label, $field;

    public function __construct($section, $slug, $label) {
        $this->section = $section;
        $this->slug = $slug;
        $this->label = $label;
        $this->field = 'cu-' . $this->slug . '-field';
    }

    abstract public function render($args);

    public function section() { return $this->section; }
    public function slug() { return $this->slug; }
    public function label() { return $this->label; }
    public function field() { return $this->field; }
}

/**
 * Class CuActiveTabField
 * Provides a hidden field that indicates which tab was active in AJAX calls
 */
class CuActiveTabField extends CuField
{
    private $tabs;
    private $cur_tab;
    private $error_sent;

    /**
     * CuActiveTabField constructor.
     * @param $tabs array of tabs [slug => label]
     *              first element is the default tab
     */
    public function __construct($tabs)
    {
        $this->error_sent = false;
        $this->tabs = $tabs;
        $default = array_keys($tabs)[0];

        // This setting is only valid for GET requests, and is overridden
        // if calling the is_active methods from a POST.  The difference
        // is determined by whether the $input param is set.
        $this->cur_tab = (isset($_GET['tab'])) ? $_GET['tab'] : $default;

        if (!CuAdminSettings::is_admin() && $this->cur_tab == 'admin')
            $this->cur_tab = $default;

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

abstract class CuOptionField extends CuField {

    protected $valid, $value;

    public function add_error($errno, $msg) {
        add_settings_error($this->field, $this->slug, 'ERROR in '
            . $this->label . ' = [' . $this->value . ']: ' . $msg, 'error');
        $this->valid = false;
    }

    public function validate(&$options, $input) {
        $this->value = $input->get_option($this->slug);   // Original value for error messages
        $this->valid = true;
        $value = $this->_clean_and_validate($this->value);
        if ($this->valid)
            $options->update_option($this->slug, $value);
    }

    // Return sanitized value.  Calling add_error sets the valid flag to false
    abstract protected function _clean_and_validate($value);
}


/**
 * Class CuDateField
 * Dates are text fields with a format of m/d only
 * Single is one date, otherwise multiple dates in comma delimited list
 */
class CuDateField extends CuOptionField
{
    private $validator, $single;

    public function __construct($section, $slug, $label, $single = false) {
        parent::__construct($section, $slug, $label);
        $this->validator = new CuDateValidator(array($this, 'add_error'));
        $this->single = $single;
    }

    public function render($args)
    {
        $width = $this->single ? '60px' : '300px';
        echo '
        <input type="text" style="width:' . $width . '" name="'
            . $args['name'] . '" value="' . $args['value'] . '"/>';
        if (isset($args['desc'])) {
            echo '
        <span class="description">' . $args['desc'] . '</span>';
        }
    }

    protected function _clean_and_validate($value) {
        $value = sanitize_text_field($value);
        if (!empty($value)) {
            $value = str_replace(' ', '', $value);  // don't want any spaces
            if ($this->single)
                $this->validator->validate_date($value);
            else
                $this->validator->validate_dates($value);
        }
        return $value;
    }
}

/**
 * Class CuCheckBoxField
 * Checkbox based on Option data
 */
class CuCheckboxField extends CuOptionField
{
    private $box_text;

    public function __construct($section, $slug, $label, $box_text) {
        $this->box_text = $box_text;
        parent::__construct($section, $slug, $label);
    }

    public function render($args)
    {
        $checked = $args['value'] ? 'checked="checked"' : '';
        echo '<label for="' . $args['name'] . '">
            <input name="' . $args['name'] . '" type="checkbox" ' . $checked . '>
            ' . $this->box_text . '</label><br>';
    }

    protected function _clean_and_validate($value) {
        return $value == 'on';
    }
}

/**
 * Class CuTestFamilyField
 * Checkbox which controls role of Test Family members
 */
class CuTestFamilyField extends CuField
{
    private $id, $role;

    public function __construct($section, $slug, $label, $username) {
        parent::__construct($section, $slug, $label);

        $userdata = get_user_by('login', $username);

        // REDUCTION - if Test Family doesn't exist
        if (!$userdata)
            return;

        $this->id = $userdata->ID;

        // This call will fail if s2member is not present
        if (function_exists('get_user_field'))
            $this->role = get_user_field('s2member_access_label', $this->id);
        else
            $this->role = 'Inactive';  // Just to keep from crashing - totally incorrect info
    }

    public function render($args) {
        $checked = $this->role != 'Inactive' ? 'checked="checked"' : '';
        echo '<label for="' . $args['name'] . '">
            <input name="' . $args['name'] . '" type="checkbox" ' . $checked . '>
            Set "' . $this->label . '" as an active member.</label><br>';
    }

    static private $label_roles = array(
        'Inactive' => 'subscriber',
        'Test Member' => 's2member_level1',
        'Test Singer' => 's2member_level2',
        'Test Web Assistant' => 's2member_level3',
        'Test Board' => 's2member_level4',
        'Test Admin' => 'administrator',
        );

    public function handle($input) {
        $value = $input->get_option($this->slug);
        $new_role = null;
        if ($value == 'on') {
            if ($this->role == 'Inactive')
                $new_role = self::$label_roles[$this->label];
        } else {
            if ($this->role != 'Inactive')
                $new_role = self::$label_roles['Inactive'];
        }
        if ($new_role) {
            $user = new WP_User($this->id);
            $user->set_role($new_role);
        }
    }
}

abstract class CuAction extends CuField
{
    private $nonce, $action;
    protected $options;

    public function __construct($section, $slug, $label, $options)
    {
        parent::__construct($section, $slug, $label);
        $this->nonce = '_' . substr($section, 3);
        $this->action = 'cu-' . $this->slug;
        $this->options = $options;
    }

    public function render($args) {
        // The action is performed via a link, using this URL which goes
        // goes back to this page and is caught by handle()
        $url = add_query_arg(array(
            'action' => $this->action,
            $this->nonce => wp_create_nonce($this->action)),
            admin_url('options-general.php?page=' . $_GET['page']));

        $this->_render($args, $url);
    }

    abstract protected function _render($args, $url);

    public function handle () {
        if (!isset($_GET['action']) || $_GET['action'] != $this->action)
            return;

        check_admin_referer($this->action, $this->nonce);

        // This might be overkill ...
        if (!CuAdminSettings::is_admin())
            return;

        $result = $this->_handle();  // Let the kids do their thing

        // Need to reload the page without the clear action in the URL, and show the results
        $uri = 'options-general.php?page=' . $_GET['page'] . '&tab=' . $this->section;
        if (!empty($result))
            $uri .= '&cu_ar=' . $result;
        header('location:' . admin_url($uri));
        exit();
    }

    abstract protected function _handle();
}

/**
 * Class CuResetAbsenceAction
 * Provides the link (only visible to Admin) to reset all Absence Data
 */
class CuResetAbsenceAction extends CuAction
{
    protected function _render($args, $url)
    {
        echo '
        <a href="' . $url . '" class="action-warning">Reset Absence Data</a>
        <p class="description">Warning: Clicking this will delete all Absence records.</p>';
    }

    protected function _handle() {
        // clear out all the data
        $this->options->reset_section_defaults('absence');

        // Do all members, in case any current singers dropped out during the season
        $members = get_users();
        $key = $this->options->absence_meta_key();
        foreach ($members as $member) {
            $id = $member->data->ID;
            if (!empty(get_user_meta($id, $key, true)))
                update_user_meta($id, $key, '');
        }
        return 'updated';
    }
}


