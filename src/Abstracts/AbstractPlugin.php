<?php

namespace Wpify\WooCore\Abstracts;

use Wpify\PluginUtils\PluginUtils;
use Wpify\WooCore\Admin\Settings;
use Wpify\WooCore\WooCommerceIntegration;
use Wpify\WooCore\WpifyWooCore;

/**
 * Class AbstractModule
 * @package WpifyWoo\Abstracts
 */
abstract class AbstractPlugin {
	private WpifyWooCore $wpify_woo_core;
	private PluginUtils $plugin_utils;


	public function __construct(
		WpifyWooCore $wpify_woo_core,
		PluginUtils $plugin_utils,
	) {
		$this->wpify_woo_core = $wpify_woo_core;
		$this->plugin_utils   = $plugin_utils;

		add_filter( 'wpify_installed_plugins', array( $this, 'add_plugin' ) );
		add_filter( 'plugin_action_links_' . $this->id() . '/' . $this->id() . '.php', array(
			$this,
			'add_action_links'
		) );
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta_links' ), 10, 2 );
	}

	/**
	 * Plugin id
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->plugin_utils->get_plugin_slug();
	}

	/**
	 * Plugin name
	 *
	 * @return string|null
	 */
	public function name(): ?string {
		return $this->plugin_utils->get_plugin_name();
	}

	/**
	 * Plugin base option id
	 *
	 * @return string|null
	 */
	public function base_option_id(): ?string {
		return $this->id();
	}

	/**
	 * Plugin settings url
	 *
	 * @return string
	 */
	public function settings_url(): string {
		return add_query_arg( [ 'page' => sprintf( 'wpify/%s', $this->id() ) ], admin_url( 'admin.php' ) );
	}

	/**
	 * Plugin documentation url
	 *
	 * @return string
	 */
	public function documentation_url(): string {
		return '';
	}

	/**
	 * Register plugin into WPify dashboard
	 *
	 * @param $plugins
	 *
	 * @return mixed
	 */
	public function add_plugin( $plugins ) {
		$plugins[ $this->id() ] = array(
			'title'        => $this->plugin_utils->get_plugin_name(),
			'desc'         => $this->plugin_utils->get_plugin_description(),
			'icon'         => '',
			'version'      => $this->plugin_utils->get_plugin_version(),
			'doc_link'     => $this->documentation_url(),
			'option_id'    => $this->base_option_id(),
			'settings_url' => $this->settings_url(),
			'tabs'         => array(),
			'settings'     => array()
		);

		return $plugins;
	}

	/**
	 * Add action links to WP plugin list
	 *
	 * @param $links
	 *
	 * @return array
	 */
	public function add_action_links( $links ): array {
		$before = array(
			'settings' => sprintf( '<a href="%s">%s</a>', $this->settings_url(), __( 'Settings', 'wpify-woo' ) ),
		);

		$after = array(
			'wpify' => sprintf( '<a href="%s" target="_blank">%s</a>', 'https://wpify.io', __( 'Get more plugins and support', 'wpify-woo' ) ),
		);

		return array_merge( $before, $links, $after );
	}

	/**
	 * Add action links on WP plugin list into meta
	 *
	 * @param $plugin_meta
	 * @param $plugin_file
	 *
	 * @return array
	 */
	public function add_row_meta_links( $plugin_meta, $plugin_file ): array {
		$new_links = array();

		if ( strpos( $this->plugin_utils->get_plugin_file(), $plugin_file ) ) {
			$new_links = array(
				'wpify-doc' => sprintf( '<a href="%s" target="_blank">%s</a>', $this->documentation_url(), __( 'Documentation', 'wpify-woo' ) ),
			);
		}

		return array_merge( $plugin_meta, $new_links );
	}
}
