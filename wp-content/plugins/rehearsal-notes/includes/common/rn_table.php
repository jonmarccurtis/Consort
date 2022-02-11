<?php

/**
 *  User: joncu
 * Date: 3/15/19
 * Time: 3:52 PM
 */
class RnTable extends RnAjaxHandler
{

    private $columns = array();
    private $filters = array();
    private $prefilter = '';
    private $sorters = array();
    private $presort = '';
    private $presets = array();
    private $buttons = array();
    private $rows = array();
    private $table_class = '';
    private $options = array();

    public function __construct()
    {
        wp_enqueue_script('rn_tablesorter');

        // default options
        $this->options['hide columns'] = false;   // Ability to hide columns
        $this->options['download'] = '';          // Adds a download CSV button
        $this->options['no select'] = false;      // Prevents select/copy/paste of table
        $this->options['highlight filters'] = false;  // Set filters are highlighted
        $this->options['empty msg'] = 'No Rehearsal Notes were found';
        $this->options['dynamic selection'] = false;  // Ability to highlight rows
        $this->options['directions'] = '';        // Directions text at top-right of table
        $this->options['directions-left'] = '';   // Directions text at top-left
        $this->options['section breaks'] = array();  // row indices at table sections
        $this->options['title'] = '';             // Title that stays at top with scrolling
        $this->options['header'] = '';            // HTML above header (duplicated, so can't set element ids)

        // Filter/Sort settings remembered between sessions
        // Value is one of ('admin' or 'singer'), and is used in the meta key
        $this->options['remember settings'] = '';
    }

    public function set_option($option, $setting)
    {
        $this->options[$option] = $setting;
    }

