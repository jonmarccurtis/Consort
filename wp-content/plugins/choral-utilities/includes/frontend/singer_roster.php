<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

class CuSingerRoster
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
        $this->add_singers();

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
                'width' => '70px'));

        $this->table->add_column('last_name',
            array('label' => 'Last',
                'title' => 'Click to sort'));

        $this->table->add_column('address',
            array('sorter' => false));

        $this->table->add_column('city',
            array('title' => 'Click to sort'));

        $this->table->add_column('state',
            array('width' => '46',
                'title' => 'Click to sort'));

        $this->table->add_column('zip',
            array('title' => 'Click to sort',
                'sorter' => 'text'));

        $this->table->add_column('phone',
            array('title' => 'Click to sort'));

        $this->table->add_column('voice',
            array('title' => 'Click to sort',
                'width' =>'48',
                'sorter' => 'vp_sorter'));

        $this->table->add_column('email', array());

        $this->table->add_column('position',
            array('data-placeholder' => '*. for any'));
    }

    private function add_settings()
    {
        $this->table->set_option('title', 'Singer Roster');

        // Prevent selection - copy/paste
        $this->table->set_option('no select', true);

        // Initial sort order on page load
        $this->table->set_init_sort(array('last_name', 'first_name'));

        // For a preset dropDown selector
        $this->table->add_filter_preset('Sort by Name',
            array(),
            array('last_name', 'first_name'));
        $this->table->add_filter_preset('Sort by Voice part',
            array(),
            array('voice', 'last_name', 'first_name'));
        $this->table->add_filter_preset('Positions',
            array('position' => '*.'),
            array('position', 'last_name', 'first_name'));

        // These are referenced in the 'sorter' above
        $this->table->add_sorter('vp_sorter', 'numeric', '
            var parts = ["S1","S2","A1","A2","T1","T2","B1","B2"];
            for (var i = 0; i < parts.length; i++) {
                if (s.substr(0,2) == parts[i])
                return i;
            }
            return parts.length;');
    }

    private function add_singers()
    {
        // Get the s2member full labels for the positions
        $fields = json_decode($GLOBALS['WS_PLUGIN__']['s2member']['o']['custom_reg_fields'], true);
        $plabels = array();
        foreach($fields as $field) {
            if ($field['id'] == 'position') {
                $options = explode(PHP_EOL, $field['options']);
                foreach ($options as $option) {
                    $settings = explode('|', $option);
                    $plabels[$settings[0]] = $settings[1];
                }
                break;
            }
        }

        $singers = get_users();
        foreach ($singers as $singer) {
            $id = $singer->data->ID;
            $group = get_user_field('s2member_access_label', $id);
            if (in_array($group, array('Inactive', 'Member')))
                continue; // Only showing Singers

            $positions = get_user_field('position', $id);
            if (is_array($positions)) {
                $new = array();
                foreach ($positions as $position) {
                    $label = str_replace('<br>', '', $plabels[$position]);
                    $new[] = '<span class="cursor-pointer" title="' . $label .
                        '" onclick="alert(\'' . $label . '\')">' . $position . '</span>';
                }
                $positions = implode(', ', $new);
            }

            $adr = $singer->data->user_email;
            $email = '<span title="Send email" onclick="cu_email.send(\'' . $adr . '\')">' . $adr . '</span>';

            // Data keys must be the same as the column ids
            $row = array(
                'data' => array(
                    'first_name' => get_user_field('first_name', $id),
                    'last_name' => get_user_field('last_name', $id),
                    'address' => get_user_field('address', $id),
                    'city' => get_user_field('city', $id),
                    'state' => get_user_field('state', $id),
                    'zip' => get_user_field('zip', $id),
                    'phone' => get_user_field('phone', $id),
                    'voice' => get_user_field('voice', $id),
                    'email' => $email,
                    'position' => $positions),

                'class' => array(
                    'state' => 'centertext',
                    'voice' => 'centertext',
                    'email' => 'email cursor-pointer'),

                'tr_pars' => array());

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
            function send(email) {
                window.location="mailto:"+email;
            }
            cu_email.send= send;
        }
        cu_email(jQuery);
        ';

        return $js.'
        // ]]></script>';
    }

}
