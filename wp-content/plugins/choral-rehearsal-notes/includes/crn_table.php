<?php

/**
 *  User: joncu
 * Date: 3/15/19
 * Time: 3:52 PM
 */
class CrnTable
{

    private $columns = array();
    private $filters = array();
    private $sorters = array();
    private $presort = '';
    private $presets = array();
    private $buttons = array();
    private $rows = array();
    private $table_class = '';
    private $options = array();

    public function __construct()
    {
        wp_enqueue_script('crn_tablesorter');

        // default options
        $this->options['hide columns'] = false;
        $this->options['download'] = '';
        $this->options['no select'] = false;
        $this->options['highlight filters'] = false;
        $this->options['header offset'] = false;
        $this->options['directions-left'] = '';
        $this->options['title'] = '';
        $this->options['table-layout'] = 'fixed';
        $this->options['clear_empty_cells'] = true;
    }

    public function set_option($option, $setting)
    {
        $this->options[$option] = $setting;
    }

    /** Columns
     * $id = name identifier
     * $attrs = array:
     *     label: optional - default is $id capitalized
     *     tooltip: optional
     *     width: optional
     *     class: optional
     *     filter: optional - defaults to 'true'
     *     data-placeholder: optional - default text in filter
     *     custom-filters: optional array:
     *         name => fcn ,must be added with add_filter()
     *     sorter: optional - 'false' or 'custom name'
     *         Custom sorter must be added with add_sorter()
     */
    public function add_column($id, $attrs)
    {
        static $count = 0;
        // Keep track of the column number
        $attrs['col_number'] = $count;
        $count++;

        if (!isset($attrs['label']))
            $attrs['label'] = ucfirst($id);

        // If sorter is disabled for this column, we also need the no-sort class
        if (isset($attrs['sorter']) && $attrs['sorter'] === false) {
            if (isset($attrs['class']))
                $attrs['class'] .= ' no-sort';
            else
                $attrs['class'] = 'no-sort';
        }
        $this->columns[$id] = $attrs;
    }

    /** Provide initial sorting order by column
     * $cols = array: [column id]
     * Note - to sort Descending, prefix the column id with 'D|'
     */
    public function set_init_sort($cols)
    {
        $this->presort = $this->make_sort_list($cols);
    }

    private function make_sort_list($cols)
    {
        $sort = array();
        foreach ($cols as $col) {
            $order = '0';
            if (substr($col,0,2) === 'D|') {
                $order = '1';
                $col = substr($col, 2);
            }
            $sort[] = '[' . $this->columns[$col]['col_number'] . ',' . $order .']';
        }
        return implode(',', $sort);
    }

    /** JS function for custom filters
     * $name = function name used in column filter
     * $fcn = js function code
     */
    public function add_filter($name, $fcn)
    {
        $this->filters[$name] = $fcn;
    }

    /** Calls addPartser on the tablesorter
     * $id = name used in column header
     * $type = type of sorter
     * $fcn = js function code
     */
    public function add_sorter($id, $type, $fcn)
    {
        $this->sorters[$id] = array($type, $fcn);
    }

    /** Adds a present to the Dropdown (optional)
     * If not called, no preset dropdown will be created
     * - clear sorting & filters will be added automatically
     * $name = name that shows in the dropdown
     * $filters = array "col_id" => "filter"
     * $sort = array [col_id], to sort descending prefix col_id with 'D|'
     *
     * Presets e.g. {"filters":["","","text","",""],"sort":[[1,0],[3,0]]}
     *
     */
    public function add_filter_preset($name, $filters, $sort)
    {
        if (empty($this->presets)) {
            $this->presets['- clear sorting & filters -'] = $this->make_preset();
        }
        $this->presets[$name] = $this->make_preset($filters, $sort);
    }

    private function make_preset($filters = array(), $sort = array())
    {
        $flist = array();
        foreach ($this->columns as $id => $column) {
            $flist[] = '"' . (isset($filters[$id]) ? $filters[$id] : '') . '"';
        }
        $filter_str = '"filters":[' . implode(',', $flist) . ']';
        $sort_str = '"sort":[' . $this->make_sort_list($sort) . ']';
        return '{' . $filter_str . ',' . $sort_str . '}';
    }

