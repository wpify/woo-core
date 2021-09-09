<?php

namespace Wpify\WpifyWooCore;

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

	public function __construct( Premium $premium) {
		$this->premium = $premium;

	}

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
