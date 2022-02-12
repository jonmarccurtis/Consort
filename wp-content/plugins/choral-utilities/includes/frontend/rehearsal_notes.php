<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class CuRehearsalNotes
{
    private $table, $rnpath;

    public function __construct()
    {
        // WEB ADMIN: The Rehearsal Notes must be uploaded and replaced at the following location
        // in the Media Library.  If this changes, the following path needs to be updated to match.
        $lib = wp_upload_dir('2022/02');
        $this->rnpath = $lib['path'] . '/consort-rehearsal-notes.csv';

        require_once(plugin_dir_path(__FILE__).'cu_table.php');
        $this->table = new CuTable();
    }

    public function html()
    {
        if (!file_exists($this->rnpath))
            return '<p>ERROR: Rehearsal Notes were not found at "' . $this->rnpath . '"<br>Please report this error to the Web Admin.</p>';

        // Collect the settings and data
        $this->add_columns();
        $this->add_settings();
        $this->add_notes();

        // Spit it out
        $html = $this->table->get_html();
        return $html;
    }

    private function add_columns() {
        $this->table->add_column('Date',
            array('title' => 'Click to sort',
                'data-placeholder' => 'Filters ...',
                'width' => '70',
                'custom-filters' => 'dateFilter'));

        $this->table->add_column('Song',
            array('title' => 'Click to sort',
                'width' => '170'));

        $this->table->add_column('msr',
            array('label' => 'msr',
                'title' => 'Click to sort',
                'width' => '50'));

        $this->table->add_column('Section',
            array('width' => '70',
                'custom-filters' => 'sectionFilter'));

        $this->table->add_column('Note',
            array('sorter' => false));
    }

    private function add_settings()
    {
        $this->table->set_option('clear_empty_cells', false);
        $this->table->table_class('rntable');

        // Enable download to CSV
        $this->table->set_option('download', 'Consort_Master_List');

        // Initial sort order on page load
        $this->table->set_init_sort(array('Song', 'msr'));

        $instr = <<<EOT
[accordion][accordion_item title='Instructions']
<b>Filters</b> are entered in the second row, below the column headers<br>
To remove older notes: enter the last date (m-d) you copied notes, in the Date column.<br>
To view only your part's notes, enter your section letter(s) in the 'Section' column.<br>
(Notes for middle voices are already included with both their upper and lower sections.)<br>
<span style='color:red'><i>&nbsp;&nbsp;Be sure to press </i>'return'<i> after changing a filter.</i></span><br><br>
<b>Sorting</b> is done by clicking on the headers to sort by that column.<br>
Use shift-click to sort on more than one column at the same time.<br>
Additional clicks change the ascending/descending direction.<br>
The default sorting is: 'Song' ascending (up arrow), then shift-click 'msr' ascending.<br><br>
<b>DOWNLOAD</b> will only include visible rows, as they have been filtered and sorted.
[/accordion_item][/accordion]
EOT;
        $this->table->set_option('directions-left', $instr);

        // These are referenced in the 'custom-filters' above.  This
        // is Javascript inside a function with the filter() parameters.
        // e = cell text being tested, f = filter text

        // dateFilter = format is m-d
        $this->table->add_filter('dateFilter','
            var cell = e.split("-");
            var filter = f.split("-");
            if (cell.length != 2 || filter.length != 2)
                return false;
            if (cell[0] != filter[0])
                return (parseInt(cell[0]) > parseInt(filter[0]));
            return (parseInt(cell[1]) >= parseInt(filter[1]));
            ');

        // See if any filter section(s) are in the cell.  It is an OR conditional.
        $this->table->add_filter('sectionFilter','
            for (var i = 0; i < f.length; i++) {
                section = f.charAt(i);
                if (e.includes(section))
                    return true;
            }
            return false;
            ');
    }

    private function add_notes()
    {
        $rows = array_map('str_getcsv', file($this->rnpath));
        $header = array_shift($rows);
        $csv = array();
        foreach ($rows as $row) {
            $csv[] = array_combine($header, $row);
        }
        $id = 0;
        foreach ($csv as $note) {
            if (empty($note['Date']))
                continue;   // Skip empty rows

            $sections = '';
            foreach (['S', 'A', 'T', 'B'] as $section) {
                $sections .= $note[$section] == 'TRUE' ? $section : '&nbsp;';
            }

            // Data keys must be the same as the column ids
            $row = array(
                'data' => array(
                    'date' => $note['Date'],
                    'song' => $note['Song'],
                    'msr' => $note['msr'],
                    'section' => $sections,
                    'note' => $note['Note']),

                'class' => array(
                    'date' => 'centertext',
                    'msr' => 'centertext',
                    'section' => 'centertext cu_monospaced'));

            $this->table->add_row($id++, $row);
        }
        return null;
    }

}
