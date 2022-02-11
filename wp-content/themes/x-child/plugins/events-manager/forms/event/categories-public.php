<?php
/*
 * This file is called by templates/forms/location-editor.php to display fields for uploading images on your event form on your website. This does not affect the admin featured image section.
* You can override this file by copying it to /wp-content/themes/yourtheme/plugins/events-manager/forms/event/ and editing it there.
*/
global $EM_Event;
/* @var $EM_Event EM_Event */ 
$categories = EM_Categories::get(array('orderby'=>'name','hide_empty'=>0));
?>
<?php if( count($categories) > 0 ): ?>
<div class="event-categories">
	<!-- START Categories -->
	<label for="event_categories[]"><?php _e ( 'Select Category:', 'events-manager'); ?></label>

    <?php // CC_MOD Allow only single select of Event Category
	//<select name="event_categories[]" multiple size="10"> ?>
    <select name="event_categories[]">

	<?php
	$selected = $EM_Event->get_categories()->get_ids();
	$walker = new EM_Walker_CategoryMultiselect();

	// CC_MOD If not in current season - remove Singer Events category from choices
    // If user is Member - remove Singer Events category from choices
    // Ideally this would check to see if the current user has access to each category
    // But the easier approach is to hard-code this to Singers Events ID = 8,
    // and to Board and Board Events ID = 12.  Note that level0 cannot access this page.

    $is_current_season = CurrentSeason::is_current_season();  // Assumes consort-chorale plugin is loaded

    // Note that we are now using multiple roles, and that the s2 roles are not by friendly name ...
    $is_member_level = false;
    $is_board_level = current_user_can('administrator');
    if (!$is_board_level) {
        foreach(wp_get_current_user()->roles as $role) {
            if ('s2member_level1' == $role) {
                $is_member_level = true;
                break;
            }
            if ('s2member_level4' == $role) {
                $is_board_level = true;
                break;
            }
        }
    }
    if (!$is_board_level)
        unset($categories[12]); // remove Board Events category
    if ($is_member_level || !$is_current_season)
        unset($categories[8]); // remove Singer Events category
    // (Consort and Other categories are always available)

	$args_em = array( 'hide_empty' => 0, 'name' => 'event_categories[]', 'hierarchical' => true, 'id' => EM_TAXONOMY_CATEGORY, 'taxonomy' => EM_TAXONOMY_CATEGORY, 'selected' => $selected, 'walker'=> $walker);
	echo walk_category_dropdown_tree($categories, 0, $args_em);
	?></select>
	<!-- END Categories -->
</div>

<?php  // CC_MOD add Help about the Categories ?>
<div>
    <ul style="margin: 0">
    <?php if ($is_board_level): ?>
        <li><span class="cc-news-category" style="background-color:black">Board</span> Board Events: Visible only to the Board of Directors</li>
    <?php endif; ?>
        <li><span class="cc-news-category" style="background-color:#008800">Consort</span> Consort Events: Consort events shared with everyone</li>
        <li><span class="cc-news-category" style="background-color:#880000">Other</span> Other Events: Events and concerts by other groups and friends of Consort</li>
    <?php if (!$is_member_level && $is_current_season): ?>
        <li><span class="cc-news-category" style="background-color:#000088">Singer</span> Singer Events: Events for singers in the current season (rehearsals, etc)</li>
    <?php endif; ?>
    </ul>
</div>


<?php endif; ?>