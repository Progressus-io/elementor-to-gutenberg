=== BlockShift – Migrate from Elementor ===
Contributors: shadim
Tags: elementor, gutenberg, migration, conversion, blocks
Requires at least: 6.7
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your Elementor pages into the built-in WordPress editor automatically — in bulk, with an optional AI pass to fine-tune each page.

== Description ==

**BlockShift – Migrate from Elementor** moves your Elementor pages over to the **built-in WordPress editor** (the "block editor", also called Gutenberg) — automatically, so you don't have to rebuild them by hand.

If you've ever looked into leaving Elementor, you've probably heard the same disappointing answer: "there's no real converter, you'll have to rebuild every page yourself." BlockShift is that missing converter. It reads how each Elementor page was built, recreates it using standard WordPress blocks, keeps the colors, fonts, and spacing, and saves it as a brand-new page. Your original Elementor page is left exactly as it is, so nothing breaks and you can compare the old and new versions side by side.

Once a page is made of standard blocks, it no longer needs Elementor to display. That means lighter pages that load faster, one less big plugin to maintain, and content you can edit with the tools already built into WordPress.

= Why move to the block editor? =

Page builders are convenient, but they add a lot of weight. Every builder page loads the builder's own code on top of WordPress, which slows the site down, and it locks your content into a format only that builder understands. Moving to the built-in block editor fixes both problems:

* **Faster pages.** Standard blocks output far less code, so pages load quicker for visitors and score better in speed tests.
* **No lock-in.** Your content lives in plain WordPress. It keeps working with any modern theme, and you're not tied to one builder or its yearly fee.
* **Easier editing.** The block editor is the same tool WordPress ships with, so anyone on your team can update a page without learning a builder first.
* **Future-ready.** Blocks are where WordPress is heading, including full-site editing for headers, footers, and templates.

