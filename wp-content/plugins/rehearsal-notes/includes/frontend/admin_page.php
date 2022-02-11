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

class RnAdminPage extends RnAjaxHandler
{
    private $table, $options, $nonce, $notetakers, $dirs, $songs, $user_id,
        $rn_role, $date_format, $time_format, $hb_start;

    public function __construct()
    {
        // These are only needed when rendering the shortcode, not for AJAX calls
        if (!wp_doing_ajax()) {
            wp_enqueue_style('rn_common');
            wp_enqueue_style('rn_frontend');
            wp_enqueue_style('genericons');
            wp_enqueue_script('rn_common');
            wp_enqueue_script('rn_admin_page');
            wp_enqueue_script('jquery-color');   // added by WP script-loader, needed by animate outlineColor
        }

        require_once(plugin_dir_path(__FILE__).'/../common/rn_table.php');
        require_once(plugin_dir_path(__FILE__).'/../common/rn_options.php');
        require_once(plugin_dir_path(__FILE__).'/../common/rn_database.php');
        $this->table = new RnTable();
        $this->options = new RnOptions();
        $this->nonce = array();

        $this->notetakers = RnSingersDB::get_staff();
        $this->dirs = array();
        $this->rn_role = null;
        $this->user_id = get_current_user_id();
        foreach($this->notetakers as $id => $nt) {
            if ($nt['is_dir'])
                $this->dirs[$id] = $nt['display_name'];
            if ($id == $this->user_id) {
                // This is a bit tricky - compressing 3 settings to 1.
                // There are 3 views/settings for the page/forms: DIR, ADMIN, and NT.
                // First DIR and ADMIN should never happen at the same time.  An Artistic
                // Director would not be the Rehearsal Notes Administrator.
                // If someone is both ADMIN and NT they should get the ADMIN view.  So ...
                $this->rn_role = $nt['is_dir'] ? 'DIR' : ($nt['is_admin'] ? 'ADMIN' : 'NT');
            }
        }

        $this->songs = array();
        foreach($this->options->get_option('song-list') as $song)
            $this->songs[$song[RN::ID]] = $song;

        // Make them smaller because of the limited column space
        //$this->date_format = get_option('date_format');
        //$this->time_format = get_option('time_format');
        $this->date_format = 'm/d';
        $this->time_format = 'H:i';
    }

    public function html()
    {
        // Collect the settings and data
        $this->add_header();
        $this->add_columns();
        $this->add_settings();
        $this->add_notes();

        // Spit it out
        $html = $this->add_edit_form();
        $html .= $this->add_history_form();
        $html .= $this->add_trash_form();
        $html .= $this->add_move_form();
        $html .= $this->table->get_html();
        $html .= $this->getJS();
        return $html;

    }

    private function add_header() {
        $help_id = $this->rn_role == 'DIR' ? 'dir-ref-edit-page' : 'nt-ref-edit-page';
        $html = '<a class="corner-help" href="rn-refman?man=' . $help_id . '" title="Help manual for this page" target="_blank">?</a>';
        $html .= '<div class="staff-list"><a class="nt-staff" href="#">Email staff</a> &nbsp; ';
        $html .= 'ONLINE <em>Singers</em>: <span class="active-singers">(none)</span>, <em>Staff</em>: ';
        $nts = array();
        foreach ($this->notetakers as $id => $nt) {
            $email = explode('@', $nt['user_email']);
            $nts[] = '<span class="staff-name send-adr" data-id="' . $id . '"'
                . ' data-srv="' . $email[1] . '" data-note=""'
                . ' title="Send email" data-adr="' . $email[0] .'">'
                . $nt['display_name'] . '</span>';
        }
        $html .= implode(', ', $nts);
        $html .= '</div>';
        $this->table->set_option('header', $html);
    }

