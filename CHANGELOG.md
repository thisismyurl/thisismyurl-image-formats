# Changelog

## 1.6297

First public release.

- Changes BMP, TIFF and iPhone (HEIC) pictures into pictures websites can show, when they are added or when a button on the picture's page is pressed.
- Keeps a safe copy of every original, and puts it back with one button.
- Keeps links to a changed picture working, while the plugin is active, by swapping the old address for the new one when a page is shown. Stored content is never edited.
- Leaves alone pictures that were cropped or turned in WordPress, pictures with extra files, and files shared by two Library entries.
- Only converts pictures added through the Media Library. Other plugins' uploads and WordPress's own background sideloads are not touched.
- Optionally makes new JPEG and PNG pictures smaller. Off by default; a picture that would not get smaller is left as it was.
- Settings on Media > Image Formats. Everything is already chosen.
- WP-CLI: `wp timu-formats status`, `convert` and `restore`.
