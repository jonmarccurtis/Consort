<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

require_once(plugin_dir_path(__FILE__).'/rn_settings_action.php');
require_once(plugin_dir_path(__FILE__).'/../common/section_widget.php');

/**
 * Class RnSingersTab
 */
class RnSingersTab extends RnTab
{
    private $singers_list, $import_action;

    protected function __construct($active_tab)
    {
        parent::__construct($active_tab);

        $this->singers_list = new RnSingersListField(
            'singers-list',
            '',  // Enable singers list to be almost full-width
            $this->options);

        $this->import_action = new RnImportAction($this->options);
    }

    protected function register_tab()
    {
        $section = 'rn-singers-settings-section';
        $this->add_section($section, 'Singer List');
        $this->add_field($section, $this->singers_list,
            array('class' => 'sl-header'));    // to make the table full width
    }

    public function render_section_info()
    {
        echo $this->import_action->render(null);
        echo do_shortcode('[rn_refman man="admin-singer-list-instruct"]');
    }

    protected function render_submit_button()
    {
        // Not needed, all actions on this page are saved immediately
        //submit_button('Save Changes');
    }

    protected function render_outer_html() {
        echo '
<div id="rn-vps-overlay">
    <div id="rn-vps-overlay-form">
        <form id="vps-form">
            <div id="vps-info"></div>
            <div>
                <button type="button" onclick="rn_js.hide_vps_selector()">Cancel</button>
                <button type="button" onclick="rn_js.save_vps_selector()">Save</button>
            </div>
            <input type="hidden" id="singer-id" name="singer_id" />
            <input type="hidden" id="song-id" name="song_id" />
        </form>
    </div>
</div>
        ';

        require_once(plugin_dir_path(__FILE__).'/../common/singer_edit_form.php');
        $edit_form = new SingerEditForm();
        echo $edit_form->html(true);
    }

    protected function get_js()
    {
        $songs = $this->options->get_option('song-list');
        $songs_vps = array();
        foreach($songs as $song)
            $songs_vps[$song[RN::ID]] = $song[RN::VP];

        echo '
            <script type="text/javascript"><!-- // --><![CDATA[
            ';
        echo 'var rn_songs = ' . json_encode($songs_vps) . ';';
        echo '
            // ]]></script>';
    }

}

class RnSingersListField extends RnField {

    private $options, $songs, $table;

    public function __construct($slug, $label, $options)
    {
        require_once(plugin_dir_path(__FILE__).'/../common/rn_database.php');

        parent::__construct($slug, $label);
        $this->options = $options;
        $this->songs = $this->options->get_option('song-list');

        require_once(plugin_dir_path(__FILE__).'/../common/rn_table.php');
        $this->table = new RnTable();
        $this->table->table_class('singer-settings');
    }

    public function init_table() {
        $this->table->add_column('first_name',
            array('label' => 'F',
                'title' => 'Click to sort',
                'class' => 'first-name'));

        $this->table->add_column('last_name',
            array('label' => 'Last',
                'data-placeholder' => 'Filters ...',
                'title' => 'Click to sort',
                'class' => 'last-name'));

        $this->table->add_column('pos',
            array('label' => 'Pos',
                'title' => 'Click to sort',
                'class' => 'width-10'));

        $this->table->add_column('pvp',
            array('label' => 'PVP',
                'title' => 'Click to sort',
                'class' => 'width-20',
                'sorter' => 'pvp_sorter'));

        $this->table->add_sorter('pvp_sorter', 'numeric', '
            var parts = ["S1","S2","A1","A2","T1","T2","B1","B2"];
            for (var i = 0; i < parts.length; i++) {
                if (s.substr(0,2) == parts[i])
                return i;
            }
            return parts.length;');

        foreach ($this->songs as $song) {
            $this->table->add_column($song[RN::NAME],
                array('title' => 'Click to sort',
                    'class' => 'song-name',
                    'custom-filters' => 'partsFilter',
                    'sorter' => 'vp_' . $song[RN::ID]));

            $this->table->add_sorter('vp_' . $song[RN::ID], 'numeric', '
            var parts = ' . json_encode(explode(',', $song[RN::VP])) . ';
            for (var i = 0; i < parts.length; i++) {
                var vp = s.split(",");
                if (vp.length > 0)
                    vp = vp[0];
                if (vp == parts[i])
                return i;
            }
            return parts.length;');
        }

        $this->table->add_filter('partsFilter', '
            e = ","+e+",";
            f = f.split(",");
            for (var i = 0; i < f.length; i++) {
                if (!e.includes(","+f[i]+","))
                    return false;
                }
            return true;
        ');

        $this->table->set_option('empty msg', 'Use the "Import/Update" link to import singers');
        $this->table->set_option('dynamic selection', true);
        $this->table->set_option('hide columns', true);
        $this->table->set_option('download', 'RN_Singers_List');
        $this->table->set_option('directions-left', '<em>&nbsp; Use shift-click to sort multiple columns</em>');
    }

    public function render($args) {
        $this->init_table();

        $singers = RnSingersDB::get_rows();
        foreach($singers as $singer) {
            $row = $this->create_row_data($singer);
            $this->table->add_row($singer['singer_id'], $row);
        }

        echo $this->table->get_html();
    }

    public function get_fields($singer) {
        $row = $this->create_row_data($singer);
        return $this->table->get_fields($row);
    }

    private function create_row_data($singer) {
        $id = $singer['singer_id'];
        $first = get_user_field('first_name', $id);
        $last = get_user_field('last_name', $id);
        $name = $first . ' ' . $last;
        $pvp = $singer['primary_vp'];

        $pos = $singer['is_singer'] ? 's' : '';
        $pos .= $singer['is_nt'] ? 'n' : '';
        $pos .= $singer['is_dir'] ? 'd' : '';
        $pos .= $singer['is_admin'] ? 'a' : '';

        $dirlock = false;
        if ($singer['is_dir']) {
            $dirs = RnNotesDB::get_ids_with_dependent_notes('dir_id');
            $dirlock = in_array($id, $dirs);
        }

        $row = array(
            'data' => array(
                'first_name' => $first[0],
                'last_name' => $last,
                'pos' => $pos,
                'pvp' => $pvp
            ),
            'class' => array(
                'first_name' => 'first-name centertext',
                'last_name' => 'last-name',
                'pos' => 'centertext singer-pos',
                'pvp' => 'centertext singer-pvp'
            ),
            'tr_pars' => array(
                'class' => 'master_row',
                'title' => 'Edit settings for ' . $name,
                'onclick' => 'rn_js.edit_singer(\''.$first.'\','.$singer['singer_id'].')',
                'data-dirlock' => $dirlock
            )
        );

        $singer_vps = SectionWidget::singer_vps($singer, $this->songs);
        foreach($this->songs as $song) {
            $vps = $singer_vps[$song[RN::ID]];
            // Don't show non-singer VPs
            if (!$singer['is_singer'])
                $vps = '';
            $row['data'][$song[RN::NAME]] = '<span id="vps-' . $singer['singer_id']
                . '-' . $song[RN::ID] . '">' . $vps . '</span>';
            $row['class'][$song[RN::NAME]] = 'centertext vps-setting';
        }
        return $row;
    }

}

