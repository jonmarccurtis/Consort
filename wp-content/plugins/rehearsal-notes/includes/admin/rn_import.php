<?php
/**
 * Created by IntelliJ IDEA.
 * User: joncu
 * Date: 4/22/19
 * Time: 6:59 PM
 */
if (!defined('WPINC')) {
    die;   // don't allow calling directly
}

/**
 * NOTE: This file is currently not being used!  The 'real' import is being
 * done in rn_settings_action.php
 *
 * This file is the only interface between Rehearsal Notes and Wordpress.
 * Once the WP data has been imported, and it can also be updated, resync'ed,
 * all additional RN behavior is based on the imported data, and does not
 * go back to WP.
 *
 * This import is currently hard-coded to the way the needed data is stored
 * in Consort Chorale's WP site.  If RN is ever wanted to be used in another
 * WP site - either another hardcoded version will need to be custom
 * designed for that website, or ideally - an Import options form should be
 * created that will map what is needed from the Site to RN.
 *
 * The current items that are needed are:
 *     User IDs of singers - if not all users, what are the roles, etc?
 *     Who are Note Takers, Directors, and RNote Admins
 *     Where to find the Primary Voice Part of each singer
 *
 * Another possibility would be to enable an Admin to build the list
 * manually, without the need for Import.
 */

class RnImporter {
    static function getSingers() {
        $singers = array();
        $users = get_users();
        foreach($users as $user) {
            $id = $user->data->ID;

            // Who is a singer? - hardcoded to s2member roles
            $group = get_user_field('s2member_access_level', $id);
            if (!in_array($group, array('Singer', 'Web Assistant', 'Board')))
                continue;

            // Who is an NT, DIR, or Admin? - hardcoded to s2member profile field contents
            $is_nt = false;
            $is_dir = false;
            $is_admin = false;
            $positions = get_user_field('position', $id);
            if (is_array($positions)) {
                foreach($positions as $position) {
                    // CC does not have one person with both, but allowing it here
                    if ($position == 'Note Taker')
                        $is_nt = true;
                    if ($position == 'Artistic Director')
                        $is_dir = true;
                    if ($position == 'RNote Admin')
                        $is_admin = true;
                }
            }

            // Get Voice Part - hard coded to s2member profile field
            // It is expected to be one of: S1, S2, A1, A2, T1, T2, B1, B2
            // But could also be just S, A, T, or B
            $vp = get_user_field('voice');

            $singers[$id] = array(
                'is_nt' => $is_nt,
                'is_dir' => $is_dir,
                'is_admin' => $is_admin,
                'primary_vp' => $vp
            );
        }
        return $singers;
    }
}