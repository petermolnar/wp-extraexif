=== wp-extraexif ===
Contributors: cadeyrn
Tags: exif, image, media
Requires at least: 4.0
Tested up to: 4.8.1
Stable tag: 1.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

A plugin that uses [exiftool](http://owl.phy.queensu.ca/~phil/exiftool/) to read and store extra EXIF values of an image, which WordPress can not on it's own, such as Lens name & ID, GPS location data, etc.

It requires `exiftool` to be installed on the server.

== Description ==

The plugin expands the image meta by extracting data via exiftool. The default set of keywords is:

`-MIMEType', '-FileType', '-FileName', '-ModifyDate', '-CreateDate', '-DateTimeOriginal', '-ImageHeight', '-ImageWidth', '-Aperture', '-FOV', '-ISO', '-FocalLength', '-FNumber', '-FocalLengthIn35mmFormat', '-ExposureTime', '-Copyright', '-Artist', '-Model', '-GPSLongitude#', '-GPSLatitude#', '-LensID'`

which can be altered with the `wp_extraexif_exiftool_vars` filter (one argument, an array with the values above).

Later on the extraced values live in the same place where the default, WordPress extraced EXIF lives and can be access from the same array.

== Installation ==

1. Upload contents of `wp-extraexif.zip` to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress

== Frequently Asked Questions ==

== Changelog ==

Version numbering logic:

* every A. indicates BIG changes.
* every .B version indicates new features.
* every ..C indicates bugfixes for A.B version.

= 1.0 =
*2017-07-14*

Complete rewrite to get rid of cache files, to utilize exiftool better, and to be PHP 5.2 compatible in order to push the plugin to the wordpress.org repository.

= 0.4 =
*2016-09-07*

* filters added for extractable EXIF values

= 0.3 =
*2016-08-20*

* EXIF is not merged with WordPress attachment meta any more but instead created as standalone JSON files based on the full file path hash, so it's easy to read.


= 0.1 =
*2016-07-22*

* initial public release
