<?php

namespace Wpify\WooCore\Abstracts;

use Wpify\License\License;
use Wpify\WooCore\Admin\Settings;
use Wpify\WooCore\WooCommerceIntegration;

/**
 * Class AbstractModule
 *
 * @package WpifyWoo\Abstracts
 */
abstract class AbstractModule {
	private $settings;
	protected $requires_activation = false;

	/** @var string $id */
	private $id = '';

	/** @var WooCommerceIntegration */
	private $woocommerce_integration;

	private $license = null;

	/**
	 * Setup
	 *
	 * @return void
	 */
	public function __construct( WooCommerceIntegration $woocommerce_integration ) {
		$this->woocommerce_integration = $woocommerce_integration;
		$this->id                      = $this->id();

		add_filter(
			'woocommerce_get_sections_' . Settings::OPTION_NAME,
			array(
				$this,
				'add_settings_section',
			)
		);

		if ( $this->requires_activation ) {
			$enqueue       = isset( $_GET['section'] ) && $_GET['section'] === $this->id();
			$this->license = new License( $this->id(), sprintf( '%s-%s', Settings::OPTION_NAME, $this->id() ), $enqueue );
			if ( ! $this->license->is_activated() ) {
				add_action( 'admin_notices', array( $this, 'activation_notice' ) );
			}
		}
	}

	/**
	 * Module ID - use underscores
	 *
	 * @return mixed
	 */
	abstract public function id();

	public function add_settings_section( $tabs ) {
		$tabs[ $this->id() ] = $this->name();

		return $tabs;
	}

	/**
	 * Module name
	 *
	 * @return mixed
	 */
	abstract public function name();

	/**
	 * Check if the module is enabled.
	 *
	 * @return bool
	 */
	public function is_module_enabled(): bool {
		return in_array( $this->get_id(), $this->woocommerce_integration->get_modules(), true );
	}

	/**
	 * Get module ID
	 *
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
		$setting = isset( $this->get_settings()[ $id ] ) ? $this->get_settings()[ $id ] : null;;
		$setting = apply_filters( 'wpify_woo_setting', $setting, $id, $this->id() );

		return apply_filters( "wpify_woo_setting_{$id}", $setting, $id, $this->id() );
	}

	/**
	 * Get module settings
	 *
	 * @return array
	 */
	public function get_settings(): array {
		if ( empty( $this->settings ) ) {
			$this->settings = get_option( $this->get_option_key(), array() );

			foreach ( $this->settings() as $key => $item ) {
				if ( isset( $item['id'] ) && ! isset( $settings[ $item['id'] ] ) ) {
					$this->settings[ $item['id'] ] = isset( $item['default_value'] ) ? $item['default_value'] : '';
				}
			}
		}


		return $this->settings;
	}

	public function get_option_key() {
		return sprintf( '%s-%s', Settings::OPTION_NAME, $this->id() );
	}

	/**
	 * Module Settings
	 *
	 * @return array Settings.
	 */
	public function settings(): array {
		return array();
	}

	public function needs_activation() {
		foreach ( $this->settings() as $setting ) {
			if ( ! empty( $setting['type'] ) && 'license' === $setting['type'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add activation notice if the license s not active yet.
	 */
	public function activation_notice() { ?>
        <div class="error notice">
            <p><?php printf( __( 'Your %1$s plugin licence is not activated yet. Please <a href="%2$s">enter your license key</a> to start using the plugin!', 'wpify-woo' ), $this->name(),
					admin_url( 'admin.php?page=wc-settings&tab=wpify-woo-settings&section=' . $this->get_id() ) ); ?></p>
        </div>
		<?php
	}

	public function get_settings_url() {
		return add_query_arg( [ 'section' => $this->id() ], admin_url( 'admin.php?page=wc-settings&tab=wpify-woo-settings' ) );
	}

	public function is_settings_page() {
		return is_admin() && ! empty( $_GET['section'] ) && $_GET['section'] === $this->id();
	}

	public function is_enabled() {
	}

	public function requires_activation() {
		return $this->requires_activation;
	}

	public function is_activated() {
		return $this->license ? $this->license->is_activated() : true;
	}
}
