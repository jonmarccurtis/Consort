<?php
/**
 * Created by IntelliJ IDEA.
 * User: joncu
 * Date: 4/17/19
 * Time: 12:25 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}


abstract class RnDB
{
    protected static $name;

    protected static function table_name() {
        global $wpdb;
        return $wpdb->prefix . static::$name;
    }

    abstract public static function add_table();

    // For end-of-season clear data
    public static function clear_table() {
        global $wpdb;
        $table_name = self::table_name();
        return $wpdb->query("TRUNCATE TABLE $table_name");
    }

    // For uninstall
    public static function drop_table() {
        global $wpdb;
        $table_name = self::table_name();
        return $wpdb->query("DROP TABLE $table_name");
    }

    public static function get_rows($select = '*', $where='') {
        global $wpdb;
        $table_name = self::table_name();
        if (!empty($where))
            $where = 'WHERE ' . $where;
        $res = $wpdb->get_results("
            SELECT $select
            FROM $table_name
            $where
        ", ARRAY_A);

        if ($res === false)
            return false;
        if (count($res) == 0)
            return $res;

        return static::post_get_rows($res);
    }

    protected static function post_get_rows($rows) {
        return $rows;
    }
}

class RnSingersDB extends RnDB
{
    static protected $name = 'rn_singers';

    /**
     * Used during Activate to add the RN table to WP DB
     */
    public static function add_table () {
        global $wpdb;
        $table_name = self::table_name();

        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            singer_id bigint(20) unsigned NOT NULL,
            is_nt tinyint(1) NOT NULL default 0,
            is_dir tinyint(1) NOT NULL default 0,
            is_admin tinyint(1) NOT NULL default 0,
            is_singer tinyint(1) NOT NULL default 1,
            primary_vp varchar(2) NOT NULL,
            vp_exceptions text NOT NULL,
            vp_overrides text NOT NULL,
            PRIMARY KEY (singer_id)
        ) $charset;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Either adds, or does a full update of an existing singer
     *
     * @param $singer = array(
     *     [singer_id] default = current_user
     *     [is_nt] default = false
     *     [is_dir] default = false
     *     [is_admin] default = false
     *     [is_singer] default = true
     *     primary_vp - (required)
     *     [vp_exceptions] array song_id => array(song_id, vps)
     *     [vp_overrides] array song_id => array(song_id, vps)
     * @return false if failed, else note_id of new note.
     */
    public static function add_singer($singer) {
        global $wpdb;

        if (!isset($singer['singer_id']))
            $singer['singer_id'] = get_current_user_id();
        if (!isset($singer['primary_vp']))
            return false;
        if (!isset($singer['vp_exceptions']))
            $singer['vp_exceptions'] = array();
        $singer['vp_exceptions'] = json_encode($singer['vp_exceptions']);
        if (!isset($singer['vp_overrides']))
            $singer['vp_overrides'] = array();
        $singer['vp_overrides'] = json_encode($singer['vp_overrides']);

        return $wpdb->replace(self::table_name(), $singer);
    }

    /**
     * Is able to do full or partial updates of existing singers
     *
     * @param $id = singer_id
     * @param $data = array ($field => $value)
     * @return false|int
     */
    public static function update_singer($id, $data) {
        global $wpdb;

        if (isset($data['vp_exceptions']))
            $data['vp_exceptions'] = json_encode($data['vp_exceptions']);
        if (isset($data['vp_overrides']))
            $data['vp_overrides'] = json_encode($data['vp_overrides']);

        return $wpdb->update(self::table_name(), $data, array('singer_id' => $id));
    }

    public static function remove_singer($singer_id) {
        global $wpdb;

        $wpdb->delete(self::table_name(), array('singer_id' => $singer_id));
    }

    /**
     * @param null $role = 'all' default, 'dir', or 'nt'
     * @return array [user_id, first_name, last_name]
     */
    public static function get_staff($role = 'all') {
        switch ($role) {
            case 'dir':
                $where = 'is_dir = 1';
                break;
            case 'admin':
                $where = 'is_admin = 1';
                break;
            case 'nt':
                $where = 'is_nt = 1';
                break;
            default:
                $where = 'is_dir = 1 OR is_admin = 1 OR is_nt = 1';
                break;
        }
        $singers = parent::get_rows('singer_id, is_dir, is_nt, is_admin', $where);
        if ($singers === false) {
            error_log('get_staff query rows failed');
            return array();
        }
        if (count($singers) == 0)
            return array();

        // NOTE: Cannot use WP get_users() here, because it will not return the
        // Administrator if the current user is not an admin!!!!

        $staff = array();
        foreach($singers as $member) {
            $id = $member['singer_id'];
            $data = get_userdata($id);
            $staff[$id]['ID'] = $data->ID;
            $staff[$id]['display_name'] = $data->display_name;
            $staff[$id]['user_email'] = $data->user_email;
            $staff[$id]['is_dir'] = $member['is_dir'];
            $staff[$id]['is_nt'] = $member['is_nt'];
            $staff[$id]['is_admin'] = $member['is_admin'];
        }
        return $staff;
    }

    public static function get_singer($singer_id) {
        $singer = parent::get_rows('*', 'singer_id = ' . $singer_id);
        if ($singer === false)
            return false;
        if (count($singer) != 1)
            return false;
        // Note that json_decode is already done below
        return $singer[0];
    }

    protected static function post_get_rows($rows)
    {
        $has_exceptions =  isset($rows[0]['vp_exceptions']);
        $has_overrides =  isset($rows[0]['vp_overrides']);
        if ($has_exceptions || $has_overrides) {
            $decoded = array();
            foreach($rows as $id => $value) {
                if ($has_exceptions)
                    $value['vp_exceptions'] = json_decode($value['vp_exceptions'], true);
                if ($has_overrides)
                    $value['vp_overrides'] = json_decode($value['vp_overrides'], true);
                $decoded[$id] = $value;
            }
            return $decoded;
        }
        return $rows;
    }
}

