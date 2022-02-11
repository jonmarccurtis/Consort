<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class CuMemberHistory
{
    private $table, $columns, $data;

    public function __construct()
    {
        require_once(plugin_dir_path(__FILE__).'cu_table.php');
        $this->table = new CuTable();

        require_once(plugin_dir_path(__FILE__).'/../../assets/data/history_data.php');
        $this->columns = CuHistory::years();
        $this->data = CuHistory::data();
    }

    public function html()
    {
        // Collect the settings and data
        $this->add_columns();
        $this->add_settings();
        $this->add_members();

        // Spit it out
        return $this->table->get_html();
    }

    private function add_columns() {
        $this->table->add_column('name',
            array('tooltip' => 'Click to sort',
                'width' => '120px',
                'data-placeholder' => 'Filters ...',
                'sorter' => 'name_sorter'));

        $this->table->add_column('active_years',
            array('label' => 'Active<br>Years',
                'tooltip' => 'Click to sort',
                'width' => '53'));

        foreach ($this->columns as $year => $total) {
            $this->table->add_column($total . '<br>' . $year,
                array('width' => '28',
                    'sorter' => false,
                    'custom-filters' => array(
                        $year => 'yearFilter')));
        }
    }

    private function add_settings()
    {
        $this->table->set_option('title', 'Member History');

        // Enable hiding columns and download to CSV
        $this->table->set_option('highlight filters', true);
        if (S2MEMBER_CURRENT_USER_ACCESS_LEVEL >= 3)
            $this->table->set_option('download', 'Consort_Member_History');

        // Initial sort order on page load
        $this->table->set_init_sort(array('name'));

        // For a preset dropDown selector
        $this->table->add_filter_preset('Sort by name',
            array(),
            array('name'));
        $this->table->add_filter_preset('Sort by active years',
            array(),
            array('D|active_years', 'name'));
        $this->table->add_filter_preset('Founding members',
            array('30<br>94' => '94'),
            array('name'));
        $this->table->add_filter_preset('More than 20 years',
            array('active_years' => '>20'),
            array('name'));

        $this->table->add_sorter('name_sorter', 'text', '
            var name = s.trim();
            var idx = name.lastIndexOf(" ");
            if (idx == -1)
                return name;
            var first = name.substring(0, idx);
            var last = name.substring(idx + 1, name.length);
            return last + " " + first;
        ');

        $this->table->add_filter('yearFilter','
            return data.iExact == data.iFilter;
        ');
    }

    private function add_members()
    {
        $id = 0;
        foreach ($this->data as $name => $active_years) {
            $row = array(
                'data' => array(
                    'name' => $name,
                    'active_years' => array_sum($active_years)
                ),
                'class' => array(
                    'name' => 'righttext',
                    'active_years' => 'centertext'
                )
            );
            $index = 0;
            $years = array_keys($this->columns);
            foreach($active_years as $active) {
                $year = $years[$index++];
                $row['data'][$year] = $active ? $year : '';
                $row['class'][$year] = 'centertext';
            }
            $this->table->add_row($id++, $row);
        }
    }
}
