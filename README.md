# Image Formats

[![WordPress](https://img.shields.io/badge/WordPress-6.7%2B-blue.svg)](https://wordpress.org/) [![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/) [![License](https://img.shields.io/badge/License-GPL--2.0--or--later-blue.svg)](LICENSE)

Changes BMP, TIFF and iPhone (HEIC) pictures into pictures websites can show. A safe copy of every original is kept, so you can always put it back.

## What it does

Some picture files cannot be shown in a web browser at all, and others are far bigger than they need to be. This plugin changes them into kinds that work well on a website, using the image library WordPress already uses (GD or Imagick).

- **Fixes pictures when you add them.** BMP and TIFF become PNG, which keeps every detail. HEIC becomes the best kind your website can make (AVIF, WebP or JPEG).
- **One button on any picture.** "Fix this picture" changes it. "Put the original back" undoes it. Nothing happens until you press.
- **Originals are kept.** A copy is saved in a folder in uploads (with a random name, closed to visitors on servers that honour `.htaccess`) before anything changes.
- **Links keep working.** If a changed picture was already used in a post, the old address is swapped for the new one when the page is shown. Stored content is never edited, so restoring an original makes the old address work again.
- **Only smaller, only when asked.** New JPEG and PNG pictures can optionally be made smaller (off by default). A result that is not smaller is thrown away and the original left alone.
- **Details survive.** Title, caption, description and camera details are kept. WordPress's own "scaled" large original is saved and restored too.
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
wp image-formats status
wp image-formats convert <id>            # or --all; add --dry-run to look first
wp image-formats restore <id>
```

## For developers

- Filter `timu_formats_quality( $quality, $target_mime, $source_mime )` changes the encoder quality.
- Actions `timu_formats_converted( $attachment_id, $details )` and `timu_formats_restored( $attachment_id )` run after a picture is changed or put back.

## What it stores

One settings option (`timu_formats_options`), a folder-name token (`timu_formats_token`), a small post meta note on each converted picture (`_timu_formats_*`), and the saved originals. Uninstalling removes the settings option only. The notes, the token and the saved originals are left on purpose so an original is never lost.

## Not yet

Bulk conversion from the dashboard and SVG handling are planned. Bulk conversion works today through WP-CLI.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
