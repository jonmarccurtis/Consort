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
 * Class CuSnackList
 * Supports the Consort Week Snack Signup, Caldera Entry Form and Signup List
 *     Note that while the internal short-term is 'snack'.  The UI uses
 *     the term 'Refreshments'.
 *
 * The Caldera Form must have specific contents:
 *     The signup name must follow the read-only entry pattern:
 *         Hidden entry slug 'user', value {user:login}
 *         Hidden entry slug 'hid_name', value %vis_name%
 *         Name (required) slug 'vis_name', conditionally disabled
 *             if user != jcurtis or lray (or others)
 *     This code expects to find name in 'hid_name' - saved in entries
 *     The select box (required) slug 'snack_signup_dropdown' - saved in entries
 *     Define a Variable: Name = 'monday' (case sensitive), Behavior = 'static'
 *         Value = date (integer) of Monday's rehearsal.
 *         It must be between 1 < 25 and is hardcoded to be in August
 *
 * The signup list is hardcoded to the 5 days of Consort Week:
 *     Wednesday is Women's sectional, Thursday is Men's sectional
 *     Each date has 2 slots for Drinks and 2 slots for Cookies.
 *
 * This class has 2 public methods:
 *     dropdown() - called from a Caldera Forms callback:
 *         caldera_forms_render_get_field.  This determines the contents of
 *         the dropdown, 'snack_signup_dropdown'.  This is based on the list
 *         of available slots, removing those found in the entry list.
 *     html() - called from CU shortcode [cu_snack_list id="CFID"]:
 *         Returns HTML table showing the current signup list.
 *         Must include id= the Caldera ID of the form.
 *
 * The data is based on two structures:
 *     The slots array - dynamically created in init_slots():
 *         key = abbrev day of the week - Mon, Tue, Wed, etc
 *         label = the visible dropdown options
 *         Drink or Cookies = lists of names signed up
 *     Caldera Forms Entries - based on the options in the dropdown and
 *         the 'hid_name' entry.
 *         The dropdown keys are constructed here using a combination
 *         of the day key and snack type, e.g. Mon-Drinks, Wed-Cookies, etc.
 *
 * Constructing the slots array converts the list of Caldera Entries
 *     hid_name and dropdown key (Tue-Drinks) exploded into the array.
 *
 */
class CuSnackList
{
    private $slots;
    private static $block_recurse = false;

    public function __construct()
    {
        require_once( CFCORE_PATH . 'classes/admin.php' );
        $this->slots = array();
    }

    /**
     * @param $form
     * @return true, or error string
     */
    private function init_slots($form) {
        if (!isset($form['db_id']))
            return 'Cannot find form, Caldera ID may be invalid';

        // Make sure 'monday' is set to an integer 1 < 25

        $err = 'Static variable \'monday\' (date of first rehearsal) - ';
        if (!isset($form['variables']))
            return $err . 'not found';

        $vars = $form['variables'];
        if (!isset($vars['types'])) // Not sure this can happen
            return $err . 'not defined';

        // Cannot find a Caldera API to get the value, so this is
        // done manually ...
        $date = 0;
        for($idx = 0; $idx < count($vars['types']); $idx++) {
            if ($vars['types'][$idx] == 'static') {
                if ($vars['keys'][$idx] == 'monday') {
                    $date = intval($vars['values'][$idx]);
                    if (strval($date) != $vars['values'][$idx])
                        return $err . 'must be an integer';
                    break;
                }
            }
        }
        if ($date == 0)
            return $err . 'is missing';
        if ($date < 1 || $date > 25)
            return $err . 'is invalid';

        // Now that we know what day Monday is, we can create the slots labels
        for ($wd = 0; $wd < 5; $wd++) {
            $day = date('l', strtotime('Monday + ' . $wd . ' days'));
            $key = substr($day, 0, 3);
            $label = $day . ' August ' . ($date + $wd);
            if ($key == 'Wed')
                $label .= ' (Women\'s rehearsal - only women should sign up)';
            else if ($key == 'Thu')
                $label .= ' (Men\'s rehearsal - only men should sign up)';

            $this->slots[$key] = array(
                'label' => $label,
                'Drinks' => array(),
                'Cookies' => array());
        }

        // Now add the lists of Drinks and Cookies signups from the Caldera entries.
        // New behavior from Caldera Forms, get_entries now iterates the fields of the
        // form - cause recursive calls to our dropdown callback, thus an infinite loop.
        CuSnackList::$block_recurse = true;
        $entries = Caldera_Forms_Admin::get_entries($form, 1, 100);
        CuSnackList::$block_recurse = false;
        if (isset($entries['entries'])) {
            foreach ($entries['entries'] as $entry) {
                if (isset($entry['data']['snack_signup_dropdown'])) {
                    $slot = $entry['data']['snack_signup_dropdown'];
                    list($day, $type) = explode('-', $slot);
                    $this->slots[$day][$type][] = $entry['data']['hid_name'];
                }
            }
        }
        return true;
    }


