<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class CuPositionList
{
    private $table;

    public function __construct()
    {
        require_once(plugin_dir_path(__FILE__).'cu_table.php');
        $this->table = new CuTable();
    }

    public function html()
    {
        // Collect the settings and data
        $this->add_columns();
        $this->add_settings();
        $this->add_members();

        // Spit it out
        $html = $this->table->get_html();
        $html .= $this->getJS();
        return $html;
    }

    private function add_columns() {
        $this->table->add_column('position',
            array('data-placeholder' => 'Filters ...',
                'title' => 'Click to sort'));

        $this->table->add_column('first_name',
            array('label' => 'First',
                'title' => 'Click to sort',
                'width' => '45'));

        $this->table->add_column('last_name',
            array('label' => 'Last',
                'title' => 'Click to sort',
                'width' => '80'));

        $this->table->add_column('email', array());

        $this->table->add_column('group',
            array('title' => 'Click to sort',
                'data-placeholder' => 'all',
                'width' =>'70',
                'custom-filters' => array(
                    'Non-Singers' => 'groupFilter',
                    'Singer' => 'groupFilter',
                    'Web Assistant' => 'groupFilter',
                    'Board' => 'groupFilter')));

        $this->table->add_column('notes',
            array('sorter' => false));

        $this->table->add_column('volunteer',
            array('label' => 'Volunteer Interests',
                'data-placeholder' => '*. for any'));
    }

    private function add_settings()
    {
        $this->table->set_option('title', 'Volunteer Positions');

        $this->table->set_option('table-layout', 'auto');

        // Enable hiding columns and download to CSV
        $this->table->set_option('hide columns', true);
        $this->table->set_option('download', 'Consort_Master_List');

        // Initial sort order on page load
        $this->table->set_init_sort(array('position', 'last_name', 'first_name'));

        // For a preset dropDown selector
        $this->table->add_filter_preset('By Position',
            array(),
            array('position', 'last_name', 'first_name'));
        $this->table->add_filter_preset('By Name',
            array(),
            array('last_name', 'first_name'));

        // Button fcn must be defined in getJS()
        $this->table->add_button(array(
            'label' => 'EMAIL TO',
            'fcn' => 'cu_email.startEmail(\', \', \'to\')',
            'tooltip' => 'Start email addressed TO visible members'
        ));
        $this->table->add_button(array(
            'label' => 'EMAIL BCC',
            'fcn' => 'cu_email.startEmail(\', \', \'bcc\')',
            'tooltip' => 'Start email addressed BCC visible members'
        ));

        // These are referenced in the 'custom-filters' above
        $this->table->add_filter('groupFilter','
            var rank = "swb";
            var cell = data.iExact.charAt(0);
            var filter = data.iFilter.charAt(0);
            if (filter == "n")
                return cell == "m";
            else
                return rank.indexOf(cell) >= rank.indexOf(filter);');
    }

    private function add_members()
    {
        // This code comes directly from s2member ...
        $position_keys = array();
        foreach (json_decode($GLOBALS["WS_PLUGIN__"]["s2member"]["o"]["custom_reg_fields"], TRUE) as $field) {
            if ($field['id'] == 'position') {
                foreach (preg_split("/[\r\n\t]+/", $field["options"]) as $i => $option_line) {
                    $option_value = $option_label = $option_default = "";
                    @list($option_value, $option_label, $option_default) = preg_split("/\|/", trim($option_line));
                    $position_keys[$option_value] = array('label' => str_replace('<br>', '', $option_label));
                }
                break;
            }
        }

        // Hate to hard-code this, but this list indicates the minimum
        // s2member level needed by some of the positions. Positions not
        // on this list can be any level above 0.
        $min_levels = array(
            'Artistic Director' => 3,
            'President' => 4,
            'Treasurer' => 4,
            'Secretary' => 4,
            'Volunteers' => 3,
            'Website' => 5,
            'Fundraising' => 4,
            'Membership' => 4,
            'Lunch' => 3,
            'Snacks' => 3,
            'RNote Admin' => 3,
            'Note Taker' => 3,
            'Auditions' => 3
        );

        $color = 0x0;
        foreach ($position_keys as $position => $pars) {
            $color += 0x88;
            $red = (int) 0xff & $color >> 4;
            $green = (int) 0xff & $color >> 2;
            $blue = (int) 0xff & $color;

            $position_keys[$position] = array_merge($pars, array(
                'filled' => false,
                'min_level' => isset($min_levels[$position]) ? $min_levels[$position] : 1,
                'style' => 'background-color:rgba('.$red.','.$green.','.$blue.',0.3)'));
        }

        $members = get_users();
        foreach ($members as $member) {
            $id = $member->data->ID;
            $group = get_user_field('s2member_access_label', $id);
            if ($group == 'Inactive')
                continue; // Inactive is used to filter out the Test Family


            $positions = get_user_field('position', $id);
            if (!is_array($positions))
                continue;

            $first_name = get_user_field('first_name', $id);
            $last_name = get_user_field('last_name', $id);

            // Combine volunteer interests with other
            $vol_int = get_user_field('volunteer_interests', $id);
            if (is_array($vol_int))
                $vol_int = implode(', ', $vol_int);
            $vol_other = get_user_field('volunteer_other', $id);
            if (!empty($vol_other))
                $vol_int .= '; ' . $vol_other;

            $level = get_user_field('s2member_access_level', $id);

            foreach ($positions as $position) {
                // Check if user has correct access level for this position
                $level_ok = true;
                $req_level = $position_keys[$position]['min_level'];
                if ($level < $req_level) {
                    if ($req_level == 5) {
                        $is_really_admin = in_array('administrator', get_userdata($id)->roles);
                        if (!$is_really_admin)
                            $level_ok = false;
                    } else {
                        $level_ok = false;
                    }
                }
                if (!$level_ok)
                    $group = '<span style="color:red">'.$group.'</span>';

                $pos_label = $position_keys[$position]['label'];
                $pos_cell = '<span title="' . $pos_label .
                    '" onclick="event.stopPropagation();alert(\'' . $pos_label . '\');">' . $position . '</span>';

                // Data keys must be the same as the column ids
                $row = array(
                    'data' => array(
                        'position' => $pos_cell,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $member->data->user_email,
                        'group' => $group,
                        'notes' => get_user_option('s2member_notes', $id),
                        'volunteer' => $vol_int),

                    'class' => array('email' => 'email'),

                    'tr_pars' => array(
                        'class' => 'master_row',
                        'title' => 'View the profile of ' . $first_name . ' ' . $last_name,
                        'onclick' => 'cu_email.gotoProfile(' . $id . ')',
                        'style' => $position_keys[$position]['style']));

                $this->table->add_row($id . $position, $row);

                $position_keys[$position]['filled'] = true;
            }
        }
        // Now add the unfilled positions
        foreach ($position_keys as $position => $pars) {
            if ($pars['filled'])
                continue;

            // Data keys must be the same as the column ids
            $row = array(
                'data' => array(
                    'position' => $position,
                    'first_name' => '-',
                    'last_name' => '-',
                    'email' => '-',
                    'group' => '-',
                    'notes' => '-',
                    'volunteer' => '-'),

                'tr_pars' => array(
                    'style' => 'font-weight: bold'));

            $this->table->add_row('0' . $position, $row);
        }
    }

    private function getJS() {
        $js = '
        <script type="text/javascript"><!-- // --><![CDATA[';

        // It appears that Outlook is the most restrictive email client - limiting the size of mailto to
        // 2000 characters.
        $js .= '
        function cu_email($) {
            function gotoProfile(num) {
                location.href = "/wp-admin/user-edit.php?user_id="+num;
            }
            cu_email.gotoProfile = gotoProfile;
             
            function startEmail(delim, adr) {
                var emails = "";
                $(".master_row").not(".filtered").each(function() {
                    var email = $(this).find(".email").html();                    
                    if (email) 
                        emails += email + delim;
                });
                if (emails.length == 0)
                    alert("No member email addresses have been found.  Try reducing the filters.");
                else 
                    sendEmails(emails, delim, adr, 0);
            }
            cu_email.startEmail = startEmail;
            
            function sendEmails(emails, delim, adr, split) {
                var timeout = 2000;
                var mailtoPrefix = "mailto:'.wp_get_current_user()->user_email.'";
                if (adr == "to")
                    mailtoPrefix += delim;
                else
                    mailtoPrefix += "?" + adr + "=";
                    
                var maxUrlCharacters = (delim == ";") ? 1900 : 100000;
                var currentIndex = 0;
                var nextIndex = 0;

                if (emails.length < maxUrlCharacters) {
                    if (split)
                        alert("The following email will be the last of "+(split+1)+" emails created for the full list of addresses.");
                    window.location = mailtoPrefix + emails;
                    return;
                }

                do {
                    currentIndex = nextIndex;
                    nextIndex = emails.indexOf(delim, currentIndex + 1);
                } while (nextIndex != -1 && nextIndex < maxUrlCharacters)

                if (currentIndex == -1) {
                    if (split)
                        alert("The following email will be the last of "+(split+1)+" emails created for the full list of addresses.");
                    window.location = mailtoPrefix + emails;
                } else {
                    if (split == 0)
                        alert("List of addresses is too long.  Multiple emails will be created.");
                    window.location = mailtoPrefix + emails.slice(0, currentIndex);
                    setTimeout(function () {
                        sendEmails(emails.slice(currentIndex + 1), delim, adr, ++split);
                    }, timeout);
                }
            }
        }
        cu_email(jQuery);
        ';

        return $js.'
        // ]]></script>';
    }

}
