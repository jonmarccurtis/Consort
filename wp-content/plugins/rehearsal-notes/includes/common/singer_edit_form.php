<?php
/**
 * Created by IntelliJ IDEA.
 * User: joncu
 * Date: 4/25/19
 * Time: 3:45 PM
 */
if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

require_once(plugin_dir_path(__FILE__).'/ajax_handler.php');
require_once(plugin_dir_path(__FILE__).'/section_widget.php');
require_once(plugin_dir_path(__FILE__).'/rn_options.php');

class SingerEditForm extends RnAjaxHandler {

    private $sw, $songs;

    public function __construct()
    {
        wp_enqueue_style('rn_common');
        wp_enqueue_script('rn_common');

        $this->sw = new SectionWidget();

        $options = new RnOptions();
        $this->songs = $options->get_option('song-list');
    }

    /**
     * Only current usage is with $admin = true.  This was originally designed to be used for both
     * the Singer Settings form and the Singer's VP My Note Filter form.  But the two were too different.
     * Now, the other Singer form is in singer_page.php.
     */
    public function html($admin = false) {
        $html = '
<div id="rn-edit-overlay" class="rn-sef-overlay">
    <div class="rn-sef-overlay-content">
        <a class="corner-help" href="rn-refman?man=admin-ref-vp-form" title="Help manual for this form" target="_blank">?</a>
        <div id="singer-edit-title">Voice Parts for <span class="singers-name"></span></div>
        <form id="edit-form" class="edit-form">
            <table class="singer-table-header">
                <tr>
                    <th>Primary voice part</th>
                    <td><select id="singer_pvp" name="singer_pvp">
                        <option id="pvp-S1" value="S1">S1</option>
                        <option id="pvp-S2" value="S2">S2</option>
                        <option id="pvp-A1" value="A1">A1</option>
                        <option id="pvp-A2" value="A2">A2</option>
                        <option id="pvp-T1" value="T1">T1</option>
                        <option id="pvp-T2" value="T2">T2</option>
                        <option id="pvp-B1" value="B1">B1</option>
                        <option id="pvp-B2" value="B2">B2</option>
                        <option id="pvp-none" value=""></option>
                    </select></td>
                </tr>';

        if ($admin) {
            $lock_msg = 'Directors who have Rehearsal Notes assigned to them cannot be removed. (See ? help)';
            $html .= '
                <tr>
                    <th>RNotes positions</th>
                    <td><input type="checkbox" id="is_singer" name="is_singer" />Singer &nbsp;
                        <input type="checkbox" id="is_nt" name="is_nt" />NT &nbsp;
                        <input type="hidden" id="orig_nt" name="orig_nt">
                        <span id="dir_lock" onclick="alert(\'' . $lock_msg . '\');" title="' . $lock_msg . '"></span>
                        <input type="checkbox" id="is_dir" name="is_dir" />Dir &nbsp;
                        <input type="hidden" id="orig_dir" name="orig_dir">
                        <input type="checkbox" id="is_admin" name="is_admin" />Admin 
                        <input type="hidden" id="orig_admin" name="orig_admin">
                    </td>
                </tr>';
        }
        $html .= '
            </table>
            <div class="singer-songs">
                <table class="singer-table">
        ';

        foreach ($this->songs as $song) {
            $vps = $this->sw->html($song[RN::VP], '', $song[RN::ID]);
            $html .= '
                    <tr>
                        <th>' . $song[RN::NAME] . '</th>
                        <td>' . $vps . '</td>
                    </tr>';
        }

        $html .= '
                </table>
            </div>
            <div class="overlay-footer">
                <div><span id="message" class="rn-error" style="display:none"></span></div>
                <div class="buttons">
                    <button type="button" ' . ($admin ? 'class="button button-primary"' : '') .
            ' id="btn-cancel" onclick="rn_sef.close_edit_form()">Cancel</button>
                    <button type="button" ' . ($admin ? 'class="button button-primary"' : '') .
            ' id="btn-save" onclick="rn_sef.save_form()">Save</button>
                </div>
                <input type="hidden" id="admin" name="admin" value="' . $admin . '"/>
                <input type="hidden" id="singer_id" name="singer_id" />
                <input type="hidden" id="orig_pvp" name="orig_pvp" />
                <input type="hidden" id="last_pvp" />
            </div>
        </form>
    </div>
</div>';

        $songs = array();
        foreach ($this->songs as $song)
            $songs[] = [$song[RN::ID], $song[RN::VP]];

        // Gather the VPS from the screen/form, because they can be edited
        // with the VPS dialog. So don't have to do an AJAX call to get the most
        // current version.  The rest is passed in at page load, or update with
        // this dialog.
        //
        // The last_pvp is to track when the PVP is changed, so that the default
        // per-song settings can be updated to the new default.  This is not the
        // same as the other default calculations.  They consider the entire
        // song's list of PVPs all together.  This must handle them separately.
        //
        $html .= '
            <script type="text/javascript"><!-- // --><![CDATA[
            function rn_sef($) {
                $(document).ready(function() {
                    rn_dirty_enabled = false;
                    rn_common.set_vps_control_events();
                    
                    $("#singer_pvp").on("change", function() {
                        var new_pvp = $("#singer_pvp").val();
                        var old_pvp = $("#last_pvp").val();
                        if (new_pvp != old_pvp) {
                            var new_abbr = new_pvp.charAt(0);
                            var old_abbr = old_pvp.charAt(0);
                            var abbr_chg = (new_abbr != old_abbr);
                            
                            $("[id^=vp-]").each(function() {
                                var id = $(this).attr("id").split("-");
                                var vp = id[2];
                                var sel = $(this).hasClass("vp-selected");
                                if (sel && (vp == old_pvp || (abbr_chg && vp == old_abbr))) {
                                    $(this).removeClass("vp-selected");
                                    var song_id = id[1];
                                    var $new_option = $("#vp-"+song_id+"-"+new_pvp);
                                    if ($new_option.length) {
                                        $new_option.addClass("vp-selected");
                                    } else if (abbr_chg) {
                                        $new_option = $("#vp-"+song_id+"-"+new_abbr);
                                        if ($new_option.length) {
                                            $new_option.addClass("vp-selected");
                                        }
                                    }
                                }
                            });
                            $("#last_pvp").val(new_pvp);
                        }
                    });
                });
                
                var songs = ' . json_encode($songs) . ';
                function edit_singer(name, data) {
                    $("#message").hide();
                    $(".singers-name").html(name);
                    $("#singer_pvp").val(data.primary_vp);
                    $("#orig_pvp").val(data.primary_vp);
                    $("#last_pvp").val(data.primary_vp);
                    $("#is_singer").prop("checked", data.is_singer);
                    $("#is_nt").prop("checked", data.is_nt);
                    $("#orig_nt").val(data.is_nt ? 1 : 0);
                    $("#is_dir").prop("checked", data.is_dir);
                    $("#orig_dir").val(data.is_dir ? 1 : 0);
                    $("#is_admin").prop("checked", data.is_admin);
                    $("#orig_admin").val(data.is_admin ? 1 : 0);
                    $("#singer_id").val(data.singer_id);
                    $("#rn-edit-overlay").css("display","flex");
                    
                    $("[id^=vps-"+data.singer_id+"-]").each(function() {
                        var song_id = $(this).attr("id").split("-")[2];
                        var vps = $(this).html();
                        rn_common.set_vps_selection(vps, song_id);
                    });
                    
                    if (data.dir_lock) {
                        $("#is_dir").hide();
                        $("#dir_lock").show();
                    } else {
                        $("#is_dir").show();
                        $("#dir_lock").hide();
                    }
                }
                rn_sef.edit_singer = edit_singer;

                function close_edit_form() {
                    $("#rn-edit-overlay").hide();
                }
                rn_sef.close_edit_form = close_edit_form;
                
                function save_form() {
                    var data = {};
                    var pars = $("#edit-form").serializeArray();
                    for (var i = 0; i < pars.length; i++) {
                        data[pars[i]["name"]] = pars[i]["value"];
                    }
                    $(".vp-selected").each(function() {
                        data[$(this).attr("id")] = true;
                    });
                    rn_common.send_request("sef_sv", data, rn_sef.save_ok, rn_sef.save_fail);
                }
                rn_sef.save_form = save_form;
                
                function save_ok(res) {
                    if ($("#admin").val()) {
                        var $row = $("#rntr-"+res.singer_id);
                        
                        var $res = null;
                        $row.children().each(function() {
                            if ($(this).is(":hidden")) {
                                if ($res == null)
                                    $res = $("<tr>" + res.html + "</tr>");
                                    
                                var hid_col = $(this).data("column");
                                $res.children("[data-column=\'" + hid_col + "\']").prop("style", "display:none");
                            }
                        });
                        if ($res != null)
                            res.html = $res.html();
                        
                        $row.html(res.html);  
                        rn_table.set_selected_rows([$row]);
                        rn_table.update_table();
                        rn_js.set_vps_settings_events();
                        close_edit_form();
                    } else {
                        window.location.href += "&sel="+res;
                    }
                }
                rn_sef.save_ok = save_ok;
                
                function save_fail(msg) {
                    $("#message").html(msg);
                    $("#message").show();
                }
                rn_sef.save_fail = save_fail;
            }
            rn_sef(jQuery);
            ';

        $html .= $this->get_ajax_JS();

        $html .= '
            // ]]></script>';

        return $html;
    }

