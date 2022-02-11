<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

require_once(plugin_dir_path(__FILE__).'/rn_settings_action.php');

/**
 * Class RnAdminTab
 */
class RnAdminTab extends RnTab
{
    private $reset, $desc;

    protected function __construct($active_tab)
    {
        parent::__construct($active_tab);
        $this->reset = new RnResetDataField('reset', 'Select what to delete');
        $this->desc = new RnResetDataDesc('desc', 'Sections to be deleted:');
    }

    protected function register_tab()
    {
        // Redundant - the tab itself is already restricted
        if (RnOptions::is_admin()) {
            $section = 'rn-admin-settings-section';
            $this->add_section($section, 'Administrative Actions');
            $this->add_field($section, $this->reset);
            $this->add_field($section, $this->desc);
        }
    }

    public function render_section_info()
    {
        echo '<p><a href="/rn-refman?man=admin-ref-admin" target="_blank">Instruction Manual</a></p>';
        echo '<div class="rn-error">WARNING: This action permanently removes Rehearsal Notes data from the database! 
            <br>Normally, this should only be done at the end of the season, and after the data has been saved.
            </div><br>
            <div>Save copies of Rehearsal Notes data by going to each of these pages and clicking the DOWNLOAD button:</div><div style="margin-left:15px">Song List, Singer List, Reporting, and Admin -&gt; Edit Rehearsal Notes (be sure Presets=All Notes).</div><br>
            <div>While it is possible to select individual sections to be deleted, dependent sections will be automatically included.
            </div> ';
    }

    protected function render_submit_button()
    {
        submit_button('Delete Data', 'delete button-primary');
    }


    protected function validate(&$options, $input) {
        $sections = array('(None were selected)');
        $reset = $input->get_option('reset');
        if ($reset != 'none') {
            require_once(plugin_dir_path(__FILE__).'/../common/rn_database.php');

            $sections = array();
            $sections[] = 'Singer Done markings';
            RnDoneDB::clear_table();

            if (in_array($reset, array('notes', 'songs', 'singers'))) {
                $sections[] = 'Rehearsal Notes';
                RnNotesDB::clear_table();
            }
            if (in_array($reset, array('songs', 'singers'))) {
                $sections[] = 'Song List';
                $options->reset_to_defaults();
            }
            if ($reset == 'singers') {
                $sections[] = 'Singer List';
                RnSingersDB::clear_table();
            }
        }
        add_settings_error('rn-reset', 'reset',
            '<em>The following sections were deleted</em>:<br> '. implode('<br> ', array_reverse($sections)));

        return $options;
    }

    protected function get_js()
    {
        return '
        function rn_admin_reset($) {
            $(document).ready(function() {
                $("#rn-reset-sel").on("change", function() {
                    switch($(this).val()) {
                        case "none": 
                            $("#rn-desc-singers").hide();
                            $("#rn-desc-songs").hide();
                            $("#rn-desc-notes").hide();
                            $("#rn-desc-done").hide();
                            break;
                        case "singers": 
                            $("#rn-desc-singers").show();
                            $("#rn-desc-songs").show();
                            $("#rn-desc-notes").show();
                            $("#rn-desc-done").show();
                            break;
                        case "songs":
                            $("#rn-desc-singers").hide();
                            $("#rn-desc-songs").show();
                            $("#rn-desc-notes").show();
                            $("#rn-desc-done").show();
                            break;
                        case "notes":
                            $("#rn-desc-singers").hide();
                            $("#rn-desc-songs").hide();
                            $("#rn-desc-notes").show();
                            $("#rn-desc-done").show();
                            break;
                        case "done":
                            $("#rn-desc-singers").hide();
                            $("#rn-desc-songs").hide();
                            $("#rn-desc-notes").hide();
                            $("#rn-desc-done").show();
                            break;
                    }
                    $(".rn-reset-desc").show();
                });
                $("#rn-settings-form").on("submit", function(e) {
                    if ($("#rn-reset-sel").val() != "none") {
                        if (!confirm("You are about to permanently delete this data!  Are you sure?"))
                            e.preventDefault();
                    }
                });
                rn_dirty_enabled = false;
            });
        }
        rn_admin_reset(jQuery);
        ';
    }
}

class RnResetDataField extends RnField
{
    public function render($args)
    {
        echo '
        <select id="rn-reset-sel" name="' . $args['name'] . '" class="rn-reset">
            <option value="none"></option>
            <option value="singers">All Rehearsal Note data</option>
            <option value="songs">Song List</option>
            <option value="notes">Rehearsal Notes</option>
            <option value="done">Singer Done markings</option>
        </select>';
    }
}

class RnResetDataDesc extends RnField
{
    public function render($args)
    {
        $img = plugins_url('/../../assets/img/delete.gif', __FILE__);
        echo '
        <table class="rn-reset-desc" style="display:none">
            <tr>
                <td width="20"><span id="rn-desc-singers"><img src="' . $img . '"></span></td>
                <td>Singer List</td>
            </tr>
            <tr>
                <td><span id="rn-desc-songs"><img src="' . $img . '"></span></td>
                <td>Song List</td>
            </tr>
            <tr>
                <td><span id="rn-desc-notes"><img src="' . $img . '"></span></td>
                <td>Rehearsal Notes</td>
            </tr>
            <tr>
                <td><span id="rn-desc-done"><img src="' . $img . '"></span></td>
                <td>Singer Done markings</td>
            </tr>
        </table>
        ';
    }
}

