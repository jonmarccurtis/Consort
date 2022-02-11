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
 * Class RnVpPage
 */
class RnVpPage
{
    private $songs, $table;

    public function __construct()
    {
        wp_enqueue_style('rn_common');
        wp_enqueue_style('rn_frontend');

        require_once(plugin_dir_path(__FILE__).'/../common/section_widget.php');
        require_once(plugin_dir_path(__FILE__).'/../common/rn_database.php');

        require_once(plugin_dir_path(__FILE__).'/../common/rn_options.php');
        $options = new RnOptions();
        $this->songs = $options->get_option('song-list');

        require_once(plugin_dir_path(__FILE__).'/../common/rn_table.php');
        $this->table = new RnTable();
        $this->table->table_class('singer-settings');
    }

    public function init_table() {
        $this->table->add_column('first_name',
            array('label' => 'F',
                'title' => 'Click to sort',
                'width' => 15));

        $this->table->add_column('last_name',
            array('label' => 'Last',
                'data-placeholder' => 'Filters ...',
                'title' => 'Click to sort',
                'width' => 110));

        $this->table->add_column('pvp',
            array('label' => 'pvp',
                'title' => 'Click to sort',
                'width' => 30,
                'sorter' => 'pvp_sorter'));

        $this->table->add_sorter('pvp_sorter', 'numeric', '
            var parts = ["S1","S2","A1","A2","T1","T2","B1","B2"];
            for (var i = 0; i < parts.length; i++) {
                if (s.substr(0,2) == parts[i])
                return i;
            }
            return parts.length;');

        foreach ($this->songs as $song) {
            $words = explode(' ', $song[RN::NAME]);
            $name = substr($words[0], 0, 4) . '<br>';
            array_shift($words);
            foreach($words as $word)
                $name .= $word[0];

            $this->table->add_column($name,
                array('title' => $song[RN::NAME],
                    'class' => 'song-name',
                    'custom-filters' => 'partsFilter',
                    'sorter' => 'vp_' . $song[RN::ID]));

            $this->table->add_sorter('vp_' . $song[RN::ID], 'numeric', '
            var parts = ' . json_encode(explode(',', $song[RN::VP])) . ';
            for (var i = 0; i < parts.length; i++) {
                var vp = s.split(",");
                if (vp.length > 0)
                    vp = vp[0];
                if (vp == parts[i])
                return i;
            }
            return parts.length;');
        }

        $this->table->add_filter('partsFilter', '
            e = ","+e+",";
            f = f.split(",");
            for (var i = 0; i < f.length; i++) {
                if (!e.includes(","+f[i]+","))
                    return false;
                }
            return true;
        ');

        $this->table->set_option('empty msg', 'Singer list has not be set yet.');
        $this->table->set_option('hide columns', true);
        $this->table->set_option('directions-left', '<em>&nbsp; Use shift-click to sort multiple columns.  Hover to see full song names.</em>');
        $this->table->set_option('header', '<a class="corner-help" href="/rn-refman?man=singer-ref-vpa-page" title="Help manual for this page" target="_blank">?</a>');

        $this->table->set_init_sort(['pvp','last_name','first_name']);
    }

    public function html() {
        $this->init_table();

        $singers = RnSingersDB::get_rows();
        foreach($singers as $singer) {
            if ($singer['is_singer']) {
                $row = $this->create_row_data($singer);
                $this->table->add_row($singer['singer_id'], $row);
            }
        }

        return $this->table->get_html();
    }

    private function create_row_data($singer) {
        $id = $singer['singer_id'];
        $first = get_user_field('first_name', $id);
        $last = get_user_field('last_name', $id);
        $pvp = $singer['primary_vp'];

        $row = array(
            'data' => array(
                'first_name' => $first[0],
                'last_name' => $last,
                'pvp' => $pvp
            ),
            'class' => array(
                'first_name' => 'first-name centertext',
                'last_name' => 'last-name',
                'pvp' => 'centertext'
            ),
            'tr_pars' => array(
                'class' => 'master_row',
            )
        );

        $singer_vps = SectionWidget::singer_vps($singer, $this->songs);
        foreach($this->songs as $song) {
            $vps = $singer_vps[$song[RN::ID]];
            $row['data'][$song[RN::NAME]] = '<span id="vps-' . $singer['singer_id']
                . '-' . $song[RN::ID] . '">' . $vps . '</span>';
            $row['class'][$song[RN::NAME]] = 'centertext vps-setting';
        }
        return $row;
    }

}