    /**
     * Determine the contents of the Signup Dropdown
     * based on what slots have not yet been filled.
     * @param $field
     * @return $field
     */
    public function dropdown($field, $form) {
        if (CuSnackList::$block_recurse)
            return $field;

        $res = $this->init_slots($form);
        if ($res !== true) {
            // Try to pass back useful error message in the dropdown
            $field['config']['option'][1] = array(
                'value' => 'error',
                'label' => 'ERROR: ' . $res);

        } else {
            // Show only open slots in the dropdown
            $index = 0;
            foreach ($this->slots as $date => $slot) {
                foreach (['Drinks', 'Cookies'] as $type) {
                    // Note hard-coded number of slots available == 2
                    if (count($slot[$type]) < 2) {
                        $field['config']['option'][++$index] = array(
                            'value' => $date . '-' . $type,
                            'label' => $type . ' for ' . $slot['label']);
                    }
                }
            }
        }
        return $field;
    }

    private function error_msg($msg) {
        return '<p>[cu_snack_list] ERROR: ' . $msg . '</p>';
    }

    public function html($atts)
    {
        if (!isset($atts['id']))
            return $this->error_msg('Missing \'id\' attribute = Caldera Form ID');

        $res = $this->init_slots(Caldera_Forms_Forms::get_form($atts['id']));
        if ($res !== true)
            return $this->error_msg($res);

        $html = "<table>";
        foreach($this->slots as $day => $slot) {
            $html .= "<tr><td colspan='4'><b>" . $slot['label'] . "</b></td></tr>";
            foreach(['Drinks','Cookies'] as $type) {
                $html .= '<tr><td class="cu-form-label">' . $type . '</td>';
                $name = count($slot[$type]) < 1 ? '' : $slot[$type][0];
                $html .= '<td class="cu-form-value">' . $name . '</td>';
                $html .= '<td class="cu-form-label">' . $type . '</td>';
                $name = count($slot[$type]) < 2 ? '' : $slot[$type][1];
                $html .= '<td class="cu-form-value">' . $name . '</td></tr>';
            }
        }
        $html .= "</table>";

        $html .= "<p><em>If you need to cancel or change, contact 
            <span id='snack_can' class='send-adr' title='Send email'>Lucinda Ray</span></em></p>";

        $html .= $this->getJS();
        return $html;
    }

    private function getJS()
    {
        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[';

        $js .= '
        function cu_wsll($) {
            $(document).ready(function () {
                $("#snack_can").on("click", function() {
                    var e1 = "mai";
                    var e2 = "lto:luc";
                    var e3 = "indaray@a";
                    var e4 = "ol.c";
                    var e5 = "om";
                    window.location.href = e1 + e2 + e3 + e4 + e5;
                });
            });
        }
        cu_wsll(jQuery);
        ';

        return $js.'
        // ]]></script>';
    }

}
