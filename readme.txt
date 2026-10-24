=== Thisismyurl Image Formats ===
Contributors: thisismyurl
Tags: images, webp, avif, heic, bmp
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.6297.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Converts images to formats browsers can show and sites can afford: BMP, TIFF and HEIC to web formats, and optionally JPEG and PNG to WebP or AVIF. Originals are kept so every conversion can be undone.

== Description ==

This is a pre-release. It is under active development and is not yet in the WordPress.org directory.

WordPress accepts BMP, TIFF and HEIC uploads but leaves them exactly as they arrive, and browsers cannot show TIFF or HEIC at all. It does not make WebP or AVIF versions of your JPEG and PNG files. This plugin does those jobs with the image library WordPress already uses (GD or Imagick). Nothing is installed on your server and nothing leaves your site.

What it does now:

* Converts BMP, TIFF, HEIC and HEIF uploads as they arrive: BMP and TIFF to PNG (or WebP), HEIC and HEIF to AVIF, WebP or JPEG, whichever your server can write.
* Optionally converts JPEG and PNG uploads to WebP or AVIF. This is off by default. A converted file that would be larger than the original is discarded.
* Converts what is already in your Media Library from the command line, with a dry run.
* Keeps the original so a conversion can be undone, in a folder inside your uploads folder.
* Shows on each converted image what it was and how much space was saved.

What it does not do yet: update references to a converted image inside your posts and pages, handle SVG, or convert in bulk from the dashboard. These are the next phases.

== Installation ==

1. Download the zip, then use Plugins > Add New Plugin > Upload Plugin.
2. Activate it, then open Settings > Image Formats.

== Frequently Asked Questions ==

= Does it delete my originals? =

Not by default. The original goes to a backup folder inside your uploads folder. You can choose to keep none, in which case the original is deleted after a successful conversion.

= What if my server cannot read or write a format? =

The upload goes through unchanged. The settings screen shows what your server can write, and the plugin only offers formats it can actually produce.

= Which command-line commands are there? =

`wp image-formats status`, `wp image-formats convert <id>...` or `--all` (with `--dry-run`), and `wp image-formats restore <id>...`.

== Changelog ==

= 0.6297.1 =
* First development build: capabilities check, conversion policy, verified converter, upload hook, local backup, restore, settings screen and WP-CLI.
