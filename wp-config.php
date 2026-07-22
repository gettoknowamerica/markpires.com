<?php
define( 'WP_CACHE', true );

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'u851465535_0IwuV' );

/** Database username */
define( 'DB_USER', 'u851465535_l2Uk7' );

/** Database password */
define( 'DB_PASSWORD', 'ho9xzyD9Li' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'vP61[s0]R)vu10:7~x-`AkI~B;a=t_+* 8;kL?8C7.H-PCN):*a_gzi3C8]vlA#b' );
define( 'SECURE_AUTH_KEY',   'Q0n]e3ty@g](J V+5*9rWorw[Ty.{ e:m p>LRzT1q1~XUn4+>l92F$y{x2UBFji' );
define( 'LOGGED_IN_KEY',     'N>OGyLy*gNu)X[T_7Tp#^;}kfyqKuk[V<Mn,o;E:WY/k!OcXCfZ!k=[ViY9<U4mm' );
define( 'NONCE_KEY',         'L6+9.;]fpso*3Asv;2xKqkgll7&~sSdaWfd[1c}/bV(A2/HhPXA?=B9!D<.;bD3e' );
define( 'AUTH_SALT',         '51{n;(qiZZr~_a5o4Jlwv0~%s7*EmGYz{D0V)+~$65-~e`exfpsCDz0}7sMUo{Aq' );
define( 'SECURE_AUTH_SALT',  'a6{sKtu[`kD[r(,+~J)DBxj,Cn_x/.5[4n4*&h=M>EpjJZIz|l+E4k4/x?G$9Rtj' );
define( 'LOGGED_IN_SALT',    ',yhwYRv(@mZ^C*B,Y n%`+$S}WrHR/mPX [imKI%-wKvP/[e)Y`T~c[+M ^%L^ML' );
define( 'NONCE_SALT',        'U|&u9v>;8FreC ,ZAR;<e}AYll1R^(Wx]m+Y]rdNLjs^HU_Gwv&]]p!nCrYQr8eJ' );
define( 'WP_CACHE_KEY_SALT', '@T`WRvJI($jE7e}#mK?; #Kcy[}7dlDz,#LIUlmB8*8SRQw3H3,*[yAuVHpp4/Ld' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'FS_METHOD', 'direct' );
define( 'COOKIEHASH', 'bda4de21e00c3d54bb2c05d10531cdef' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
