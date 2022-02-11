<?php

/**
 * Created by IntelliJ IDEA.
 * User: joncu
 * Date: 4/12/20
 * Time: 3:38 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

/**
 * Class WPProfileSettings
 * Modifies s2member User Profile custom fields which are shared by RNotes
 * in Settings -> Rehearsal Notes, Singer List tab.
 */
class WPProfileSettings
{
    /**
     * Called when a Singer's Profile is modified.  Copies any changes over to the
     * RNotes database.
     */
    static public function import_singer($id) {
        require_once(plugin_dir_path(__FILE__).'/rn_settings_action.php');
        RnImportAction::import_singers($id);
    }

    static public function customize_profile($user) {
        require_once(plugin_dir_path(__FILE__) . '/../common/rn_database.php');

        // See if the Director Position should be locked
        $lock_dir = false;
        $positions = get_user_field('position', $user->ID);
        if ($positions && in_array('Artistic Director', $positions)) { // This is an Artistic Director
            $dirs = RnNotesDB::get_ids_with_dependent_notes('dir_id');

            if (in_array($user->ID, $dirs)) {  // Director with RNotes - checkbox must be disabled/locked.
                $lock_dir = true;
            }
        }

        // See if PVP should be locked
        $lock_pvp = false;
        // Only check when current season is active (or Chorale Utilities is not loaded)
        $is_cs = class_exists('CurrentSeason', false) ? CurrentSeason::is_current_season() : true;
        if ($is_cs) {
            // Only lock if the current user is in the RN Singer List
            $singers = RNSingersDB::get_rows('singer_id');
            if (array_search($user->ID, array_column($singers, 'singer_id')) !== false) {
                $lock_pvp = true;
            }
        }

        // WARNING: These are hardcoded to s2member custom field names
        echo '
                <script type="text/javascript"><!-- // --><![CDATA[
                function rn_profile($) {
                    $(document).ready(function() {
                        var $pvp = $("#ws-plugin--s2member-profile-voice");
                        $pvp.width("30px");
            ';

        if ($lock_pvp) {
            echo '
                        $pvp.attr("disabled", true);
                        $("<div><em>The Primary Voice Part for currently active Singers can be set in Settings-&gt;Rehearsal Notes, <a href=\"/wp-admin/options-general.php?page=rn-settings&tab=singers\">Singers&nbsp;List&nbsp;tab</a>.</em></div>").insertAfter($pvp);';
        }

        if ($lock_dir) {
            // WARNING: This is hardcoded for the Artistic Director Position being index == 0!
            echo '
                        var $label = $("label[for=\'ws-plugin--s2member-profile-position-0\']");
                        $label.html($label.html() + " (<em>Locked: <a href=\"/rn-refman?man=admin-ref-vp-form\" target=\"_blank\">more info</a></em>)");
                        $("#ws-plugin--s2member-profile-position---0").attr("disabled", true);';
        }

        echo '
                    });
                }
                rn_profile(jQuery);
                // ]]></script>
            ';

        if ($lock_dir) {
            echo '
                <input type="hidden" name="ws_plugin__s2member_profile_position[]" value="Artistic Director" />>';
        }
    }

}