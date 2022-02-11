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

class RnSingerPage extends RnAjaxHandler
{
    private $table, $options, $nonce, $vps, $singer_vps, $songs, $dirs, $singer, $hb_start,
        $date_format, $time_format, $singer_ovps;

    public function __construct()
    {
        // These are only needed when rendering the shortcode, not for AJAX calls
        if (!wp_doing_ajax()) {
            wp_enqueue_style('rn_common');
            wp_enqueue_style('rn_frontend');
            wp_enqueue_script('rn_common');
            wp_enqueue_script('rn_singer_page');
            wp_enqueue_script('jquery-color');   // added by WP script-loader, needed by animate outlineColor
        }

        require_once(plugin_dir_path(__FILE__).'/../common/section_widget.php');
        require_once(plugin_dir_path(__FILE__).'/../common/rn_table.php');
        require_once(plugin_dir_path(__FILE__).'/../common/rn_options.php');
        require_once(plugin_dir_path(__FILE__).'/../common/rn_database.php');
        $this->table = new RnTable();
        $this->options = new RnOptions();
        $this->nonce = array();

        $dirs = RnSingersDB::get_staff('dir');
        $this->dirs = array();
        foreach($dirs as $id => $dir)
            $this->dirs[$id] = $dir['display_name'];

        $this->singer = RnSingersDB::get_singer(get_current_user_id());

        $songs = $this->options->get_option('song-list');
        $this->singer_vps = SectionWidget::singer_vps($this->singer, $songs);

        $this->singer_ovps = $this->singer['vp_overrides'];

        $this->songs = array();
        foreach($songs as $song) {
            $this->songs[$song[RN::ID]] = $song;
        }

        $this->date_format = get_option('date_format');
        $this->time_format = get_option('time_format');

        // Prepare the VPS list for testing
        $this->vps = array();
        foreach($this->singer_vps as $song_id => $vps_str) {
            if (isset($this->singer_ovps[$song_id])) {
                // Apply overrides
                $vps_str = $this->singer_ovps[$song_id];
            }
            $vps_list = explode(',', $vps_str);
            $vps_test = array();
            foreach($vps_list as $vps) {
                $vps_test[] = ',' . $vps . ',';
            }
            $this->vps[$song_id] = $vps_test;
        }
    }

    public function html()
    {
        // Collect the settings and data
        $this->add_columns();
        $this->add_settings();
        $this->add_notes();

        // Spit it out
        $html = $this->add_singer_edit_form();
        $html .= $this->add_question_form();
        $html .= $this->add_track_form();
        $html .= $this->table->get_html();
        $html .= $this->getJS();
        return $html;

    }

