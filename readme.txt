=== Thisismyurl Image Formats ===
Contributors: thisismyurl
Tags: images, webp, avif, heic, bmp
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.6297.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Changes pictures that websites cannot show, or that take up too much space, into pictures that work well. A safe copy of every original is kept, so you can always put it back.

== Description ==

This plugin is still being built. You are welcome to try it, and it is not on the WordPress.org list yet.

Some picture files are too big for a website. Others, like BMP, TIFF and the HEIC pictures from an iPhone, cannot be shown in a web browser at all. This plugin changes those pictures into kinds that work well on a website.

**Is it safe?** Yes. Before any picture is changed, a copy of the original is saved in a safe folder. You can put the original back whenever you like. Nothing is sent over the internet. Nothing is changed until you add a picture or press a button.

What it does:

* When you add a BMP, TIFF or iPhone (HEIC) picture, it changes it into a picture that works on a website.
* On any picture's page in your Media Library, there is a button to change that picture, and a button to put the original back.
* If you turn it on, it can also make new JPEG and PNG pictures smaller. This is off to begin with. If the new file would not be smaller, your picture is left exactly as it was.
* It shows how much space each change saved.

You do not need to change any settings. They are already chosen for you.

Coming later: fixing the links to a changed picture inside your pages and posts, changing many pictures at once from the dashboard, and safe handling of SVG pictures.

== Installation ==

1. Download the zip file. In your dashboard, go to Plugins, then Add New Plugin, then Upload Plugin.
2. Choose the zip file and press Install Now, then Activate.
3. That is all. If you want to look at the settings, go to Media, then Image Formats.

== Frequently Asked Questions ==

= Will I lose my original pictures? =

No. A copy is saved in a safe folder first, and you can put it back with one button on the picture's page. The only way to lose an original is to turn the "Keep a copy" setting to "No", which we do not suggest.

= Will my picture's title, caption and description change? =

No. Those stay exactly as they are. Camera details are kept too.

= What if my website cannot change a picture? =

Then nothing happens. The picture is added as it was, and a message tells you in plain words.

= Does it make my pictures a different size? =

No. It changes the kind of picture, not how big it looks on the screen. WordPress still shrinks very large pictures by itself, as it always has, and this plugin saves the large original too.

= Will the new picture always be smaller? =

For JPEG and PNG, yes. If the new file would not be smaller, your picture is left alone. BMP, TIFF and HEIC pictures are changed so people can see them, and they almost always get much smaller too.

= I know how to use the command line. Is there one? =

Yes: `wp image-formats status`, `wp image-formats convert <id>` (or `--all`, with `--dry-run` to look first) and `wp image-formats restore <id>`.

== Changelog ==

= 0.6297.1 =
* First test version.