    private function user_meta_key() {
        return 'rn_' . $this->options['remember settings'] . '_table_settings';
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
    public function add_column($id, $attrs = array())
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
     *
     * This setting is overwritten by set_init_filter.  It is
     * only provided if the sort needs to be different than any
     * of the preset filters, so they cannot be used.
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

    /** Makes this filter the default on page open
     * @param $name = name of a filter given in add_filter
     *
     * This overrides set_init_sort()
     */
    public function set_init_filter($name) {
        $this->prefilter = $name;
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
			<table id="rn_table" class="rn-table table_grid ' . $this->table_class . '" cellspacing="0" width="100%">
			<caption>';

        if (!empty($this->options['header'])) {
            $html .= $this->options['header'];
        }
        $html .= '<div class="header">
            <div class="header-left">';

        if (!empty($this->options['title'])) {
            $html .= '<span class="rn-title"> ' . $this->options['title'] . '</span> &nbsp; ';
        }

        // Add Presets Dropdown
        if (!empty($this->presets)) {
            $html .= '
                Presets:&nbsp;<select class="presets">
                    <option value=""> </option>';
            foreach ($this->presets as $label => $setting) {
                $html .= '
                    <option value=\'' . $setting . '\'>' . $label . '</option>';
            }
            $html .= '
                </select> <small><i> Shift&#8209click:</i>&nbsp;Sort&nbsp;multiple&nbsp;columns</small>';
        }
        if (!empty($this->options['directions-left'])) {
            $html .= '<div class="directions">' .
                $this->options['directions-left'] . '</div>';
        }
        $html .= '</div>';  // header-left

        // Add buttons
        $html .= '
            <div class="header-right">
                <div class="buttonlist">
                    <ul>';
        if (!empty($this->buttons)) {
            foreach ($this->buttons as $button) {
                $html .= '
                    <li><a href="#" onclick="' . $button['fcn'] . ';return false;" title="' .
                    $button['tooltip'] . '"><span>' . $button['label'] . '</span></a></li>';
            }
        }
        if ($this->options['hide columns']) {
            $html .= '
                    <li class="hide_button"><a href="#" onclick="rn_table.hide_columns();return false;" title="Hide/Unhide columns"><span class="hide_button_text">HIDE COLUMNS</span></a></li>';
        }
        if (!empty($this->options['download'])) {
            $html .= '
                    <li><a href="#" onclick="rn_table.download();return false;" title="Download visible table to CSV"><span>DOWNLOAD</span></a></li>';
        }
        $html .= '
                </ul>
            </div>';  // header-right

        if (!empty($this->options['directions'])) {
            $html .= '<div class="directions">' .
                $this->options['directions'] . '</div>';
        }

        $html .= '</div></div>';  // directions, header
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
                    <td colspan="' . (count($this->columns) - 1) . '">' . $this->options['empty msg'] . '</td>
                </tr>';
        } else {
            $count = 0;
            foreach ($this->rows as $id => $row) {
                $html .= $this->get_row($id, $row);
                if (in_array(++$count, $this->options['section breaks'])) {
                    $html .= '
            </tbody>
            <tbody>';
                }
            }
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

    /**
     * @param $id
     * @param $row - row data, same as sent to add_row()
     * @return string - a complete row including its <tr>
     */
    public function get_row($id, $row) {
        $html = '<tr id="rntr-' . $id . '"';
        if (isset($row['tr_pars'])) {
            foreach ($row['tr_pars'] as $par => $value)
                $html .= ' ' . $par . '="' . $value . '"';
        }
        $html .= '>';
        $html .= $this->get_fields($row);
        $html .= '</tr>';
        return $html;
    }

    /**
     * @param $row - row data, same as sent to add_row()
     * @return string - the innerHTML of a row
     */
    public function get_fields($row) {
        $col = 0;
        $html = '';
        foreach ($row['data'] as $field => $value) {
            $class = isset($row['class'][$field]) ? (' class="' . $row['class'][$field] . '"') : '';
            $text = !empty($value) ? $value : '';
            $html .= '<td data-column="' . $col++ . '" ' . $class . '>' . $text . '</td>';
        }
        return $html;
    }

    private function getJS()
    {
        // Create the Tablesorter Headers and Filters strings
        // e.g. 7: {sorter:"vp_sorter"}, 10: {filter:false, sorter:false}
        // 4: {"Online":dropFilter, "Offline":dropFilter}

        // The initial filter must be set before the page is loaded, so that
        // page loading does not reset the 'remembered settings' from the
        // last page load.
        $init_filter = null;
        if ($this->prefilter != '')
            $init_filter = $this->presets[$this->prefilter];
        if ($this->options['remember settings'] != '') {
            $saved_filter = get_user_meta(get_current_user_id(), $this->user_meta_key(), true);
            if ($saved_filter != '')
                $init_filter = json_encode($saved_filter);
        }

        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[
        function rn_table($) {';

        $ts_filters = '';
        $ts_headers = '';
        foreach ($this->columns as $id => $column) {
            $filters = isset($column['custom-filters']) ? $column['custom-filters'] : null;
            if ($filters !== null) {
                if (is_array($filters)) {
                    $ts_filters .= $column['col_number'] . ': {';
                    foreach ($filters as $name => $fcn) {
                        $ts_filters .= '"' . $name . '":rn_table.' . $fcn . ', ';
                    }
                    $ts_filters .= '}, ';
                } else {
                    $ts_filters .= $column['col_number'] . ': ';
                    $ts_filters .= 'rn_table.' . $filters . ', ';
                }
            }

            $filter = isset($column['filter']) ? $column['filter'] : null;
            $sorter = isset($column['sorter']) ? $column['sorter'] : null;
            if ($filter !== null || $sorter !== null) {
                    $ts_headers .= $column['col_number'] . ': {';
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

        // If Presets or 'remember settings' - add the Binding
        if (!empty($this->presets) || $this->options['remember settings'] != '') {
            $js .= '
            $("#rn_table").bind("filterEnd sortEnd", function(e, table) {
                var data = {};
                data["filters"] = $.tablesorter.getFilters($(e.target));
                data["sort"] = e.target.config.sortList;
                var settings = JSON.stringify(data);';

            if (!empty($this->presets)) {
                $js .= '
                $(".presets").val(settings);';
            }

            if ($this->options['dynamic selection']) {
                $js .= '
                $(".rn-selected").each(function() {
                    if ($(this).is(":visible")) {
                    var offset = $(this).offset().top - ($(window).height() / 2);
                    $("html, body").animate({scrollTop: offset}, 700);
                    return false;
                }
                });';
            }

            if ($this->options['remember settings'] != '') {
                $js .= '
                var data = {
                    settings: settings,
                    type: e.type,
                    page: "' . $this->options['remember settings'] . '"
                };
                rn_common.send_request("rn_tset", data, rn_table.save_response, 
                    rn_table.save_response);';
            }

            $js .= '
            });';
        }

        // The following must be added after the Tablesorter has initialized
        // and the 2nd copy of the headers have been created.  In other words
        // These must be bound to both headers.
        $js .= '
            $("#rn_table").bind("tablesorter-initialized", function(e, table) {';

        if (!empty($this->presets)) {
            $js .= '
                $(".presets").change(function() {
                    var settings = $(this).val();
                    if (settings) {
                        rn_table.apply_filter(settings);
                    }
                });';
        }

        if ($this->options['highlight filters']) {
            $js .= '
                $(".tablesorter-filter").change(function() {
                    if ($(this).data("column") > 1) {
                        if ($(this).val() == "") 
                            $(this).removeClass("rn_filtered");
                        else
                            $(this).addClass("rn_filtered");
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

        if ($init_filter != null) {
            $js .= '
                apply_filter(\'' . $init_filter . '\');';
        }

        // End of Tablesorter initialization
        $js .= '
            });';

        // Add the TableSorter
        $js .= '
            $("#rn_table").tablesorter({
                headers: {' . $ts_headers . '},
                sortList: [' . $this->presort . '],
                widthFixed: false,
                widgets: ["filter"' . (wp_is_mobile() ? '' : ', "stickyHeaders"') . '],
                widgetOptions: {
                    filter_hideFilters: false,
                    filter_functions: { ' . $ts_filters . ' },
                    stickyHeaders_zIndex: 30002,  // i-excel menu is 30001
                    stickyHeaders_offset: ' . (is_admin_bar_showing() ? '30' : '0') . '
                }
            });';

        // End Document Ready
        $js .= '
        });';

        $js .= '
        function apply_filter(settings) {
            var data = JSON.parse(settings);
            var $table = $("#rn_table");
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
                        $(filter).removeClass("rn_filtered");
                    else
                        $(filter).addClass("rn_filtered");
                }
            });';
        }
        $js .= '
        }
        rn_table.apply_filter = apply_filter;';

        if ($this->options['remember settings'] != '') {
            // If it fails, fail silently
            $js .= '
        function save_response(res) {}
        rn_table.save_response = save_response;
            ';
        }

        // Add filter functions
        foreach ($this->filters as $name => $fcn) {
            $js .= '
            function ' . $name . '(e,n,f,i,$r,c,data) {' . $fcn .
            '
            }
            rn_table.' . $name . ' = ' . $name . ';';
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
                    $.each($("table#rn_table").find("input[name=\'hide_check\']:checked"), function() {
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
                    $.each($("table#rn_table").find("input[name=\'hide_check\']:checked"), function() {
                        var col = $(this).attr("col-number");
                        $("[data-column="+col+"]").show("slow");
                    });
                }
            }
            rn_table.hide_columns = hide_columns;';
        }

        if (!empty($this->options['download'])) {
            $js .= '
            function download() {
                var csv = "";
                $.each($("#rn_table tr"), function() {
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
            rn_table.download = download;';
        }

        $js .= '
            function update_table() {
                var pos = Math.max( $("html").scrollTop(), $("body").scrollTop());
                $("#rn_table").trigger("update");
                $("html, body").scrollTop(pos);
            }
            rn_table.update_table = update_table;';

        if ($this->options['dynamic selection']) {
            $js .= '
            function is_selected_row(row) {
                return (row).hasClass("rn-selected");
            }
            rn_table.is_selected_row = is_selected_row;
            
            function set_selected_rows(rows, multi=false, set=true) {
                if (!multi) 
                    $(".rn-selected").removeClass("rn-selected");
                $.each(rows, function() {
                    if (set)
                        $(this).addClass("rn-selected");
                    else
                        $(this).removeClass("rn-selected");
                });
            }
            rn_table.set_selected_rows = set_selected_rows;
            ';
        }
         // Finish outer class
        $js .= '
        }
        rn_table(jQuery);
        // ]]></script>';
        return $js;
    }

    protected function check_user_can_handle_ajax() {
        if (!current_user_can('access_s2member_level2'))
            self::send_fatal_error(16001); // Non-member: Access denied
    }

    protected function do_ajax_request($action) {
        if (method_exists($this, $action) === false)
            self::send_fatal_error(16002); // Action method not defined
        $this->$action();
    }

    public function rn_table_settings() {
        if (!isset($_POST['settings']))
                self::send_fatal_error(16003);
        $settings = sanitize_text_field($_POST['settings']);
        $settings = json_decode(stripslashes($settings), true);

        if (!isset($_POST['type']))
            self::send_fatal_error(16004);
        $type = sanitize_text_field($_POST['type']);
        if (!in_array($type, array('filterEnd', 'sortEnd')))
            self::send_fatal_error(16005);

        if (!isset($_POST['page']))
            self::send_fatal_error(16006);
        $page = $_POST['page'];
        if (!in_array($page, array('singer', 'admin')))
            self::send_fatal_error(16007);
        $this->set_option('remember settings', $page);

        // Only update the part that has changed.  This is necessary to avoid
        // a timing defect where the init of the table does only a sort, so
        // it passes in an empty filter which can get propagated back.
        $saved = get_user_meta(get_current_user_id(), $this->user_meta_key(), true);
        if ($saved == '') {
            $saved = $settings;
        } else if ($type == 'filterEnd') {
            $saved['filters'] = $settings['filters'];
        } else {
            $saved['sort'] = $settings['sort'];
        }

        update_user_meta(get_current_user_id(), $this->user_meta_key(), $saved);
        wp_send_json_success('');
    }
}