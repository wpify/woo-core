<?php

namespace Wpify\WooCore\Abstracts;

use Wpify\License\License;
use Wpify\WooCore\Admin\Settings;
use Wpify\WooCore\WooCommerceIntegration;

/**
 * Class AbstractModule
 * @package WpifyWoo\Abstracts
 */
abstract class AbstractModule {
	protected $requires_activation = false;
	protected int $settings_version = 1;

	/** @var string $id */
	private $id = '';

	/** @var WooCommerceIntegration */
	private $woocommerce_integration;

	private $license = null;

	/**
	 * Setup
	 * @return void
	 */
	public function __construct( WooCommerceIntegration $woocommerce_integration ) {
		$this->woocommerce_integration = $woocommerce_integration;
		$this->id                      = $this->id();
		add_filter( 'woocommerce_get_sections_' . Settings::OPTION_NAME, array( $this, 'add_settings_section' ) );
		if ( is_admin() && defined( 'WpifyWooFakturoidDeps\ICL_LANGUAGE_CODE' ) && \false === get_option( $this->get_option_key() ) ) {
			$default_lang = apply_filters( 'wpml_default_language', null );
			if ( ICL_LANGUAGE_CODE !== $default_lang ) {
				add_filter( 'default_option_' . $this->get_option_key(), function () {
					return get_option( $this->get_option_key( \true ), array() );
				} );
			}
		}
		if ( $this->requires_activation ) {
			$enqueue       = isset( $_GET['section'] ) && $_GET['section'] === $this->id();
			$this->license = new License( $this->plugin_slug(), $enqueue, is_multisite() ? get_current_network_id() : 0 );
			if ( ! $this->license->is_activated() ) {
				add_action( 'admin_notices', array( $this, 'activation_notice' ) );
			}
		}
		if ( $this->settings_version === 2 ) {
			//add_action( 'admin_menu', [ $this, 'register_menu_page' ], 20 );
			add_filter( 'wpify_admin_menu_bar_data', array( $this, 'add_admin_menu_bar_data' ) );
		}
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

	public function add_settings_section( $tabs ) {
		$tabs[ $this->id() ] = $this->name();

		return $tabs;
	}

	/**
	 * Module name
	 * @return mixed
	 */
	abstract public function name();

	/**
	 * Check if the module is enabled.
	 * @return bool
	 */
	public function is_module_enabled(): bool {
		return in_array( $this->get_id(), $this->woocommerce_integration->get_modules(), \true );
	}

	/**
	 * Get module ID
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
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
		if ( defined( 'WpifyWooFakturoidDeps\ICL_LANGUAGE_CODE' ) ) {
			$default_lang = apply_filters( 'wpml_default_language', null );
			if ( $default_lang !== ICL_LANGUAGE_CODE && get_option( $this->get_option_key() ) === \false ) {
				// Fallback to default language settings if the translated option does not exist at all.
				return get_option( $this->get_option_key( \true ), array() );
			}
		}

		return get_option( $this->get_option_key(), array() );
	}

	public function get_option_key( $raw = \false ) {
		$key = \sprintf( '%s-%s', Settings::OPTION_NAME, $this->id() );
		if ( $raw ) {
			return $key;
		}
		if ( defined( 'WpifyWooFakturoidDeps\ICL_LANGUAGE_CODE' ) ) {
			$default_lang = apply_filters( 'wpml_default_language', null );
			if ( $default_lang !== ICL_LANGUAGE_CODE ) {
				$key = sprintf( '%s_%s', $key, ICL_LANGUAGE_CODE );
			}
		}

		return $key;
	}

	/**
	 * Module Settings
	 * @return array Settings.
	 */
	public function settings(): array {
		return array();
	}

	public function needs_activation() {
		foreach ( $this->settings() as $setting ) {
			if ( ! empty( $setting['type'] ) && 'license' === $setting['type'] ) {
				return \true;
			}
		}

		return \false;
	}

	/**
	 * Add activation notice if the license s not active yet.
	 */
	public function activation_notice() {
		?>
        <div class="error notice">
            <p><?php
				printf( __( 'Your %1$s plugin licence is not activated yet. Please <a href="%2$s">activate the domain</a> by connecting it with your WPify account!', 'wpify-woo' ), $this->name(), admin_url( 'admin.php?page=wc-settings&tab=wpify-woo-settings&section=' . $this->get_id() ) );
				?></p>
        </div>
		<?php
	}

	public function get_settings_url() {
		if ( $this->settings_version === 2 ) {
			return add_query_arg( [ 'page' => sprintf( 'wpify/%s', $this->id ) ], admin_url( 'admin.php' ) );
		}

		return add_query_arg( [ 'section' => $this->id() ], admin_url( 'admin.php?page=wc-settings&tab=wpify-woo-settings' ) );
	}

	public function get_documentation_url() {
		return '';
	}

	public function is_settings_page() {
		$page = $_GET['page'] ?? '';
		if ( \str_contains( $page, 'wpify/' ) ) {
			$section = explode( '/', $page )[1] ?? '';
			if ( $section === $this->id() ) {
				return \true;
			}
		}

		// Load items only in admin (for settings pages) or rest (for async lists)
		return ( wp_is_json_request() || is_admin() ) && ! empty( $_GET['section'] ) && $_GET['section'] === $this->id();
	}

	public function is_enabled() {
	}

	public function requires_activation() {
		return $this->requires_activation;
	}

	public function is_activated() {
		return $this->license ? $this->license->is_activated() : \true;
	}

	public function get_license() {
		return $this->license;
	}

	public function register_menu_page() {
		if ( did_action( 'wpify_woo_settings_menu_page_registered' ) ) {
			// Register the submenu page.
			\add_submenu_page(
				'wpify',
				$this->name(),
				$this->name(),
				'manage_options',
				sprintf( 'wpify/%s', $this->id ),
				array( $this, 'render_settings_page' )
			);
		}
	}

	public function get_settings_version(): int {
		return $this->settings_version;
	}

	public function add_admin_menu_bar_data( $data ) {
		if ( ! $this->is_settings_page() ) {
			return $data;
		}

		$data['title']    = $this->name();
		$data['icon']     = '';
		$data['menu'][]   = array(
			'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 5h-3m-4.25-2v4M13 5H3m4 7H3m7.75-2v4M21 12H11m10 7h-3m-4.25-2v4M13 19H3"/></svg>',
			'label' => __( 'Settings', 'wpify' ),
			'link'  => $this->get_settings_url(),
		);
		$data['doc_link'] = $this->get_documentation_url();

		return $data;
	}
}
