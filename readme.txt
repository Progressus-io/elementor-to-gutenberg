=== Migrate Off Elementor ===
Contributors: shadim
Tags: gutenberg, migration, conversion, blocks
Requires at least: 6.7
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your Elementor pages into the built-in WordPress editor automatically — in bulk, page by page.

== Description ==

Migrate Off Elementor converts Elementor pages into native WordPress blocks, in bulk or one page at a time, so you do not have to rebuild them by hand.

It reads the Elementor data already stored on each page, rebuilds the layout with standard WordPress blocks plus a few of its own, carries over colors, fonts and spacing, and saves the result as a new page. The original is left in place, so you can compare the two and switch over when you are ready. Elementor does not need to be active for a conversion to run.

= What it can convert =

**Content:** headings, text, images, galleries and carousels, buttons, videos, icons, spacers, dividers, Google Maps, and shortcodes.

**Interactive elements:** accordions, toggles, tabs (including nested ones), counters, and progress bars.

**Boxes and lists:** icon boxes, image boxes, icon lists, call-to-action boxes, testimonials and testimonial carousels, and social icons.

**Forms and site parts:** contact and search forms, navigation menus, the site logo, and post/blog listings.

**WooCommerce:** product grids, cart, checkout, mini-cart, product categories, store notices, "my account", and add-to-cart.

Anything with no exact block equivalent is converted with a general-purpose fallback and flagged in the Conversion Log.

= What a conversion changes on your site =

Converted pages are saved as brand-new pages, and your Elementor originals are never deleted or edited. Some optional steps in the Conversion Wizard do change site-wide settings, so they are listed here in full:

* **Theme install.** If you choose a block theme in the wizard's theme step that is not installed, the plugin downloads and installs it from WordPress.org. It only runs if your account may install themes.
* **Theme switch.** If you choose a theme other than the active one, the plugin switches the site's active theme when the run starts. That changes how the whole site looks to visitors, not only the converted pages.
* **Additional CSS is merged.** When the theme is switched, the previous theme's Additional CSS is appended to the new theme's Additional CSS, marked with a "Migrated from" comment. It is on by default, you can turn it off in the wizard, and it always runs in automatic mode.
* **Template parts.** Converted Elementor headers and footers are saved as block template parts. On a block theme, the converted header and footer marked as default take the place of the theme's own header and footer across the whole site.

Choosing "Keep current theme" in the theme step skips all four.

= What to expect =

Most pages land very close to the original straight away. Elementor and the block editor handle layout and styling differently, so some things are approximated rather than copied one to one, mostly on busier and highly customised designs. The Conversion Log lists, page by page, what converted cleanly and what is worth a look, and because the result is ordinary block content you fix anything in the editor you already know. Specifically:

* Complex pages usually need a few small tweaks after conversion.
* Elementor Pro and third-party widgets with no block equivalent are converted with a safe fallback and flagged, rather than recreated exactly.
* Motion effects, some pop-ups, and similar builder-only extras may not carry across and can need rebuilding with block tools.
* Global Elementor styles (theme-wide colors and fonts set in Elementor) are matched as closely as possible but may need a final check against your theme.

== Installation ==

1. In your dashboard, go to **Plugins > Add New**, search for this plugin, and click **Install Now** - or upload the ZIP under **Plugins > Add New > Upload Plugin**.
2. Click **Activate**.
3. A new **Migrate Off Elementor** menu appears in your sidebar.
4. (Recommended) Open **Settings** first and set the page width to match your Elementor design.
5. Open **Conversion Wizard**, pick a page, and convert it.

Back up your site, or work on a staging copy, before your first run: the wizard's theme step can install a theme, switch the active theme and merge Additional CSS.

== Frequently Asked Questions ==

= Does Elementor need to stay installed after I convert? =

No. Elementor only needs to have built the pages in the first place. Once a page is converted to blocks you can switch Elementor off.

= Will it delete or change my original Elementor pages? =

No. The converted version is always a brand-new page and the original is left untouched. Site-wide settings are a separate matter: see "What a conversion changes on your site" above.

= Do I need to install any other plugin for it to work? =

No. Conversion targets the blocks already in WordPress plus the plugin's own built-in ones, so there is no extra block library to install.

= Does it work with WooCommerce stores? =

Yes. Common WooCommerce elements (product grids, cart, checkout, mini-cart, categories, notices, my-account, and add-to-cart) are converted into their WooCommerce block versions.

= Do I need to pay for anything or get an "API key"? =

No — the conversion is completely free and runs entirely on your own site, and no account is required.