class RnNotesDB extends RnDB
{
    static protected $name = 'rn_notes';

    /**
     * Used during Activate to add the RN table to WP DB
     */
    public static function add_table () {
        global $wpdb;
        $table_name = self::table_name();

        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            note_id smallint(4) unsigned NOT NULL auto_increment,
            time datetime NOT NULL,
            author_id bigint(20) unsigned NOT NULL,
            dir_id bigint(20) unsigned NOT NULL,
            revision_id smallint(4) unsigned NOT NULL default 0,
            song_id smallint(2) unsigned NOT NULL,
            measure smallint(3) unsigned NOT NULL default 0,
            note_vps tinytext NOT NULL default '',
            note longtext NOT NULL,
            location varchar(10) NOT NULL,
            discussion longtext NOT NULL default '',
            PRIMARY KEY (note_id)
        ) $charset;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function get_note($note_id) {
        $data = parent::get_rows('*', 'note_id = ' . $note_id);
        if ($data === false || count($data) != 1)
            return false;
        return $data[0];
    }

    /**
     * @param $note_id
     * @param $data - array of fields to update, plus note_id
     * @return bool|array - false if fatal failure
     *         array: state: modified - by another, returns current note
     *                       saved - returns saved note
     */
    public static function update_note($src_ts, $fields) {
        global $wpdb;

        if (!isset($fields['note_id']))
            return false;

        $note = self::get_note($fields['note_id']);
        if ($note === false)
            return false;

        if (strtotime($note['time']) != $src_ts) {
            return array('state' => 'modified', 'note' => $note);
        }

        $fields['time'] = current_time('mysql');
        $fields['author_id'] = get_current_user_id();
        $res = $wpdb->update(self::table_name(), $fields,
            array('note_id' => $fields['note_id']));
        if ($res === false)
            return false;

        // Update Note with what was just modified
        $note = array_merge($note, $fields);

        // Add to revisions
        $rev = $note;
        $rev['revision_id'] = $fields['note_id'];
        unset($rev['note_id']);
        $res = $wpdb->insert(self::table_name(), $rev);
        if ($res === false)
            return false;

        return array('state' => 'saved', 'note' => $note);
    }

    /**
     * @param $rnote = array(
     *     [author_id] default = current_user
     *     [dir_id] (required) assigned dir for song
     *     song_id - (required) id of the song
     *     [measure] default = 0
     *     [note_vps] - (required) comma delimited voice parts
     *     note - (required) contents of rnote
     *     location - (required) one of: nt-inbox, dir-inbox, review
     *     [discussion] default = ''
     * @return false if failed, else note_id of new note.
     */
    public static function add_note($note) {
        global $wpdb;

        if (!isset($note['author_id']))
            $note['author_id'] = get_current_user_id();
        if (!isset($note['song_id']))
            return false;
        if (!isset($note['dir_id']))
            return false;
        if (!isset($note['measure']))
            $note['measure'] = 0;
        if (!isset($note['note_vps']))
            return false;
        if (!isset($note['note']))
            return false;
        if (!isset($note['location']))
            return false;
        if (!in_array($note['location'], array('nt-inbox', 'dir-inbox', 'review')))
            return false;
        $note['time'] = current_time('mysql');

        $result = $wpdb->insert(self::table_name(), $note);
        if ($result === false)
            return false;

        $note_id = $wpdb->insert_id;
        $note = self::get_note($note_id);
        if ($result === false)
            return false;

        // Add to revisions
        $rev = $note;
        $rev['revision_id'] = $note_id;
        unset($rev['note_id']);
        $res = $wpdb->insert(self::table_name(), $rev);
        if ($res === false) {
            // Too late to return an error, since the note has already
            // been successfully added.  So just log this one.
            error_log('RNotes: failed to save Revision for:' . $note_id);
        }

        return array('state' => 'new', 'note' => $note);
    }

