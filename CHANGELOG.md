# Changelog

All notable changes to this package are documented here.

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
