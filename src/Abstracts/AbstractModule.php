<?php

namespace Wpify\WooCore\Abstracts;

use Wpify\License\License;
use Wpify\WooCore\Admin\Settings;

/**
 * Class AbstractModule
 * @package WpifyWoo\Abstracts
 */
abstract class AbstractModule {
	/** @var string $id */
	private $id = '';

	private $license = null;
	private ?array $settings_cache = null;
	private array $option_key_cache = [];
	private ?bool $requires_activation_cache = null;

	/**
	 * Setup
	 * @return void
	 */
	public function __construct() {
		$this->id = $this->id();

		add_filter( 'wpify_get_sections_' . $this->plugin_slug(), array( $this, 'add_settings_section' ) );
		add_filter( 'wpify_admin_menu_bar_data', array( $this, 'add_admin_menu_bar_data' ) );

		// On 'init' the language is known: Polylang defines it only in 'setup_theme'.
		$language_fallback = function () {
			if ( is_admin() && $this->has_language_settings() && Settings::get_settings_language() && ! Settings::option_exists( $this->get_option_key() ) ) {
				// After the default of the registered setting (priority 10), so the form shows the main settings.
				add_filter( 'default_option_' . $this->get_option_key(), function () {
					return get_option( $this->get_option_key( true ), array() );
				}, 20 );
			}
		};
		did_action( 'init' ) ? $language_fallback() : add_action( 'init', $language_fallback );
		add_action( 'admin_init', function () {
			if ( $this->requires_activation() && $this->is_settings_page() ) {
				$this->license = new License( $this->plugin_slug(), true, is_multisite() ? get_current_network_id() : 0 );
			}
		} );
	}

	/**
	 * Module ID - use underscores
	 * @return mixed
	 */
	abstract public function id();

	/**
	 * Plugin slug, needed for license activation.
	 * @return string
	 */
	abstract public function plugin_slug(): string;

	/**
	 * Module name
	 * @return mixed
	 */
	abstract public function name();

	/**
	 * Get module ID
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Parent settings page ID for sections
	 * @return string
	 */
	public function parent_settings_id(): string {
		return $this->plugin_slug();
	}

	/**
	 * Menu slug for settings page url
	 * @return string
	 */
	public function get_menu_slug() {
		return sprintf( 'wpify/%s', $this->id );
	}

	/**
	 * Module settings full url
	 * @return string
	 */
	public function get_settings_url() {
		return add_query_arg( array( 'page' => $this->get_menu_slug() ), admin_url( 'admin.php' ) );
	}

	/**
	 * Module documentation path
	 * @return string
	 */
	public function get_documentation_path(): string {
		return '';
	}

	/**
	 * Module documentation url
	 * @return string
	 */
	public function get_documentation_url(): string {
		$path = $this->get_documentation_path();

		// Pokud modul nemá vlastní path, fallback na plugin URL
		if ( $path === '' ) {
			return apply_filters( 'wpify_woo_plugin_documentation_url_' . $this->plugin_slug(), '' );
		}

		$domain = 'https://docs.wpify.cz/';
		if ( in_array( determine_locale(), array( 'cs_CZ', 'sk_SK' ), true ) ) {
			$domain = 'https://docs.wpify.cz/cs/';
		}

		return esc_url( $domain . $path );
	}

	/**
	 * Display module in admin menu bar
	 * @return bool
	 */
	public function display_in_menubar(): bool {
		return true;
	}

	/**
	 * Add module section into settings
	 *
	 * @param $sections
	 *
	 * @return array
	 */
	public function add_settings_section( $sections ) {
		$sections[ $this->id() ] = array(
			'title'       => $this->name(),
			'parent'      => $this->parent_settings_id(),
			'menu_slug'   => $this->get_menu_slug(),
			'url'         => $this->get_settings_url(),
			'option_id'   => $this->id(),
			'option_name' => $this->get_option_key(),
			'tabs'        => $this->settings_tabs(),
			'settings'    => $this->settings(),
			'in_menubar'  => $this->display_in_menubar(),
			'language_settings' => $this->has_language_settings(),
		);

		return $sections;
	}

	/**
	 * @param $id
	 *
	 * @return mixed|null
	 */
	public function get_setting( $id ) {
		$settings = $this->get_settings();
		$setting  = $settings[ $id ] ?? null;
		$setting = apply_filters( 'wpify_woo_setting', $setting, $id, $this->id() );

		return apply_filters( "wpify_woo_setting_{$id}", $setting, $id, $this->id() );
	}

