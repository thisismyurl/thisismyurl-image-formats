=== Thisismyurl Image Formats ===
Contributors: thisismyurl
Donate link: https://github.com/sponsors/thisismyurl
Tags: images, webp, avif, heic, convert
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6297
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Changes BMP, TIFF and iPhone (HEIC) pictures into ones websites can show. A safe copy of every original is kept, so you can always put it back.

== Description ==

Some picture files cannot be shown on a website at all. BMP, TIFF and the HEIC pictures from an iPhone are the common ones. Others are simply much bigger than they need to be. This plugin changes those pictures into kinds that work well on a website.

**It is safe to try.** Before any picture is changed, a copy of the original is saved in a safe folder on your website. You can put the original back whenever you like, with one button. Nothing is sent over the internet, and nothing is changed until you add a picture or press a button.

**What it does**

* When you add a BMP, TIFF or iPhone (HEIC) picture, it changes it into a picture that works on a website. BMP and TIFF become PNG, which keeps every detail. HEIC becomes the best kind your website can make.
* On any picture's page in your Media Library there is a button to change that picture, and a button to put the original back.
* Links to a changed picture keep working. If the picture was already used in a page or post, the old link is swapped for the new one when the page is shown. Nothing you wrote is edited.
* It shows how much space each change saved.
* If you turn it on, it can also make new JPEG and PNG pictures smaller. This is off to begin with. If the new file would not be smaller, your picture is left exactly as it was.

**What stays the same**

* The picture's title, caption and description are not touched.
* Camera details, such as the date and exposure, are kept.
* WordPress still shrinks very large pictures by itself, as it always has. This plugin keeps the large original too, so it can be put back.

You do not need to change any settings. They are already chosen for you. If you want to look, go to Media, then Image Formats.

**Needs your website to be able to make the new kind of picture.** Most websites can. The settings page tells you which kinds yours can make. If your website cannot make a picture, nothing is changed and a message says so in plain words.

**Works well with Image Support.** Image Support, from the same author, shows you which pictures need a description or are far too large. It is not needed for this plugin to work.

= Not included yet =

Changing many pictures at once from the dashboard, and safe handling of SVG pictures, are planned for a later version. Many pictures at once is possible today with the command line (see the questions below).

== Installation ==

1. In your dashboard, go to Plugins, then Add New Plugin, and search for "Image Formats". Press Install Now, then Activate.
2. That is all. Nothing is changed until you add a BMP, TIFF or iPhone picture, or press a button on a picture's page.
3. If you want to look at the settings, go to Media, then Image Formats.

== Frequently Asked Questions ==

= Will I lose my original pictures? =

No. A copy is saved in a safe folder first, and you can put it back with one button on the picture's page. The only way to lose an original is to turn the "Keep a copy" setting to "No", which we do not suggest.

= Where are my originals kept? =

In a folder inside your uploads folder, called "timu-formats-backups" followed by a random ending. The folder is closed to visitors on most servers, and its name cannot be guessed. If you delete this plugin from the Plugins screen, the folder is left in place on purpose, so your originals are not lost. You can delete it yourself when you are sure you do not need them.

= Will my picture's title, caption and description change? =

No. Those stay exactly as they are. Camera details are kept too.

= Will pictures already used in my pages stop showing? =

No. When a picture is changed, its web address changes. The plugin swaps the old address for the new one when the page is shown, so your pages keep working. The pages themselves are never edited, so putting the original back makes the old address work again.

= What if my website cannot change a picture? =

Then nothing happens. The picture is added as it was, and a message tells you in plain words.

= Does it make my pictures a different size? =

No. It changes the kind of picture, not how big it looks on the screen.

= Will the new picture always be smaller? =

For JPEG and PNG, yes. If the new file would not be smaller, your picture is left alone. BMP, TIFF and HEIC pictures are changed so people can see them, and they almost always get much smaller too.

= Does it send my pictures anywhere? =

No. Everything happens on your own website, using the picture tools your website already has (GD or Imagick). It makes no outside connections, has no accounts, and tracks nothing.

= What does it store? =

One settings record, a small note with each picture it has changed (what it was, how much space was saved, and where the saved original is), and the saved originals themselves. Deleting the plugin removes the settings record. The notes and saved originals are kept so an original can still be found.

= I know how to use the command line. Is there one? =

Yes: `wp image-formats status`, `wp image-formats convert <id>` (or `--all`, with `--dry-run` to look first) and `wp image-formats restore <id>`.

= Can developers change what it does? =

Yes. The `timu_formats_quality` filter changes the picture quality, and the `timu_formats_converted` and `timu_formats_restored` actions run after a picture is changed or put back.

== Screenshots ==

1. The settings page. Everything is already chosen, and it says so first.
2. The less common choices sit under "More options", so they stay out of the way.
3. A picture that browsers often cannot show, with a button to fix it. Nothing happens until you press it.
4. After the change: how much space was saved, and a button to put the original back.

== Changelog ==

= 1.6297 =
* First public release.
* Changes BMP, TIFF and iPhone (HEIC) pictures into pictures websites can show, when you add them or when you press a button on a picture's page.
* A safe copy of every original is kept, and can be put back with one button.
* Links to a changed picture keep working, without editing your pages.
* Optionally makes new JPEG and PNG pictures smaller.
* Command line support: `wp image-formats status`, `convert` and `restore`.

== Upgrade Notice ==

= 1.6297 =
First public release.
