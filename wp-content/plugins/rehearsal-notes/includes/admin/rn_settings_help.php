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
 * Class RnAdminTab
 */
class RnHelpTab extends RnTab
{
    protected function register_tab()
    {
        $section = 'rn-help-settings-section';
        $this->add_section($section, 'Rehearsal Note Administrator\'s Instruction Manual');
    }

    public function render_section_info()
    {
        echo '
    <div class="help-page">
        Table of Contents:
        <ul>
            <li><a href="/rn-refman?man=admin-instruct" target="_blank">Instructions for Administrators</a></li>
            <li><a href="/rn-refman?man=admin-instruct#toc-set-up" target="_blank">How to Set Up Rehearsal Notes</a></li>
            <li><a href="/rn-refman?man=admin-instruct#toc-add-singer" target="_blank">Adding someone to the Singers List</a></li>
            <li><a href="/rn-refman?man=admin-instruct#toc-remove-singer" target="_blank">Remove someone from the Singers List</a></li>
            <li><a href="/rn-refman?man=admin-instruct#toc-add-nt" target="_blank">Adding someone to list of Note Takers</a></li>
            <li><a href="/rn-refman?man=admin-instruct#toc-remove-nt" target="_blank">Removing someone from list of Note Takers</a></li>
            <li><a href="/rn-refman?man=admin-instruct#toc-change-pvp" target="_blank">Changing a Singer\'s Primary Voice Part</a></li>
            <li><a href="/rn-refman?man=admin-instruct#toc-change-vpa" target="_blank">Changing Voice Part Assignments</a></li>
            <li><a href="/rn-refman?man=admin-instruct#toc-edit-pub-note" target="_blank">Editing/Deleting a Published Note, already copied</a></li>
            <li><a href="/rn-refman?man=admin-instruct#toc-settings" target="_blank">Rehearsal Note Settings</a></li>
        </ul>
        Instruction Manuals for each Tab:
        <ul>
            <li><a href="/rn-refman?man=admin-ref-song-list" target="_blank">Song List</a></li>
            <li><a href="/rn-refman?man=admin-ref-singer-list" target="_blank">Singer List</a></li>
            <li><a href="/rn-refman?man=admin-ref-reporting" target="_blank">Reporting</a></li>
            <li><a href="/rn-refman?man=admin-ref-admin" target="_blank">Administration</a></li>
    </div>
        ';
    }
}