	/**
	 * Get module settings
	 * @return array
	 */
	public function get_settings(): array {
		if ( $this->settings_cache !== null ) {
			return $this->settings_cache;
		}

		if ( $this->has_language_settings() && Settings::get_settings_language() && ! Settings::option_exists( $this->get_option_key() ) ) {
			// Fallback to default language settings if the translated option does not exist at all.
			$default = get_option( $this->get_option_key( true ) );

			return $this->cache_settings( is_array( $default ) ? $default : array() );
		}

		$settings = get_option( $this->get_option_key() );

		return $this->cache_settings( is_array( $settings ) ? $settings : array() );
	}

	/**
	 * Whether the settings have their own copy per language (WPML / Polylang). A module that handles
	 * languages itself (e.g. rules or feeds per language) returns false and keeps one set of settings
	 * for all languages.
	 *
	 * @return bool
	 */
	public function has_language_settings(): bool {
		return true;
	}

	/**
	 * Settings read before the multilingual plugin knows the language (Polylang: 'setup_theme') may belong
	 * to a different language, so they are cached only afterwards.
	 *
	 * @param array $settings Module settings.
	 *
	 * @return array
	 */
	private function cache_settings( array $settings ): array {
		if ( did_action( 'setup_theme' ) ) {
			$this->settings_cache = $settings;
		}

		return $settings;
	}

	public function get_option_key( $raw = false ) {
		$cache_key = $raw ? 'raw' : 'localized';
		if ( isset( $this->option_key_cache[ $cache_key ] ) ) {
			return $this->option_key_cache[ $cache_key ];
		}

		$key = \sprintf( '%s-%s', Settings::OPTION_NAME, $this->id() );
		if ( $raw ) {
			$this->option_key_cache[ $cache_key ] = $key;

			return $this->option_key_cache[ $cache_key ];
		}
		$language = $this->has_language_settings() ? Settings::get_settings_language() : '';
		if ( $language ) {
			$key = sprintf( '%s_%s', $key, $language );
		}

		// Polylang defines the language only in 'setup_theme'; an earlier key is not final.
		if ( did_action( 'setup_theme' ) ) {
			$this->option_key_cache[ $cache_key ] = $key;
		}

		return $key;
	}

	/**
	 * Module Settings tabs
	 * @return array Settings tabs.
	 */
	public function settings_tabs(): array {
		return array();
	}

	/**
	 * Module Settings
	 * @return array Settings.
	 */
	public function settings(): array {
		return array();
	}

	public function requires_activation() {
		if ( $this->requires_activation_cache !== null ) {
			return $this->requires_activation_cache;
		}

		foreach ( $this->settings() as $setting ) {
			if ( ! empty( $setting['type'] ) && 'license' === $setting['type'] ) {
				$this->requires_activation_cache = true;

				return $this->requires_activation_cache;
			}
		}

		$this->requires_activation_cache = false;

		return $this->requires_activation_cache;
	}

	public function is_settings_page() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( str_contains( $page, 'wpify/' ) ) {
			$section = explode( '/', $page )[1] ?? '';
			if ( $section === $this->id() ) {
				return true;
			}
		}

		$option_name = sprintf( '%s-%s', Settings::OPTION_NAME, $this->id() );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is a simple presence check, not processing data
		if ( isset( $_POST[ $option_name ] ) ) {
			return true;
		}

		// Load items only in admin (for settings pages) or rest (for async lists)
		$section_param = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		if ( ( wp_is_json_request() || is_admin() ) && ! empty( $section_param ) && $section_param === $this->id() ) {
			return true;
		}

		return apply_filters( 'wpify_woo_is_settings_page', false, $this->id(), $page, $this->plugin_slug() );
	}

	public function is_enabled() {
	}

	public function is_activated() {
		return $this->license ? $this->license->is_activated() : true;
	}

	public function get_license() {
		return $this->license;
	}

	public function add_admin_menu_bar_data( $data ) {
		if ( ! $this->is_settings_page() ) {
			return $data;
		}

		$allow_menu_bar_item = apply_filters(
			'wpify_woo_allow_module_menu_bar_item',
			true,
			$this->plugin_slug(),
			$this->id(),
			$data
		);
		if ( ! $allow_menu_bar_item ) {
			return $data;
		}

		$data['parent']   = $this->parent_settings_id();
		$data['plugin']   = $this->plugin_slug();
		$data['menu'][]   = array(
			'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 5h-3m-4.25-2v4M13 5H3m4 7H3m7.75-2v4M21 12H11m10 7h-3m-4.25-2v4M13 19H3"/></svg>',
			'label' => __( 'Settings', 'wpify-core' ),
			'link'  => $this->get_settings_url()
		);
		$data['doc_link'] = $this->get_documentation_url();

		return $data;
	}
}
