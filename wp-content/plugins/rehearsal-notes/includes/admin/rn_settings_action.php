<?php
/**
 * User: joncu
 * Date: 3/15/19
 * Time: 12:31 PM
 */

if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

require_once(plugin_dir_path(__FILE__).'/rn_settings.php');

/**
 * Class RnActionsTab
 * This is a psuedo 'alternative' tab.  It does not render a tab on the settings page.
 * Instead it handles action requests, which are not using standard WP ajax calls, but
 * instead go through the GET request to do certain actions, then reload the page.
 * Note: there used to be several - now down to one.  But leaving the switch in case
 * we get more later.
 */
class RnActionsTab extends RnTab
{
    // Override of normal tab - to handle the action
    public function create_tab() {
        $action = null;
        switch($_GET['rn_action']) {
            case RnImportAction::ACTION:
                $action = new RnImportAction($this->options);
                break;
            default:
                return; // Not one of our actions, but die is dangerous here
        }
        $action->handle();
    }

}

abstract class RnAction extends RnField
{
    private $nonce, $action, $tab;
    protected $options;

    public function __construct($tab, $slug, $action, $label, $options)
    {
        parent::__construct($slug, $label);
        $this->nonce = '_' . substr($slug, 0, 4);
        $this->action = $action;
        $this->options = $options;
        $this->tab = $tab;
    }

    public function render($args) {
        // The action is performed via a link, using this URL which goes
        // goes back to this page and is caught by handle()
        $url = add_query_arg(array(
            'rn_action' => $this->action,
            $this->nonce => wp_create_nonce($this->action)),
            admin_url('options-general.php?page=' . $_GET['page']));

        $this->_render($args, $url);
    }

    abstract protected function _render($args, $url);

    public function handle () {
        check_admin_referer($this->action, $this->nonce);

        $result = $this->_handle();  // Let the kids do their thing

        // Need to reload the page without the action in the URL, and show the results
        $uri = 'options-general.php?page=' . $_GET['page'] . '&tab=' . $this->tab;
        if (!empty($result)) {
            session_start();
            $_SESSION['rn_ac_res'] = $result;
            $uri .= '&rn_ac_res=session';
        }
        header('location:' . admin_url($uri));
        exit();
    }

    abstract protected function _handle();
}


class RnImportAction extends RnAction
{
    const ACTION = 'rn-import';

    public function __construct($options)
    {
        parent::__construct('singers', 'import', self::ACTION,
            'Import from WordPress', $options);
    }

    protected function _render($args, $url)
    {
        echo '
        <a href="' . $url . '">Import/Update Singer List</a>';
    }

    /**
     * @return string (optional) message to show on page reload
     * The rules are:
     *     Singer is removed if no longer in WP
     *     NT, DIR, and Primary VP are updated
     *     Singer/Not - is unchanged, new singers default to True
     */
    protected function _handle()
    {
        return RnImportAction::import_singers();
    }

    static public function import_singers($id = null)
    {
        require_once(plugin_dir_path(__FILE__).'/../common/rn_database.php');

        $updated = array();
        $added = array();
        $removed = array();
        $errors = array();
        $singers = RnSingersDB::get_rows();
        $cur_singers = array();
        foreach($singers as $singer) {
            if ($id == null || $id == $singer['singer_id'])
                $cur_singers[$singer['singer_id']] = $singer;
        }

        if ($id == null) {
            $all_users = true;
            $users = get_users();
        } else {
            $all_users = false;
            $users = array(get_userdata($id));
        }
        foreach ($users as $user) {
            $id = $user->data->ID;
            $group = get_user_field('s2member_access_label', $id);
            if (!in_array($group, array('Singer', 'Web Assistant', 'Board')))
                continue; // Only Singers

            $name = $user->data->display_name;

            $is_nt = 0;
            $is_dir = 0;
            $is_admin = 0;
            $positions = get_user_field('position', $id);
            if ($positions) {
                if (in_array('Note Taker', $positions))
                    $is_nt = 1;
                if (in_array('Artistic Director', $positions))
                    $is_dir = 1;
                if (in_array('RNote Admin', $positions))
                    $is_admin = 1;
            }

            $singer = array(
                'singer_id' => $id,
                'is_nt' => $is_nt,
                'is_dir' => $is_dir,
                'is_admin' => $is_admin,
                'primary_vp' => get_user_field('voice', $id));

            $needs_update = true;
            if (isset($cur_singers[$id])) {
                $cs = $cur_singers[$id];
                if (   $cs['is_nt'] == $singer['is_nt']
                    && $cs['is_dir'] == $singer['is_dir']
                    && $cs['is_admin'] == $singer['is_admin']
                    && $cs['primary_vp'] == $singer['primary_vp'])
                {
                    $needs_update = false;
                } else {
                    // Preserve any existing exceptions
                    $singer['vp_exceptions'] = $cs['vp_exceptions'];
                }
            }
            if ($needs_update) {
                // This will add or do full update if existing
                $res = RnSingersDB::add_singer($singer);
                if ($res === false) {
                    $errors[] = $name;
                } else if ($res == 1) {
                    $added[] = $name;
                } else if ($res == 2) {
                    $updated[] = $name;
                }
                RnImportAction::update_capabilities($singer['singer_id'], $singer);
            }

            unset($cur_singers[$id]);
        }
        // If there are any singers left on the list, then they are no longer
        // in WP, so remove them.
        foreach($cur_singers as $singer_id => $singer_del) {
            RnSingersDB::remove_singer($singer_id);
            $removed[] = get_userdata($singer_id)->display_name;
            RnImportAction::update_capabilities($singer_id, $singer, true);
        }

        $msg = '';
        if ($all_users) {
            $msg = '<div class="help-page">The following singers were modified:';
            $msg .= '<ul>';
            $msg .= '<li>Added ' . count($added) . ': ' . implode(', ', $added) . '</li>';
            $msg .= '<li>Updated ' . count($updated) . ': ' . implode(', ', $updated) . '</li>';
            $msg .= '<li>Removed ' . count($removed) . ': ' . implode(', ', $removed) . '</li>';
            if (count($errors) > 0)
                $msg .= '<li>Failed to update ' . count($errors) . ': ' . implode(', ', $errors) . '</li>';
            $msg .= '</ul></div>';
        }
        return $msg;
    }

    static public function update_capabilities($id, $singer, $removed = false) {
        // UberMenu uses WP capabilities to filter its content.  Here we transfer
        // the singer settings into the rn_can_edit_rnotes capability.

        $cap_name = 'rn_can_edit_rnotes';
        $has_cap = user_can($id, $cap_name);
        $grant = !$removed && ($singer['is_nt'] || $singer['is_dir'] || $singer['is_admin']);

        if ($grant != $has_cap) {  // capability has changed
            $user = new WP_User($id);
            if ($grant)
                $user->add_cap($cap_name, true);
            else
                $user->remove_cap($cap_name);
        }
    }
}
