<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class CuMemberList
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
        $this->table->add_column('first_name',
            array('label' => 'First',
                'title' => 'Click to sort',
                'data-placeholder' => 'Filters ...',
                'width' => '45'));

        $this->table->add_column('last_name',
            array('label' => 'Last',
                'title' => 'Click to sort',
                'width' => '80'));

        $this->table->add_column('address',
            array('sorter' => false));

        $this->table->add_column('city',
            array('title' => 'Click to sort'));

        $this->table->add_column('state',
            array('width' => '48',
                'title' => 'Click to sort'));

        $this->table->add_column('zip',
            array('title' => 'Click to sort',
                'sorter' => 'text'));

        $this->table->add_column('phone',
            array('title' => 'Click to sort'));

        $this->table->add_column('voice',
            array('title' => 'Click to sort',
                'width' =>'50',
                'sorter' => 'vp_sorter'));

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

        $this->table->add_column('position',
            array('data-placeholder' => '*. for any'));

        $this->table->add_column('volunteer',
            array('label' => 'Volunteer Interests',
                'data-placeholder' => '*. for any'));
    }

    private function add_settings()
    {
        $this->table->set_option('title', 'Member List');

        $this->table->set_option('table-layout', 'auto');

        // Enable hiding columns and download to CSV
        $this->table->set_option('hide columns', true);
        $this->table->set_option('download', 'Consort_Master_List');

        // Initial sort order on page load
        $this->table->set_init_sort(array('last_name', 'first_name'));

        // For a preset dropDown selector
        $this->table->add_filter_preset('Members/Singers by Name',
            array(),
            array('last_name', 'first_name'));
        $this->table->add_filter_preset('Members/Singers by Voice part',
            array(),
            array('voice', 'last_name', 'first_name'));
        $this->table->add_filter_preset('Singers by Name',
            array('group' => 'Singer'),
            array('last_name', 'first_name'));
        $this->table->add_filter_preset('Singers by Voice part',
            array('group' => 'Singer'),
            array('voice', 'last_name', 'first_name'));
        $this->table->add_filter_preset('Positions',
            array('position' => '*.'),
            array('position', 'last_name', 'first_name'));
        $this->table->add_filter_preset('Volunteer Interests',
            array('volunteer' => '*.'),
            array('volunteer', 'last_name', 'first_name'));

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
        /*
        $this->table->add_button(array(
            'label' => 'EMAIL',
            'fcn' => 'cu_email.startEmail(\', \')',
            'tooltip' => 'Start email, separating addresses with a comma'
        ));
        Latest research indicates that Outlook now has a setting to accept commas:
            File -> Option -> Mail -> Send messages:
            "Commas can be used to separate multiple message recipients"

        But leaving in the code for now in case this is not true ...

        $this->table->add_button(array(
            'label' => 'EMAIL ;',
            'fcn' => 'cu_email.startEmail(\'; \')',
            'tooltip' => 'Start email, separating addresses with a semi-colon'
        ));
        */

        // These are referenced in the 'sorter' above
        $this->table->add_sorter('vp_sorter', 'numeric', '
            var parts = ["S1","S2","A1","A2","T1","T2","B1","B2"];
            for (var i = 0; i < parts.length; i++) {
                if (s.substr(0,2) == parts[i])
                return i;
            }
            return parts.length;');

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
        $members = get_users();
        foreach ($members as $member) {
            $id = $member->data->ID;
            $group = get_user_field('s2member_access_label', $id);
            if ($group == 'Inactive')
                continue; // Inactive is used to filter out the Test Family

            $first_name = get_user_field('first_name', $id);
            $last_name = get_user_field('last_name', $id);

            $position = get_user_field('position', $id);
            if (is_array($position))
                $position = implode(', ', $position);

            // Combine volunteer interests with other
            $vol_int = get_user_field('volunteer_interests', $id);
            if (is_array($vol_int))
                $vol_int = implode(', ', $vol_int);
            $vol_other = get_user_field('volunteer_other', $id);
            if (!empty($vol_other))
                $vol_int .= '; ' . $vol_other;

            // Data keys must be the same as the column ids
            $row = array(
                'data' => array(
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'address' => get_user_field('address', $id),
                    'city' => get_user_field('city', $id),
                    'state' => get_user_field('state', $id),
                    'zip' => get_user_field('zip', $id),
                    'phone' => get_user_field('phone', $id),
                    'voice' => get_user_field('voice', $id),
                    'email' => $member->data->user_email,
                    'group' => $group,
                    'notes' => get_user_option('s2member_notes', $id),
                    'position' => $position,
                    'volunteer' => $vol_int),

                'class' => array(
                    'state' => 'centertext',
                    'voice' => 'centertext',
                    'email' => 'email'),

                'tr_pars' => array(
                    'class' => 'master_row',
                    'title' => 'View the profile of '.$first_name.' '.$last_name,
                    'onclick' => 'cu_email.gotoProfile('.$id.')'));

            $this->table->add_row($id, $row);
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
