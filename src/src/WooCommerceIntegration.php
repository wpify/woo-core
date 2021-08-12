<?php

namespace WpifyWooCore;

use WpifyWooCore\Admin\Settings;

/**
 * Class WooCommerceIntegration
 * @package WpifyWoo
 */
class WooCommerceIntegration {
	/**
	 * @var Premium
	 */
	private $premium;
	/**
	 * @var Settings
	 */
	private $settings;

	public function __construct( Settings $settings, Premium $premium) {
		$this->premium = $premium;
		$this->settings = $settings;
	}

	const OPTION_NAME = 'wpify-woo-settings';

	/**
	 * Check if a module is enabled
	 *
	 * @param string $module Module name.
	 *
	 * @return bool
	 */
	public function is_module_enabled( string $module ): bool {
		return in_array( $module, $this->get_enabled_modules(), true );
	}

	/**
	 * Get an array of enabled modules
	 * @return array
	 */
	public function get_enabled_modules(): array {
		return $this->get_settings( 'general' )['enabled_modules'] ?? array();
	}

	/**
	 * Get settings for a specific module
	 *
	 * @param string $module Module name.
	 *
	 * @return array
	 */
	public function get_settings( string $module ): array {
		return get_option( $this->get_settings_name( $module ), array() );
	}

	public function get_settings_name( string $module ): string {
		return sprintf( '%s-%s', $this::OPTION_NAME, $module );
	}

	/**
	 * Get available modules
	 */
	public function get_modules(): array {
		$modules = apply_filters( 'wpify_woo_modules', [] );

		foreach ( $this->premium->get_extensions() as $extension ) {
			$exists = array_filter( $modules, function ( $module ) use ( $extension ) {
				return $extension['id'] === $module['value'];
			} );
			if ( empty( $exists ) ) {
				$modules[] = [
					'label'    => sprintf( '<a href="%1$s"><strong>%2$s</strong></a> - %3$s <span class="wpify-woo-settings__premium"><a href="%1$s">%4$s</a></span>', $extension['url'], $extension['title'], $extension['short_description'], __( 'Get the addon', 'wpify-woo' ) ),
					'value'    => $extension['id'],
					'disabled' => true,
				];
			}
		}

		return $modules;
	}
}
