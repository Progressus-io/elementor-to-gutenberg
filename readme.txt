=== BlockShift – Migrate from Elementor ===
Contributors: shadim
Tags: elementor, gutenberg, migration, conversion, blocks
Requires at least: 6.7
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional migration tool to convert Elementor layouts into native Gutenberg blocks.

== Description ==

BlockShift – Migrate from Elementor is a professional migration tool that converts your Elementor-built pages into native WordPress Gutenberg blocks. It supports batch conversion, AI-powered improvements, and detailed conversion logging.

= Features =

* Batch conversion of Elementor pages to Gutenberg blocks
* AI-powered enhancement of converted pages
* Support for common Elementor widgets (heading, text, image, button, video, icon, and more)
* Conversion logging per widget
* Admin wizard for guided batch migrations

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **BlockShift – Migrate from Elementor** in the admin menu to begin migration.

== Frequently Asked Questions ==

= Does this plugin require Elementor to be active? =

Elementor must have been used to build the pages you want to convert. It does not need to remain active after conversion.

= Is the original content preserved? =

The plugin creates new converted pages/posts. Your original Elementor content is not deleted.

== External services ==

This plugin can connect to the third-party services listed below. The first three are only contacted from the admin area, as part of actions you explicitly start (running an AI improvement pass, or submitting feedback). The last two load only when a converted page that uses those features is viewed.

= Anthropic Claude API =

Used for the optional "AI-powered improvement" feature, which refines the layout and styling of a converted page. It runs only when you enter your own Anthropic API key in the plugin settings and start an improvement pass.

When a pass runs, the plugin sends to https://api.anthropic.com/v1/messages: your Anthropic API key, the converted page's content/markup, the text instructions (prompts), and the URLs of the screenshots described below (so the model can compare the original and converted designs). Nothing is sent if no API key is configured.

This service is provided by Anthropic, PBC. Terms: https://www.anthropic.com/legal/commercial-terms — Privacy policy: https://www.anthropic.com/legal/privacy

= Screenshot service =

Used to capture screenshots of the original Elementor page and the converted Gutenberg page so the AI improvement feature can compare them visually.

When you run an AI improvement pass, the plugin sends the public URL of the page being captured and a device flag ("desktop" or "mobile") to https://webshot.lvendr.com. The service returns image URLs, which are then referenced in the request to the Anthropic API above.

This service is operated by Progressus. Terms: https://progressus.io/terms — Privacy policy: https://progressus.io/privacy

= Feedback service =

Used only if you choose to send feedback about a conversion to the plugin developer. It is never sent automatically; submission requires your explicit consent on the feedback screen.

When you submit feedback, the plugin sends a feedback package to https://etgm.lvendr.com/wp-json/metg-feedback/v1/submit, authenticated with a per-site client ID/secret generated and stored on your site. The package contains: environment data (site domain, a hashed site URL, plugin/WordPress/PHP versions, active theme, locale), your browser/screen details, the conversion run data and summary, your rating and notes, and the converted content and artifacts (such as screenshots) for the pages you select.

This service is operated by Progressus. Terms: https://progressus.io/terms — Privacy policy: https://progressus.io/privacy

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

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
