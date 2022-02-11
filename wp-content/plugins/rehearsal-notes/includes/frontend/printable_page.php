<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

require_once(plugin_dir_path(__FILE__).'/../common/ajax_handler.php');

class RnPrintablePage extends RnAjaxHandler
{
    private $options, $vps, $singer_vps, $songs, $singer;

    public function __construct()
    {
        require_once(plugin_dir_path(__FILE__).'/../common/rn_database.php');
        $this->singer = RnSingersDB::get_singer(get_current_user_id());

        // These are only needed when rendering the shortcode, not for AJAX calls
        if (!wp_doing_ajax()) {
            wp_enqueue_style('rn_common');
            wp_enqueue_style('rn_frontend');
            wp_enqueue_script('rn_common');

            require_once(plugin_dir_path(__FILE__).'/../common/section_widget.php');
            require_once(plugin_dir_path(__FILE__).'/../common/rn_options.php');
            $this->options = new RnOptions();

            $songs = $this->options->get_option('song-list');
            $this->singer_vps = SectionWidget::singer_vps($this->singer, $songs);

            $this->songs = array();
            foreach($songs as $song) {
                $this->songs[$song[RN::ID]] = $song;
            }

            // Prepare the VPS list for testing
            $this->vps = array();
            foreach($this->singer_vps as $song_id => $vps_str) {
                $vps_list = explode(',', $vps_str);
                $vps_test = array();
                foreach($vps_list as $vps) {
                    $vps_test[] = ',' . $vps . ',';
                }
                $this->vps[$song_id] = $vps_test;
            }
        }
    }

    public function html()
    {
        $notes = get_query_var('nt');
        $have_notes =  ($notes != '');

        $html = '
        <table>
            <tr>
                <td width="200px"><a href="/rehearsal-notes"><- back to Rehearsal Notes</a></td>';
        if ($have_notes) {
            $html .= '
                <td>1. Use your browser\'s "Print" button to print this page.<br>
                    2. <em>IMPORTANT!</em> Once printed, click button on right to set these notes "Done" -><br>
                    3. Use the printed copy to mark your music.
                </td>
                <td><button id="done_btn" class="button" onclick="rn_print.set_all_done()">Set all Done</button>
                    <div id="done_msg">&nbsp;</div>
                </td>';
        }
        $html .= '
            </tr>
        </table>';

        $now = strtotime(current_time('mysql'));
        $time = str_replace(date('Y'), '', date_i18n(get_option('date_format'), $now));
        $time .= date_i18n(get_option('time_format'), $now);
        $name = wp_get_current_user()->display_name;
        $html .= '<div class="edit-title"><small>Rehearsal Notes for ' . $name . ', printed: ' . $time . '</small></div>';

        $html .= '
        <table class="print-table">
            <tr>
                <th width="40">Done</th>
                <th width="200">Song</th>
                <th width="40">ms</th>
                <th width="40">My<br>note</th>
                <th width="100">Parts</th>
                <th>Rehearsal Note</th>
            </tr>';

        if (!$have_notes) {
            $html .= '<tr><td colspan="7">No notes have been selected.</td></tr>';
        } else {
            $note_ids = explode(',', $notes);
            foreach ($note_ids as $note_id) {
                if ($note_id == '')
                    continue;
                if (!is_numeric($note_id))
                    continue;
                $note = RnNotesDB::get_note($note_id);
                if ($note == false)
                    continue;

                $mine = '';
                $note_vps = ',' . $note['note_vps'] . ',';
                foreach($this->vps[$note['song_id']] as $vps) {
                    if (strpos($note_vps, $vps) !== false) {
                        $mine = 'X';
                        break;
                    }
                }
                $ms = ($note['measure'] == 0) ? '' : $note['measure'];
                $vps = implode(',&shy', explode(',', $note['note_vps']));

                $html .= '
            <tr>
                <td></td>
                <td>' . $this->songs[$note['song_id']][RN::NAME] . '</td>
                <td class="centertext">' . $ms . '</td>
                <td class="centertext">' . $mine . '</td>
                <td class="centertext">' . $vps . '</td>
                <td class="quiet_p">' . $note['note'] . '</td>
            </tr>';
            }
        }

        $html .= '
        </table>
        ';

        $html .= '
            <script type="text/javascript"><!-- // --><![CDATA[
            function rn_print($) {
                function set_all_done() {
                    $("#done_msg").html("<span class=\'rn-pending\'>processing ...</span>");
                    $("#done_btn").prop("disabled", true);
                    rn_common.send_request("rn_pdn", {notes: "' . $notes . '"}, rn_print.set_ok, rn_print.set_fail);
                }
                rn_print.set_all_done = set_all_done;
                
                function set_ok(res) {
                    $("#done_msg").html("<span class=\'rn-success\'>Success</span>");
                }
                rn_print.set_ok = set_ok;
                
                function set_fail(res) {
                    $("#done_msg").html("<span class=\'rn-error\'>"+res+"</span>");
                }
                rn_print.set_fail = set_fail;
            } 
            rn_print(jQuery);
            ';

        $html .= 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";';
        $html .= $this->get_ajax_JS();

        $html .= '
            // ]]></script>';

        return $html;
    }

    // AJAX Callback support

    protected function check_user_can_handle_ajax() {
        if ($this->singer === false)
            self::send_fatal_error(20001); // non-singer: Access denied
    }

    protected function do_ajax_request($action)
    {
        if (method_exists($this, $action) === false)
            self::send_fatal_error(20002); // Action method not defined
        $this->$action();
    }

    public function rn_print_done() {
        $notes = $this->validate_text('notes', 20101);
        if ($notes != '') {
            $singer_id = get_current_user_id();
            $done_list = RnDoneDB::get_current_singer_done();
            $note_list = explode(',', $notes);
            foreach($note_list as $note_id) {
                if ($note_id != '') {
                    if (!is_numeric($note_id))
                        $this->send_fatal_error(20102);  // Invalid note_id

                    if (!in_array($note_id, $done_list)) {
                        RnDoneDB::set_done($singer_id, $note_id, true);
                    }
                }
            }
        }
        wp_send_json_success(array('notes' => $notes, 'done' => true));
    }

    private function validate_text($name, $err_prefix, $validate_fcn = null) {
        if (!isset($_POST[$name]))
            $this->send_fatal_error($err_prefix . '.1');  // Missing Field
        $val = stripslashes($_POST[$name]);

        if ($validate_fcn)
            call_user_func($validate_fcn, $val, $err_prefix);

        return $val;
    }

}
