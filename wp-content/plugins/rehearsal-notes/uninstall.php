<?php
/**
 * Created by IntelliJ IDEA.
 * User: joncu
 * Date: 4/25/19
 * Time: 6:52 AM
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
if ( ! current_user_can( 'activate_plugins' ) ) {
    return;
}
// While recommended - neither of the following makes sense, or works
//check_admin_referer( 'bulk-plugins' );
//if ( __FILE__ != WP_UNINSTALL_PLUGIN )
//    return;

if ('rehearsal-notes' != WP_UNINSTALL_PLUGIN)
    return;

require_once(plugin_dir_path(__FILE__).'/includes/common/rn_options.php');
$options = new RnOptions();
$options->remove_options();

require_once(plugin_dir_path(__FILE__).'/includes/common/rn_database.php');
RnNotesDB::drop_table();
RnSingersDB::drop_table();
RnDoneDB::drop_table();
RnOnlineDB::drop_table();
