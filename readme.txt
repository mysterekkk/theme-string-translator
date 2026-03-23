=== Theme String Translator ===
Contributors: luroweb
Tags: translation, i18n, theme, localization, woocommerce
Requires at least: 5.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Translate your WordPress theme strings without WPML. Lightweight scanner, inline editor, PO/MO/JSON export.

== Description ==

Theme String Translator is a lightweight WordPress plugin that scans your active theme for translatable strings and provides a simple interface to translate them — without needing WPML, Polylang, or any heavy multilingual framework.

**Key Features:**

* **Auto-scan** your theme for all translatable strings (__(), _e(), esc_html__(), etc.)
* **Detect hardcoded** text in templates
* **Support for get_theme_mod()** default values
* **ACF field** label translation (if ACF is active)
* **WooCommerce template** string detection
* **Inline translation editor** in WordPress admin
* **Export** to .po, .mo, JSON, or PHP array
* **Import** from .po or JSON files
* **Runtime translation** via gettext filters
* **Auto-updates** from GitHub releases

**Built by [LuroWeb](https://luroweb.pl)**

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to Theme Translator in the admin menu
4. Click "Scan Theme" to detect all strings
5. Select target languages and start translating

== Frequently Asked Questions ==

= Does this replace WPML? =
No. This plugin focuses specifically on theme UI string translation, not full multilingual content management. For translating posts and pages, use Polylang or WPML.

= Does it work with child themes? =
Yes, it scans the active theme (child theme if active).

= Does it work with WooCommerce? =
Yes, it detects strings in WooCommerce template overrides within your theme.

= Will translations survive theme updates? =
Translations are stored in the database. Exported .po/.mo files should be placed in a location that persists across updates.

== Screenshots ==

1. Dashboard showing scan statistics
2. String list with inline translation editor
3. Export options (PO, MO, JSON, PHP)

== Changelog ==

= 1.0.0 =
* Initial release
* Theme scanner with regex-based string detection
* Inline translation editor
* PO/MO/JSON/PHP export
* PO/JSON import
* Runtime gettext filter translation
* GitHub auto-updater
* ACF and WooCommerce support
