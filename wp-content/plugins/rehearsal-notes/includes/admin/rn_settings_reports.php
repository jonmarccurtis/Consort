<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

/**
 * Class RnReportsTab
 */
class RnReportsTab extends RnTab
{
    protected function register_tab()
    {
        $section = 'rn-reports-settings-section';
        $this->add_section($section, 'Rehearsal Notes Report for ' . current_time('mysql'));
    }

    public function render_section_info()
    {
        echo '<p><a href="/rn-refman?man=admin-ref-reporting" target="_blank">Instruction Manual</a></p>';
        require_once(plugin_dir_path(__FILE__).'/../common/rn_database.php');
        require_once(plugin_dir_path(__FILE__).'/../common/section_widget.php');
        require_once(plugin_dir_path(__FILE__).'/../common/rn_options.php');
        require_once(plugin_dir_path(__FILE__).'/../common/rn_table.php');

        // Get the DB data ...

        $res = RnNotesDB::get_rows('max(time)',
            'revision_id = 0 AND location = \'published\'');
        if ($res === false) {
            echo 'Unable to read Notes Database.';
            return;
        }
        if (empty($res)) {
            echo 'Notes database is empty';
            return;
        }
        $last_note_entered = $res[0]['max(time)'];
        $last_note = strtotime($last_note_entered);

        $notes = RnNotesDB::get_rows('note_id, song_id, note_vps',
            'revision_id = 0 AND location = \'published\'');
        if ($notes === false) {
            echo 'Unable to read Notes Database.';
            return;
        }

        $res = RnSingersDB::get_rows('singer_id, primary_vp, vp_exceptions',
            'is_singer = 1');
        if ($res === false) {
            echo 'Unable to read Singers Database.';
            return;
        }

        $options = new RnOptions();
        $song_list = $options->get_option('song-list');
        $songs = array();
        foreach($song_list as $song) {
            $songs[$song[RN::ID]] = $song;
        }

        // Compile the data ...

        $total_mine = 0;
        $total_done = 0;
        $parts = array();
        foreach(['S','A','T','B'] as $part) {
            $parts[$part] = array(
                'mine' => 0,
                'done' => 0
            );
        }

        $singers = array();
        foreach($res as $singer) {
            if (empty($singer['primary_vp']))
                continue; // This is a mistake in the settings.  All singers should have a PVP.  So leave them out.

            $mine_count = 0;
            $done_count = 0;

            $singer_id = $singer['singer_id'];

            $done_ids = array();
            $res = RnDoneDB::get_rows('note_id',
                'singer_id = ' . $singer_id . ' AND done = 1');
            if ($res !== false) {
                foreach($res as $done)
                    $done_ids[] = $done['note_id'];
            }

            // Get the singer's VPS list per song
            $raw_vps = SectionWidget::singer_vps($singer, $songs);
            $singer_vps = array();
            foreach($raw_vps as $song_id => $vps_str) {
                $vps_list = explode(',', $vps_str);
                $vps_test = array();
                foreach ($vps_list as $vps) {
                    $vps_test[] = ',' . $vps . ',';
                }
                $singer_vps[$song_id] = $vps_test;
            }

            foreach($notes as $note) {
                // See if the note is "mine"
                $is_mine = false;
                $test_vps = ',' . $note['note_vps'] . ',';
                foreach($singer_vps[$note['song_id']] as $vps) {
                    if (strpos($test_vps, $vps) !== false) {
                        $is_mine = true;
                        break;
                    }
                }
                if (!$is_mine)
                    continue; // don't care about non-mine notes

                $mine_count++;
                if (in_array($note['note_id'], $done_ids))
                    $done_count++;
            }

            if ($mine_count > 0)
                $done_percent = round($done_count * 100 / $mine_count);
            else
                $done_percent = 100;

            $res = RnOnlineDB::get_rows('online',
                'singer_id = ' . $singer_id . ' AND is_admin_page = 0');
            $last_online = '[never]';
            $up_to_date = false;
            if ($res !== false && count($res) == 1) {
                $last_online = $res[0]['online'];
                $up_to_date = strtotime($last_online) > $last_note;
            }

            $user = get_userdata($singer_id);
            $singers[$singer_id] = array(
                'my_notes' => $mine_count,
                'done_notes' => $done_count,
                'done_percent' => $done_percent,
                'pvp' => $singer['primary_vp'],
                'first' => $user->first_name,
                'last' => $user->last_name,
                'last_online' => $last_online,
                'up_to_date' => $up_to_date
            );

            $total_mine += $mine_count;
            $total_done += $done_count;
            $parts[$singer['primary_vp'][0]]['mine'] += $mine_count;
            $parts[$singer['primary_vp'][0]]['done'] += $done_count;
        }

        $percent_done = 0;
        if ($total_mine > 0)
            $percent_done = round($total_done * 100 / $total_mine);

        // Create the Table ...

        $table = new RnTable();
        $table->table_class('rn-report');
        $table->set_option('download', 'RN_Report');
        $table->set_option('directions-left', '&nbsp;<em>Use shift-click to sort multiple columns</em>');

        $table->add_column('first',
            array('width' => '80'));
        $table->add_column('last',
            array('width' => '110'));
        $table->add_column('vp',
            array('label' => 'VP',
                'width' => '30',
                'sorter' => 'pvp_sorter'));
        $table->add_column('count',
            array('width' => '60'));
        $table->add_column('done',
            array('width' => '40'));
        $table->add_column('online',
            array('label' => 'Last Online',
                'width' => '150',
                'sorter' => 'online_sorter'));

        $table->add_sorter('pvp_sorter', 'numeric', '
            var parts = ["S1","S2","A1","A2","T1","T2","B1","B2"];
            for (var i = 0; i < parts.length; i++) {
                if (s.substr(0,2) == parts[i])
                return i;
            }
            for (var i = 0; i < parts.length; i++) {
                if (s == parts[i].substr(0,1))
                return i;
            }
            return parts.length;');

        $table->add_sorter('online_sorter', 'text', '
            return (s == "[never]") ? "0" : s;
            ');

        $table->set_init_sort(['vp','last','first']);

        $table->set_option('section breaks', array(1, 5));

        // Totals Row, table section 1
        $row_id = 1000;
        $totals_row = array(
            'data' => array(
                'first' => 'TOTAL',
                'last' => '',
                'vp' => '',
                'count' => count($notes),
                'done' => $percent_done . '%',
                'online' => $last_note_entered
            ),
            'tr_pars' => array(
                'class' => 'totals-row'
            )
        );
        $table->add_row(++$row_id, $totals_row);

        // Parts Rows, table section 2, rows 2-5
        foreach($parts as $part => $total) {
            $percent_done = 0;
            if ($total['mine'] > 0)
                $percent_done = round($total['done'] * 100 / $total['mine']);

            $totals_row = array(
                'data' => array(
                    'first' => '',
                    'last' => '',
                    'vp' => $part,
                    'count' => '',
                    'done' => $percent_done . '%',
                    'online' => ''
                ),
                'tr_pars' => array(
                    'class' => 'totals-row'
                )
            );
            $table->add_row(++$row_id, $totals_row);
        }

        // Singers Rows, table section 3
        foreach($singers as $id => $singer) {
            $row = array(
                'data' => array(
                    'first' => $singer['first'],
                    'last' => $singer['last'],
                    'vp' => $singer['pvp'],
                    'count' => $singer['done_notes'] . '/' . $singer['my_notes'],
                    'done' => $singer['done_percent'] . '%',
                    'online' => $singer['last_online']
                ),
                'class' => array(
                    'online' => $singer['up_to_date'] ? '' : 'singer-late'
                )
            );
            $table->add_row($id, $row);
        }

        echo $table->get_html();
    }
}