    private function add_edit_form() {
        $msr_help = "Enter the starting measure for your question. It is only used for sorting the notes.\\r\\n\\r\\nLeave blank if the question refers to the entire song.\\r\\n\\r\\nDo not enter a range. Put the starting measure here and then indicate the range, or ending measure, in the question.";
        $vp_help = "Section indicates which voice parts the question refers to.  Click to remove/add voice parts.\\r\\n\\r\\nSelected voice parts are highlighted in blue.";
        $help_id = $this->rn_role == 'DIR' ? 'dir-ref-edit-form' : 'nt-ref-add-form';
        $html = '
<div id="rn-edit-overlay" class="rn-overlay">
    <div id="rn-overlay-warning">
        <div><em>WARNING</em>: This note has just been modifed by <span id="warning-author"></span>.  The new version is:
        </div>
        <table id="warning-table">
            <tr>
                <th>SONG</th>
                <th>MEASURE</th>
                <th>SECTION</th>
                <th>NOTE</th>
                <th>LOCATION</th>
                <th>DISCUSSION</th>
            </tr>
            <tr id="rntr-warning"></tr>
        </table>
        <div>Your options are:<ul>
            <li>Press "Cancel" to accept this new version.</li>
            <li>Press "Save" to replace this version with yours.</li>  
            <li>Combine both versions in the form below, then press "Save".</li>
        </ul>
        </div>
    </div>
    <div id="rn-overlay-main" class="rn-overlay-content">
        <a class="corner-help" href="rn-refman?man=' . $help_id . '" title="Help manual for this form" target="_blank">?</a>
        <form id="edit-form" class="edit-form">
            <div id="edit-title-bar">
                <div id="edit-title"></div>
                <div id="edit-title-author">On behalf of: 
                    <select id="author_id" name="author_id">';

        $singers = RnSingersDB::get_rows('singer_id, is_singer');
        if ($singers !== false) {
            $me = get_current_user_id();
            $sorted = array();
            foreach($singers as $singer) {
                if ($singer['is_singer']) {
                    $id = $singer['singer_id'];
                    if ($id != $me) {
                        $data = get_userdata($id);
                        $sorted[$data->last_name] = array('id' => $id, 'first_name' => $data->first_name);
                    }
                }
            }
            ksort($sorted); // Sort by last name
            $html .= '<option value="' . $me . '" selected="selected"></option>';
            foreach($sorted as $last_name => $singer) {
                $html .= '<option value="' . $singer['id'] . '">' . $singer['first_name'] . ' ' . $last_name . '</option>';
            }
        }

        $warning_msg = $this->rn_role == 'ADMIN' ? 'Be careful changing these settings' : 'These settings can no longer be changed';
        $html .= '
                    </select>
                </div>
            </div>
            <table id="edit-table">
                <tr class="edit-warning">
                    <td colspan="5">You are editing a Published Note that has been read/copied.  ' . $warning_msg . '...</td>
                </tr>
                <tr class="song-attrs">
                    <th>SONG</th>
                    <th class="measure">MEASURE [<a href="#" onclick="alert(\'' . $msr_help . '\');return false;">?</a>]</th>
                    <th class="measure">SECTION [<a href="#" onclick="alert(\'' . $vp_help . '\');return false;">?</a>]</th>
                    <th class="edit-location">LOCATION</th>
                </tr>
                <tr id="song-attrs">
                    <td>
                        <select id="sel-songs" name="song_id">';

        foreach($this->songs as $song) {
            $html .= '
                            <option id="song-' . $song[RN::ID] . '" value="'. $song[RN::ID] . '"' .
                                '>' . $song[RN::NAME] . '</option>';
            }

        $html .= '
                        </select><br>
                        Director: <select id="dir-id" name="dir_id">';
        foreach($this->dirs as $dir_id => $dir_name) {
            $html .= '      <option value="' . $dir_id . '">' . $dir_name . '</option>';
        }
        $html .=        '</select>
                    </td>
                    <td class="measure">
                        <input class="measure" type="text" id="measure" name="measure"><br>
                        <span id="start-ms"></span> - <span id="end-ms"></span>
                    </td>
                    <td id="parts"></td>
                    <td class="edit-location">
                        <select id="location" name="location">
                            <option id="loc-nt-inbox" value="nt-inbox">Note Taker\'s Inbox</option>
                            <option id="loc-dir-inbox" value="dir-inbox">Director\'s Inbox</option>
                            <option id="loc-review" value="review">For Review</option>
                            <option id="loc-published" value="published" style="display:none">Published</option>
                            <option id="loc-trash" value="trash" style="display:none">Trash</option>
                        </select>
                    </td>
                </tr>
                <tr class="edit-warning">
                    <td colspan="5">Use Strike-through, <span class="st-button">ABC</span> below, to indicate what has been changed or deleted ...</td>
                </tr>
                <tr>
                    <td colspan="5">';
        $html .= do_shortcode('[accordion][accordion_item title="formatting standards"][rn_refman man="seg-rnote-format"][/accordion_item][/accordion]');

        ob_start();
        wp_editor('', 'note-editor', array('textarea_rows' => 5, 'teeny' => false,
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
                <tr>
                    <th colspan="5" class="discussion">DISCUSSION <span class="desc">(Unpublished comments: for Note Takers only)</span></th>
                </tr>
                <tr>
                    <td colspan="5"><textarea name="discussion" id="discussion"></textarea></td>
                </tr>
            </table>
            <div class="overlay-footer">
                <div id="message"></div>
                <div class="buttons">
                    <button type="button" id="btn-cancel" onclick="rn_adtab.close_edit_form()">Cancel</button>
                    <button type="button" id="btn-close" onclick="rn_adtab.close_edit_form()">Close</button>
                    <button type="button" id="btn-save" onclick="rn_adtab.save_rnote()">Save</button>
                    <button type="button" id="btn-add" onclick="rn_adtab.save_rnote()">Add Another</button>
                </div>
                <input type="hidden" id="note_id" name="note_id" />
                <input type="hidden" id="note_ts" name="note_ts" />
            </div>
        </form>
    </div>
</div>';
        return $html;
    }

    private function add_move_form() {
        $html = '
<div id="rn-move-overlay">
    <div id="rn-move-overlay-form">
        <table id="move-to">
            <tr id="move-to-nt-inbox">
                <td><div id="move-to-nt-inbox-icon"><span class="genericon-checkmark"></span></div></td>
                <td><div id="nt-inbox-btn" class="move-to-btn">Note Taker\'s Inbox</div></td>
            </tr>
            <tr id="move-to-dir-inbox">
                <td><div id="move-to-dir-inbox-icon"><span class="genericon-checkmark"></span></div></td>
                <td><div id="dir-inbox-btn" class="move-to-btn">Director\'s Inbox</div></td>
            </tr>
            <tr id="move-to-review">
                <td><div id="move-to-review-icon"><span class="genericon-checkmark"></span></div></td>
                <td><div id="review-btn" class="move-to-btn">For Review</div></td>
            </tr>
            <tr id="move-to-published">
                <td><div id="move-to-published-icon"><span class="genericon-checkmark"></span></div></td>
                <td><div id="published-btn" class="move-to-btn">Published</div></td>
            </tr>
            <tr id="move-to-trash">
                <td><div id="move-to-trash-icon"><span class="genericon-checkmark"></span></div></td>
                <td><div id="trash-btn" class="move-to-btn">Trash</div></td>
            </tr>
        </table>
    </div>
</div>';
        return $html;
    }

    private function add_history_form()
    {
        $html = '
<div id="rn-history-overlay" class="rn-overlay">
    <div class="rn-overlay-content">
        <div id="edit-title">Note History</div>
        <div class="instructions">Click on Editor to send them an email.</div>
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
                <th class="hist-disc">Discussion</th>
            </tr>
        </table>
        <table id="edit-table" class="rn-history-table">
            <tbody id="rn-history">
            </tbody>
        </table>
        <div class="overlay-footer">
            <div id="message"></div>
            <div class="buttons">
                <button type="button" id="btn-close" onclick="rn_adtab.close_history()">Close</button>
            </div>
        </div>
    </div>
</div>';

        return $html;
    }

    function add_trash_form() {
        $html = '
<div id="rn-trash-overlay" class="rn-overlay">
    <div class="rn-overlay-content">
        <div class="edit-title">Indicate reasons for sending to Trash</div>
        <form id="trash-form">
            <input type="checkbox" name="r1" value="Duplicate"> It is a duplicate<br>
            <input type="checkbox" name="r2" value="Answered"> It was answered without needing a change in the music<br>
            Answer:<br>
            <input type="text" class="trash-text" name="answer"><br>
            <input type="checkbox" name="r3" value="Mistake"> It was created by mistake<br>
            Other:<br>
            <input type="text" class="trash-text" name="other"><br>
            <div class="overlay-footer">
                <div></div>
                <div class="buttons">
                    <button type="button" id="btn-trash-cancel" onclick="rn_adtab.cancel_trash()">Cancel</button>
                    <button type="button" id="btn-trash-save" onclick="rn_adtab.save_trash()">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>';

        return $html;
    }


    private function add_columns() {
        $this->table->add_column('done',
            array('title' => 'Click to sort',
                'width' => '40'));

        $this->table->add_column('song',
            array('label' => 'Song',
                'title' => 'Click to sort',
                'data-placeholder' => 'Filters ...',
                'width' => '200'));

        $this->table->add_column('measure',
            array('label' => 'ms',
                'title' => 'Starting Measure: Filter shows +/-10, must be typed quickly',
                'width' => '40',
                'sorter' => 'ms_sorter',
                'custom-filters' => 'msFilter'));

        $this->table->add_column('note_vps',
            array('label' => 'Parts',
                'title' => 'Click to sort',
                'width' => '70',
                'custom-filters' => 'partsFilter'));

        $this->table->add_column('note',
            array('label' => 'Question/Note',
                'title' => 'Click to sort',
                'sorter' => false));

        $this->table->add_column('location',
            array('width' => '80',
                'title' => 'Click to sort',
                'sorter' => 'loc_sorter'));

        $this->table->add_column('director',
            array('label' => 'Dir',
                'width' => '30',
                'title' => 'Click to sort'));

        $this->table->add_column('author_id',
            array('label' => 'Author',
                'title' => 'Click to sort',
                'width' => '90',
                'sorter' => 'text'));

        $this->table->add_column('editors',
            array('label' => 'Editors',
                'title' => 'Most recent is in larger print',
                'width' => '90',
                'sorter' => 'text'));

        $this->table->add_column('date',
            array('label' => 'Date, Time',
                'title' => 'Click to sort'));

        $this->table->add_column('discussion',
            array('title' => 'Click to sort',
                'sorter' => false));

        $this->table->add_column('actions',
            array('label' => '',
                'width' => '58',
                'sorter' => false,
                'filter' => false));
    }

    private function add_settings()
    {
        $this->table->set_option('title', 'RN Admin');

        // Enable hiding columns and download to CSV
        $this->table->set_option('hide columns', true);
        $this->table->set_option('download', 'Rehearsal_Notes');
        $this->table->set_option('dynamic selection', true);
        $this->table->set_option('directions', '<em>Click row</em>: Edit, 
                <em>Click location</em>: Move, 
                <span class="genericon-document"></span>Duplicate,
                <span class="genericon-time"></span>History,
                <span class="genericon-edit"></span>Highlight (<em>alt-click:</em> multiple)');
        if ($this->rn_role == 'DIR') {
            $help_id = 'dir-instruct';
            $role = 'Directors';
        } else {
            $help_id = 'nt-instruct';
            $role = 'Note Takers';
        }
        $this->table->set_option('directions-left', '<a href="/rn-refman?man=' . $help_id .
            '" target="_blank"><em>Instructions for ' . $role . '</em></a>');

        // For a preset dropDown selector
        $this->table->add_filter_preset('All notes',
            array(),
            array('song', 'measure'));

        $this->table->add_filter_preset('Note Taker\'s Inbox',
            array('location' => 'Note Taker'),
            array('song', 'measure'));

        $spacer = '';
        if (count($this->dirs) > 1) {
            $this->table->add_filter_preset('Director\'s Inbox',
                array('location' => 'Director'),
                array('song', 'measure'));
            $spacer = '- ';
        }

        foreach($this->dirs as $dir) {
            $this->table->add_filter_preset($spacer . $dir . '\'s Inbox',
                array('location' => 'Director', 'director' => $this->dir_abbrev($dir)),
                array('song', 'measure'));
        }

        $this->table->add_filter_preset('For Review',
            array('location' => 'For Review'),
            array('song', 'measure'));

        $this->table->add_filter_preset('Not Published',
            array('location' => '!Pub && !Trash'),
            array('location', 'song', 'measure'));

        $this->table->add_filter_preset('Published',
            array('location' => 'Published'),
            array('song', 'measure'));

        $this->table->add_filter_preset('Trash',
            array('location' => 'Trash'),
            array('song', 'measure'));

        $filter = 'Note Taker\'s Inbox';
        if ($this->rn_role == 'DIR') {
            $filter = $spacer . $this->dirs[$this->user_id] . '\'s Inbox';
        } else {
            $this->table->set_option('remember settings', 'admin');
        }
        $this->table->set_init_filter($filter);

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

        $this->table->add_sorter('ms_sorter', 'numeric', '
            return (s == "") ? 0 : s;
        ');

        $this->table->add_sorter('loc_sorter', 'numeric', '
            return "NDFPT".indexOf(s[0]);
        ');

        // Button fcn must be defined in getJS()

        if ($this->rn_role != 'DIR') {
            $this->table->add_button(array(
                'label' => 'ASK QUESTION',
                'fcn' => 'rn_adtab.show_edit_form(\'question\')',
                'tooltip' => 'Create new Question for Directors'
            ));
        }

        $this->table->add_button(array(
            'label' => 'ADD NOTE',
            'fcn' => 'rn_adtab.show_edit_form(\'note\')',
            'tooltip' => 'Create new Rehearsal Note'
        ));
    }

    private function add_notes()
    {
        // Record the first heartbeat time - immediately before the first query
        $this->hb_start = current_time('mysql');
        $notes = RnNotesDB::get_notes();
        $done = RnDoneDB::get_done_count();

        foreach($notes as $note) {
            $note['done'] = isset($done[$note['note_id']]) ? $done[$note['note_id']]['count'] : 0;
            $row = $this->create_row_data($note);
            $this->table->add_row($note['note_id'], $row);
        }
    }

    private function create_row_data($note) {

        $note_id = $note['note_id'];

        $location = $this->wrap($note_id, 'loc-', $this->get_location($note['location']),
            array('loc' => $note['location'], 'type' => 'move', 'id' => $note_id), 'action-btn', 'Change location');

        $song = $this->songs[$note['song_id']][RN::NAME];
        $note_name = $song . ', m' . $note['measure'] . ' - ' . $note['note_vps'];

        $editors = $this->get_editors($note, $note_name);

        $ts = strtotime($note['time']);
        $date = $this->format_ts($ts);
        $date = $this->wrap($note_id, 'ts-', $date, array('ts' => $ts));

        $note_vps = '(all)';
        if ($this->songs[$note['song_id']][RN::VP] != $note['note_vps'])
            $note_vps = implode(',&thinsp;', explode(',', $note['note_vps']));

        $dir = $this->dirs[$note['dir_id']];
        $dir_initials = $this->dir_abbrev($dir);

        // Data keys must be the same as the column ids
        $row = array(
            'data' => array(
                'done' => $this->wrap($note_id, 'done-', $note['done']),
                'song' => $song,
                'measure' => $note['measure'],
                'note_vps' => $note_vps,
                'note' => $note['note'],
                'location' => $location,
                'director' => $dir_initials,
                'author' => $editors['author'],
                'editors' => $editors['editors'],
                'date' => $date,
                'discussion' => $note['discussion'],
                'actions' => $this->get_actions($note_id, $note['location'])),

            'class' => array(
                'done' => 'centertext',
                'measure' => 'centertext',
                'note_vps' => 'centertext vps-width',
                'note' => 'quiet_p',
                'discussion' => 'disc-col'),

            'tr_pars' => array(
                'class' => 'master_row action-btn',
                'data-type' => 'edit',
                'data-id' => $note_id)
        );
        return $row;
    }

    private function dir_abbrev($dir) {
        $dir_names = explode(' ', $dir);
        $dir_initials = '';
        foreach($dir_names as $dir_name)
            $dir_initials .= $dir_name[0];
        return $dir_initials;
    }

    private function format_ts($ts) {
        $text = str_replace('2019', '', date_i18n($this->date_format, $ts));
        $text .= ', ' . date_i18n($this->time_format, $ts);
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

    private function get_editors($note, $note_name) {
        $editors = array('author' => '', 'editors' => '');
        $last_editor = $note['author_id'];
        $res = RnNotesDB::get_rows('author_id', 'revision_id = ' . $note['note_id'] . ' ORDER BY note_id');
        if ($res === false || empty($res)) {
            $editors['author'] = $this->get_sendto_name($last_editor, $note['note_id'], $note_name, 'author');
            $editors['editors'] = $this->get_sendto_name($last_editor, $note['note_id'], $note_name, 'editor');
        } else {
            $author_id = $res[0]['author_id'];
            $editors['author'] = $this->get_sendto_name($author_id, $note['note_id'], $note_name, 'author');

            $editor_ids = array();
            $editor_list = array();
            foreach($res as $revision) {
                $rev_id = $revision['author_id'];
                if ($rev_id != $author_id && $rev_id != $last_editor && !in_array($rev_id, $editor_ids)) {
                    $editor_ids[] = $rev_id;
                    $editor_list[] = $this->get_sendto_name($rev_id, $note['note_id'], $note_name, 'revision');
                }
            }
            $editor_list[] = $this->get_sendto_name($last_editor, $note['note_id'], $note_name, 'editor');
            $editors['editors'] = implode('<br>', $editor_list);
        }
        return $editors;
    }

    private function get_sendto_name($user_id, $note_id, $note_name, $person) {
        $data = get_userdata($user_id);
        $email = explode('@', $data->user_email);
        $name = $data->display_name;
        $attrs = array(
            'srv' => $email[1],
            'note' => $note_name,
            'adr' => $email[0]);
        switch($person) {
            case 'author':
                $slug = 'auth-';
                break;
            case 'revision':
                $name = '<small>' . $name . '</small>';
                $slug = 'rev-';
                break;
            case 'editor':
                $attrs['editor_id'] = $user_id;
                $slug = 'editor-';
                break;
        }
        return $this->wrap($note_id, $slug, $name, $attrs, 'send-adr', 'Send email');
    }

    private function wrap($note_id, $slug, $text, $data = array(), $class=null, $title=null) {
        $attrs = '';
        foreach($data as $key => $val) {
            $attrs .= ' data-' . $key . '="' . $val . '"';
        }
        $title = $title != null ? ' title="' . $title . '"' : '';
        $class = $class != null ? ' class="' . $class . '"' : '';
        return '<span id="' . $slug . $note_id . '"'. $class . $title . $attrs . '>' . $text . '</span>';
    }

    private function get_actions($note_id, $location) {
        $html = '<span class="genericon-document action-btn" 
                    data-type="dup" data-id="' . $note_id . '"
                    title="Duplicate"></span>';
        $html .= '<span class="genericon-time action-btn" 
                    data-type="hist" data-id="' . $note_id . '"
                    title="History"></span>';
        $html .= '<span class="genericon-edit action-btn" 
                    data-type="mark" data-id="' . $note_id . '"
                    title="Highlight"></span>';
        if (RnOptions::is_admin() && $location == 'trash') {
            $html .= '<span class="genericon-trash action-btn" 
                    data-type="del" data-id="' . $note_id . '"
                    title="Delete"></span>';
        }
        return $html;
    }

    private function getJS() {
        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[';

        $adrs = array();
        foreach($this->notetakers as $id => $nt)
            $adrs[] = $nt['user_email'];
        $adrs = implode(',', $adrs);
        $adrs = explode('@', $adrs);

        $js .= '
            var adrs = ' . json_encode($adrs) . ';
            var dirs = ' . json_encode($this->dirs) . ';
            var songs = ' . json_encode($this->songs) . ';
            var rn_role = "' . $this->rn_role . '";
            var author_id = "' . $this->user_id . '";
            var hb_ts = "' . $this->hb_start . '";
            var ajaxurl = "' . admin_url('admin-ajax.php') . '";
        ';
        $js .= $this->get_ajax_JS();
        $js .= RN::renderJS();

        return $js.'
        // ]]></script>';
    }

    protected function check_user_can_handle_ajax() {
        if ($this->rn_role == null)
            self::send_fatal_error(3001); // non-NT: Access denied
    }

    protected function do_ajax_request($action)
    {
        if (method_exists($this, $action) === false)
            self::send_fatal_error(3002); // Action method not defined
        $this->$action();
    }

    public function rn_get_rnote() {
        $note_id = $this->validate_int('note_id', 3003);

        $note = RnNotesDB::get_note($note_id);
        if ($note === false)
            wp_send_json_error(3004); // Unable to get the existing note

        $done = RnDoneDB::get_done_count($note_id);
        $count = isset($done[$note_id]) ? $done[$note_id]['count'] : 0;
        $note['done'] = $count;
        // Saved state sends only the fields for the note
        $res = array('state' => 'saved', 'note' => $note);
        $this->send_note_response($res);
    }


    public function rn_save_rnote() {
        $fields = array();
        $fields['note_id'] = $this->validate_int('note_id', 3101);
        $fields['song_id'] = $this->validate_int('song_id', 3102, function($val) {
            if (!in_array($val, array_keys($this->songs)))
                $this->send_fatal_error(3102.5);   // Non-existent song #
        });
        $fields['dir_id'] = $this->validate_int('dir_id', 3103, function($val) {
            if (!in_array($val, array_keys($this->dirs)))
                $this->send_fatal_error(3103.5);   // Invalid Director's ID
        });
        $new_note = ($fields['note_id'] == 0);

        $fields['measure'] = $this->validate_int('measure', 3104, function($val) {
            if ($_POST['measure'] != '' && strval($val) != $_POST['measure']) {
                if (strpos($_POST['measure'], '-') !== false)
                    $this->send_user_error('Enter start measure only, indicate range in the text');
                $this->send_user_error('Invalid start measure number');
            }
        });
        $sm = $fields['measure'];
        $song = $this->songs[$fields['song_id']];
        if ($sm != 0 && $song[RN::SM] > 0) {
            if ($sm < $song[RN::SM] || $sm > $song[RN::EM]) {
                $this->send_user_error('Start measure must be between ' . $song[RN::SM]
                    . ' and ' . $song[RN::EM] . '.');
            }
        }

        $pub_read = $this->validate_bool('pub_read', 3112);
        $clear_done = $this->validate_bool('clear_done', 3113);
        if ($pub_read) {
            if ($new_note)
                $this->send_fatal_error(3112.1);   // Only for existing notes

        } else {
            if ($clear_done)
                $this->send_fatal_error(3113.1);   // Only for Published Notes

            // Published notes do not send VPS
            $fields['note_vps'] = $this->validate_text('note_vps', 3105, function ($val) {
                if (empty($val))
                    $this->send_user_error('Must select at least one section/voice part');
            });
            $vp_test = ',' . $song[RN::VP] . ',';
            $vps = explode(',', $fields['note_vps']);
            foreach ($vps as $vp) {
                if (strpos($vp_test, ',' . $vp . ',') === false)
                    $this->send_fatal_error(3105.5);  // Invalid characters in vp
            }
        }

        $fields['location'] = $this->validate_location('location', 3106);
        $fields['note'] = $this->validate_text('rn_note', 3107, function($val) {
            if (empty($val) || (strpos($val, 'Write question here') !== false)
                || (strpos($val, 'Write note here') !== false))
                $this->send_user_error('Rehearsal note content cannot be empty');
        });
        $fields['discussion'] = $this->validate_text('discussion', 3108);


        if ($new_note) {  // Adding a new note

            if (!isset($_POST['author_id']))
                $author_id = get_current_user_id();
            else
                $author_id = $this->validate_int('author_id', 3109);

            if ($author_id == get_current_user_id()) {
                unset($fields['note_id']);
                $res = RnNotesDB::add_note($fields);

            } else {
                if (get_userdata($author_id) === false)
                    $this->send_fatal_error(3109.5);  // Invalid author_id

                // Adding a question on behalf of another, it must first be added
                // to NT Inbox by that author.  Then updated with the settings provided.
                $first_ask = $fields;
                unset($first_ask['note_id']);
                $first_ask['author_id'] = $author_id;
                $first_ask['location'] = 'nt-inbox';
                $res = RnNotesDB::add_note($first_ask);
                if ($res === false)
                    $this->send_fatal_error(3109.6);  // DB failed to add note

                $fields['note_id'] = $res['note']['note_id'];
                $src_ts = strtotime($res['note']['time']);
                $res = RnNotesDB::update_note($src_ts, $fields);
                if ($res !== false)
                    $res['state'] = 'new';  // To be handled as new
            }

        } else {  // Update existing note
            // clearing done must happen before the note change so it will show up
            // correctly in heartbeat updates.
            if ($clear_done)
                RnDoneDB::clear_all_done($fields['note_id']);

            $src_ts = $this->validate_int('note_ts', 3110);
            $res = RnNotesDB::update_note($src_ts, $fields);
        }
        if ($res === false)
            $this->send_fatal_error(3111);  // DB failed to add note

        $this->send_note_response($res);
    }

    public function rn_move_rnote() {
        $note_id = $this->validate_int('note_id', 3201);
        $from = $this->validate_location('from', 3202);
        $to = $this->validate_location('to', 3203);

        $note = RnNotesDB::get_note($note_id);
        if ($note === false)
            wp_send_json_error(3204); // Unable to get the existing note

        if ($note['location'] == $to) {
            // The note has already been move to $to - then just return
            // success and let the note location be updated on the client.
            // If already moved to Trash - keep original reason.
            $res = array('state' => 'saved', 'note' => $note);

        } else if ($note['location'] != $from) {
            // The note has been moved to something else, send modified warning
            $res = array('state' => 'modified', 'note' => $note);

        } else {  // Move it!
            $fields = array('note_id' => $note_id, 'location' => $to);
            if ($to == 'trash')
                $fields['discussion'] = $this->validate_text('discussion', 3205);

            // The current's note's time is passed in - because we've already
            // tested for whether it is OK to update only the location.  This
            // avoids update failing due to the timestamp check.
            $res = RnNotesDB::update_note(strtotime($note['time']), $fields);
            if ($res === false) {
                $this->send_fatal_error(3206); // Update failed
            }
            if ($res['state'] == 'modified')
                $this->send_fatal_error(3207); // Using the existing timestamp should prevent this
        }
        $this->send_note_response($res);
    }

    /**
     * @param $res = array('state', 'note')
     * Used by all except heartbeat, which can send back multiple notes
     */
    private function send_note_response($res) {
        $note_id = $res['note']['note_id'];
        $done = RnDoneDB::get_done_count($note_id);
        $res['note']['done'] = isset($done[$note_id]) ? $done[$note_id]['count'] : 0;
        $res = $this->prep_results($res);
        wp_send_json_success($res);
    }

    public function rn_history_rnote() {
        $note_id = $this->validate_int('note_id', 3301);

        $cols = 'song_id, measure, note_vps, note, location, author_id, time, discussion, dir_id';
        $history = RnNotesDB::get_rows($cols, 'revision_id = ' . $note_id);
        if ($history === false)
            $this->send_fatal_error(3302); // Failed to get history

        $rev = 1;
        $html = '';
        foreach($history as $note) {
            $song = $this->songs[$note['song_id']][RN::NAME];
            $note_name = $song . ', m' . $note['measure'] . ' - ' . $note['note_vps'];

            $auth_data = get_userdata($note['author_id']);
            $auth_email = explode('@', $auth_data->user_email);
            $author = '<span title="Send email" class="send-adr" data-srv="' .
                $auth_email[1] . '" data-note="' . $note_name . '" data-adr="' .
                $auth_email[0] .  '">' . $auth_data->display_name . '</span>';

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
                'hist-dir' => $this->dirs[$note['dir_id']],
                'hist-auth' => $author,
                'hist-date' => $this->format_ts(strtotime($note['time'])),
                'hist-disc' => $note['discussion']
            );
            $html .= '<tr>';
            foreach($fields as $class => $val) {
                $html .= '<td class="'. $class . '">' . $val . '</td>';
            }
            $html .= '</tr>';
        }
        wp_send_json_success($html);
    }

    /**
     * This is only accessible to Admins.  It does not remove the note from
     * the DB, but does remove it from "All Notes" display in the UI.  So
     * is only for cleaning up the UI list and should only be used to remove
     * notes that do not need to be kept for searches/duplicate checks.
     * It is not removed from the DB so that heartbeats can process it.
     */
    public function rn_delete_rnote() {
        if (!RnOptions::is_admin())
            $this->send_fatal_error(3400);  // Only admins can do this

        $note_id = $this->validate_int('note_id', 3401);  // missing ID
        $note = RnNotesDB::get_note($note_id);
        if ($note === false)
            $this->send_fatal_error(3402);  // Invalid ID
        if ($note['location'] != 'trash')
            $this->send_fatal_error(3403);  // Cannot delete if not in trash

        // Even this action cannot remove the record from the DB, so the
        // heartbeat can handle it.  But it prevents the note from showing
        // up in the UI tables.
        $fields = array(
            'note_id' => $note_id,
            'location' => 'deleted'
        );

        // The current's note's time is passed in - because this action
        // is always possible and overrides any previous changes.
        // This avoids update failing due to the timestamp check.
        $res = RnNotesDB::update_note(strtotime($note['time']), $fields);
        if ($res === false) {
            $this->send_fatal_error(3404); // Update failed
        }
        if ($res['state'] == 'modified')
            $this->send_fatal_error(3405); // Using the existing timestamp should prevent this

        $this->send_note_response($res);
    }

    /**
     * The Heartbeat is always continuously running.  If it encounters an error, like
     * the server is not available, it just tries again in the next 15 second beat.
     * It sends an "active" flag, so if the window is not currently in focus, it
     * will still update, but will report inactive, so it displays as inactive for
     * others.  On re-focus, it immediately stops/starts a new heartbeat so that
     * it updates its active status right away.
     */
    public function rn_heartbeat() {
        $last_ts = $this->validate_text('hb_ts', 4101, function($val) {  // Missing HB TS
            if (strtotime($val) === false)
                $this->send_fatal_error(4102);}); // Invalid HB TS

        $active = $this->validate_bool('hb_active', 4103);   // Missing Active flag
        if ($active)
            RnOnlineDB::update_time(true);

        $online = RnOnlineDB::online();
        $online_staff = array();
        $online_singers = array();
        foreach($online as $hb) {
            if ($hb['is_admin_page'])
                $online_staff[] = $hb['singer_id'];
            else {
                $singer = get_userdata($hb['singer_id']);
                $email = explode('@', $singer->user_email);
                $online_singers[] = '<span class="singer-name send-adr" data-srv="'
                    . $email[1] . '" title="Send email" data-note="" data-adr="' . $email[0] . '">'
                    . $singer->display_name . '</span>';
            }
        }
        if (count($online_singers) == 0)
            $online_singers[] = '(none)';

        $this_ts = current_time('mysql');
        $notes = RnNotesDB::get_changes($last_ts, $this_ts);
        $done_chg = RnDoneDB::get_done_count_changes($last_ts, $this_ts);
        $done = RnDoneDB::get_done_count();

        $results = array(
            'online_staff' => $online_staff,
            'online_singers' => $online_singers,
            'new_ts' => $this_ts,
            'changes' => array(),
            'done_chg' => array_values($done_chg));
        foreach($notes as $note) {
            $note['done'] = isset($done[$note['note_id']]) ? $done[$note['note_id']]['count'] : 0;

            // Don't know which are new/modified - so return both
            $new = $this->prep_results(array('state' => 'new', 'note' => $note));
            $mod = $this->prep_results(array('state' => 'modified', 'note' => $note));
            $results['changes'][] = array(
                'note_id' => $note['note_id'],
                'del' => ($note['location'] == 'deleted'),
                'new' => $new,
                'mod' => $mod);
        }
        wp_send_json_success($results);
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
        $res['note']['dir_name'] = $this->dirs[$this->songs[$res['note']['song_id']][RN::DIR]];
        $res['note']['measure'] = $res['note']['measure'] > 0 ? $res['note']['measure'] : '';
        $res['note']['note_ts'] = strtotime($res['note']['time']);
        $row = $this->create_row_data($res['note']);

        if ($res['state'] == 'new')
            $res['html'] = $this->table->get_row($res['note']['note_id'], $row);

        else {  // saved or modified
            $res['html'] = $this->table->get_fields($row);
            $res['note']['author'] = get_userdata($res['note']['author_id'])->display_name;
            $res['note']['loc_name'] = $this->get_location($res['note']['location']);

            if ($res['state'] == 'modified') { // Warning dialog needs a subset
                unset($row['data']['done']);
                unset($row['data']['director']);
                unset($row['data']['author']);
                unset($row['data']['date']);
                unset($row['data']['actions']);
                $res['warning'] = $this->table->get_fields($row);
            }
        }
        return $res;
    }

    private function validate_int($name, $err_prefix, $validate_fcn = null) {
        if (!isset($_POST[$name]))
            $this->send_fatal_error($err_prefix . '.1');  // Missing Field
        $val = intval($_POST[$name]);

        if ($validate_fcn)
            call_user_func($validate_fcn, $val, $err_prefix);
        else if (strval($val) != $_POST[$name])
            $this->send_fatal_error($err_prefix . '.2');  // Invalid Field contents

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

    private function validate_location($field_name, $err_prefix) {
        $location = $this->validate_text($field_name, $err_prefix, function($val, $err_prefix) {
            if (!in_array($val, array('nt-inbox','dir-inbox','review','published','trash')))
                $this->send_fatal_error($err_prefix . '.5');  // Invalid location
        });
        return $location;
    }

    private function validate_bool($name, $err_prefix) {
        $val = $this->validate_text($name, $err_prefix, function($val, $err_prefix) {
            if (!in_array($val, array('true','false')))
                $this->send_fatal_error($err_prefix . '.5');  // Invalid Boolean
        });
        return ($val == 'true');
    }

}