    public static function get_notes() {
        return self::get_rows('*', 'revision_id = 0 AND location != \'deleted\'');
    }

    public static function get_published_notes() {
        return self::get_rows('note_id, song_id, measure, note_vps, note',
            'revision_id = 0 AND location = \'published\'');
    }

    public static function get_changes($from_ts, $to_ts) {
        return self::get_rows('*',
            'revision_id = 0 AND author_id != ' . get_current_user_id() .
            ' AND time BETWEEN \'' . $from_ts . '\' AND \'' . $to_ts . '\'');
    }

    /**
     * Returns an array of IDs of type_id that have dependent Rehearsal Notes
     * If none of the type_id have dependent notes, returns an empty array
     *
     * This is used for safeguarding the deletion of Songs from the Song List
     * or Directors while there exist any Rehearsal Notes for them (a fatal condition).
     */
    public static function get_ids_with_dependent_notes($type_id) {
        global $wpdb;
        $table_name = self::table_name();
        $res = $wpdb->get_results("
            SELECT $type_id
            FROM $table_name
            WHERE revision_id = 0 AND location != 'deleted'
            GROUP BY $type_id
        ", ARRAY_A);

        $ids = array();
        foreach($res as $index => $row)
            $ids[] = intval($row[$type_id]);
        return $ids;
    }
}

class RnOnlineDB extends RnDB
{
    protected static $name = 'rn_online';

    public static function add_table() {
        global $wpdb;
        $table_name = self::table_name();

        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            singer_id bigint(20) unsigned NOT NULL,
            is_admin_page tinyint(1) NOT NULL,
            online datetime NOT NULL,
            PRIMARY KEY (singer_id, is_admin_page)
        ) $charset;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function update_time($is_admin_page) {
        global $wpdb;
        $wpdb->replace(self::table_name(), array(
            'singer_id' => get_current_user_id(),
            'is_admin_page' => $is_admin_page,
            'online' => current_time('mysql')));
    }

    public static function online() {
        return self::get_rows('singer_id, is_admin_page',
            'online > \'' . current_time('mysql') . '\' - INTERVAL 30 SECOND');
    }
}

class RnDoneDB extends RnDB
{
    protected static $name = 'rn_done';

    public static function add_table() {
        global $wpdb;
        $table_name = self::table_name();

        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            singer_id bigint(20) unsigned NOT NULL,
            note_id smallint(4) unsigned NOT NULL,
            done tinyint(1) NOT NULL,
            time datetime NOT NULL,
            PRIMARY KEY (singer_id, note_id)
        ) $charset;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * @return array of note_ids
     */
    public static function get_current_singer_done() {
        $rows = self::get_rows('note_id',
            'singer_id = ' . get_current_user_id() . ' AND done = 1');
        $ids = array();
        if ($rows !== false) {
            foreach($rows as $row)
                $ids[] = $row['note_id'];
        }
        return $ids;
    }

    public static function set_done($singer_id, $note_id, $done) {
        global $wpdb;

        $wpdb->replace(self::table_name(), array(
            'singer_id' => $singer_id,
            'note_id' => $note_id,
            'done' => $done,
            'time' => current_time('mysql')));
    }

    public static function clear_all_done($note_id) {
        global $wpdb;

        return $wpdb->update(self::table_name(),
            array(
                'done' => 0,
                'time' => current_time('mysql')
            ),
            array('note_id' => $note_id));
    }

    /**
     * @param $note_id
     * @return number of singers that have marked the note Done
     * Used by Rehearsal_notes_admin to know if a published note
     * can be edited.
     */
    public static function get_done_count($note_id = null) {

        $where = ($note_id != null) ? ' WHERE note_id = ' . $note_id : '';
        return self::_get_done_count($where);
    }

    public static function get_done_count_changes($from_ts, $to_ts) {
        $changed = self::get_rows('note_id',
            'time BETWEEN \'' . $from_ts . '\' AND \'' . $to_ts . '\'');
        if (count($changed) == 0)
            return array();

        $ids = array();
        foreach($changed as $change) {
            $ids[] = $change['note_id'];
        }
        $where = ' WHERE note_id IN (' . implode(',', $ids) . ')';
        return self::_get_done_count($where);
    }

    private static function _get_done_count($where) {
        global $wpdb;

        $query = '
            SELECT note_id, SUM(done) AS count
            FROM ' . self::table_name() . $where . '
            GROUP BY note_id
        ';
        $counts = $wpdb->get_results($query, ARRAY_A);
        $res = array();
        foreach ($counts as $count) {
            $res[$count['note_id']] = $count;
        }
        return $res;
    }

    public static function get_all_changes($from_ts, $to_ts) {
        return self::get_rows('*',
            'time BETWEEN \'' . $from_ts . '\' AND \'' . $to_ts . '\'');
    }

}