= What we promise (and what we don't) =

We promise to do the heavy lifting. BlockShift reads your Elementor pages and rebuilds them as real blocks, carrying over the text, images, structure, and styling, so you skip the slow, error-prone job of recreating everything by hand.

What we don't promise is a pixel-perfect copy on every page with zero effort. Elementor and the block editor are built differently, so a small amount of review and touch-up is normal, especially on complex layouts. The honest goal is to turn days of rebuilding into a few clicks plus a short review, and to make that review as painless as possible (more on that below).

= About the results: why it isn't always 100% =

BlockShift gets most pages very close straight away. On busier, highly customised designs you may notice small differences in spacing, alignment, or a widget that has no exact block equivalent. This isn't a bug. It happens because the two systems handle layout and styling in different ways, so some things have to be approximated rather than copied one-to-one.

Rather than pretend that never happens, BlockShift is built to make the last mile quick:

* **A conversion report.** After each page, the Conversion Log lists exactly which elements converted cleanly and which few might want a look, so you're never hunting blind.
* **Everything is a normal block.** Because the result is standard WordPress content (not a locked builder format), you fix anything directly in the editor you already know. No shortcodes, no special mode.
* **AI clean-up on demand.** For pages you want to match as closely as possible, the AI features compare a picture of the old and new page and tighten the layout for you (see below).

In practice, most pages need only minor tweaks, and the tools above turn what used to be a rebuild into a few small edits.

= AI Enhancement (optional) =

After a page is converted, BlockShift can use AI to push it closer to the original design. It's completely optional and needs your own Anthropic (Claude) API key; the conversion itself works without it.

Here's how it works. From the AI Enhancement screen you pick a converted page (or several, to run in bulk). BlockShift captures a screenshot of the original Elementor page and the converted one, sends both to the AI to compare, and applies the layout and styling adjustments it suggests.

The best part is that you can run it as many times as you like. The first pass does a general clean-up. After that you can keep refining, adding a short instruction each time such as "make the hero heading bigger" or "tighten the spacing between the cards", until the page looks right. Every run takes fresh screenshots, so it always works from the page's current state.

One requirement to know: because the screenshots are taken from your live page by an outside service, the page has to be reachable from the public internet. In practice that means it should be published, not password-protected, not behind a "coming soon"/maintenance screen, and on a public site rather than a local address. A quick way to check is to open the page in a private browser window while logged out; if you can see it there, so can the screenshot service.

You bring the key, you decide which pages to run, and you review the result. Anthropic bills your own account for what you use. Full detail is in the External services section below.

= Current limitations =

We'd rather be upfront:

* **Some manual review is expected.** Complex pages usually need a few small tweaks after conversion.
* **Elementor Pro and third-party widgets** that have no block equivalent are converted with a safe fallback and flagged for you, rather than recreated exactly.
* **Dynamic and interactive extras** (advanced motion effects, some pop-ups, and similar builder-only features) may not carry across and can need rebuilding with block tools.
* **Global Elementor styles** (theme-wide colors and fonts set in Elementor) are matched as closely as possible but may need a final check against your theme.
* **The AI features are optional and require your own API key** and outbound internet access from your server.

None of these stop you migrating. They're the spots to look at during your review, and the Conversion Log points most of them out for you.

= In plain terms, BlockShift lets you =

* **Stop paying for and depending on a page builder.** Your pages keep working even after you switch Elementor off.
* **Speed up your site.** Pages built with standard blocks load lighter and faster because they don't have to load Elementor's extra code.
* **Keep editing easily.** Everything ends up in the normal WordPress editor, so you (or your clients) can make changes without learning a builder.
* **Save days of work.** Instead of rebuilding pages one element at a time, you convert them in a few clicks.

= What makes BlockShift different =

* **It actually converts — automatically.** Many "solutions" just embed Elementor inside the editor, or expect you to rebuild by hand. BlockShift genuinely rebuilds your pages as real blocks.
* **No extra plugins required.** Some converters make you install another block library (like Kadence) to work. BlockShift converts to standard WordPress blocks and its own built-in blocks — nothing else to install.
* **Handles more of your page.** Over 50 Elementor elements are supported, including WooCommerce shop elements. Most free converters cover around 20 basic ones.
* **Optional AI touch-up.** If you want a page to match the original as closely as possible, BlockShift can take a "before and after" picture of the page and use AI to tidy up the layout (you bring your own AI key — details below).
* **A clear report after every conversion.** The Conversion Log tells you exactly which elements converted perfectly and which few might need a quick manual tweak, so there are no surprises.
* **Safe by design.** Your original Elementor pages are never deleted or changed. You decide when — or whether — to switch your site over to the new versions.

= What can it convert? =

**Everyday content:** headings, text, images, image galleries and carousels, buttons, videos, icons, spacers, dividers, Google Maps, and shortcodes.

**Interactive elements:** accordions, toggles, tabs (including nested tabs and accordions), counters, and progress bars.

**Boxes and lists:** icon boxes, image boxes, icon lists, call-to-action boxes, testimonials and testimonial carousels, and social icons.

**Forms:** contact forms and search forms.

**Site parts:** navigation menus, the site logo, and post/blog listings.

**WooCommerce (online stores):** product grids, cart, checkout, mini-cart, product categories, store notices, "my account", and add-to-cart.

Anything BlockShift doesn't have an exact match for is converted safely with a general-purpose fallback, so the page always comes through in one piece — and the Conversion Log points out anything worth a second look.

= Who is it for? =

* **Agencies and freelancers** moving client sites off Elementor without hours of rebuilding.
* **Site owners** who want faster pages and fewer plugins.
* **Anyone** who wants to edit their site with the standard WordPress editor instead of a page builder.

== Installation ==

1. In your WordPress dashboard, go to **Plugins → Add New**, search for "BlockShift – Migrate from Elementor", and click **Install Now** — or upload the plugin ZIP under **Plugins → Add New → Upload Plugin**.
2. Click **Activate**.
3. A new **BlockShift – Migrate from Elementor** menu appears in your dashboard sidebar.
4. (Recommended) Open **BlockShift → Settings** first and set the page width to match your Elementor design, so converted pages look the same width as before.
5. Open **BlockShift → Conversion Wizard**, pick a page, and convert it.

**Before you start:** it's always wise to back up your site first, or try it on a test/staging copy. BlockShift never deletes your Elementor pages, but a backup is good practice for any big change.

== Frequently Asked Questions ==

= Do I need to know any code to use this? =

No. You pick the pages you want and click a button. Everything happens inside your normal WordPress dashboard.

= Does Elementor need to stay installed after I convert? =

No. Elementor only needs to have been used to build the pages in the first place. Once a page is converted to blocks, it no longer needs Elementor — you can switch Elementor off (once you're happy with the results).

= Will it delete or change my original Elementor pages? =

No. BlockShift always creates a brand-new page for the converted version and leaves your original untouched. You can review the new version and only switch it live when you're ready.

= Will the converted page look exactly the same? =

It gets you very close automatically — colors, fonts, spacing, and layout are all carried over. Because Elementor and the WordPress editor work differently under the hood, very complex pages may need a few small manual tweaks. The optional AI touch-up and the Conversion Log make those quick to spot and fix.

= Do I need to install any other plugin for it to work? =

No. Some other converters require you to install an extra block library (such as Kadence Blocks). BlockShift doesn't — it uses the blocks already in WordPress plus its own built-in ones.

= Does it work with WooCommerce stores? =

Yes. BlockShift converts common WooCommerce elements (product grids, cart, checkout, mini-cart, categories, notices, my-account, and add-to-cart) into their WooCommerce block versions.

= What happens to elements it doesn't recognise? =

They're converted with a safe general-purpose fallback so the page still comes through cleanly, and the Conversion Log flags them so you know if any need a manual touch-up.

= Do I need to pay for anything or get an "API key"? =

No — the conversion itself is completely free and runs on your own site. The only feature that needs an API key is the *optional* AI touch-up, which uses the Anthropic (Claude) service. If you don't want to use AI, you can ignore that entirely.

= How much does the AI touch-up cost? =

The plugin doesn't charge for it. If you choose to use it, you provide your own Anthropic account, and Anthropic bills you directly based on how much you use it. It's completely optional.

= Can I run the AI enhancement more than once on the same page? =

Yes, as often as you like. The first pass does a general clean-up. After that you can keep refining, adding a short instruction each time (like "make the heading bigger" or "fix the spacing on the pricing section"), until you're happy. Each run takes fresh screenshots and works from the page's current state. The only cost is your own Anthropic usage.

= Can I run the AI on several pages at once? =

Yes. On the AI Enhancement screen you can select multiple converted pages and enhance them in bulk, instead of one at a time.

= The AI enhancement says my page can't be reached. What do I do? =

The screenshots are taken from your live page by an outside service, so the page must be viewable on the public internet. Check that it's published, has no password, and isn't hidden behind a "coming soon" or maintenance screen or a login. Also note that local sites (localhost, 127.0.0.1, private IPs, or .local/.test addresses, such as a XAMPP setup) can't be reached from outside, so run the AI on a live or staging site. The quickest test is to open the page in a private browser window while logged out.

= Why isn't the converted page 100% identical? =

Because Elementor and the block editor build pages differently, some styling has to be approximated rather than copied exactly, mostly on complex layouts. Most pages come out very close, and the Conversion Log, the normal block editor, and the optional AI clean-up make the remaining tweaks quick. See the "About the results" section above.

= Can I convert my whole site at once? =

You can convert page after page using the Conversion Wizard. We recommend doing them in small batches and checking each one, rather than converting everything blindly — that way you catch any tweaks early.

= Will it work with my theme? =

Yes. Converted pages use standard WordPress blocks, so they work with any modern block-friendly theme. The wizard also shows some basic theme information to help set expectations.

= Is any of my data sent to outside services? =

The conversion itself runs entirely on your own site. A few optional features do connect to outside services (the AI touch-up, the screenshots it uses, address suggestions when editing a Map, and any Google fonts/maps/videos your page already used). Each one is explained in full in the **External services** section below, and the AI and feedback features only ever run when you choose to start them.

== External services ==

This plugin can connect to the third-party services listed below. The first three are only contacted from the admin area, as part of actions you explicitly start (running an AI improvement pass, or submitting feedback). The last three load only when a converted page that uses those features is viewed, or while editing a Map block.

= Anthropic Claude API =

Used for the optional "AI-powered improvement" feature, which refines the layout and styling of a converted page. It runs only when you enter your own Anthropic API key in the plugin settings and start an improvement pass.

When a pass runs, the plugin sends to https://api.anthropic.com/v1/messages: your Anthropic API key, the converted page's content/markup, the text instructions (prompts), and the URLs of the screenshots described below (so the model can compare the original and converted designs). Nothing is sent if no API key is configured.

This service is provided by Anthropic, PBC. Terms: https://www.anthropic.com/legal/commercial-terms — Privacy policy: https://www.anthropic.com/legal/privacy

= Screenshot service =

Used to capture screenshots of the original Elementor page and the converted Gutenberg page so the AI improvement feature can compare them visually.

When you run an AI improvement pass, the plugin sends the public URL of the page being captured and a device flag ("desktop" or "mobile") to https://webshot.lvendr.com. The service returns image URLs, which are then referenced in the request to the Anthropic API above.

This service is operated by Progressus. Privacy policy: https://progressus.io/privacy-policy

= Feedback service =

Used only if you choose to send feedback about a conversion to the plugin developer. It is never sent automatically; submission requires your explicit consent on the feedback screen.

When you submit feedback, the plugin sends a feedback package to https://etgm.lvendr.com/wp-json/metg-feedback/v1/submit, authenticated with a per-site client ID/secret generated and stored on your site. The package contains: environment data (site domain, a hashed site URL, plugin/WordPress/PHP versions, active theme, locale), your browser/screen details, the conversion run data and summary, your rating and notes, and the converted content and artifacts (such as screenshots) for the pages you select.

This service is operated by Progressus. Privacy policy: https://progressus.io/privacy-policy

= Google Fonts =

Converted pages may reuse web fonts from the original Elementor design. When such a page is viewed, the visitor's browser loads those fonts from Google Fonts (https://fonts.googleapis.com and https://fonts.gstatic.com).

This service is provided by Google. Terms: https://policies.google.com/terms — Privacy policy: https://policies.google.com/privacy

= Embedded maps and videos =

If a converted page contains a map or video, the page embeds it from Google Maps (https://maps.google.com) or YouTube (https://www.youtube.com), the same way the original Elementor page did. These embeds load only when such a page is viewed.

Provided by Google. Terms: https://policies.google.com/terms and https://www.youtube.com/t/terms — Privacy policy: https://policies.google.com/privacy

= OpenStreetMap (Nominatim) =

Used for address autocomplete when you edit a Map block in the block editor.

While editing a Map block, as you type an address (3 or more characters), the plugin sends that address text to the OpenStreetMap Nominatim geocoding service (https://nominatim.openstreetmap.org) and displays matching location suggestions. This happens only in the admin editor, never on the public-facing site.

This service is provided by the OpenStreetMap Foundation. Usage policy: https://operations.osmfoundation.org/policies/nominatim/ — Privacy policy: https://wiki.osmfoundation.org/wiki/Privacy_Policy

== Screenshots ==

1. The Conversion Wizard — pick your Elementor pages and convert them to blocks.
2. Conversion results, with a summary for each page and the option to review or improve it.
3. The AI touch-up view — your original Elementor page next to the converted version.
4. The Conversion Log — see which elements converted perfectly and which need a quick look.
5. Settings — page width, conversion defaults, logging, and the optional AI key.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
