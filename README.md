# Image Formats

[![WordPress](https://img.shields.io/badge/WordPress-6.7%2B-blue.svg)](https://wordpress.org/) [![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/) [![License](https://img.shields.io/badge/License-GPL--2.0--or--later-blue.svg)](LICENSE)

Changes BMP, TIFF and iPhone (HEIC) pictures into pictures websites can show. A safe copy of every original is kept, so you can always put it back.

## What it does

Some picture files cannot be shown in a web browser at all, and others are far bigger than they need to be. This plugin changes them into kinds that work well on a website, using the image library WordPress already uses (GD or Imagick).

- **Fixes pictures when you add them.** BMP and TIFF become PNG, which keeps every detail. HEIC becomes the best kind your website can make (AVIF, WebP or JPEG).
- **One button on any picture.** "Fix this picture" changes it. "Put the original back" undoes it. Nothing happens until you press.
- **Originals are kept.** A copy is saved in a folder in uploads (with a random name, closed to visitors on servers that honour `.htaccess`) before anything changes.
- **Links keep working while the plugin is active.** If a changed picture was already used in a post, the old address is swapped for the new one when the page is shown (post content and widget text; not menus, theme settings or page builders). Stored content is never edited, so restoring an original makes the old address work again. Turn the plugin off and those swaps stop, so put originals back first.
- **Only smaller, only when asked.** New JPEG and PNG pictures can optionally be made smaller (off by default). A result that is not smaller is thrown away and the original left alone.
- **Details survive.** Title, caption and description are not touched, and camera details are carried into the Media Library details. A large original that WordPress kept next to a shrunk copy is saved and restored too.
- **Leaves risky pictures alone.** Pictures cropped or turned in WordPress, pictures with extra files, and files shared by two Library entries are not changed.
- **Only Media Library uploads.** The upload hook acts on the Media Library uploader, the block editor and the REST media route. It ignores other plugins' uploads and WordPress's own background sideloads.
- **No outside connections.** Nothing is sent anywhere.

Settings are on `Media > Image Formats`. Everything is already chosen, and the page says so first.

## Requirements

- WordPress 6.7+
- PHP 7.4+
- GD or Imagick able to write the target format (the settings page lists what your server can make)

## Installation

1. Upload the `thisismyurl-image-formats` folder to `/wp-content/plugins/`, or search for "Image Formats" in Plugins > Add New Plugin once it is listed.
2. Activate the plugin.

## WP-CLI

```
wp timu-formats status
wp timu-formats convert <id>            # or --all; add --dry-run to look first
wp timu-formats restore <id>
```

## For developers

- Filters `timu_formats_quality( $quality, $target_mime, $source_mime )`, `timu_formats_target_mime( $target, $source_mime, $context )` and `timu_formats_convert_upload( $convert, $upload, $context )`.
- Actions `timu_formats_converted( $attachment_id, $details )` and `timu_formats_restored( $attachment_id )` run after a picture is changed or put back.

## What it stores

Options `timu_formats_options`, `timu_formats_token` (names the backup folder) and `timu_formats_links` (old to new addresses), a post meta note on each converted picture (`_timu_formats_*`, which records where its saved original is), and the saved originals. Uninstalling removes the options. The notes and the saved originals are left on purpose so an original is never lost.

## Not yet

Bulk conversion from the dashboard and SVG handling are planned. Bulk conversion works today through WP-CLI.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
