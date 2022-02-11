<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'ccwpdev');

/** MySQL database username */
define('DB_USER', 'ccwp');

/** MySQL database password */
define('DB_PASSWORD', 'ccwpAdmin1$');

/** MySQL hostname */
define('DB_HOST', '127.0.0.1');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'Id{DfB-LhCtT/sE}|]dge<m4n4ZQm#JrcZc^Lm!64e~;xv5a~r!0vtQ,?^A%`h<1');
define('SECURE_AUTH_KEY',  'roI[Mm@p5WM,skv{yR$s>Q%.&&3fb;#y~hN#rn9=*z4c>>=iDnr:JG)j:MepG#N`');
define('LOGGED_IN_KEY',    '5=x-6nU>8_LZ`%@8[Wu(paFLLY2zQLP+?b/BbWr9$JDk(D^(hT(G/9XcX)+x]M*V');
define('NONCE_KEY',        'h3DLi5cF1`k@<,fm#wUdnY&yJAl)kZ5*Wy+]mK3KYsEBP&`yM_dnfWU;PUc{)?<3');
define('AUTH_SALT',        'OES%)jU3969Sf4mXyUpAOuc`OA/U_dp@w#6vQ7$@1]qctka_PA)5Sw 4ojq|TSG8');
define('SECURE_AUTH_SALT', ':<N.tfTL t;!@`ijdLhUJYaE[_RJ~gwp997=sb+8b^g(i`|7&mc3fqyB}9,8{v-5');
define('LOGGED_IN_SALT',   ')+xFRIW{]76T}&@M9y=(9*Do=-V!5wXxb;6-ybw>3f2&5KV%[lG{f2$co<3{t%A.');
define('NONCE_SALT',       'u.$-v!?eiG=Z1{}f#P%Prh|sd.Y@wpbky}`L:bC5J.t9Mkqkqs:]TYN<YiRnas0O');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_pus6q5_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
// Enable WP_DEBUG mode
define( 'WP_DEBUG', true );

// Enable Debug logging to the /wp-content/debug.log file
//define( 'WP_DEBUG_LOG', true );

// Disable display of errors and warnings
//define( 'WP_DEBUG_DISPLAY', true );
//@ini_set( 'display_errors', 0 );

// Use dev versions of core JS and CSS files (only needed if you are modifying these core files)
//define( 'SCRIPT_DEBUG', true );

// Turn off the unneccessary emails in DEV
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);

// Sometimes during migration ...
//define('WP_ALLOW_REPAIR', true);

/** CC_MOD the following are additions for plugins */

/** For S2Member - to know if this is a localhost server.  This should
 * be removed when using on the actual server.
 */
define('LOCALHOST', true);

/** for ezPHP - restricts usage of embedded PHP
 *  NOTE: Looks like we MAY need PHP in both pages and posts.
 */
/* define('EZPHP_EXCLUDED_POST_TYPES', 'post'); */

/** for wp-mail-smtp - localhost configuration
 *  BEWARE - THIS ASSUMES A DIFFERENT WP-CONFIG ON THE SERVER
 *  SO THIS FILE WILL NEED TO BE REMOVED FROM SVN!  Either that,
 *  or once the server has been started, the authentication can
 *  be completed, and like PHPMailer - it will work from either
 *  the server or localhost, and then localhost can switch to
 *  using Gmail OAUTH.
 */
define( 'WPMS_ON', true );
define( 'WPMS_SMTP_PASS', 'CCS4web#' );

/** for LAMPSERVER - when not on the Vendola Wifi
 * Outside of that LAN the Virtual Machine does not connect
 * to the external internet.  The website itself is OK, but
 * loading any of the Admin dashboard triggers a check for
 * anything needing updates - and that check has to time-out
 * causing long delays.  This setting blocks the external calls.
 */
define('WP_HTTP_BLOCK_EXTERNAL', false);
/** This should be used with it, but it won't fix the slowdown. */
# define( 'WP_ACCESSIBLE_HOSTS', 'api.wordpress.org,backup-guard.com' );


/** CC_MOD end of additions (must go before the following line */

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
    define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');



