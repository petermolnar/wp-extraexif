<?php
/*
Plugin Name: wp-extraexif
Plugin URI: https://github.com/petermolnar/wp-extraexif
Description: Read extra EXIF for images with exiftool
Version: 0.1
Author: Peter Molnar <hello@petermolnar.net>
Author URI: http://petermolnar.net/
License: GPLv3
*/

/*  Copyright 2016 Peter Molnar ( hello@petermolnar.net )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace WP_EXTRAEXIF;

\add_action( 'init', 'WP_EXTRAEXIF\init' );
\register_activation_hook( __FILE__ , '\WP_EXTRAEXIF\plugin_activate' );
\register_deactivation_hook( __FILE__ , '\WP_EXTRAEXIF\plugin_deactivate' );

/**
 *
 */
function defaults() {
	// hardcoded
	$config = array (
		// exiftool value => store as meta key
		'LensID'       => 'lens',
		'GPSLatitude'  => 'geo_latitude',
		'GPSLongitude' => 'geo_longitude',
		'GPSAltitude'  => 'geo_altitude',
		'Title'        => 'title',
	);

	$ini = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'config.ini';
	if ( file_exists ( $ini ) ) {
		$config = array_merge ( $config, parse_ini_file( $ini ) );
	}

	$current = \get_option( __NAMESPACE__ );

	if ( $current != $config )
		\update_option( __NAMESPACE__, $config );

	return $config;
}

/**
 * activate hook
 */
function plugin_activate() {
	if ( version_compare( phpversion(), 5.3, '<' ) ) {
		die( 'The minimum PHP version required for this plugin is 5.3' );
	}

	$test = test_exiftool();

	if ( true !== $test )
		die ( $test );

}

/**
 * activate hook
 */
function plugin_deactivate() {
	\delete_option( __NAMESPACE__ );
}

/**
 *
 */
function init() {
	add_filter( 'wp_read_image_metadata', 'WP_EXTRAEXIF\read_extra_exif', 1, 3 );
}

function test_exiftool () {
	if ( ! function_exists( 'exec' ) )
		return "This plugin requires `exec` function which is not available.";

	$cmd = 'exiftool -ver';
	exec( $cmd, $path, $retval);

	if ( 0 != $retval || empty( $path ) )
		return "exiftool cannot be executed via `exec`. This plugin requires "
		 . "exiftool to be installed on the system and available in \$PATH";

	return true;
}

/**
 * additional EXIF which only exiftool can read
 *
 */
function read_extra_exif ( $meta, $path ='', $sourceImageType = '' ) {

	if ( empty( $path ) || ! is_file( $path ) || ! is_readable( $path )) {
		debug ( "{$path} doesn't exist", 4 );
		return $meta;
	}

	if ( $sourceImageType != IMAGETYPE_JPEG ) {
		debug ( "{$path} is not JPG", 5 );
		return $meta;
	}

	$test = test_exiftool();
	if ( true !== $test ) {
		debug ( "can't find exiftool", 4 );
		return $meta;
	}

	$extra = \get_option( __NAMESPACE__, defaults() );

	$args = $metaextra = array();

	foreach ($extra as $exiftoolID => $metaid ) {
		// only try to get the missing
		if ( ! isset( $meta[ $metaid ]) ) {
			$args[] = $exiftoolID;
		}
	}

	if ( empty( $args ) )
		return $meta;

	$args = join(' -', $args);
	$cmd = "exiftool -s -{$args} {$path}";

	debug("Extracting extra EXIF for {$path} with command {$cmd}", 7 );
	exec( $cmd, $exif, $retval);

	if ($retval != 0 ) {
		debug("Extracting extra EXIF failed with error code ${retval}", 4 );
		return $meta;
	}

	foreach ( $exif as $cntr => $data ) {
		$data = array_map( 'trim', explode (' : ', $data ) );

		if ( $data[0] == 'GPSLatitude' || $data[0] == 'GPSLongitude' )
				$data[1] = exif_gps2dec( $data[1] );
		elseif ( $data[0] == 'GPSAltitude' )
			$data[1] = exif_gps2alt( $data[1] );

		$metaextra[ $extra[ $data[0] ] ] = $data[1];
	}

	if ( ! empty( $metaextra ) ) {
		debug ( "Adding extra EXIF", 7);
		debug ( $metaextra, 7 );
		$meta = array_merge($meta, $metaextra);
	}

	return $meta;
}

/**
 *
 */
function exif_gps2dec ( $string ) {
	//103 deg 20' 38.33" E
	preg_match( "/([0-9.]+)\s?+deg\s?+([0-9.]+)'\s?+([0-9.]+)\"\s?+([NEWS])/",
		trim( $string ), $matches );

	$dd = $matches[1] + ( ( ( $matches[2] * 60 ) + ( $matches[3] ) ) / 3600 );
	if ( $matches[4] == "S" || $matches[4] == "W" )
		$dd = $dd * -1;
	return round($dd,6);
}

/**
 *
 */
function exif_gps2alt ( $string ) {
	//2062.6 m Above Sea Level
	preg_match( "/([0-9.]+)\s?+m/", trim($string), $matches );

	$alt = $matches[1];
	if ( stristr( $string, 'below') )
		$alt = $alt * -1;
	return $alt;
}


/**
 *
 * debug messages; will only work if WP_DEBUG is on
 * or if the level is LOG_ERR, but that will kill the process
 *
 * @param string $message
 * @param int $level
 *
 * @output log to syslog | wp_die on high level
 * @return false on not taking action, true on log sent
 */
function debug( $message, $level = LOG_NOTICE ) {
	if ( empty( $message ) )
		return false;

	if ( @is_array( $message ) || @is_object ( $message ) )
		$message = json_encode($message);

	$levels = array (
		LOG_EMERG => 0, // system is unusable
		LOG_ALERT => 1, // Alert 	action must be taken immediately
		LOG_CRIT => 2, // Critical 	critical conditions
		LOG_ERR => 3, // Error 	error conditions
		LOG_WARNING => 4, // Warning 	warning conditions
		LOG_NOTICE => 5, // Notice 	normal but significant condition
		LOG_INFO => 6, // Informational 	informational messages
		LOG_DEBUG => 7, // Debug 	debug-level messages
	);

	// number for number based comparison
	// should work with the defines only, this is just a make-it-sure step
	$level_ = $levels [ $level ];

	// in case WordPress debug log has a minimum level
	if ( defined ( '\WP_DEBUG_LEVEL' ) ) {
		$wp_level = $levels [ \WP_DEBUG_LEVEL ];
		if ( $level_ > $wp_level ) {
			return false;
		}
	}

	// ERR, CRIT, ALERT and EMERG
	if ( 3 >= $level_ ) {
		\wp_die( '<h1>Error:</h1>' . '<p>' . $message . '</p>' );
		exit;
	}

	$trace = debug_backtrace();
	$caller = $trace[1];
	$parent = $caller['function'];

	if (isset($caller['class']))
		$parent = $caller['class'] . '::' . $parent;

	if (isset($caller['namespace']))
		$parent = $caller['namespace'] . '::' . $parent;

	return error_log( "{$parent}: {$message}" );
}
