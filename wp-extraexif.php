<?php
/*
Plugin Name: wp-extraexif
Plugin URI: https://github.com/petermolnar/wp-extraexif
Description: Read EXIF for images with cli `exiftool`
Version: 0.4
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
//\register_deactivation_hook( __FILE__ , '\WP_EXTRAEXIF\plugin_deactivate' );

define ( 'WP_EXTRAEXIF\CACHE', \WP_CONTENT_DIR . DIRECTORY_SEPARATOR.
	'cache' . DIRECTORY_SEPARATOR . 'exif' . DIRECTORY_SEPARATOR );

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

	$dirs = [ CACHE ];
	foreach ( $dirs as $dir ) {
		if ( ! is_dir( $dir ) ) {
			if ( ! mkdir( $dir ) )
				die( "failed to create {$dir}" );
		}
	}
}

/**
 *
 */
function init() {
	add_filter( 'wp_read_image_metadata', 'WP_EXTRAEXIF\read_extra_exif', 1, 3 );

	$dirs = [ CACHE ];
	foreach ( $dirs as $dir ) {
		if ( ! is_dir( $dir ) ) {
			if ( ! mkdir( $dir ) )
				debug( "failed to create {$dir}", 4 );
		}
	}
}

/**
 * check the existence and executability of exiftool
 *
 */
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

	exif_cache( $path );

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
 */
function clear_cache() {
	$list = scandir( CACHE );

	foreach ($list as $key => $name ) {
		$path = realpath( CACHE . $name );

		if ( is_file( $path ) && ! in_array ( $name, array( '.', '..' ) ) ) {
			unlink( $path );
		}
	}
}

/**
 *
 */
function exif_cache( $jpg ) {

	if ( ! is_file( $jpg ) ) {
		debug( "nonexistent JPG file at {$jpg}", 4 );
		return;
	}

	$hash = md5 ( $jpg );
	$cached = CACHE . $hash;
	$img_timestamp = @filemtime ( $jpg );

	if ( is_file( $cached  ) ) {
		$cache_timestamp = @filemtime ( $cached );
		if ( $cache_timestamp == $img_timestamp ) {
			//debug( "EXIF cache is present for {$jpg}", 7 );
			return json_decode ( file_get_contents( $cached ), true );
		}
	}

	$filters = [
		'Make',
		'Camera Model Name',
		'Aperture',
		'GPS Altitude',
		'GPS Latitude',
		'GPS Longitude',
		'Lens ID',
		'Shutter Speed',
		'Field Of View',
		'Focal Length',
		'Hyperfocal Distance',
		'ISO',
		'Create Date',
		'Copyright Notice',
	 ];
	 $filters = \apply_filters( 'wp_extraexif_list', $filters );

	 $merges = [
		'Shutter Speed' => 'Exposure Time',
		'Aperture' => 'F Number',
	 ];
	 $merges = \apply_filters( 'wp_extraexif_merges', $merges );

	 $mapping = [
		'Make' => 'make',
		'Camera Model Name' => 'camera',
		'Aperture' => 'aperture',
		'GPS Altitude' => 'geo_altitude',
		'GPS Latitude' => 'geo_latitude',
		'GPS Longitude' => 'geo_longitude',
		'Lens ID' => 'lens',
		'Shutter Speed' => 'shutter_speed',
		'Field Of View' => 'field_of_view',
		'Focal Length' => 'focal_length',
		'Hyperfocal Distance' => 'focus_distance',
		'ISO' => 'iso',
		'Create Date' => 'date',
		'Copyright Notice' => 'copyright',
	 ];
	 $mapping = \apply_filters( 'wp_extraexif_mapping', $mapping );

	$cmd = "exiftool {$jpg}";
	exec( $cmd, $exif_raw, $retval);

	if ($retval != 0 )
		die( "exiftool failed, exited with {$retval} and {$exif}" );

	foreach ( $exif_raw as $l ) {
		preg_match( '/^(.*?)\s+:\s+(.*)$/', $l, $data );
		if ( empty( $data[0]) || empty( $data[1] ) || empty( $data[2] ) )
			continue;

		if ( $data[1] == 'GPS Latitude' || $data[1] == 'GPS Longitude' )
			$data[2] = exif_gps2dec( $data[2] );
		elseif ( $data[1] == 'GPS Altitude' )
			$data[2] = exif_gps2alt( $data[2] );

		$exif [ $data[1] ] = $data[2];
	}

	$r = array();
	foreach ( $filters as $filter ) {
		if ( isset( $exif[ $filter ] ) )
			$r[ $mapping [ $filter ] ] = $exif[ $filter ];
		elseif ( isset( $merges[ $filter ] )
			&& isset( $exif[ $merges[ $filter ] ] ) )
			$r[ $mapping [ $filter ] ] = $exif[ $merges[ $filter ] ];
	}

	ksort( $r );

	file_put_contents( $cached , json_encode( $r, JSON_PRETTY_PRINT ) );
	touch( $cached, $img_timestamp );
	debug( "EXIF cache is created for {$jpg}", 6 );

	return $r;
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
