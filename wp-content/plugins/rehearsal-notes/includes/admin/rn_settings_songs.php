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
require_once(plugin_dir_path(__FILE__).'/../common/rn_database.php');

/**
 * Class RnSongsTab
 */
class RnSongsTab extends RnTab
{
    private $song_list;

    protected function __construct($active_tab)
    {
        parent::__construct($active_tab);

        // Field provider classes
        $this->song_list = new RnSongListField(
            'song-list',
            '',  // Enable songs list to be almost full-width
            $this->options);
    }

    protected function register_tab() {
        $section = 'rn-rnotes-settings-section';
        $this->add_section($section, 'Song List');
        $this->add_field($section, $this->song_list,
            array('class' => 'sl-header'));    // to make the table full width
    }

    public function render_section_info() {
        $dirs = RnSingersDB::get_staff('dir');
        echo '<div' . ((count($dirs) == 0) ? ' class="rn-error"' : '')
            . '>Director(s): ';

        if (count($dirs) == 0) {
            $names = 'No names found: Import directors in the Singers List';
        } else {
            $dir_names = [];
            foreach($dirs as $id => $dir) {
                $dir_names[] = $dir['display_name'];
            }
            $names = implode(', ', $dir_names);
        }
        echo $names . '</div>';

        echo do_shortcode('[rn_refman man="admin-song-list-instruct"]');
    }

    protected function render_submit_button()
    {
        submit_button('Save Changes');
        echo '<input type="button" class="button" value="Download CSV" onclick="rn_js.download_songs()">';
    }

    protected function validate(&$options, $input) {
        $this->song_list->validate($options, $input);
    }

    protected function get_js() {
        return $this->song_list->js_data();
    }
}

/**
 * Class RnSongListField
 * Provides the Complex Song List options
 * This option is actually a combination of 3 sub-options:
 *    A cache of the list of Directors, read from Member positions
 *    An array of songs - the Song List
 *    Iterator index for the unique Song IDs
 *
 * In its validation method, it validates each song individually
 * so that a mistake in one song does not all the changes to the option.
 * Changes in other songs are still kept.
 */
class RnSongListField extends RnOptionField
{
    private $options, $dirs;

    public function __construct($slug, $label, $options)
    {
        parent::__construct($slug, $label);
        $this->options = $options;

        $staff = RnSingersDB::get_staff('dir');
        $this->dirs = array();
        foreach($staff as $id => $dir) {
            $this->dirs[$id] = $dir['display_name'];
        }
    }

    public function render($args)
    {
        // Container for the Songs list
        echo '
        <table class="sl-table">
            <thead>
                <tr>
                    <th class="sl-action"></th>
                    <th class="sl-song">Song</th>
                    <th class="sl-dir">Director</th>
                    <th class="sl-ms">Measures</th>
                    <th class="sl-vp">Voice Parts</th>
                </tr>
            </thead>
            <tbody id="song-list"></tbody>
            <tfoot id="add-song"></tfoot>
        </table>';

        // The entire array, actual data, is kept here - which is filled in on submit
        echo '
        <input type="hidden" name="' . $args['name'] . '" />';
    }

    public function js_data() {
        $def_dir = (count($this->dirs) > 0) ? array_keys($this->dirs)[0] : '';

        $js = '
        var rn_songs_w_notes = ' . json_encode(RnNotesDB::get_ids_with_dependent_notes('song_id')) . ';
        var rn_songs = ' . json_encode($this->options->get_option('song-list')) . ';
        ' . RN::renderJS() . '
        var rn_dirs = ' . json_encode($this->dirs) . ';
        var rn_defs = ["", "", ' . $def_dir . ', "", "", "S,A,T,B"];
        var rn_song_list_id = "' . $this->options->get_path($this->slug) . '";';
        return $js;
    }

