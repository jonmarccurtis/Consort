<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

require_once(plugin_dir_path(__FILE__).'/../common/cu_options.php');

class CuAbsenceTable
{
    private $options, $months, $dates, $update_errors;

    public function __construct()
    {
        $this->options = new CuOptions();
        $this->months = array();
        $this->dates = array();
        $this->update_errors = array();
    }

    public function html()
    {
        if (!$this->get_data())
            return '<pre>Rehearsal dates are not available yet</pre>';

        $html = $this->add_columns();
        $html .= $this->add_members();
        $html .= $this->getJS();
        return $html;
    }

    private function get_data()
    {
        $primary_dates = $this->options->get_option('primary-rehearsal-dates');
        $assoc_dates = $this->options->get_option('associate-rehearsal-dates');
        $consort_week = $this->options->get_option('consort-week-start-date');

        if (empty($primary_dates) || empty($assoc_dates) || empty($consort_week))
            return false;

        $this->build_data($primary_dates, 'primary');
        $this->build_data($assoc_dates, 'assoc');
        $this->build_data($consort_week, 'cw');
        ksort($this->months);
        ksort($this->dates);

        return true;
    }

    private function add_columns() {
        $html = '
        <table class="absence">
            <tr>
                <th colspan="2"></th>';

        foreach($this->months as $month) {
            $html .= '
                <th colspan="' . $month['cols'] . '" class="month">' . $month['name'] . '</th>';
        }

        $html .= '
            </tr>
                <th>Singers</th>
                <th>Part</th>';

        foreach($this->dates as $date) {
            $html .= '
                <th class="' . $date['type'] . ' days">' . $date['day'] . '</th>';
        }

        $html .= '
            </tr>';
        return $html;
    }

    private function build_data($date_list, $type) {
        if (empty($date_list))  // (redundant)
            return;
        $dates = explode(',', $date_list);
        foreach($dates as $date) {
            list ($month, $day) = explode('/', $date);
            $date = $month . '/' . substr('0' . $day, -2);
            if (!isset($this->months[$month])) {
                $dateObj   = DateTime::createFromFormat('!m', $month);
                $name = $dateObj->format('F');
                $this->months[$month] = array('name' => $name, 'cols' => 1);
            } else {
                $this->months[$month]['cols']++;
            }

            if ($type == 'cw') {
                $day = $day . '-' . ($day + 5);
                $type = 'primary';
            }
            $this->dates[$date] = array('day' => $day, 'type' => $type);
        }
    }

    private function add_members()
    {
        // Gather data
        $singers = array();
        $members = get_users();

        $mids = array();
        foreach ($members as $member) {
            $mids[]= $member->data->ID;
        }
        foreach ($mids as $id) {
            $group = get_user_field('s2member_access_label', $id);
            if (in_array($group, array('Inactive', 'Member')))
                continue; // Only showing Singers

            $vp = trim(get_user_field('voice', $id));
            if (empty($vp))
                continue;  // Weed out non-singers

            // Flag for Non-Singer in Administrative Notes.  This enables marking of
            // Web Assist or Board members who are not participating in the current season.
            $notes = get_user_field('s2member_notes', $id);
            if (strpos($notes, "Non-Singer") !== false)
                continue;

            $first = get_user_field('first_name', $id);
            $last = get_user_field('last_name', $id);


            $singers[$this->sort_key($vp, $last, $first)] = array(
                'id' => $id,
                'name' => $first . ' ' . $last,
                'vp' => $vp,
                'dates' => get_user_meta($id, $this->options->absence_meta_key(), true));
        }
        ksort($singers);

        $cur_id = get_current_user_id();
        $edit_all = false;
        $positions = get_user_field('position', $cur_id);
        if ($positions) {
            $edit_all = in_array('Membership', $positions);
        }

        $html = '';
        $cur_vp = '';
        foreach ($singers as $singer) {
            $vp_class = ($cur_vp != $singer['vp']) ? 'vp-first' : '';
            if ($vp_class == 'vp-first')
                $cur_vp = $singer['vp'];

            $html .= '
                <tr data-id="' . $singer['id'] . '">
                    <td class="member ' . $vp_class . '">' . $singer['name'] . '</td>
                    <td class="' . $vp_class .'">' . $singer['vp'] . '</td>';

            $cur_singer = ($edit_all || ($cur_id == $singer['id']));
            foreach ($this->dates as $date => $day) {
                $cell = '';
                if (strpos($day['day'], '-') !== false) {
                    $cell = 'req';
                } else if (strpos($singer['dates'], $date) !== false) {
                    if ($cur_singer)
                        $cell = '<input id="' . $date . '" type="checkbox" checked=":checked" />';
                    else
                        $cell = 'X';
                } else if ($cur_singer) {
                    $cell = '<input id="' . $date . '" type="checkbox" />';
                }
                $classes = implode(' ', array($day['type'], 'days', $vp_class));
                $html .= '
                    <td class="' . $classes . '">' . $cell . '</td>';
            }
            $html .= '
                </tr>';
        }
        $html .= '
        </table>';

        return $html;
    }