    protected function check_user_can_handle_ajax() {
        if (!current_user_can('access_s2member_level2'))
            self::send_fatal_error(2100); // Non-member: Access denied
    }

    protected function do_ajax_request($action) {
        if (method_exists($this, $action) === false)
            self::send_fatal_error(2100.1); // Action method not defined
        $this->$action();
    }

    public function rn_sef_save() {
        if (!isset($_POST['admin']))
            self::send_fatal_error(2101); // Admin setting missing
        $admin = $_POST['admin'];

        if (!isset($_POST['singer_id']))
            self::send_fatal_error(2102); // Missing Singer ID
        $id = intval($_POST['singer_id']);
        if ($id != $_POST['singer_id'] || $id < 1)
            self::send_fatal_error(2103); // Invalid Singer ID
        // We will find out if it is an actual singer_id in the DB request

        if (!isset($_POST['orig_pvp']))
            self::send_fatal_error(2104); // Missing Original Singer Primary Voice Part
        $orig_pvp = $_POST['orig_pvp'];
        if (!in_array($orig_pvp, ['S1','S2','A1','A2','T1','T2','B1','B2','']))
            self::send_fatal_error(2105); // Invalid Original Singer Primary Voice Part

        if (!isset($_POST['singer_pvp']))
            self::send_fatal_error(2106); // Missing Singer Primary Voice Part
        $full_pvp = $_POST['singer_pvp'];
        if (!in_array($full_pvp, ['S1','S2','A1','A2','T1','T2','B1','B2','']))
            self::send_fatal_error(2107); // Invalid Singer Primary Voice Part
        $abbr_pvp = substr($full_pvp, 0, 1);

        $new_pvp = ($orig_pvp != $full_pvp);

        // These are optional checkbox settings
        $is_singer = isset($_POST['is_singer']);
        $is_nt = isset($_POST['is_nt']);
        $orig_nt = isset($_POST['orig_nt']) ? !!$_POST['orig_nt'] : false;
        $is_dir = isset($_POST['is_dir']);
        $orig_dir = isset($_POST['orig_dir']) ? !!$_POST['orig_dir'] : false;
        $is_admin = isset($_POST['is_admin']);
        $orig_admin = isset($_POST['orig_admin']) ? !!$_POST['orig_admin'] : false;

        $new_nt = ($orig_nt != $is_nt);
        $new_dir = ($orig_dir != $is_dir);
        $new_admin = ($orig_admin != $is_admin);

        // Search for exceptions in the resulting set of checked boxes
        // The boxes have the form of vp-{song_id}-{vp}
        // and only those that are checked are sent.
        $vps = array();
        foreach($_POST as $post => $val) {
            if (substr($post, 0, 3) == 'vp-') {
                $setting = explode('-', $post);
                if (count($setting) == 3) {
                    $song_id = $setting[1];
                    $vp = $setting[2];
                    if (isset($vps[$song_id])) {
                        $vps[$song_id][] = $vp;
                    } else {
                        $vps[$song_id] = array($vp);
                    }
                }
            }
        }

        $full_test = ',' . $full_pvp . ',';
        $abbr_test = ',' . $abbr_pvp . ',';
        $exceptions = array();
        foreach($this->songs as $song) {
            $song_id = $song[RN::ID];
            $song_test = ',' . $song[RN::VP] . ',';
            if (!isset($vps[$song_id])) {
                // The singer is not singing in this song
                if (strpos($song_test, $full_test) !== false || strpos($song_test, $abbr_test) !== false) {
                    // And the song does include the singer's default PVP
                    // So this is an exception
                    $exceptions[$song_id] = array( // Not singing in this song
                        'song_id' => $song_id,
                        'exceptions' => '');
                }
            } else {
                // The singer is singing at least one VP in this song
                foreach($vps[$song_id] as $vp) {  // Singer can have > 1 vp in a song
                    if ($vp != $full_pvp && $vp != $abbr_pvp) {
                        // It has at least one exception
                        $exceptions[$song_id] = array ( // need the entire list of vps
                            'song_id' => $song_id,
                            'exceptions' => implode(',', $vps[$song_id]));
                        break;
                    }
                }
            }
        }

        require_once(plugin_dir_path(__FILE__).'/rn_database.php');
        $singer = array(
            'is_singer' => $is_singer,
            'is_nt' => $is_nt,
            'is_dir' => $is_dir,
            'is_admin' => $is_admin,
            'vp_exceptions' => $exceptions);

        if ($new_pvp || $new_nt || $new_dir || $new_admin) {
            // HACK ALERT!  This is locked into s2member
            $fields = get_user_option('s2member_custom_fields', $id);
            if ($new_pvp) {
                $fields['voice'] = $full_pvp;
                $singer['primary_vp'] = $full_pvp;
            }
            if ($new_nt || $new_dir || $new_admin) {
                $pos = isset($fields['position']) ? $fields['position'] : array();
                if ($new_nt) {
                    if ($is_nt)
                        $pos[] = 'Note Taker';
                    else
                        $pos = array_merge(array_diff($pos, ['Note Taker']));
                }
                if ($new_dir) {
                    if ($is_dir)
                        $pos[] = 'Artistic Director';
                    else
                        $pos = array_merge(array_diff($pos, ['Artistic Director']));
                }
                if ($new_admin) {
                    if ($is_admin)
                        $pos[] = 'RNote Admin';
                    else
                        $pos = array_merge(array_diff($pos, ['RNote Admin']));
                }
                $fields['position'] = $pos;

                require_once(plugin_dir_path(__FILE__).'../admin/rn_settings_action.php');
                RnImportAction::update_capabilities($id, $singer);
            }
            update_user_option($id, 's2member_custom_fields', $fields);
        }

        $res = RnSingersDB::update_singer($id, $singer);
        if ($res === false)
            self::send_user_error('Failed to update Singer');

        if ($admin) {
            require_once(plugin_dir_path(__FILE__).'/../admin/rn_settings_singers.php');
            $rslf = new RnSingersListField('singers-list', '', new RnOptions());

            // Need a full copy of the singer to update the entire row
            // This avoids needing to reload the entire page
            $singer = RnSingersDB::get_singer($id);
            $res = array(
                'singer_id' => $id,
                'html' => $rslf->get_fields($singer));
            wp_send_json_success($res);
        } else {
            wp_send_json_success($id);
        }
    }