    protected function _clean_and_validate($input, &$working_options)
    {
        $songs = json_decode($input);
        if ($songs === null) {
            $this->add_error(501, 'Corrupted song list - try again.');
            return;
        }

        // Song List is a complex type, involving an array of songs.  Validation is done
        // on each song individually so that if an error is made in one song, that song
        // is reverted to the original settings, but the rest of the song changes can
        // still be applied.

        $orig_songs = $this->options->get_option('song-list');
        $init_id = $next_id = $this->options->get_option('next-id');

        $new_songs = [];
        foreach($songs as $song) {
            // Validate each song individually
            $song = new RnSongCleaner($song, $this->dirs);

            // Normally these settings are handled by the parent class
            $this->valid = true;
            $this->value = $song->to_string();

            if ($song->name == '')
                $this->add_error(502, 'Missing song name.');
            if ($song->sm == '' && $song->em != '')
                $this->add_error(503, 'Missing start measure.');
            if ($song->sm != '' && $song->em == '')
                $this->add_error(504, 'Missing end measure.');
            if ($song->sm != '' && $song->em != '' && $song->sm > $song->em)
                $this->add_error(505, 'End measure must be greater than start measure.');

            // TODO - no special characters allowed, letters and numbers only
            // Voice Parts must be 1-2 chars and unique
            $vps_list = explode(',', $song->vps);
            if (count($vps_list) == 0)
                $this->add_error(506, 'Missing Voice Parts.');
            $test_vps = array();
            $dup_vps = array();
            $bad_vps = array();
            $alnum_vps = array();
            foreach($vps_list as $vp) {
                if (strlen($vp) > 2)
                    $bad_vps[] = $vp;
                if (!ctype_alnum($vp))
                    $alnum_vps[] = $vp;
                if (in_array($vp, $test_vps))
                    $dup_vps[] = $vp;
                $test_vps[] = $vp;
            }
            if (count($dup_vps) > 0)
                $this->add_error(507, 'Cannot have duplicate Voice Parts: ' . implode(', ', $dup_vps));
            if (count($bad_vps) > 0)
                $this->add_error(508, 'Voice Parts must be 1-2 characters: ' . implode(', ', $bad_vps));
            if (count($alnum_vps) > 0)
                $this->add_error(509, 'Voice Parts must contain letters and numbers only: ' . implode(', ', $alnum_vps));

            $insert = $this->find_sorted_index($new_songs, $song->name);

            if (!$this->valid) {  // An error was found, and reported
                if ($song->id == '') {
                    continue;  // its a new song - so it is not added to the DB

                } else {
                    // find its original
                    $idx = array_search($song->id, array_column($orig_songs, 0));
                    if ($idx === false)
                        continue;  // Can't find an original

                    $orig = $orig_songs[$idx];
                    if ($orig[RN::NAME] != $song->name) {
                        // If the original name is different, need to find its sorted
                        // position and check again for duplicates.

                        $this->valid = true;  // clear the flag
                        $insert = $this->find_sorted_index($new_songs, $orig[RN::NAME]);

                        // If the old name now causes a duplicate - then all we can do
                        // is mangle the newer of the two songs.
                        if (!$this->valid) {
                            if ($new_songs[$insert][RN::ID] > $orig[RN::ID]) {
                                $new_songs[$insert][RN::NAME] .= ' - Duplicate';
                            } else {
                                $orig[RN::NAME] .= ' - Duplicate';
                            }
                        }
                    }
                    // Put the original back in the DB
                    $song = new RnSongCleaner($orig, $this->dirs);
                }
            }

            // If its a new song, give it an ID
            if ($song->id == '')
                $song->id = $next_id++;

            // Place it in the sorted song list
            array_splice($new_songs, $insert, 0, array($song->song()));
        }

        if ($init_id != $next_id)
            $working_options->update_option('next-id', $next_id);

        // Since validation is already done, per-song, always update the option in the
        // DB by reporting that it is valid.
        $this->valid = true;
        return $new_songs;
    }

    /**
     * @param $new_songs array used to build the sorted song list
     * @param $name name of the song to be added to the list
     * @return int position of the song to be added, or of its duplicate
     *
     * It will set $this->valid = false if a Duplicate is found
     * This can only be detected if valid is reset to true before invocation
     */
    function find_sorted_index($new_songs, $name) {
        for($i = 0; $i < count($new_songs); $i++) {
            $comp = strcmp($new_songs[$i][RN::NAME], $name);
            if ($comp > 0) {
                break;
            }
            if ($comp == 0) {
                $this->add_error(506, 'Duplicate song name found');
                break;
            }
        }
        // This is the index to add the sorted song, or the index that it
        // is a duplicate of.
        return $i;
    }
}

/** Sanitize songs sent via AJAX
 * If invalid DIR ID - sets to first Director on list
 * If invalid Voice Part, sets to all.
 * Provides a to_string() method used in error messages
 * song() returns the song array used in options
 */
class RnSongCleaner {
    public $name, $id, $dir, $dir_id, $sm, $em, $vps;

    public function __construct($song, $dirs) {
        $this->id = $song[RN::ID];
        $this->name = trim(sanitize_text_field($song[RN::NAME]));

        $dir_id = $song[RN::DIR];
        if (isset($dirs[$dir_id])) {
            $this->dir_id = $dir_id;
            $this->dir = $dirs[$dir_id];
        } else {
            $this->dir_id = array_keys($dirs)[0];
            $this->dir = $dirs[$this->dir_id];
        }

        $this->sm = ($song[RN::SM] == '') ? '' : (int)$song[RN::SM];
        $this->em = ($song[RN::EM] == '') ? '' : (int)$song[RN::EM];

        $vps = trim(sanitize_text_field($song[RN::VP]));
        $this->vps = preg_replace('/\s+/','', $vps); // remove all spaces
    }

    public function to_string() {
        return implode(',', array($this->name, $this->dir, $this->sm, $this->em, $this->vps));
    }

    public function song() {
        return array($this->id, $this->name, $this->dir_id, $this->sm, $this->em, $this->vps);
    }
}

