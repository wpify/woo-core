# Changelog

All notable changes to this package are documented here.

## [5.8.0]

### Added
- Custom Fields show a loading skeleton until their fields render, on every admin screen with WPify fields (settings, CPT editors, product tabs, terms).
- Product tabs: Custom Fields no longer touch the panel edges, and WooCommerce's fixed label width, half-width floated inputs and short floated textareas (description beside the field) no longer squeeze them.
- Outside the WPify screens the Custom Fields select and the multi-group item header match the native WordPress fields (border, height, arrow, focus; tinted header).
- `AbstractModule::has_language_settings()`: a module that handles languages itself returns false and keeps one set of settings for all languages (no per-language copy, no language notice).
- Settings pages on multilingual sites say whose settings are being edited: the main settings (only when some languages have their own) or one language only, with a link back to the main settings and a button to delete the settings of that language (or an unused `_all` copy) for good.
- Support page FAQ answers the common plugin questions from support: where the license key is, moving the license to a new domain, why an update is not available, settings in another language. The pricing question was removed (it belongs to the purchase terms).
- Polish, German, Hungarian and Romanian translations (alongside Czech and Slovak).
- A locale without its own translation uses another locale of the same language, e.g. de_AT, de_CH and de_DE_formal use the German one.

### Changed
- Softer tinted backgrounds of notices, sections and the license field (the `--wpify-core-*-soft` tokens).
- Small buttons (`.button-small`) on the WPify screens use 12px instead of WordPress's 11px.

### Fixed
- Multilingual sites (WPML, Polylang): "All languages" in the admin language switcher edits the main settings, the same as the default language. Before, WPML saved them under an `_all` copy that nothing used.
- Polylang: the settings of a language are edited and saved for that language in the admin too (the language was not known yet when the settings were read).
- A language without settings of its own shows the main settings in the form, so saving there no longer stores empty values.

## [5.7.1]

### Fixed
- WPify dashboard shows a plugin whose license WPify reports as not valid as unlicensed ("The license is not valid.") instead of active, and offers no update link for it.

## [5.7.0]

### Added
- Filters `wpify_woo_menu_bar_should_render` (show the WPify header on a plugin's own screens, e.g. CPT list and editor) and `wpify_admin_menu_bar_sections` (add header tabs that are not settings pages).
- `assets/components.css` (handle `wpify-core-components`): design tokens and shared components — card, section, repeater, connector/operator, accordion (also on `<details>`), tooltip, badge, notice, icon button, spinner, empty state, compact toggle. Loads automatically on WPify screens; elsewhere (e.g. orders) via `Admin\MenuBar::enqueue_components_style()`. Markup: `docs-internal/admin-components.md`.
- `.wpify-input-quiet` to opt a field or a wrapper out of the blanket input styling.

### Changed
- Unified look of WPify screens (`admin.css`): list tables, post editor (tabs on top, sticky sidebar), section titles, field heights; better layout on phones (header, license field, module cards, Custom Fields columns).
- The header, `admin.css` and thickbox load only on WPify screens (`MenuBar::should_render()`). Plugins that enqueue `admin.css` themselves should drop it.
- Plugin list, news and update data from wpify.cz are requested with the admin's language (`locale`) and cached per language; documentation links follow the admin's language too.
- Support page leads through the checklist, FAQ and plugin documentation before the form; logs are attached by count (latest 1 / 3 / all) instead of picking files.

### Fixed
- WPify dashboard no longer hangs when wpify.cz is unreachable.
- Escaped remote data (plugin list, news) on the WPify dashboard.
- Support form attachments keep their file names and extensions.
- Core strings follow the user's admin language.
- `AbstractPlugin::get_license()` returns `null` instead of throwing for plugins without activation.
- Redirect after activating/deactivating a plugin is stored per user and ignores AJAX requests.
- Multisite: no "Deactivate" for network-activated plugins.
- Declared the `wpify/plugin-utils` and `wpify/asset` dependencies.
- `admin.css` rules on native WordPress markup are scoped to WPify screens.

## [5.6.2]

### Changed
- Version bump only (no functional change). Lets a dependent plugin that bundles a newer WPify Custom Fields take precedence as the active settings renderer via the existing "highest woo-core version wins" election in `Admin\Settings`. No code changes.

## [5.6.1]

- Previous release.