One optional key exists: Settings > Integrations has a field for a Google Maps API key. Leave it empty (the default) and converted Map blocks keep using the keyless Google Maps embed, exactly as before. Fill it in and Map blocks are rendered through the Google Maps Embed API on your own Google account instead. It changes nothing else, it is applied when a page is rendered rather than saved into your pages, and clearing it puts every map straight back to the keyless embed.

= Will it work with my theme? =

Yes. Converted pages use standard WordPress blocks, so they work with any modern block-friendly theme. The wizard's theme step reports what your current theme supports, and can install and switch to a block theme if you ask it to.

= Is any of my data sent to outside services? =

The conversion itself runs entirely on your own site. A few optional features do connect to outside services (any Google fonts/maps/videos your page already used, and sending feedback if you choose to). Each one is explained in full in the **External services** section below, and the feedback feature only ever runs when you choose to start it.

== External services ==

This plugin can connect to the third-party services listed below. The feedback service is only contacted from the admin area, as part of an action you explicitly start (submitting feedback). The others load only when a converted page that uses those features is viewed, or while a Map block is open in the block editor.

= Feedback service =

Used only if you choose to send feedback about a conversion to the plugin developer. It is never sent automatically; submission requires your explicit consent on the feedback screen.

When you submit feedback, the plugin sends a feedback package to https://block-shift.com/wp-json/etg-feedback/v1/submit, authenticated with a per-site client ID/secret generated and stored on your site. The package contains: environment data (site domain, a hashed site URL, plugin/WordPress/PHP versions, active theme, locale), your browser/screen details, the conversion run data and summary, your rating and notes, and, for the pages you select, the original Elementor page data and the converted page content. Page content is sent as-is: it is not anonymised or filtered, so it may contain personal data if the page does.

This service is operated by Progressus. Privacy policy: https://progressus.io/privacy-policy

= Google Fonts =

Converted pages may reuse web fonts from the original Elementor design. When such a page is viewed, the visitor's browser loads those fonts from Google Fonts (https://fonts.googleapis.com and https://fonts.gstatic.com).

This service is provided by Google. Terms: https://policies.google.com/terms — Privacy policy: https://policies.google.com/privacy

= Embedded maps and videos =

If a converted page contains a map or video, the page embeds it from Google Maps (https://maps.google.com) or YouTube (https://www.youtube.com), the same way the original Elementor page did. These embeds load when such a page is viewed, and also in the block editor while a Map block is open, so that you can see a preview of the map you are editing. In the editor the preview is rebuilt once you stop typing, not on every keystroke.

A Google Maps embed is a page served by Google inside an iframe. Once it loads, Google's own code in that iframe fetches map tiles and its supporting resources from further Google hosts: https://www.google.com, https://maps.googleapis.com, https://places.googleapis.com, https://maps.gstatic.com, https://fonts.googleapis.com and https://fonts.gstatic.com. The plugin sends Google only the address or the coordinates stored in the block, plus the zoom level.

Provided by Google. Terms: https://policies.google.com/terms and https://www.youtube.com/t/terms — Privacy policy: https://policies.google.com/privacy

= Google Maps Embed API =

Only used if you enter a Google Maps API key in Settings > Integrations. There is no key by default and this service is not contacted until you set one.

With a key set, Map blocks are embedded from the Google Maps Embed API (https://www.google.com/maps/embed/v1/place) instead of the keyless Google Maps embed, both on the public page and in the block editor preview. The plugin sends Google your API key, the address or coordinates stored in the block, and the zoom level.

Provided by Google. Terms: https://policies.google.com/terms and https://cloud.google.com/maps-platform/terms — Privacy policy: https://policies.google.com/privacy

== Source code ==

Development happens in the open at https://github.com/Progressus-io/elementor-to-gutenberg, which holds the uncompressed sources for everything the plugin builds, including the JavaScript and SCSS behind every block under src/. Released ZIPs are built from it with `npm run build`.

Two third-party libraries ship in compressed form:

* Swiper 11.2.10 (MIT), at assets/vendor/swiper/. Source: https://github.com/nolimits4web/swiper/releases/tag/v11.2.10
* Font Awesome Free 6.5.0 (icons CC BY 4.0, fonts SIL OFL 1.1, code MIT), at assets/vendor/fontawesome/. Source: https://github.com/FortAwesome/Font-Awesome/releases/tag/6.5.0

Lucide line icons (ISC) are inlined uncompressed in assets/js/pgs-icons.js. Source: https://github.com/lucide-icons/lucide

== Screenshots ==

1. The Conversion Wizard - pick your Elementor pages and convert them to blocks.
2. Conversion results, with a summary for each page.
3. The Conversion Log - which elements converted cleanly and which need a look.
4. Settings - page width, conversion defaults, and logging.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
