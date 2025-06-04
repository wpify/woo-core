<?php

namespace Wpify\WooCore\Abstracts;

use Wpify\PluginUtils\PluginUtils;
use Wpify\WooCore\WpifyWooCore;

/**
 * Class AbstractModule
 * @package WpifyWoo\Abstracts
 */
abstract class AbstractPlugin {
	protected bool $requires_activation = true;
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
	 * Plugin data
	 *
	 * @return array
	 */
	public function plugin_data(): array {
		return get_plugin_data( $this->plugin_utils->get_plugin_file() );
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
		return $this->plugin_data()['Name'] ?? $this->plugin_utils->get_plugin_name();
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
	 * Menu slug for settings page url
	 * @return string
	 */
	public function get_menu_slug(): string {
		return sprintf( 'wpify/%s', $this->base_option_id() );
	}

	/**
	 * Plugin settings url
	 *
	 * @return string
	 */
	public function settings_url(): string {
		return add_query_arg( [ 'page' => $this->get_menu_slug() ], admin_url( 'admin.php' ) );
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
	 * Plugin support url
	 *
	 * @return string
	 */
	public function support_url(): string {
		return '';
	}

	/**
	 * Plugin icon url
	 *
	 * @return string
	 */
	public function icon_file(): string {
		if ( file_exists( $this->plugin_utils->get_plugin_path( 'icon.svg' ) ) ) {
			return $this->plugin_utils->get_plugin_url( 'icon.svg' );
		}

		return '';
	}

	/**
	 * Plugin general Settings tabs
	 * @return array Settings tabs.
	 */
	public function settings_tabs(): array {
		return array();
	}

	/**
	 * Plugin general Settings
	 * @return array Settings.
	 */
	public function settings(): array {
		return array();
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
			'title'        => $this->name(),
			'desc'         => $this->plugin_data()['Description'],
			'icon'         => $this->icon_file(),
			'version'      => $this->plugin_utils->get_plugin_version(),
			'doc_link'     => $this->documentation_url(),
			'support_url'  => $this->support_url(),
			'menu_slug'    => $this->get_menu_slug(),
			'option_id'    => $this->base_option_id(),
			'settings_url' => $this->settings_url(),
			'tabs'         => $this->settings_tabs(),
			'settings'     => $this->settings(),
			'license'      => $this->requires_activation ? get_option( sprintf( '%s_license', $this->id() ) ) : true,
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
		$web_link = add_query_arg( array(
			'utm_source'   => $this->id() ?: 'plugin-dashboard',
			'utm_medium'   => 'plugin-link',
			'utm_campaign' => 'company-link'
		), 'https://wpify.io/' );

		$before = array(
			'settings' => sprintf( '<a href="%s">%s</a>', $this->settings_url(), __( 'Settings', 'wpify-core' ) ),
		);

		$after = array(
			'wpify' => sprintf( '<a href="%s" target="_blank">%s</a>', $web_link, __( 'Get more plugins and support', 'wpify-core' ) ),
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

		if ( $this->documentation_url() && strpos( $this->plugin_utils->get_plugin_file(), $plugin_file ) ) {
			$doc_link = add_query_arg( array(
				'utm_source'   => $this->id() ?: 'plugin-dashboard',
				'utm_medium'   => 'plugin-link',
				'utm_campaign' => 'documentation-link'
			), $this->documentation_url() );

			$new_links = array(
				'wpify-doc' => sprintf( '<a href="%s" target="_blank">%s</a>', $doc_link, __( 'Documentation', 'wpify-core' ) ),
			);
		}

		return array_merge( $plugin_meta, $new_links );
	}
}