    // This is the small single-vps dialog, defined in
    // rn_settings_singers.php, called from rn_settings.js
    public function rn_sef_vps_save() {
        require_once(plugin_dir_path(__FILE__).'/rn_database.php');

        if (!isset($_POST['singer_id']))
            self::send_fatal_error(2202); // Missing Singer ID
        $singer_id = intval($_POST['singer_id']);
        if ($singer_id != $_POST['singer_id'] || $singer_id < 1)
            self::send_fatal_error(2203); // Invalid Singer ID
        $singer = RnSingersDB::get_singer($singer_id);
        if ($singer === false)
            self::send_fatal_error(2204); // Singer not found

        if (!isset($_POST['song_id']))
            self::send_fatal_error(2205); // Missing Song ID
        $song_id = intval($_POST['song_id']);
        if ($song_id != $_POST['song_id'] || $song_id < 1)
            self::send_fatal_error(2206); // Invalid Song ID
        // TODO - start saving songs as associative
        $song = null;
        foreach($this->songs as $a_song) {
            if ($a_song[RN::ID] == $song_id) {
                $song = $a_song;
                break;
            }
        }
        if ($song == null)
            self::send_fatal_error(2207); // Song not found
        $song_vps = $song[RN::VP];

        // Validate VPS
        if (!isset($_POST['vps']))
            self::send_fatal_error(2208); // Missing VPS
        $vps = $_POST['vps'];
        $vps_list = explode(',', $vps);
        if ($vps != '') {
            $vps_test = ',' . $song_vps . ',';
            foreach($vps_list as $vp) {
                if (strpos($vps_test, ',' . $vp . ',') === false)
                    self::send_fatal_error(2209); // Invalid VP
            }
        }

        // Is the VPS an exception?
        $is_exception = ($vps == '');  // Not singing the song is an exception
        $full_pvp = $singer['primary_vp'];
        $abbr_pvp = $full_pvp[0];
        if (!$is_exception) {
            foreach($vps_list as $vp) {
                if ($vp != $full_pvp && $vp != $abbr_pvp) {
                    $is_exception = true;
                    break;
                }
            }
        }

        // Is this a change from the existing exceptions?
        $is_changed = false;
        $exceptions = $singer['vp_exceptions'];
        $has_exception = isset($exceptions[$song_id]);
        if (!$is_exception) {
            if ($has_exception) {
                unset($exceptions[$song_id]);
                $is_changed = true;
            }
        } else { // is_exception
            if ($has_exception) {
                if ($vps != $exceptions[$song_id]['exceptions']) {
                    // Its a different exception
                    $exceptions[$song_id] = array(
                        'song_id' => $song_id,
                        'exceptions' => $vps);
                    $is_changed = true;
                }
            } else {
                $exceptions[$song_id] = array(
                    'song_id' => $song_id,
                    'exceptions' => $vps);
                $is_changed = true;
            }
        }

        if ($is_changed) {
            $res = RnSingersDB::update_singer($singer_id,
                array('vp_exceptions' => $exceptions));
            if ($res == false)
                self::send_fatal_error(2210); // Failed to update
        }
        wp_send_json_success(array(
            'singer_id' => $singer_id,
            'song_id' => $song_id,
            'vps' => $vps));
    }
}
