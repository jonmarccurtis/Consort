<?php

// =============================================================================
// FUNCTIONS.PHP
// -----------------------------------------------------------------------------
// Overwrite or add your own custom functions to X in this file.
// =============================================================================

// =============================================================================
// TABLE OF CONTENTS
// -----------------------------------------------------------------------------
//   01. Enqueue Parent Stylesheet
//   02. Swap styles for Printable template
//   03. X_Entry_Navigation override
//   04. Additional Functions
// =============================================================================

// Enqueue Parent Stylesheet
// =============================================================================

//add_filter( 'x_enqueue_parent_stylesheet', '__return_true' );

add_action( 'wp_enqueue_scripts', 'my_theme_enqueue_styles');
function my_theme_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/cc-child.css', array( 'parent-style' ) );
    wp_enqueue_script( 'child-script', get_stylesheet_directory_uri() . '/functions.js', array( 'jquery' ), null, true );
}

// Swap styles for Printable template
// =============================================================================
// For the Template Blank (Printable), swap out the X integrity-light.css for a
// copy that does not have its @media print {} section.  That section causes the
// PDF printing to be unusable.  This is currently used by the Rehearsal Notes
// Reference Manual.  Give it a lower priority #(20) so it gets handled last.

add_action('wp_enqueue_scripts', 'swap_styles_for_printable', 20);
function swap_styles_for_printable() {
    if (is_page_template('template-printable.php')) {
        wp_dequeue_style('x-stack');
        wp_deregister_style('x-stack');
        wp_enqueue_style('printable-style', get_stylesheet_directory_uri() . '/printable.css');
    }
}

// Override X Entry Navigation - to stay within the category
// =============================================================================

function x_entry_navigation() {

    $stack = x_get_stack();

    if ( $stack == 'ethos' ) {
        $left_icon  = '<i class="x-icon-chevron-left" data-x-icon-s="&#xf053;"></i>';
        $right_icon = '<i class="x-icon-chevron-right" data-x-icon-s="&#xf054;"></i>';
    } else {
        $left_icon  = '<i class="x-icon-arrow-left" data-x-icon-s="&#xf060;"></i>';
        $right_icon = '<i class="x-icon-arrow-right" data-x-icon-s="&#xf061;"></i>';
    }

    $is_ltr    = ! is_rtl();
    $is_ltr = false;  // CC_MOD Reverse the links

    // CC_MOD - support event-categories for calendar appointments
    $cur_post = get_post();
    $taxonomy = $cur_post->post_type == 'event' ? 'event-categories' : 'category';

    // CC_MOD - set in_same_term == true
    $prev_post = get_adjacent_post( true, '', false, $taxonomy );
    $next_post = get_adjacent_post( true, '', true, $taxonomy );

    // CC_MOD - add in the post titles
    if ($next_post)
        $left_icon = $left_icon . ' ' . $next_post->post_title;
    if ($prev_post)
        $right_icon = $prev_post->post_title . ' ' . $right_icon;

    $prev_icon = ( $is_ltr ) ? $left_icon : $right_icon;
    $next_icon = ( $is_ltr ) ? $right_icon : $left_icon;

    ?>

    <div class="x-nav-articles">

        <?php // CC_MOD reverse the order, right-left of the links ?>

        <?php if ( $next_post ) : ?>
            <a href="<?php echo get_permalink( $next_post ); ?>" title="<?php __( 'Next Post', '__x__' ); ?>" class="next">
                <?php echo $next_icon; ?>
            </a>
        <?php endif; ?>

        <?php // CC_MOD add blank item to keep the right-link on the right side ?>
        <?php if ( ! $next_post ) : ?>
            &nbsp;
        <?php endif; ?>

        <?php if ( $prev_post ) : ?>
            <a href="<?php echo get_permalink( $prev_post ); ?>" title="<?php __( 'Previous Post', '__x__' ); ?>" class="prev">
                <?php echo $prev_icon; ?>
            </a>
        <?php endif; ?>

    </div>

    <?php

}

// Additional Functions
// =============================================================================

// Hide dashboard Portfolio menu item
add_action('admin_head', 'hide_portfolio');
function hide_portfolio() {
    echo '<style>
    li#menu-posts-x-portfolio {
    display: none;
}
  </style>';
}

// Reset s2member Profile, Custom checkbox fields to display vertically
// s2member defaults to profile checkboxes in horizontal alignment using &nbsp;&nbsp;.  This changes
// it to vertical alignment using <br>
add_action('ws_plugin__s2member_custom_field_gen_checkboxes_sep', 'profile_align_checkboxes', 10, 1);
function profile_align_checkboxes($args) {
    return '<br>';
}

// Change favicon for internal APC website
add_filter( 'get_site_icon_url','apc_favicon', 10, 3 );
function apc_favicon ($url, $size, $blog_id) {
    if (is_page(4431)) {  // Allan Petker Chorale page ID
        switch ($size) {
            case 32:
                $url = site_url('wp-content/uploads/2021/05/cropped-apc-favicon-32x32.png');
                break;
            case 180:
                $url = site_url('wp-content/uploads/2021/05/cropped-apc-favicon-180x180.png');
                break;
            case 192:
                $url = site_url('wp-content/uploads/2021/05/cropped-apc-favicon-192x192.png');
                break;
            case 270:
                $url = site_url('wp-content/uploads/2021/05/cropped-apc-favicon-270x270.png');
                break;
            default:
                $url = site_url('wp-content/uploads/2021/05/cropped-apc-favicon.png');
                break;
        }
    }
    return $url;
}

