<?php

// =============================================================================
// VIEWS/INTEGRITY/_BREADCRUMBS.PHP
// -----------------------------------------------------------------------------
// Breadcrumb output for Integrity.
// =============================================================================

?>

<?php // CC_MOD - only show navigation links on blog pages ?>
<?php if ( ! is_front_page() && is_single() ) : ?>
  <?php if ( x_get_option( 'x_breadcrumb_display' ) == '1' ) : ?>

    <div class="x-breadcrumb-wrap">
      <div class="x-container max width">

        <?php // CC_MOD - do not show breadcrumbs ?>
        <?php // x_breadcrumbs(); ?>

        <?php if ( is_single() || x_is_portfolio_item() ) : ?>
          <?php x_entry_navigation(); ?>
        <?php endif; ?>

      </div>
    </div>

  <?php endif; ?>
<?php endif; ?>