    private function sort_key($vp, $last, $first) {
        static $parts = array('S1', 'S2', 'A1', 'A2', 'T1', 'T2', 'B1', 'B2');
        $key = array_search($vp, $parts);
        if ($key === false) {
            $key = 'ZZ';  // Anyone without a proper voice part goes at the end
        }
        return $key . '~' . $last . '~' . $first;  // Most agree that ~ is near last in sorting
    }

    private function getJS() {
        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[';

        $js .= '
        function cu_js($) {
            $(document).ready(function() {
                $("table.absence input").on("change", function() {
                    var $tr = $(this).closest("tr");
                    var singer_id = $tr.data("id");
                    var dates = [];
                    $tr.find("input").each(function() {
                        if ($(this).is(":checked"))
                            dates.push($(this).attr("id"));
                    });
                    var data = {
                        action: "cu_update_missed_rehearsals",
                        _ajax_nonce: "' . wp_create_nonce('cu_update_missed_rehearsals-' . get_current_user_id()) . '",
                        dates: dates.join(),
                        singer_id: singer_id
                    };
                    $.post("' . admin_url('admin-ajax.php') . '", data, cu_js.response);
                });
            });
            function response(res) {
                if (!res.success) {
                    alert("ERROR " + res.data + ": Notify Web Admin");
                    location.reload();
                }
            }
            cu_js.response = response;
        }
        cu_js(jQuery);
        ';

        return $js.'
        // ]]></script>';
    }

    /**
     * AJAX callback
     * Since this is a front-end access point, for security - only error IDs are returned
     */
    public function update_missed_rehearsals() {
        $id = get_current_user_id();
        check_ajax_referer('cu_update_missed_rehearsals-' . $id);
        if (!current_user_can('access_s2member_level2'))
            wp_send_json_error(101);  // Access denied

        if (!isset($_POST['singer_id']))
            wp_send_json_error(102);  // Singer_id missing
        $singer_id = $_POST['singer_id'];
        if ($singer_id != $id) {
            $positions = get_user_field('position', $id);
            if (!in_array('Membership', $positions))
                wp_send_json_error(103);  // Cannot change another's dates
        }

        if (!isset($_POST['dates']))
            wp_send_json_error(104);  // Bad request
        $dates = $_POST['dates'];
        if (!empty($dates)) {
            // TODO - change DateValidator to use wp_send_json_error()
            $validator = new CuDateValidator(array($this, 'update_error'));
            $validator->validate_dates($dates);
        }
        if (!empty($this->update_errors))
            wp_send_json_error(105);  // Bad dates

        update_user_meta($singer_id, $this->options->absence_meta_key(), $dates);

        wp_send_json_success('');
    }

    public function update_error($errno, $msg) {
        $this->update_errors[] = $errno;
    }
}
