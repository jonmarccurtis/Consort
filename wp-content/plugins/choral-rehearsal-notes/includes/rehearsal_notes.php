<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class CrnRehearsalNotes
{
    private $table, $rnpath;

    public function __construct($atts)
    {
        if (isset($atts['path']))
            $this->rnpath = wp_upload_dir()['basedir'] . '/' . $atts['path'];
        else
            $this->rnpath = '[shortcode missing path attribute]';

        require_once(plugin_dir_path(__FILE__).'crn_table.php');
        $this->table = new CrnTable();
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
                'width' => '200'));

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
        $this->table->table_class('crntable');

        // Enable download to CSV
        $this->table->set_option('download', 'Consort_Rehearsal_Notes');

        // Initial sort order on page load
        $sort_order = array('Song', 'msr');
        $this->table->set_init_sort($sort_order);
        $this->table->add_sort_button('DEFAULT SORT', 'Reset the default sort order', $sort_order);

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
                    'song' => 'songtext',
                    'msr' => 'centertext',
                    'section' => 'centertext crn_monospaced'));

            $this->table->add_row($id++, $row);
        }
        return null;
    }

}
