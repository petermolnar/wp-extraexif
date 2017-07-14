<?php
/*
Plugin Name: wp-extraexif
Plugin URI: https://github.com/petermolnar/wp-extraexif
Description: Read EXIF for images with cli `exiftool`
Version: 1.0
Author: Peter Molnar <hello@petermolnar.eu>
Author URI: http://petermolnar.net/
License: GPLv3
*/

/*  Copyright 2016-2017 Peter Molnar ( hello@petermolnar.eu )

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

add_action( 'init', 'WP_EXTRAEXIF_init' );
register_activation_hook( __FILE__ , 'WP_EXTRAEXIF_plugin_activate' );

/**
 * activate hook
 */
function WP_EXTRAEXIF_plugin_activate() {
	if ( ! function_exists( 'shell_exec' ) )
		die("This plugin requires `exec` function to call `exiftool` and it is not available.");

	$cmd = 'exiftool -ver';
	$r = shell_exec( $cmd );

	if ( empty( $r ) )
		die("exiftool cannot be executed via `exec`. This plugin requires `exiftool` to be installed on the system and available in \$PATH variable; please talk to your system administrator.");
}

/**
 *
 */
function WP_EXTRAEXIF_init() {
	add_filter( 'wp_read_image_metadata', 'WP_EXTRAEXIF_read_meta', 1, 4 );
}

function WP_EXTRAEXIF_read_meta( $meta, $file, $sourceImageType, $iptc ) {
	$meta = WP_EXTRAEXIF_run($meta, $file);
	return $meta;
}

function WP_EXTRAEXIF_run($current_meta, $file) {
	if (isset($current_meta['extraexif']) && !empty($current_meta['extraexif'])) {
		return $current_meta;
	}

	$vars = array('-sort', '-json', '-MIMEType', '-FileType', '-FileName', '-ModifyDate', '-CreateDate', '-DateTimeOriginal', '-ImageHeight', '-ImageWidth', '-Aperture', '-FOV', '-ISO', '-FocalLength', '-FNumber', '-FocalLengthIn35mmFormat', '-ExposureTime', '-Copyright', '-Artist', '-Model', '-GPSLongitude#', '-GPSLatitude#', '-LensID');
	$vars = apply_filters('wp_extraexif_exiftool_vars', $vars);
	array_unshift($vars, 'exiftool');
	array_push($vars, $file);
	$cmd = join(' ', $vars);
	WP_EXTRAEXIF_debug("executing: {$cmd}", LOG_DEBUG);

	$meta = $current_meta;
	$out = shell_exec($cmd);
	$out = json_decode($out);
	$out = array_pop($out);
	foreach($out as $mkey => $mvalue) {
		if(isset($current_meta['image_meta'][$mkey])) {
			continue;
		}

		$current_meta['image_meta'][$mkey] = $mvalue;
	}

	$current_meta['extraexif'] = True;
	WP_EXTRAEXIF_debug("returning: " . var_export($current_meta, True), LOG_DEBUG);

	return $current_meta;
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
function WP_EXTRAEXIF_debug( $message, $level = LOG_NOTICE ) {
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

	// ERR, CRIT, ALERT and EMERG
	if ( 3 >= $level_ ) {
		wp_die( '<h1>Error:</h1>' . '<p>' . $message . '</p>' );
		exit;
	}

	$trace = debug_backtrace();
	$caller = $trace[1];
	$parent = $caller['function'];

	return error_log( "{$parent}: {$message}" );
}
