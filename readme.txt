=== wp-extraexif ===
Contributors: cadeyrn
Tags: exif, image, media
Requires at least: 4.0
Tested up to: 4.6
Stable tag: 0.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

A plugin that uses [exiftool](http://owl.phy.queensu.ca/~phil/exiftool/) to read EXIF values of an image.

== Description ==

== Installation ==

1. Upload contents of `wp-extraexif.zip` to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress

== Frequently Asked Questions ==

== Changelog ==

Version numbering logic:

* every A. indicates BIG changes.
* every .B version indicates new features.
* every ..C indicates bugfixes for A.B version.

= 0.4 =
*2016-09-07*

* filters added for extractable EXIF values

= 0.3 =
*2016-08-20*

* EXIF is not merged with WordPress attachment meta any more but instead created as standalone JSON files based on the full file path hash, so it's easy to read.


= 0.1 =
*2016-07-22*

* initial public release