    private function add_singer_edit_form() {
        $html = '
<div id="rn-sef-overlay" class="rn-sef-overlay">
    <div class="rn-sef-overlay-content-front">
        <a class="corner-help" href="/rn-refman?man=singer-ref-vp-form" title="Help manual for this form" target="_blank">?</a>
        <div id="singer-edit-title-front">
            <div class="floatright">' . $this->singer['primary_vp'] .'</div>
            <div>Voice Parts for ' . get_user_field('first_name', get_current_user_id()) . '</div>
        </div>
        <div class="vp-instructions">Director voice part assignments are used to set the "My note" column and filter which Rehearsal Notes you see. This can be customized by checking the override boxes and using your own settings.</div>
        <form id="sef-edit-form" class="edit-form">
            <div class="singer-songs">
                <table id="singer-table" class="singer-table"></table>
            </div>
            <div class="overlay-footer">
                <div><span id="sef-message" class="rn-error" style="display:none"></span></div>
                <div>
                    <button type="button" id="btn-sef-cancel" onclick="rn_sef.close_edit_form()">Cancel</button>
                    <button type="button" id="btn-sef-save" onclick="rn_sef.save_form()">Save</button>
                </div>
                <input type="hidden" id="singer_id" name="singer_id" value="' .
                    $this->singer['singer_id'] . '" />
            </div>
        </form>
    </div>
</div>';

        $songs = array();
        foreach ($this->songs as $song)
            $songs[] = [$song[RN::ID], $song[RN::VP]];

        $table_html = '<tr><th></th><td><em><b>Director assignments</b></em></td>';
        $table_html .= '<td colspan="2"><em><b>Check box to override</b></em></td></tr>';

        $sw = new SectionWidget();
        foreach ($this->songs as $song) {
            $id = $song[RN::ID];
            $vp = $song[RN::VP];
            $vps = $this->singer_vps[$id];
            $ovps = $vps;
            $over_disp = ' style="display:none"';
            $checked = '';
            if (isset($this->singer_ovps[$id])) {
                $ovps = $this->singer_ovps[$id];
                $over_disp = '';
                $checked = ' checked="checked"';
            }
            $dir = $sw->html($vp, $this->singer_vps[$id], $id, true);
            $over = $sw->html($vp, $ovps, $id);
            $table_html .= '<tr><th>' . addslashes($song[RN::NAME]) . '</th>';
            $table_html .= '<td>' . $dir . '</td>';
            $table_html .= '<td><input type="checkbox" class="ocb-check" data-id="' . $id . '"' . $checked . ' /></td>';
            $table_html .= '<td id="over-' . $id . '"' . $over_disp . '>' . $over . '</td></tr>';
        }

        $html .= '
            <script type="text/javascript"><!-- // --><![CDATA[
            function rn_sef($) {
                function set_ocb_check_events() {
                    $(".ocb-check").off("click", rn_sef.ocb_check_event);
                    $(".ocb-check").on("click", rn_sef.ocb_check_event);
                }
                function ocb_check_event() {
                    var id = $(this).data("id");
                    var $over = $("#over-"+id);
                    if ($over.is(":visible"))
                        $over.hide();
                    else 
                        $over.show();
                } 
                rn_sef.ocb_check_event = ocb_check_event;
                
                function set_sef_change_events() {
                    $("#sef-edit-form :input").off("change", rn_sef.sef_change_event);
                    $("#sef-edit-form :input").on("change", rn_sef.sef_change_event);
                }
                function sef_change_event() {
                    rn_stab.setFormDirty();
                }
                rn_sef.sef_change_event = sef_change_event;
                
                var songs = ' . json_encode($songs) . ';
                function edit_singer() {
                    $("#sef-message").hide();
                    rn_stab.clearFormDirty();
                    $("#singer-table").html(\'' . $table_html . '\');
                    rn_common.set_vps_control_events();
                    set_ocb_check_events();
                    set_sef_change_events();
                    $("#rn-sef-overlay").css("display","flex");
                }
                rn_sef.edit_singer = edit_singer;

                function close_edit_form() {
                    if (rn_stab.isFormDirty()) {
                        if (!confirm("Changes you made may not be saved."))
                            return;
                    }
                    rn_stab.clearFormDirty();
                    $("#rn-sef-overlay").hide();
                }
                rn_sef.close_edit_form = close_edit_form;

                function save_form() {
                    var data = {};
                    var pars = $("#sef-edit-form").serializeArray();
                    for (var i = 0; i < pars.length; i++) {
                        data[pars[i]["name"]] = pars[i]["value"];
                    }
                    $(".vp-selected").each(function() {
                        if ($(this).is(":visible"))
                            data[$(this).attr("id")] = true;
                    });
                    rn_common.send_request("rn_ssef", data, rn_sef.save_ok, rn_sef.save_fail);
                }
                rn_sef.save_form = save_form;

                function save_ok(res) {
                    rn_stab.clearFormDirty();
                    location.reload();
                }
                rn_sef.save_ok = save_ok;

                function save_fail(msg) {
                    $("#sef-message").html(msg);
                    $("#sef-message").show();
                }
                rn_sef.save_fail = save_fail;
            }
            rn_sef(jQuery);
            ';

        $html .= '
            // ]]></script>';

        return $html;
    }

    private function add_question_form() {
        $msr_help = "Enter the starting measure for your question. It is only used for sorting the notes.\\r\\n\\r\\nLeave blank if the question refers to the entire song.\\r\\n\\r\\nDo not enter a range. Put the starting measure here and then indicate the range, or ending measure, in the question.";
        $vp_help = "Section indicates which voice parts the question refers to.  Click to remove/add voice parts.\\r\\n\\r\\nSelected voice parts are highlighted in blue.";
        $html = '
<div id="rn-edit-overlay" class="rn-overlay">
    <div id="rn-overlay-main" class="rn-overlay-content">
        <a class="corner-help" href="/rn-refman?man=singer-ref-ask-question-form" title="Help manual for this form" target="_blank">?</a>
        <div id="edit-title"></div>
        <form id="edit-form" class="edit-form">
            <table id="edit-table">
                <tr class="song-attrs">
                    <th>SONG</th>
                    <th class="measure">MEASURE [<a href="#" onclick="alert(\'' . $msr_help . '\');return false;">?</a>]</th>
                    <th class="measure">SECTION [<a href="#" onclick="alert(\'' . $vp_help . '\');return false;">?</a>]</th>
                </tr>
                <tr id="song-attrs">
                    <td>
                        <select id="sel-songs" name="song_id">';

        foreach($this->songs as $id => $song) {
            $html .= '
                            <option id="song-' . $id . '" value="'. $id . '"' .
                                '>' . $song[RN::NAME] . '</option>';
            }

        $html .= '
                        </select><br>
                        Director: <span id="dir-name"></span>
                        <input type="hidden" id="dir_id" name="dir_id">
                    </td>
                    <td class="measure">
                        <input class="measure" type="text" id="measure" name="measure" min="0"><br>
                        <span id="start-ms"></span> - <span id="end-ms"></span>
                    </td>
                    <td id="parts"></td>
                </tr>
                <tr>
                    <td colspan="5">';
        $html .= do_shortcode('[accordion][accordion_item title="formatting standards"][rn_refman man="seg-rnote-format"][/accordion_item][/accordion]');

        ob_start();
        wp_editor('', 'note-editor', array('textarea_rows' => 5, 'teeny' => false, 'media_buttons' => false,
            'quicktags' => RnOptions::is_admin(), // Only show Text tab for Admin
            'tinymce' => array(
                'toolbar1' => 'bold,italic,underline,strikethrough,hr,bullist,numlist,alignleft,aligncenter,alignright,forecolor,pastetext,removeformat,charmap,undo,redo,link',
                'toolbar2' => '',
                'content_css' => plugins_url('/../../assets/css/rn_common.css', __FILE__)
            )));
        $html .= ob_get_clean();

        $html .= '
                    </td>
                </tr>
            </table>
            <div class="overlay-footer">
                <div id="message"></div>
                <div class="buttons">
                    <button type="button" id="btn-cancel" onclick="rn_stab.close_edit_form()">Cancel</button>
                    <button type="button" id="btn-close" onclick="rn_stab.close_edit_form()">Close</button>
                    <button type="button" id="btn-save" onclick="rn_stab.save_rnote()">Send</button>
                    <button type="button" id="btn-add" onclick="rn_stab.save_rnote()">Send Another</button>
                </div>
                <input type="hidden" id="note_ts" name="note_ts" />
            </div>
        </form>
    </div>
</div>';
        return $html;
    }

    private function add_track_form() {
        $html = '
<div id="rn-history-overlay" class="rn-overlay">
    <div class="rn-overlay-content">
        <a class="corner-help" href="/rn-refman?man=singer-ref-track-questions-form" title="Help manual for this form" target="_blank">?</a>
        <div id="history-edit-title" class="edit-title"></div>
        <div class="instructions">Click on most recent Editor to send them an email about a question.</div>
        <table id="history-header" class="rn-history-table">
            <tr>
                <th class="hist-rev">rev</th>
                <th class="hist-song">Song</th>
                <th class="hist-ms">ms</th>
                <th class="hist-parts">Parts</th>
                <th class="hist-note">Note</th>
                <th class="hist-loc">Location</th>
                <th class="hist-dir">Director</th>
                <th class="hist-auth">Editor</th>
                <th class="hist-date">Date, Time</th>
            </tr>
        </table>
        <table id="edit-table" class="rn-history-table track-table">
        </table>
        <div class="overlay-footer">
            <div id="hist-message"></div>
            <div class="buttons">
                <button type="button" id="btn-close" onclick="rn_stab.close_track()">Close</button>
            </div>
        </div>
    </div>
</div>';

        return $html;
    }

    private function add_columns() {
        $this->table->add_column('done',
            array('title' => 'Click to sort',
                'width' => '40',
                'custom-filters' => array(
                    'yes' => 'doneFilter',
                    'no' => 'doneFilter')));

        $this->table->add_column('song',
            array('label' => 'Song',
                'title' => 'Click to sort',
                'data-placeholder' => 'Filters ...',
                'width' => '15%'));

        $this->table->add_column('measure',
            array('label' => 'ms',
                'title' => 'Starting Measure: Filter shows +/-10, must be typed quickly',
                'width' => '40',
                'sorter' => 'ms_sorter',
                'custom-filters' => 'msFilter'));

        $this->table->add_column('my_note',
            array('label' => 'My note',
                'title' => 'Click to sort',
                'width' => '40'));

        $this->table->add_column('note_vps',
            array('label' => 'Parts',
                'title' => 'Click to sort',
                'width' => '70',
                'custom-filters' => 'partsFilter'));

        $this->table->add_column('note',
            array('label' => 'Rehearsal Note',
                'sorter' => false));
    }

    private function get_directions_left() {
        $html = '
            <table id="dir-left">
                <tr>
                    <td><a href="#" id="my-note-filter"
                        title="View/Set voice parts per song">View Voice Parts, Adjust "My note" filter</a></td>
                </tr>
            </table>
        ';
        return $html;
    }

    private function add_settings()
    {
        $this->table->set_option('dynamic selection', true);
        $this->table->set_option('hide columns', wp_is_mobile());
        $this->table->set_option('directions-left', $this->get_directions_left());
        $this->table->set_option('directions', '<em>Click row</em>: Highlight (<em>alt-click multiple</em>)');
        $this->table->set_option('header', '<a class="corner-help" href="/rn-refman?man=singer-ref-rehearsal-notes-page" title="Help manual for this page" target="_blank">?</a>');

        // For a preset dropDown selector
        $this->table->add_filter_preset('MY NOTES, NOT DONE (Rec)',
            array('my_note' => 'X', 'done' => 'no'),
            array('song', 'measure'));

        $this->table->add_filter_preset('My notes, Done',
            array('my_note' => 'X', 'done' => 'yes'),
            array('song', 'measure'));

        $this->table->add_filter_preset('My notes (all)',
            array('my_note' => 'X'),
            array('song', 'measure'));

        $this->table->add_filter_preset('All notes',
            array(),
            array('song', 'measure'));

        $this->table->add_filter_preset(' - All notes, Not Done',
            array('done' => 'no'),
            array('song', 'measure'));

        $this->table->add_filter_preset(' - All notes, Done',
            array('done' => 'yes'),
            array('song', 'measure'));

        $this->table->set_option('remember settings', 'singer');
        $this->table->set_init_filter('MY NOTES, NOT DONE (Rec)');

        // Measure filter returns +/- 10 nearby measures
        $this->table->add_filter('msFilter','
            var cell = (e == "") ? 0 : parseInt(e);
            var filter = parseInt(f);
            return ((filter - 10) <= cell && cell <= (filter + 10));
        ');

        $this->table->add_filter('partsFilter','
            if (e == "(all)")
                return true;
            for (var i = 0; i < f.length; i++) {
                if (!e.includes(f.charAt(i)))
                    return false;
            }
            return true;
        ');

        $this->table->add_filter('doneFilter','
            var filter = (data.filter == "yes");
            var cb = $(data.$cells[0]).children("input").is(":checked");
            return (filter == cb);
        ');

        $this->table->add_sorter('ms_sorter', 'numeric', '
            return (s == "") ? 0 : s;
        ');

        // Button fcn must be defined in getJS()

        $this->table->add_button(array(
            'label' => 'ASK QUESTION',
            'fcn' => 'rn_stab.show_edit_form(\'question\')',
            'tooltip' => 'Create a new question for Directors'
        ));

        $this->table->add_button(array(
            'label' => 'TRACK QUESTIONS',
            'fcn' => 'rn_stab.track_questions()',
            'tooltip' => 'Get the status on questions you have asked online'
        ));

        $this->table->add_button(array(
            'label' => 'PRINT',
            'fcn' => 'rn_stab.printable_page()',
            'tooltip' => 'Show notes on a printable page'
        ));
    }

    private function add_notes()
    {
        // Record the first heartbeat time - immediately before the first query
        $this->hb_start = current_time('mysql');
        $notes = RnNotesDB::get_published_notes();
        $done = RnDoneDB::get_current_singer_done();

        foreach($notes as $note) {
            $note['done'] = in_array($note['note_id'], $done);
            $row = $this->create_row_data($note);
            $this->table->add_row($note['note_id'], $row);
        }
    }

    private function create_row_data($note) {

        $checked = $note['done'] ? ' checked="checked"' : '';
        $done = '<input type="checkbox" id="dcb-' . $note['note_id'] . '" class="done_check" data-id="'
            . $note['note_id'] . '"' . $checked . ' />';

        $note_id = $note['note_id'];

        $mine = '';
        $test_vps = ',' . $note['note_vps'] . ',';
        foreach($this->vps[$note['song_id']] as $vps) {
            if (strpos($test_vps, $vps) !== false) {
                $mine = 'X';
                break;
            }
        }

        $note_vps = '(all)';
        if ($this->songs[$note['song_id']][RN::VP] != $note['note_vps'])
            $note_vps = implode(',&thinsp;', explode(',', $note['note_vps']));

        // Data keys must be the same as the column ids
        $row = array(
            'data' => array(
                'done' => $done,
                'song' => $this->songs[$note['song_id']][RN::NAME],
                'measure' => $note['measure'],
                'mine' => $mine,
                'note_vps' => $note_vps,
                'note' => '<div class="note-outer">' . $note['note'] .
                    '<div class="note-marker"></div></div>'),

            'class' => array(
                'done' => 'centertext',
                'mine' => 'centertext',
                'measure' => 'centertext',
                'note_vps' => 'centertext vps-width',
                'note' => 'quiet_p'),

            'tr_pars' => array(
                'class' => 'master_row',
                'data-id' => $note_id)
        );
        return $row;
    }

    private function wrap($note_id, $slug, $text, $data = array(), $class=null) {
        $attrs = '';
        foreach($data as $key => $val) {
            $attrs .= ' data-' . $key . '="' . $val . '"';
        }
        $class = $class != null ? ' class="' . $class . '"' : '';
        return '<span id="' . $slug . $note_id . '"'. $class . $attrs . '>' . $text . '</span>';
    }

    private function getJS() {
        $nts = RnSingersDB::get_staff('nt');
        $adrs = array();
        foreach($nts as $id => $nt)
            $adrs[] = $nt['user_email'];
        $adrs = implode(',', $adrs);
        $adrs = explode('@', $adrs);

        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[';

        $js .= '
            var adrs = ' . json_encode($adrs) . ';
            var dirs = ' . json_encode($this->dirs) . ';
            var songs = ' . json_encode($this->songs) . ';
            var rn_role = ' . json_encode($this->singer) . ';
            var hb_ts = "' . $this->hb_start . '";
            var ajaxurl = "' . admin_url('admin-ajax.php') . '";
        ';
        $js .= $this->get_ajax_JS();
        $js .= RN::renderJS();

        return $js.'
        // ]]></script>';
    }

    protected function check_user_can_handle_ajax() {
        if ($this->singer === false)
            self::send_fatal_error(13001); // non-NT: Access denied
    }

    protected function do_ajax_request($action)
    {
        if (method_exists($this, $action) === false)
            self::send_fatal_error(13002); // Action method not defined
        $this->$action();
    }

    public function rn_singer_save_question() {
        $fields = array();
        $fields['song_id'] = $this->validate_int('song_id', 13102, function($val) {
            if (!in_array($val, array_keys($this->songs)))
                $this->send_fatal_error(13102.5);   // Non-existent song #
        });
        $fields['dir_id'] = $this->validate_int('dir_id', 13103, function($val) {
            if (!in_array($val, array_keys($this->dirs)))
                $this->send_fatal_error(13103.5);   // Not a director's ID
        });
        $fields['measure'] = $this->validate_int('measure', 13104, function($val) {
            if ($_POST['measure'] != '' && strval($val) != $_POST['measure']) {
                if (strpos($_POST['measure'], '-') !== false)
                    $this->send_user_error('Enter start measure only, indicate range in the text');
                $this->send_user_error('Invalid start measure number');
            }
        }, false);  // do not check for valid integer, (doing it here)
        $sm = $fields['measure'];
        $song = $this->songs[$fields['song_id']];
        if ($sm != 0 && $song[RN::SM] > 0) {
            if ($sm < $song[RN::SM] || $sm > $song[RN::EM]) {
                $this->send_user_error('Start measure must be between ' . $song[RN::SM]
                    . ' and ' . $song[RN::EM] . '.');
            }
        }
        $fields['note_vps'] = $this->validate_text('note_vps', 13105, function($val) {
            if (empty($val))
                $this->send_user_error('Must select at least one section/voice part');
        });
        $vp_test = ',' . $song[RN::VP] . ',';
        $vps = explode(',', $fields['note_vps']);
        foreach($vps as $vp) {
            if (strpos($vp_test, ',' . $vp . ',') === false)
                $this->send_fatal_error(13105.5);  // Invalid characters in vp
        }

        $fields['location'] = 'nt-inbox';
        $fields['note'] = $this->validate_text('rn_note', 13106, function($val) {
            if (empty($val) || (strpos($val, 'Write question here') !== false))
                $this->send_user_error('Question content cannot be empty');
        });

        $res = RnNotesDB::add_note($fields);
        if ($res === false)
            $this->send_fatal_error(13110);  // DB failed to add note

        wp_send_json_success('');
    }


    public function rn_singer_sef_save() {
        if (!isset($_POST['singer_id']))
            self::send_fatal_error(13201); // Missing Singer ID
        $singer_id = intval($_POST['singer_id']);
        if ($singer_id != $_POST['singer_id'] || $singer_id < 1)
            self::send_fatal_error(13202); // Invalid Singer ID
        // We will find out if it is an actual singer_id in the DB request

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
        $overs = array();
        foreach($vps as $id => $vps_list)
            $overs[$id] = implode(',', $vps_list);

        require_once(plugin_dir_path(__FILE__).'/../common/rn_database.php');
        $singer = array(
            'vp_overrides' => $overs);

        $res = RnSingersDB::update_singer($singer_id, $singer);
        if ($res === false)
            self::send_fatal_error(13203);  // Failed to update overrides

        wp_send_json_success($id);
    }

    public function rn_singer_heartbeat() {
        $last_ts = $this->validate_text('hb_ts', 14101, function($val) {  // Missing HB TS
            if (strtotime($val) === false)
                $this->send_fatal_error(14102); // Invalid HB TS
        });

        $active = $this->validate_bool('hb_active', 14103);   // Missing Active flag
        if ($active)
            RnOnlineDB::update_time(false);

        $this_ts = current_time('mysql');
        $notes = RnNotesDB::get_changes($last_ts, $this_ts);
        $done = RnDoneDB::get_current_singer_done();  // Add in the done setting

        $results = array(
            'new_ts' => $this_ts,
            'changes' => array());
        foreach($notes as $note) {
            $note['done'] = in_array($note['note_id'], $done);

            // Don't know which are new/modified - so return both
            $new = $this->prep_results(array('state' => 'new', 'note' => $note));
            $mod = $this->prep_results(array('state' => 'modified', 'note' => $note));
            $results['changes'][] = array(
                'note_id' => $note['note_id'],
                'new' => $new,
                'mod' => $mod);
        }
        wp_send_json_success($results);
    }

    public function rn_singer_track_questions() {
        $id = get_current_user_id();
        $name = get_user_field('first_name', $id);

        $questions = RnNotesDB::get_rows('revision_id', 'location = \'nt-inbox\' AND revision_id <> 0 AND author_id = ' . $id);
        if ($questions === false)
            $this->send_fatal_error(15101); // Failed to get questions

        if (count($questions) == 0) {
            $html = 'No questions found';
        } else {
            $html = '';
            foreach($questions as $question) {
                $cols = 'song_id, measure, note_vps, note, location, author_id, time';
                $history = RnNotesDB::get_rows($cols, 'revision_id = ' . $question['revision_id']);
                if ($history === false)
                    $this->send_fatal_error(15102); // Failed to get history

                // The above query can return other questions for Note Takers, because they
                // can end up authoring questions in the NoteTakers inbox that they did not begin.
                $first = reset($history);
                if ($first['author_id'] != get_current_user_id())
                    continue;

                $rev = 1;
                $html .= '<tbody class="question">';
                $first = true;
                foreach ($history as $note) {
                    $song = $this->songs[$note['song_id']][RN::NAME];
                    $note_name = $song . ', m' . $note['measure'] . ' - ' . $note['note_vps'];

                    $auth_data = get_userdata($note['author_id']);
                    list($adr, $srv) = explode('@', $auth_data->user_email);
                    $auth_name = $auth_data->display_name;
                    if ($first) {
                        // The singer does not need their own email address in the first rev,
                        // and if that's the only rev - they need to send inquiries to the Note Takers.
                        $adr = 'NTS';
                        $auth_name = 'Note Takers';
                        $first = false;
                    }
                    $author = '<span title="Send email" class="send-adr" data-srv="' .
                        $srv . '" data-note="' . $note_name . '" data-adr="' .
                        $adr .  '">' . $auth_name . '</span>';

                    $note_vps = '(all)';
                    if ($this->songs[$note['song_id']][RN::VP] != $note['note_vps'])
                        $note_vps = implode(',&thinsp;', explode(',', $note['note_vps']));

                    $fields = array(
                        'hist-rev' => $rev++,
                        'hist-song' => $song,
                        'hist-ms' => ($note['measure'] == 0) ? '' : $note['measure'],
                        'hist-parts' => $note_vps,
                        'hist-note' => $note['note'],
                        'hist-loc' => $this->get_location($note['location']),
                        'hist-dir' => $this->dirs[$this->songs[$note['song_id']][RN::DIR]],
                        'hist-auth' => $author,
                        'hist-date' => $this->format_ts(strtotime($note['time'])),
                    );
                    $html .= '<tr>';
                    foreach ($fields as $class => $val) {
                        $html .= '<td class="' . $class . '">' . $val . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody>';
            }
        }

        $res = array(
            'name' => $name,
            'html' => $html
        );
        wp_send_json_success($res);
    }

    private function format_ts($ts) {
        $text = str_replace('2019', '', date_i18n($this->date_format, $ts));
        $text .= date_i18n($this->time_format, $ts);
        return $text;
    }

    private function get_location($loc) {
        static $locs = array(
            'nt-inbox' => 'Note Taker\'s Inbox',
            'dir-inbox' => 'Director\'s Inbox',
            'review' => 'For Review',
            'published' => 'Published',
            'trash' => 'Trash');

        return $locs[$loc];
    }

    public function rn_singer_done()
    {
        $action = $this->validate_text('rnda', 13301, function ($val, $prefix) {
            if (!in_array($val, array('get', 'set', 'clr'))) {
                $this->send_fatal_error($prefix . '.5');  // Invalid RN Done Action
            }
        });

        if ($action == 'get') {
            $notes = RnDoneDB::get_current_singer_done();
            if ($notes === false)
                $this->send_fatal_error(13302);  // Failed to get list of Done notes
            wp_send_json_success($notes);
            return;
        }

        $note_id = $this->validate_int('note_id', 13303, function ($val, $prefix) {
            if ($val < 0)
                $this->send_fatal_error($prefix . '.5');  // Invalid Note_ID
        });

        if ($action = 'set') {
            $done = $this->validate_int('done', 13304, function ($val, $prefix) {
                if ($val != 1 && $val != 0)
                    $this->send_fatal_error($prefix . '.5');  // Done is a boolean
            });
            RnDoneDB::set_done(get_current_user_id(), $note_id, $done);
            wp_send_json_success(array('note_id' => $note_id, 'done' => $done));
            return;
        }

        // Action == clr, so make sure this is legit
        $notes = RnNotesDB::get_published_notes();
        foreach ($notes as $note) {
            if ($note['note_id'] == $note_id) {
                RnDoneDB::clear_all_done_for($note_id);
                wp_send_json_success('');
                return;
            }
        }
        $this->send_fatal_error(13305);  // Invalid Note ID
    }


    /**
     * @param $res = array(state, note)
     * Prepares a single note for request response
     * If state =
     *     new - add full row HTML
     *     saved - add fields HTML
     *     modified - add fields HTML and warning HTML
     */
    private function prep_results($res) {
        $res['note']['measure'] = $res['note']['measure'] > 0 ? $res['note']['measure'] : '';
        $row = $this->create_row_data($res['note']);

        if ($res['state'] == 'new')
            $res['html'] = $this->table->get_row($res['note']['note_id'], $row);

        else {  // saved or modified
            $res['html'] = $this->table->get_fields($row);
        }
        return $res;
    }

    private function validate_int($name, $err_prefix, $validate_fcn = null, $check_int = true) {
        if (!isset($_POST[$name]))
            $this->send_fatal_error($err_prefix . '.1');  // Missing Field
        $val = intval($_POST[$name]);
        if ($check_int) {
            if (strval($val) != $_POST[$name])
                $this->send_fatal_error($err_prefix . '.2');  // Invalid Field contents
        }

        if ($validate_fcn)
            call_user_func($validate_fcn, $val, $err_prefix);

        return $val;
    }

    private function validate_text($name, $err_prefix, $validate_fcn = null) {
        if (!isset($_POST[$name]))
            $this->send_fatal_error($err_prefix . '.1');  // Missing Field
        $val = stripslashes($_POST[$name]);

        if ($validate_fcn)
            call_user_func($validate_fcn, $val, $err_prefix);

        return $val;
    }

    private function validate_bool($name, $err_prefix) {
        $val = $this->validate_text($name, $err_prefix, function($val, $err_prefix) {
            if (!in_array($val, array('true','false')))
                $this->send_fatal_error($err_prefix . '.5');  // Invalid Boolean
        });
        return ($val == 'true');
    }
}
