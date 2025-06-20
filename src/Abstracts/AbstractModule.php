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

	/**
	 * Setup
	 * @return void
	 */
	public function __construct() {
		$this->id = $this->id();

		add_filter( 'wpify_get_sections_' . $this->plugin_slug(), array( $this, 'add_settings_section' ) );
		add_filter( 'wpify_admin_menu_bar_data', array( $this, 'add_admin_menu_bar_data' ) );

		if ( is_admin() && defined( 'ICL_LANGUAGE_CODE' ) && false === get_option( $this->get_option_key() ) ) {
			$default_lang = apply_filters( 'wpml_default_language', null );
			if ( ICL_LANGUAGE_CODE !== $default_lang ) {
				add_filter( 'default_option_' . $this->get_option_key(), function () {
					return get_option( $this->get_option_key( true ), array() );
				} );
			}
		}
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
	 * Module documentation url
	 * @return string
	 */
	public function get_documentation_url() {
		return '';
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
		);

		return $sections;
	}

	/**
	 * @param $id
	 *
	 * @return mixed|null
	 */
	public function get_setting( $id ) {
		$setting = isset( $this->get_settings()[ $id ] ) ? $this->get_settings()[ $id ] : null;
		$setting = apply_filters( 'wpify_woo_setting', $setting, $id, $this->id() );

		return apply_filters( "wpify_woo_setting_{$id}", $setting, $id, $this->id() );
	}

	/**
	 * Get module settings
	 * @return array
	 */
	public function get_settings(): array {
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$default_lang = apply_filters( 'wpml_default_language', null );
			if ( $default_lang !== ICL_LANGUAGE_CODE && get_option( $this->get_option_key() ) === false ) {
				// Fallback to default language settings if the translated option does not exist at all.
				$default = get_option( $this->get_option_key( true ) );

				return is_array( $default ) ? $default : array();
			}
		}

		$settings = get_option( $this->get_option_key() );

		return is_array( $settings ) ? $settings : array();
	}

	public function get_option_key( $raw = false ) {
		$key = \sprintf( '%s-%s', Settings::OPTION_NAME, $this->id() );
		if ( $raw ) {
			return $key;
		}
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$default_lang = apply_filters( 'wpml_default_language', null );
			if ( $default_lang !== ICL_LANGUAGE_CODE ) {
				$key = sprintf( '%s_%s', $key, ICL_LANGUAGE_CODE );
			}
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
		foreach ( $this->settings() as $setting ) {
			if ( ! empty( $setting['type'] ) && 'license' === $setting['type'] ) {
				return true;
			}
		}

		return false;
	}

	public function is_settings_page() {
		$page = $_GET['page'] ?? '';
		if ( str_contains( $page, 'wpify/' ) ) {
			$section = explode( '/', $page )[1] ?? '';
			if ( $section === $this->id() ) {
				return true;
			}
		}

		$option_name = sprintf( '%s-%s', Settings::OPTION_NAME, $this->id() );
		if ( isset( $_POST[ $option_name ] ) ) {
			return true;
		}

		// Load items only in admin (for settings pages) or rest (for async lists)
		return ( wp_is_json_request() || is_admin() ) && ! empty( $_GET['section'] ) && $_GET['section'] === $this->id();
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
