# Changelog

## 1.6297

First public release.

- Changes BMP, TIFF and iPhone (HEIC) pictures into pictures websites can show, when they are added or when a button on the picture's page is pressed.
- Keeps a safe copy of every original, and puts it back with one button.
- Keeps links to a changed picture working by swapping the old address for the new one when a page is shown. Stored content is never edited.
- Optionally makes new JPEG and PNG pictures smaller. Off by default; a picture that would not get smaller is left as it was.
- Settings on Media > Image Formats. Everything is already chosen.
- WP-CLI: `wp image-formats status`, `convert` and `restore`.