    /** Add buttons to show in the caption
     * $button = array
     *     label, fcn, tooltip
     */
    public function add_button($button)
    {
        $this->buttons[] = $button;
    }

    /** Add a single row to the table
     * $row = array of fields
     */
    public function add_row($id, $row)
    {
        $this->rows[$id] = $row;
    }

    public function table_class($class)
    {
        $this->table_class .= $class . ' ';
    }

    public function get_html()
    {
        $html = '';

        // Start table
        $html .= '
        <div class="tborder topic_table' . ($this->options['no select'] ? ' noselect' : '') . '">
			<table id="crn_table" class="crn_table table_grid ' . $this->table_class . '" cellspacing="0" width="100%"
			    style="table-layout:' . $this->options['table-layout'] . '">
			<caption class="crn_caption">';

        $html .= '<div class="header">
            <div class="header-left">';

        if (!empty($this->options['title'])) {
            $html .= '<span class="crn_title"> ' . $this->options['title'] . '</span> &nbsp; ';
        }

        // Add Presets Dropdown
        if (!empty($this->presets)) {
            $html .= '
                Presets: <select class="presets">
                    <option value=""> </option>';
            foreach ($this->presets as $label => $setting) {
                $html .= '
                    <option value=\'' . $setting . '\'>' . $label . '</option>';
            }
            $html .= '
                </select> <small><i> Use shift-click to sort multiple columns</i></small>';
        }
        if (!empty($this->options['directions-left'])) {
            $html .= '<div class="directions">' .
                $this->options['directions-left'] . '</div>';
        }
        $html .= '</div>';  // header-left

            // Add buttons
        $html .= '
                <div class="buttonlist floatright">
                    <ul>';
        if ($this->options['hide columns']) {
            $html .= '
                    <li class="hide_button"><a href="#" onclick="crn_table.hide_columns()" title="Hide/Unhide columns"><span class="hide_button_text">HIDE COLUMNS</span></a></li>';
        }
        if (!empty($this->options['download'])) {
            $html .= '
                    <li><a href="#" onclick="crn_table.download()" title="Download visible table to CSV"><span>DOWNLOAD</span></a></li>';
        }
        if (!empty($this->buttons)) {
            foreach ($this->buttons as $button) {
                $html .= '
                    <li><a href="#" onclick="' . $button['fcn'] . '" title="' .
                    $button['tooltip'] . '"><span>' . $button['label'] . '</span></a></li>';
            }
        }
        $html .= '
                    </ul>
                </div>';

        $html .= '
            </caption>
            <thead>
				<tr>';

        // Add Header Row
        foreach ($this->columns as $column) {
            $attrs = '';
            foreach (['class', 'width', 'data-placeholder', 'title'] as $attr) {
                if (isset($column[$attr]))
                    $attrs .= ' ' . $attr . '="' . $column[$attr] . '"';
            }
            $html .= '
                    <th ' . $attrs . ' col-name="' . $column['label'] . '">';

            if ($this->options['hide columns']) {
                $html .= '
                        <div class="hide_check"><input name="hide_check" col-number="' . $column['col_number'] .
                    '" type="checkbox" title="Click to hide this column"> HIDE</div>';
            }
            $html .=  $column['label'] . '</th>';
        }

        // End Headers - start Body
        $html .= '
                </tr>
            </thead>
            <tbody>';

        // Add Body Rows
        if (empty($this->rows)) {
            $html .= '
                <tr>
                    <td colspan="' . count($this->columns) . '">No records were found</td>
                </tr>';
        } else {
            foreach ($this->rows as $id => $row) {
                // Start Row
                $html .= '
                <tr id="ts_' . $id . '"';
                if (isset($row['tr_pars'])) {
                    foreach ($row['tr_pars'] as $par => $value)
                        $html .= ' ' . $par . '="' . $value . '"';
                }
                $html .= '>';

                // Add Fields
                $col = 0;
                foreach ($row['data'] as $field => $value) {
                    $class = isset($row['class'][$field]) ? (' class="' . $row['class'][$field] . '"') : '';
                    if ($this->options['clear_empty_cells'])
                        $text = !empty($value) ? $value : '';
                    else
                        $text = $value;
                    $html .= '
                    <td data-column="' . $col++ . '" ' . $class . '>' . $text . '</td>';
                }
            }
            $html .= '
                </tr>';
        }

        // End table
        $html .= '
            </tbody>
            </table>
        </div>';

        // Javascript Section
        $html .= $this->getJS();
        return $html;
    }

