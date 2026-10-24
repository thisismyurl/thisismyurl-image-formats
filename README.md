# Thisismyurl Image Formats

Converts images to formats browsers can show and sites can afford. BMP, TIFF and HEIC become web formats; JPEG and PNG can become WebP or AVIF. Originals are kept so every conversion can be undone. Uses the image library WordPress already uses (GD or Imagick); no outside services.

**Status: pre-release (0.6297.1).** Planned phases are in the plan document in the AI-OS repo (`clients/thisismyurl/plugins/IMAGE-FORMATS-PLAN.md`). It will replace the separate format plugins (BMP, WebP, HEIC, SVG, TIFF support) and `thisismyurl-bmp-to-png`.

See `readme.txt` for what it does now and what it does not do yet. WP-CLI: `wp image-formats status|convert|restore`.

Licensed GPL-2.0-or-later.
