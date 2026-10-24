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

**It is safe to try.** Before any picture is changed, a copy of the original is saved in a safe folder on your website (unless you turn that off in the settings). You can put the original back whenever you like, with one button. Nothing is sent over the internet, and nothing is changed until you add a picture or press a button.

**What it does**

* When you add a BMP, TIFF or iPhone (HEIC) picture to your Media Library, it changes it into a picture that works on a website. BMP and TIFF become PNG, which keeps every detail. HEIC becomes the best kind your website can make. (A TIFF with several pages keeps only its first page.)
* On any picture's page in your Media Library there is a button to change that picture, and a button to put the original back.
* Links to a changed picture keep working while this plugin is turned on. If the picture was already used in a page or post, the old link is swapped for the new one when the page is shown. Nothing you wrote is edited. This covers the text of pages, posts and widgets (see the questions below).
* It shows how much space each change saved.
* If you turn it on, it can also make new JPEG and PNG pictures smaller. This is off to begin with. If the new file would not be smaller, your picture is left exactly as it was.

**What stays the same**

* The picture's title, caption and description are not touched.
* Camera details, such as the date and exposure, are kept in the Media Library details for the picture. (The new picture file itself may not carry them. Your original does.)
* WordPress still shrinks very large pictures by itself, as it always has. If you change a picture that WordPress has already shrunk, the large original it kept is moved into the safe folder too, so it can be put back.

You do not need to change any settings. They are already chosen for you. If you want to look, go to Media, then Image Formats.

**Needs your website to be able to make the new kind of picture.** Most websites can. The settings page tells you which kinds yours can make. If your website cannot make a picture, nothing is changed. When you press the button, a message says so in plain words. When a picture is added, it is simply added as it was.

**Pictures it leaves alone.** To keep your work safe, it will not change a picture you have cropped or turned inside WordPress, a picture with extra files that belong with it, or a file that two pictures in your Library share. The button explains why.

**Works well with Image Support.** Image Support, from the same author, shows you which pictures need a description or are far too large. It is not needed for this plugin to work.

= Not included yet =

Changing many pictures at once from the dashboard, and safe handling of SVG pictures, are planned for a later version. Many pictures at once is possible today with the command line (see the questions below). Pictures added by other plugins, by WordPress's own background steps, or by the command line are not changed automatically. In the block editor on newer WordPress versions, when the browser does the picture work itself, the picture is also left as it was. In all of these cases you can use the button on the picture's page.

== Installation ==

1. In your dashboard, go to Plugins, then Add New Plugin, and search for "Image Formats". Press Install Now, then Activate.
2. That is all. Nothing is changed until you add a BMP, TIFF or iPhone picture, or press a button on a picture's page.
3. If you want to look at the settings, go to Media, then Image Formats.

== Frequently Asked Questions ==

= Will I lose my original pictures? =

No. A copy is saved in a safe folder first, and you can put it back with one button on the picture's page. The copy is only lost if you turn the "Keep a copy" setting to "No" before you change a picture (which we do not suggest) or delete the folder it is kept in. Turning the setting off later does not delete copies that are already saved. When it is off, the button says plainly that the original will be deleted.

= Where are my originals kept? =

In a folder inside your uploads folder, called "timu-formats-backups" followed by a random ending. The folder is usually closed to visitors on Apache and IIS servers, and on other servers its random name is what keeps it private. Treat it like your pictures: a saved original, such as an iPhone picture, can still hold details like where it was taken. If you delete this plugin from the Plugins screen, the folder is left in place on purpose, so your originals are not lost. You can delete it yourself when you are sure you do not need them.

= Will my picture's title, caption and description change? =

No. Those stay exactly as they are. Camera details are kept in the Media Library too.

= Will pictures already used in my pages stop showing? =

Not while this plugin is turned on. When a picture is changed, its web address changes. The plugin swaps the old address for the new one when the page is shown, in the text of pages, posts and widgets. The pages themselves are never edited, so putting the original back makes the old address work again, and links made since the change are pointed back at the original. Links in other places, such as menus, theme settings and page builders, are not swapped. In the editor, a changed picture that is already in a page may show as missing, but the page you visit is fine.

= What if I turn the plugin off? =

Pictures you changed after they were already used in a page would show as missing there, because the pages still point at the old address. Before you turn the plugin off, put those originals back with the button on each picture's page. Pictures that were changed as they were added are not affected.

= What if my website cannot change a picture? =

Then nothing happens. If you pressed a button, a message tells you in plain words. If you were adding the picture, it is simply added as it was.

= Does it make my pictures a different size? =

No. It changes the kind of picture, not how big it looks on the screen. One thing to know: if WordPress had already shrunk a large picture, the large original it kept is moved into the safe folder, and the website keeps the shrunk one.

= Will the new picture always be smaller? =

For JPEG and PNG, yes. If the new file would not be smaller, your picture is left alone. BMP, TIFF and HEIC pictures are changed so people can see them, and they almost always get much smaller too.

= Does it send my pictures anywhere? =

No. Everything happens on your own website, using the picture tools your website already has (GD or Imagick). It makes no outside connections, has no accounts, and tracks nothing.

= What does it store? =

A settings record, a short random word that names the safe folder, a list of old and new picture addresses (used to keep links working), a small note with each picture it has changed (what it was, how much space was saved, where the saved original is, and the picture's earlier addresses), and the saved originals themselves. Deleting the plugin removes the settings, the random word and the list. The notes and saved originals are kept so an original can still be found. While a picture is being changed, a short-lived marker is kept so it cannot be changed twice at once.

= I know how to use the command line. Is there one? =

Yes: `wp timu-formats status`, `wp timu-formats convert <id>` (or `--all`, with `--dry-run` to look first) and `wp timu-formats restore <id>`.

= Can developers change what it does? =

Yes. The `timu_formats_quality`, `timu_formats_target_mime` and `timu_formats_convert_upload` filters change the picture quality, the kind of picture made, and whether an upload is changed. The `timu_formats_converted` and `timu_formats_restored` actions run after a picture is changed or put back.

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
* Command line support: `wp timu-formats status`, `convert` and `restore`.

== Upgrade Notice ==

= 1.6297 =
First public release.