    private function getJS()
    {
        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[
        function crn_table($) {';

        // Create the Tablesorter Headers and Filters strings
        // e.g. 7: {sorter:"vp_sorter"}, 10: {filter:false, sorter:false}
        // 4: {"Online":dropFilter, "Offline":dropFilter}
        $ts_filters = '';
        $ts_headers = '';
        foreach ($this->columns as $id => $column) {
            $filters = isset($column['custom-filters']) ? $column['custom-filters'] : null;
            if ($filters !== null) {
                if (is_array($filters)) {
                    $ts_filters .= $column['col_number'] . ': {';
                    foreach ($filters as $name => $fcn) {
                        $ts_filters .= '"' . $name . '":crn_table.' . $fcn . ', ';
                    }
                    $ts_filters .= '}, ';
                } else {
                    $ts_filters .= $column['col_number'] . ': ';
                    $ts_filters .= 'crn_table.' . $filters . ', ';
                }
            }

            $filter = isset($column['filter']) ? $column['filter'] : null;
            $sorter = isset($column['sorter']) ? $column['sorter'] : null;
            if ($filter !== null || $sorter !== null) {
                $ts_headers  .= $column['col_number'] . ': {';
                if ($filter !== null && $filter === false)
                    $ts_headers .= 'filter:false, ';
                if ($sorter !== null) {
                    if ($sorter === false)
                        $ts_headers .= 'sorter:false';
                    else
                        $ts_headers .= 'sorter:"' . $sorter . '"';
                }
                $ts_headers .= '}, ';
            }
        }

        // Start Document Ready
        $js .= '
        $(document).ready(function() {';

        // Add Sorter Functions
        foreach ($this->sorters as $id => $sorter) {
            $type = $sorter[0];
            $fcn = $sorter[1];
            $js .= '
            $.tablesorter.addParser({
                id: "' . $id . '",
                is: function(s) { return false; },
                format: function(s) {' . $fcn . '
                },
                type: "' . $type . '"
            });';
        }

        // If Presets - add the Binding
        if (!empty($this->presets)) {
            $js .= '
            $("#crn_table").bind("filterEnd sortEnd", function(e, table) {
                var data = {};
                data["filters"] = $.tablesorter.getFilters($(e.target));
                data["sort"] = e.target.config.sortList;
                var settings = JSON.stringify(data);
                $(".presets").val(settings);
            });';
        }

        // The following must be added after the Tablesorter has initialized
        // and the 2nd copy of the headers have been created.  In other words
        // These must be bound to both headers.
        $js .= '
            $("#crn_table").bind("tablesorter-initialized", function(e, table) {';

        if (!empty($this->presets)) {
            $js .= '
                $(".presets").change(function() {
                    var settings = $(this).val();
                    if (settings) {
                        var data = JSON.parse(settings);
                        var $table = $("#crn_table");
                        $table.trigger("filterReset");
                        $table.trigger("update", [data["sort"]]);
                        $.tablesorter.setFilters($table, data["filters"], true);';

            if ($this->options['highlight filters']) {
                // Update does not trigger change() ...
                $js .= '
                        $(".tablesorter-filter").each(function(i, filter) {
                            var column = $(this).data("column");
                            if (column > 1) {
                                if (data["filters"][column] == "")
                                    $(filter).removeClass("crn_filtered");
                                else
                                    $(filter).addClass("crn_filtered");
                            }
                        });';
            }
            $js .= '
                    }
                });';
        }

        if ($this->options['highlight filters']) {
            $js .= '
                $(".tablesorter-filter").change(function() {
                    if ($(this).data("column") > 1) {
                        if ($(this).val() == "") 
                            $(this).removeClass("crn_filtered");
                        else
                            $(this).addClass("crn_filtered");
                    }
                });';
        }

        if ($this->options['hide columns']) {
            $js .= '
                $("input[name=\'hide_check\']").click(function () {
                    var col = $(this).attr("col-number");
                    var checked = $(this).prop("checked");
                    $("input[col-number=" + col + "]").prop("checked", checked);
                });';
        }

        // End of Tablesorter initialization
        $js .= '
            });';

        $offset = is_admin_bar_showing() ? '30' : '0';
        if ($this->options['header offset'] !== false)
            $offset = $this->options['header offset'];

        // Add the TableSorter
        $js .= '
            $("#crn_table").tablesorter({
                headers: {' . $ts_headers . '},
                sortList: [' . $this->presort . '],
                widthFixed: false,
                widgets: ["filter","stickyHeaders"],
                widgetOptions: {
                    filter_hideFilters: false,
                    filter_functions: { ' . $ts_filters . ' },
                    stickyHeaders_zIndex: 30002,  // i-excel menu is 30001
                    stickyHeaders_offset: ' . $offset . '
                }
            });';

        // End Document Ready
        $js .= '
        });';

        // Add filter functions
        foreach ($this->filters as $name => $fcn) {
            $js .= '
            function ' . $name . '(e,n,f,i,$r,c,data) {' . $fcn .
            '
            }
            crn_table.' . $name . ' = ' . $name . ';';
        }

        if ($this->options['hide columns']) {
            $js .= '
            function hide_columns() {
                var button_text = $(".hide_button_text");
                if ("HIDE SELECTED COLUMNS" == button_text.html()) {
                    $.each($(".hide_check"), function() {
                        $(this).hide();
                    });
                    var hidden = false;
                    $.each($("table#crn_table-sticky").find("input[name=\'hide_check\']:checked"), function() {
                        var col = $(this).attr("col-number");
                        $("[data-column="+col+"]").hide("slow");
                        hidden = true;
                    });
                    if (hidden) 
                        button_text.html("SHOW/HIDE COLUMNS");
                    else
                        button_text.html("HIDE COLUMNS");
                } else {
                    button_text.html("HIDE SELECTED COLUMNS");
                    $.each($(".hide_check"), function() {
                        $(this).show();
                    });
                    $.each($("table#crn_table-sticky").find("input[name=\'hide_check\']:checked"), function() {
                        var col = $(this).attr("col-number");
                        $("[data-column="+col+"]").show("slow");
                    });
                }
            }
            crn_table.hide_columns = hide_columns;';
        }

        if (!empty($this->options['download'])) {
            $js .= '
            function download() {
                var csv = "";
                $.each($("#crn_table tr"), function() {
                    if ($(this).hasClass("tablesorter-ignoreRow"))
                        return;
                    if ($(this).hasClass("filtered"))
                        return;
                        
                    var row = [];
                    if ($(this).hasClass("tablesorter-headerRow")) {
                        $.each($(this).find("th"), function() {
                            addCell(row, $(this), $(this).attr("col-name"));
                        });
                    } else {
                        $.each($(this).find("td"), function() {
                            addCell(row, $(this), $(this).text());
                        });
                    }
                    csv += row.join(",") + "\r\n";
                });
                
                function addCell(row, cell, text) {
                    if (cell.is(":visible")) {
                        if (text.includes(",")) {
                            text = "\"" + text.replace("\"", "\\\\\"") + "\"";
                        }
                        row.push(text);
                    }
                }
                
                var download_link = document.createElement("a");
                var blob = new Blob(["\ufeff", csv]);
                var url = URL.createObjectURL(blob);
                download_link.href = url;
                
                var dt = new Date();
                var yr = dt.getFullYear().toString();
                var mo = ("0" + (dt.getMonth() + 1)).slice(-2);
                var dy = ("0" + dt.getDate()).slice(-2);
                var hr = ("0" + dt.getHours()).slice(-2);
                var mn = ("0" + dt.getMinutes()).slice(-2);
                var date = yr + mo + dy + "_" + hr + mn;
                download_link.download = "' . $this->options['download'] . '_" + date + ".csv";
                
                document.body.append(download_link);
                setTimeout(function() {
                    download_link.click();
                    document.body.removeChild(download_link);
                }, 1000);
            }
            crn_table.download = download;';
        }

         // Finish outer class
        $js .= '
        }
        crn_table(jQuery);
        // ]]></script>';
        return $js;
    }

}