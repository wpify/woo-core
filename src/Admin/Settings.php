<?php

namespace Wpify\WooCore\Admin;

use WC_Admin_Settings;
use Wpify\Asset\AssetFactory;
use Wpify\CustomFields\CustomFields;
use Wpify\WooCore\Managers\ModulesManager;
use Wpify\WooCore\Premium;
use Wpify\WooCore\WooCommerceIntegration;
use Wpify\WooCore\WpifyWooCore;

/**
 * Class Settings
 *
 * @package WpifyWooCore\Admin
 */
class Settings {
	const OPTION_NAME = 'wpify-woo-settings';
	const SUPPORT_ID = 'wpify-support';
	const SUPPORT_MENU_SLUG = 'wpify/support';
	const DASHBOARD_SLUG = 'wpify';

	private $id;
	private $label;
	private $pages;

	/** @var CustomFields */
	private $custom_fields;

	/** @var ModulesManager */
	private $modules_manager;

	/** @var AssetFactory */
	private $asset_factory;

	private $initialized;

	public function __construct(
		CustomFields $custom_fields,
		ModulesManager $modules_manager,
		AssetFactory $asset_factory
	) {
		$this->custom_fields   = $custom_fields;
		$this->modules_manager = $modules_manager;
		$this->asset_factory   = $asset_factory;
		$this->id              = $this::OPTION_NAME;
		$this->label           = __( 'WPify Woo', 'wpify-core' );

		// Check if the WpifyWoo Core settings have been initialized already
		$this->initialized = apply_filters( 'wpify_core_settings_initialized', false );

		if ( ! $this->initialized ) {
			add_filter( 'wpify_core_settings_initialized', '__return_true' );
			add_action( 'init', array( $this, 'load_textdomain' ) );
			add_action( 'init', array( $this, 'register_settings' ) );
			add_filter( 'admin_body_class', array( $this, 'add_admin_body_class' ), 9999 );
			add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
			add_action( 'admin_menu', [ $this, 'register_menu_page' ] );
			add_action( 'in_admin_header', [ $this, 'render_menu_bar' ] );

			/** Handle activation/deactivation messages */
			if ( ! empty( $_REQUEST['wpify-woo-license-activated'] ) && $_REQUEST['wpify-woo-license-activated'] === '1' ) {
				WC_Admin_Settings::add_message( __( 'Your license has been activated.', 'wpify-core' ) );
			}
			if ( ! empty( $_REQUEST['wpify-woo-license-deactivated'] ) && $_REQUEST['wpify-woo-license-deactivated'] === '1' ) {
				WC_Admin_Settings::add_message( __( 'Your license has been deactivated.', 'wpify-core' ) );
			}
		}
	}

	public function removable_query_args( array $args ) {
		$args[] = 'wpify-woo-license-activated';
		$args[] = 'wpify-woo-license-deactivated';

		return $args;
	}

	/**
	 * Register core textdomain
	 * @return void
	 */
	function load_textdomain() {
		$core_base_path = dirname( __DIR__, 2 );
		$languages_path = $core_base_path . '/languages/';

		load_plugin_textdomain( 'wpify-core', false, $languages_path );
	}

	/**
	 * Register admin pages and settings for plugins and modules
	 *
	 * @return void
	 */
	public function register_settings(): void {
		$plugins = $this->get_plugins();
		if ( empty( $plugins ) ) {
			return;
		}
		$this->pages = array();
		foreach ( $plugins as $plugin_id => $plugin ) {
			$this->pages[ $plugin_id ] = array(
				'page_title'  => $plugin['title'],
				'menu_title'  => $plugin['title'],
				'menu_slug'   => $plugin['menu_slug'],
				'id'          => $plugin_id,
				'parent_slug' => $this::DASHBOARD_SLUG,
				'class'       => 'wpify-woo-settings',
				'option_name' => $this->get_settings_name( $plugin['option_id'] ),
				'tabs'        => $this->is_current( '', $plugin_id ) ? $plugin['tabs'] : array(),
				'items'       => $this->is_current( '', $plugin_id ) ? $plugin['settings'] : array(),
			);
			$sections                  = $this->get_sections( $plugin['option_id'] );
			foreach ( $sections as $section_id => $section ) {
				if ( empty( $section_id ) ) {
					continue;
				}

				if ( isset( $this->pages[ $section_id ] ) || $plugin['option_id'] === $section['option_id'] ) {
					$this->pages[ $plugin_id ]['page_title']  = $section['title'];
					$this->pages[ $plugin_id ]['id']          = $section_id;
					$this->pages[ $plugin_id ]['option_name'] = $section['option_name'] ?? $this->get_settings_name( $section['option_id'] );
					$this->pages[ $plugin_id ]['tabs']        = $this->is_current( '', $section_id ) ? $section['tabs'] : array();
					$this->pages[ $plugin_id ]['items']       = $this->is_current( '', $section_id ) ? $section['settings'] : array();
					continue;
				}

				$this->pages[ $section_id ] = array(
					'page_title'  => $section['title'],
					'menu_title'  => $section['title'],
					'menu_slug'   => $section['menu_slug'],
					'id'          => $section_id,
					'parent_slug' => $section['parent'],
					'class'       => 'wpify-woo-settings',
					'option_name' => $section['option_name'] ?? $this->get_settings_name( $section['option_id'] ),
					'tabs'        => $this->is_current( '', $section_id ) ? $section['tabs'] : array(),
					'items'       => $this->is_current( '', $section_id ) ? $section['settings'] : array(),
				);
			}
		}

		foreach ( $this->pages as $page ) {
			$this->custom_fields->create_options_page( $page );
		}
	}

	/**
	 * Get plugins
	 *
	 * @return array
	 */
	public function get_plugins(): array {
		return apply_filters( 'wpify_installed_plugins', [] );
	}

	/**
	 * Get sections
	 *
	 * @param string|null $subpage subpage slug
	 *
	 * @return array
	 */
	public function get_sections( string $subpage = null ): array {
		if ( ! $subpage ) {
			$current_page = empty( $_REQUEST['page'] ) ? '' : $_REQUEST['page'];
			if ( ! str_contains( $current_page, 'wpify/' ) ) {
				return [];
			}

			$subpage = explode( '/', $current_page )[1] ?? '';
		}

		return apply_filters( 'wpify_get_sections_' . $subpage, [] );
	}

	/**
	 * Get an array of enabled modules
	 *
	 * @return array
	 */
	public function get_enabled_modules(): array {
		return $this->get_settings( 'general' )['enabled_modules'] ?? array();
	}

	/**
	 * Set module as active
	 *
	 * @param string $module Module slug
	 *
	 * @return void
	 */
	public function enable_module( string $module ): void {
		$general_settings = $this->get_settings( 'general' );
		$enabled_modules  = $general_settings['enabled_modules'] ?? array();
		if ( ! \in_array( $module, $enabled_modules ) ) {
			$enabled_modules[]                   = $module;
			$general_settings['enabled_modules'] = $enabled_modules;
			update_option( $this->get_settings_name( 'general' ), $general_settings );
		}
	}

	/**
	 * Get settings for a specific module
	 *
	 * @param string $module Module slug.
	 *
	 * @return array
	 */
	public function get_settings( string $module ): array {
		return get_option( $this->get_settings_name( $module ), array() );
	}

	/**
	 * Get settings name
	 *
	 * @param string $module Module slug
	 *
	 * @return string
	 */
	public function get_settings_name( string $module ): string {
		$key = sprintf( '%s-%s', $this::OPTION_NAME, $module );
		if ( 'general' !== $module ) {
			if ( \defined( 'ICL_LANGUAGE_CODE' ) ) {
				$default_lang = apply_filters( 'wpml_default_language', null );
				if ( $default_lang !== ICL_LANGUAGE_CODE ) {
					$key = sprintf( '%s_%s', $key, ICL_LANGUAGE_CODE );
				}
			}
		}

		return $key;
	}

	/**
	 * Check if is a current settings page
	 *
	 * @param string $tab     tam id
	 * @param string $section section id
	 *
	 * @return bool
	 */
	public function is_current( $tab = '', $section = '' ): bool {
		$current_tab     = empty( $_REQUEST['tab'] ) ? '' : $_REQUEST['tab'];
		$current_section = empty( $_REQUEST['section'] ) ? '' : $_REQUEST['section'];

		if ( $tab === $current_tab && $section === $current_section ) {
			return true;
		}

		$current_module = $this->get_current_module();

		if ( $current_module === $section ) {
			return true;
		}

		foreach ( $this->modules_manager->get_modules() as $module ) {
			$option_name = $this->get_settings_name( $module->get_id() );
			if ( isset( $_REQUEST[ $option_name ] ) ) {
				return true;
			}
		}

		if ( wp_is_json_request() && isset( $_GET['module_id'] ) && $_GET['module_id'] === $section ) {
			return true;
		}

		if ( isset( $_POST['option_page'] ) && $_POST['option_page'] === $this->get_settings_name( $section ) ) {
			return true;
		}

		if ( isset( $_POST['option_page'] ) && str_contains( $_POST['option_page'], $section ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get current module slug
	 *
	 * @return false|mixed|string
	 */
	public function get_current_module() {
		foreach ( $this->modules_manager->get_modules() as $module ) {
			$module_id   = $module->get_id();
			$option_name = $this->get_settings_name( $module_id );

			if ( isset( $_REQUEST[ $option_name ] ) ) {
				return $module_id;
			}
		}

		$current_page = empty( $_REQUEST['page'] ) ? '' : $_REQUEST['page'];

		if ( ! str_contains( $current_page, 'wpify/' ) ) {
			return false;
		}

		$page_attributes = explode( '/', $current_page );

		return end( $page_attributes );
	}

	/**
	 * Register main settings pages
	 *
	 * @return void
	 */
	public function register_menu_page() {
		add_menu_page(
			__( 'WPify Plugins', 'wpify-core' ),
			__( 'WPify', 'wpify-core' ),
			'manage_options',
			$this::DASHBOARD_SLUG,
			[ $this, 'render_dashboard' ],
			'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTUwIiBoZWlnaHQ9IjU1MCIgdmlld0JveD0iMCAwIDU1MCA1NTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IG9wYWNpdHk9IjAuMyIgd2lkdGg9IjUzMiIgaGVpZ2h0PSI3OCIgcng9IjM5IiB0cmFuc2Zvcm09Im1hdHJpeCgtNC4zNzExNGUtMDggMSAxIDQuMzcxMTRlLTA4IDM2Ny43NSA5LjAwMDEyKSIgZmlsbD0id2hpdGUiLz4KPHJlY3Qgb3BhY2l0eT0iMC4zIiB3aWR0aD0iNTMwIiBoZWlnaHQ9Ijc4IiByeD0iMzkiIHRyYW5zZm9ybT0ibWF0cml4KC00LjM3MTE0ZS0wOCAxIDEgNC4zNzExNGUtMDggMjA0Ljc1IDkuMDAwMTIpIiBmaWxsPSJ3aGl0ZSIvPgo8cmVjdCBvcGFjaXR5PSIwLjgiIHdpZHRoPSI1NTcuODgyIiBoZWlnaHQ9Ijc4LjE1ODUiIHJ4PSIzOS4wNzkyIiB0cmFuc2Zvcm09Im1hdHJpeCgwLjMzODc4MSAwLjk0MDg2NSAwLjk0MDg2NSAtMC4zMzg3ODEgMzEuNzUgMjQuNDc4OCkiIGZpbGw9IndoaXRlIi8+CjxyZWN0IG9wYWNpdHk9IjAuOCIgd2lkdGg9IjU2MC42NzYiIGhlaWdodD0iNzguMTU4NSIgcng9IjM5LjA3OTIiIHRyYW5zZm9ybT0ibWF0cml4KDAuMzM4NzgxIDAuOTQwODY1IDAuOTQwODY1IC0wLjMzODc4MSAxOTMuMjQ5IDI0LjQ3ODgpIiBmaWxsPSJ3aGl0ZSIvPgo8cmVjdCBvcGFjaXR5PSIwLjgiIHdpZHRoPSIyNTkuNjQ2IiBoZWlnaHQ9Ijc4LjE1ODUiIHJ4PSIzOS4wNzkyIiB0cmFuc2Zvcm09Im1hdHJpeCgwLjMzODc4MSAwLjk0MDg2NSAwLjk0MDg2NSAtMC4zMzg3ODEgMzU2Ljc1IDI0LjQ3ODYpIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4=',
			// icon (from Dashicons for example)
			59,
		);

		add_submenu_page(
			$this::DASHBOARD_SLUG,
			__( 'WPify Plugins Dashboard', 'wpify-core' ),
			__( 'Dashboard', 'wpify-core' ),
			'manage_options',
			$this::DASHBOARD_SLUG,
			[ $this, 'render_dashboard' ],
		);

		add_submenu_page(
			$this::DASHBOARD_SLUG,
			__( 'WPify Plugins Support', 'wpify-core' ),
			__( 'Support', 'wpify-core' ),
			'manage_options',
			$this::SUPPORT_MENU_SLUG,
			[ $this, 'render_support' ],
			99
		);
		do_action( 'wpify_woo_settings_menu_page_registered' );
	}

	/**
	 * Add custom class to admin body on wpify pages
	 *
	 * @param $admin_body_class
	 *
	 * @return mixed|string
	 */
	public static function add_admin_body_class( $admin_body_class = '' ) {
		$current_page = empty( $_REQUEST['page'] ) ? '' : $_REQUEST['page'];

		if ( ! str_contains( $current_page, 'wpify' ) ) {
			return $admin_body_class;
		}

		$classes          = explode( ' ', trim( $admin_body_class ) );
		$classes[]        = 'wpify-admin-page';
		$admin_body_class = implode( ' ', array_unique( $classes ) );

		return " $admin_body_class ";
	}

	/**
	 * Get html of plugins overview for dashboard
	 *
	 * @return string
	 */
	public function get_wpify_plugins_overview(): string {
		$installed_plugins = $this->get_plugins();
		$extensions        = get_transient( 'wpify_core_all_plugins' );

		if ( ! $extensions ) {
			$response = wp_remote_get( 'https://wpify.io/wp-json/wpify/v1/plugins-list' );

			if ( ! is_wp_error( $response ) ) {
				$extensions = json_decode( $response['body'], true )['plugins'];
				set_transient( 'wpify_core_all_plugins', $extensions, DAY_IN_SECONDS );
			}
		}

		$extensions_map = array();
		if ( $extensions ) {
			foreach ( $extensions as $extension ) {
				$extensions_map[ $extension['slug'] ] = $extension;
			}

			foreach ( $installed_plugins as $slug => $plugin ) {
				if ( isset( $extensions_map[ $slug ] ) ) {
					$installed_plugins[ $slug ] = array_merge( $plugin, $extensions_map[ $slug ] );
					unset( $extensions_map[ $slug ] );
				}
			}
		}

		$html = sprintf( '<h2>%s</h2>', __( 'Installed plugins', 'wpify-core' ) );
		$html .= $this->get_wpify_modules_blocks( $installed_plugins, true );

		if ( $extensions_map ) {
			$html .= sprintf( '<h2>%s</h2>', __( 'Our other plugins', 'wpify-core' ) );
			$html .= $this->get_wpify_modules_blocks( $extensions_map );
		}

		return $html;
	}

	/**
	 * Get modules blocks html for overview
	 *
	 * @param array $plugins   plugins data
	 * @param bool  $installed is instaled
	 *
	 * @return bool|string
	 */
	public function get_wpify_modules_blocks( array $plugins, bool $installed = false ): bool|string {
		ob_start();
		?>
        <div class="wpify__cards">

			<?php foreach ( $plugins as $slug => $plugin ) {
				?>
                <div class="wpify__card">
                    <div class="wpify__card-head">
						<?php
						if ( isset( $plugin['icon'] ) && $plugin['icon'] ) {
							?>
                            <img src="<?php
							echo $plugin['icon'];
							?>" alt="<?php
							echo $plugin['title'];
							?>" width="50" height="50">
							<?php
						}
						?>
                        <div>
                            <h3>
								<?php
								echo $plugin['title'];
								?>
                            </h3>
							<?php
							$metas = [];
							if ( isset( $plugin['version'] ) ) {
								$metas[] = $plugin['version'];
							}
							if ( isset( $plugin['rating'] ) && $plugin['rating'] ) {
								$metas[] = sprintf( '⭐ %s/5', $plugin['rating'] );
							}
							if ( ! $installed && isset( $plugin['doc_link'] ) && $plugin['doc_link'] ) {
								$metas[] = '<a href="' . esc_url( $plugin['doc_link'] ) . '" target="_blank">' . _e( 'Documentation', 'wpify-core' ) . '</a>';
							}
							echo join( ' | ', $metas );
							?>
                        </div>
                    </div>
                    <div class="wpify__card-body">
						<?php
						if ( ! $installed ) {
							echo $plugin['desc'] ?? '';
						}
						?>
                    </div>
                    <div class="wpify__card-footer">
						<?php
						if ( ! $installed && isset( $plugin['price'] ) ) {
							echo '<strong>' . $plugin['price'] . '</strong>';
						}
						if ( $installed && isset( $plugin['doc_link'] ) && $plugin['doc_link'] ) {
							?>
                            <a href="<?php echo esc_url( $plugin['doc_link'] ); ?>"
                               target="_blank"><?php _e( 'Documentation', 'wpify-core' ); ?></a>
							<?php
						}
						?>
                        <div style="flex: 1"></div>
						<?php
						if ( $installed && isset( $plugin['settings_url'] ) ) {
							?>
                            <span><a class="button" href="<?php
								echo esc_url( $plugin['settings_url'] );
								?>"
                                     role="button"><?php
									_e( 'Settings', 'wpify-core' );
									?></a></span>
							<?php
						} else {
							?>
                            <span><a class="install-now button" href="<?php
								echo esc_url( $plugin['link'] );
								?>"
                                     role="button"><?php
									_e( 'Get plugin', 'wpify-core' );
									?></a></span>
							<?php
						}
						?>
                    </div>
                </div>
				<?php
			}
			?>
        </div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get html of WPify posts
	 *
	 * @return string|void
	 */
	public function get_wpify_posts() {
		$posts = get_transient( 'wpify_core_news' );

		if ( ! $posts ) {
			$response = wp_remote_get( 'https://wpify.io/wp-json/wp/v2/posts?per_page=4&_embed' );

			if ( ! is_wp_error( $response ) ) {
				$posts = json_decode( wp_remote_retrieve_body( $response ) );
				set_transient( 'wpify_core_news', $posts, DAY_IN_SECONDS );
			}
		}

		if ( empty( $posts ) ) {
			return '';
		}

		?>
        <h2><?php _e( 'WPify News', 'wpify-core' ) ?></h2>
        <div class="wpify__cards">

			<?php foreach ( $posts as $post ) { ?>
                <div class="wpify__card" style="max-width:100%">
					<?php
					$embedded  = (array) $post->_embedded;
					$thumbnail = $embedded['wp:featuredmedia'][0]->source_url;
					if ( $thumbnail ) {
						?>
                        <a href="<?php echo esc_url( $post->link ); ?>" target="_blank">
                            <img src="<?php echo esc_url( $thumbnail ); ?>" loading="lazy"
                                 style="width: 100%; height: auto">
                        </a>
					<?php } ?>
                    <div class="wpify__card-body">
                        <h3><a href="<?php echo esc_url( $post->link ); ?>" target="_blank">
								<?php echo esc_html( $post->title->rendered ); ?>
                            </a></h3>
						<?php echo $post->excerpt->rendered; ?>
                    </div>
                </div>
				<?php
			}
			?>
        </div>
		<?php
	}

	/**
	 * Render Dashboard page html
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		?>
        <div class="wpify-dashboard__wrap wrap">
            <div class="wpify-dashboard__content">
				<?php
				echo $this->get_wpify_plugins_overview();
				?>
            </div>
            <div class="wpify-dashboard__sidebar">
				<?php $this->get_wpify_posts(); ?>
            </div>
        </div>
		<?php
	}

	/**
	 * Render Support page html
	 *
	 * @return void
	 */
	public function render_support(): void {
		?>
        <div class="wpify-dashboard__wrap wrap">
            <div class="wpify-dashboard__content">
                <div class="wpify__cards">
                    <div class="wpify__card" style="max-width:100%">
                        <div class="wpify__card-body">
                            <h2><?php _e( 'Frequently Asked Questions', 'wpify-core' ); ?></h2>

                            <div class="faq">
                                <h3><?php _e( 'How do the pricing plans work?', 'wpify-core' ); ?></h3>
                                <p><?php _e( 'When you purchase the plugin, you receive support and updates for one year. After this period, the license will automatically renew at a discounted price.', 'wpify-core' ); ?></p>
                            </div>

                            <div class="faq">
                                <h3><?php _e( 'Will the plugin work if I do not renew my license?', 'wpify-core' ); ?></h3>
                                <p><?php _e( 'Yes, the plugin will continue to work, but you will no longer have access to updates and support.', 'wpify-core' ); ?></p>
                            </div>

                            <div class="faq">
                                <h3><?php _e( 'I need a feature that the plugin does not currently support.', 'wpify-core' ); ?></h3>
                                <p><?php _e( 'Let us know, and we will consider adding the requested functionality.', 'wpify-core' ); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="wpify__card">
                        <div class="wpify__card-body">
                            <h3><?php _e( 'Do you have any other questions?', 'wpify-core' ); ?></h3>
                            <p><?php _e( 'Check out the plugin documentation to see if your question is already answered.', 'wpify-core' ); ?></p>
                            <p><a href="https://wpify.io/dokumentace/" target="_blank"
                                  class="button button-primary"><?php _e( 'Documentation', 'wpify-core' ); ?></a></p>
                        </div>
                    </div>

                    <div class="wpify__card">
                        <div class="wpify__card-body">
                            <h3><?php _e( 'If you haven’t found the answer, email us at:', 'wpify-core' ); ?></h3>
                            <p><a href="mailto:support@wpify.io">support@wpify.io</a></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="wpify-dashboard__sidebar">
				<?php $this->get_wpify_posts(); ?>
            </div>
        </div>
		<?php
	}

	/**
	 * Render menubar
	 *
	 * @return void
	 */
	public function render_menu_bar(): void {
		/** @var \WP_Screen $screen */
		$screen       = get_current_screen();
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

		if ( ! $screen || ! str_contains( $current_page, 'wpify' ) ) {
			return;
		}

		global $title;

		$data     = array(
			'title'       => $title,
			'icon'        => '',
			'parent'      => '',
			'plugin'      => '',
			'menu'        => array(
				array(
					'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 6.75c0-1.768 0-2.652.55-3.2C4.097 3 4.981 3 6.75 3s2.652 0 3.2.55c.55.548.55 1.432.55 3.2s0 2.652-.55 3.2c-.548.55-1.432.55-3.2.55s-2.652 0-3.2-.55C3 9.403 3 8.519 3 6.75m0 10.507c0-1.768 0-2.652.55-3.2c.548-.55 1.432-.55 3.2-.55s2.652 0 3.2.55c.55.548.55 1.432.55 3.2s0 2.652-.55 3.2c-.548.55-1.432.55-3.2.55s-2.652 0-3.2-.55C3 19.91 3 19.026 3 17.258M13.5 6.75c0-1.768 0-2.652.55-3.2c.548-.55 1.432-.55 3.2-.55s2.652 0 3.2.55c.55.548.55 1.432.55 3.2s0 2.652-.55 3.2c-.548.55-1.432.55-3.2.55s-2.652 0-3.2-.55c-.55-.548-.55-1.432-.55-3.2m0 10.507c0-1.768 0-2.652.55-3.2c.548-.55 1.432-.55 3.2-.55s2.652 0 3.2.55c.55.548.55 1.432.55 3.2s0 2.652-.55 3.2c-.548.55-1.432.55-3.2.55s-2.652 0-3.2-.55c-.55-.548-.55-1.432-.55-3.2"/></svg>',
					'label' => __( 'Dashboard', 'wpify-core' ),
					'link'  => add_query_arg( [ 'page' => $this::DASHBOARD_SLUG ], admin_url( 'admin.php' ) ),
				),
			),
			'support_url' => add_query_arg( [ 'page' => $this::SUPPORT_MENU_SLUG ], admin_url( 'admin.php' ) ),
			'doc_link'    => 'https://wpify.io/dokumentace/',
		);
		$data     = apply_filters( 'wpify_admin_menu_bar_data', $data );
		$sections = $this->get_sections( $data['parent'] );

		foreach ( $sections as $section_id => $section ) {
			if ( isset( $section['in_menubar'] ) && ! $section['in_menubar'] ) {
				unset( $sections[ $section_id ] );
			}
		}
		$data['sections'] = $sections;

		$plugins = $this->get_plugins();
		if ( isset( $plugins[ $data['plugin'] ] ) ) {
			$data['title']       = $plugins[ $data['plugin'] ]['title'];
			$data['icon']        = $plugins[ $data['plugin'] ]['icon'];
			$data['doc_link']    = $data['doc_link'] ?: $plugins[ $data['plugin'] ]['doc_link'];
			$data['support_url'] = $plugins[ $data['plugin'] ]['support_url'] ?: $data['support_url'];
		}

		?>
        <style type="text/css">
            .wpify-admin-page #wpcontent {
                padding-left: 0;
            }

            .wpify-admin-page #wpbody {
                padding: 20px 0 0 20px;
            }

            .wpify-dashboard__wrap {
                display: flex;
                flex-direction: column;
                flex-wrap: wrap;
                gap: 40px;
            }

            .wpify__menu-bar {
                background: white;
                padding: 0 20px;
                display: flex;
                justify-content: space-between;
                align-content: center;
                min-height: 60px;
                border-bottom: 1px solid #e5e5e5;

                @media screen and (max-width: 600px) {
                    padding-top: 50px;
                    flex-wrap: wrap;
                }
            }

            .wpify__menu-bar-column {
                display: flex;
                align-content: center;
                flex-wrap: wrap;
                column-gap: 20px;
            }

            .wpify__menu-bar-column.menu-column {
                @media screen and (min-width: 600px) {
                    flex: 1;
                }

                @media screen and (max-width: 600px) {
                    order: 3;
                    width: 100%;
                }
            }

            .wpify__menu-bar-column > * {
                display: flex;
                align-content: center;
                flex-wrap: wrap;
            }

            .wpify__logo {
                padding-left: 20px;
                border-left: 1px solid silver;
            }

            .wpify__logo svg {
                height: 30px;
            }

            .wpify__menu-bar-name {
                font-size: 18px;
                font-weight: bold;
                margin-right: 20px;
            }

            .wpify__menu-bar-item {
                padding: 0 10px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                flex-wrap: wrap;
                height: 60px;
                color: dimgray;
                text-decoration: none;
            }

            .wpify__menu-bar-item:hover {
                color: black;
            }

            .wpify__menu-bar-item.current,
            .wpify__menu-section-bar-item.current {
                color: #00A0D2;
                border-bottom: 3px solid #00A0D2;
            }

            .wpify__menu-section-bar {
                background: white;
                padding: 0 20px;
                display: flex;
                flex-wrap: wrap;
                justify-content: start;
                align-content: center;
                border-bottom: 1px solid #e5e5e5;
            }

            .wpify__menu-section-bar-item {
                padding: 10px 20px;
                color: dimgray;
                text-decoration: none;
            }

            .wpify__cards {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-bottom: 2rem;
            }

            .wpify__card {
                display: flex;
                flex-direction: column;
                background: white;
                width: 100%;
                border-radius: 7px;
                overflow: hidden;
                -webkit-box-shadow: 0px 0px 5px 0px rgba(0, 0, 0, 0.42);
                -moz-box-shadow: 0px 0px 5px 0px rgba(0, 0, 0, 0.42);
                box-shadow: 0px 0px 5px 0px rgba(0, 0, 0, 0.42);
            }

            .wpify__card-head {
                display: flex;
                gap: 10px;
                padding: 20px;
                justify-content: start;
            }

            .wpify__card-head > div {
                flex: 1;
            }

            .wpify__card-head h3 {
                margin: 0;
            }

            .wpify__card-body {
                padding: 0 20px;
                flex: 1;
            }

            .wpify__card-body:empty {
                display: none;
            }

            .wpify__card-body p {
                margin-top: 0;
            }

            .wpify__card-footer {
                padding: 0 20px 20px 20px;
                display: flex;
                gap: 1rem;
                align-items: center;
            }

            .wpify__modules-toggle {
                flex-flow: row;
                flex-wrap: wrap;
                gap: 20px;
            }

            .wpify__modules-toggle .components-base-control {
                flex: 1;
                width: 300px;
                padding: 10px 20px;
                border-radius: 7px;
                -webkit-box-shadow: 0px 0px 5px 0px rgba(0, 0, 0, 0.42);
                -moz-box-shadow: 0px 0px 5px 0px rgba(0, 0, 0, 0.42);
                box-shadow: 0px 0px 5px 0px rgba(0, 0, 0, 0.42);
            }

            .wpify__modules-toggle .components-base-control .components-toggle-control__label > span {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                align-items: baseline;
            }

            .wpify__modules-toggle .components-base-control h3 {
                width: 100%;
                margin: 10px 0;
            }

            .wpify__modules-toggle .components-base-control .components-form-toggle {
                margin: 20px 10px 20px 0;
            }

            form {
                display: flex;
                background: white;
                column-gap: 40px;
                padding: 20px;
                border-radius: 7px;
                flex-wrap: wrap;
                justify-content: space-between;
                max-width: 1200px;
                -webkit-box-shadow: 0px 0px 5px 0px rgba(0, 0, 0, 0.42);
                -moz-box-shadow: 0px 0px 5px 0px rgba(0, 0, 0, 0.42);
                box-shadow: 0px 0px 5px 0px rgba(0, 0, 0, 0.42);
            }

            .wpifycf-app {
                border-bottom: 1px solid #e5e5e5;

                @media screen and (min-width: 783px) {
                    border-bottom: none;
                    border-right: 1px solid #e5e5e5;
                    width: 20%;
                }
            }

            .wpifycf-app:empty {
                display: none;
            }

            .form-table {
                @media screen and (min-width: 783px) {
                    flex: 1;
                }
            }

            p.submit {
                width: 100%;
                text-align: right;
            }

            .nav-tab-wrapper {
                flex-direction: row;
                flex-wrap: wrap;

                @media screen and (min-width: 783px) {
                    border-bottom: none;
                    margin-left: -20px;
                    display: flex;
                    flex-direction: column;
                }
            }

            .nav-tab-wrapper .nav-tab {
                background: white;
                text-align: left;
                margin-left: 0;
                padding: 10px 20px;
                border: none;
                border-bottom: 4px solid white;

                @media screen and (min-width: 783px) {
                    border-bottom: none;
                    border-left: 4px solid white;
                }
            }

            .nav-tab-wrapper .nav-tab.nav-tab-active {
                border-color: #00A0D2;
                color: #00A0D2;
            }

            @media screen and (max-width: 783px) {
                .wpify__menu-bar-item span {
                    display: none;
                }
            }

            @media screen and (min-width: 783px) {
                .wpify-dashboard__wrap {
                    flex-direction: row;
                }

                .wpify-dashboard__content {
                    flex: 1;
                }

                .wpify-dashboard__sidebar {
                    width: 300px;
                }

                .wpify__card {
                    max-width: 300px;
                }
            }

            input[type=color], input[type=date], input[type=datetime-local], input[type=datetime], input[type=email], input[type=month], input[type=number], input[type=password], input[type=search], input[type=tel], input[type=text], input[type=time], input[type=url], input[type=week], select, textarea {
                padding: 5px 10px;
                border: 1px solid #ccc;
                border-radius: 5px;
            }

            .wpifycf-select .wpifycf-select__control {
                padding: 5px 10px;
                border: 1px solid #ccc;
                border-radius: 5px;
            }

            .wpifycf-select .wpifycf-select__clear-indicator {
                color: red;
            }

            .form-table .components-form-toggle {
                height: 28px;
            }

            .form-table .components-form-toggle .components-form-toggle__track {
                width: 50px;
                height: 28px;
                border-radius: 16px;
                border-color: #cccccc;
            }

            .form-table .components-form-toggle.is-checked .components-form-toggle__track {
                background-color: #00A0D2;
                border-color: #00A0D2;
            }

            .form-table .components-form-toggle .components-form-toggle__thumb {
                width: 24px;
                height: 24px;
                background-color: #555555;
            }

            .form-table .components-form-toggle.is-checked .components-form-toggle__thumb {
                transform: translateX(22px);
            }

            .wpify-admin-page form p .button-primary {
                background: #00A0D2;
                border-color: #00A0D2;
                color: white;
                padding: 7px 25px;
                text-transform: uppercase;
            }

            .wpify-admin-page form p .button-primary:hover, .wpify-admin-page form p .button-primary:active {
                background: #826eb4;
                border-color: #826eb4;
            }
        </style>
        <div class="wpify__menu-bar">
            <div class="wpify__menu-bar-column title-column">
				<?php
				if ( $data['icon'] ) {
					?>
                    <div class="wpify__plugin-icon">
                        <img src="<?php echo $data['icon']; ?>" alt="ICO" width="40" height="40">
                    </div>
					<?php
				}
				?>
                <div class="wpify__menu-bar-name">
					<?php
					echo esc_html( $data['title'] ?: $title );
					?>
                </div>
            </div>
            <div class="wpify__menu-bar-column menu-column">
				<?php
				foreach ( $data['menu'] as $item ) {
					$url_components = parse_url( $item['link'] );
					parse_str( $url_components['query'], $query_params );
					$menu_page    = $query_params['page'] ?? '';
					$active_class = $current_page === $menu_page ? ' current' : '';
					printf( '<a class="wpify__menu-bar-item%s" href="%s">%s<span>%s</span></a>', esc_attr( $active_class ), esc_url( $item['link'] ), $item['icon'], esc_html( $item['label'] ) );
				}
				if ( $data['doc_link'] ) {
					$doc_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"><path d="M5 20.25c0 .414.336.75.75.75h10.652C17.565 21 18 20.635 18 19.4v-1.445M5 20.25A2.25 2.25 0 0 1 7.25 18h10.152q.339 0 .598-.045M5 20.25V6.2c0-1.136-.072-2.389 1.092-2.982C6.52 3 7.08 3 8.2 3h9.2c1.236 0 1.6.437 1.6 1.6v11.8c0 .995-.282 1.425-1 1.555"/><path d="m9.6 10.323l1.379 1.575a.3.3 0 0 0 .466-.022L14.245 8"/></g></svg>';
					printf( '<a class="wpify__menu-bar-item" href="%s" target="_blank">%s<span>%s</span></a>', esc_url( $data['doc_link'] ), $doc_icon, __( 'Documentation', 'wpify-core' ) );
				}
				$support_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"><path d="M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0"/><path d="M12 13.496c0-2.003 2-1.503 2-3.506c0-2.659-4-2.659-4 0m2 6.007v-.5"/></g></svg>';
				printf( '<a class="wpify__menu-bar-item%s" href="%s">%s<span>%s</span></a>', $current_page === $this::SUPPORT_MENU_SLUG ? ' current' : '', esc_url( $data['support_url'] ), $support_icon, __( 'Support', 'wpify-core' ) );
				?>
            </div>
            <div class="wpify__menu-bar-column">
                <a class="wpify__logo" href="https://wpify.io/" target="_blank" title="WPify Web">
                    <svg width="77" height="30" viewBox="0 0 1430 554" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect opacity="0.3" width="534.768" height="78.1585" rx="39.0792"
                              transform="matrix(-4.37114e-08 1 1 4.37114e-08 336.915 10.0248)"
                              fill="url(#paint0_linear_1101_8228)"></rect>
                        <rect opacity="0.3" width="530.655" height="78.1585" rx="39.0792"
                              transform="matrix(-4.37114e-08 1 1 4.37114e-08 172.382 10.0248)"
                              fill="url(#paint1_linear_1101_8228)"></rect>
                        <rect opacity="0.8" width="557.882" height="78.1585" rx="39.0792"
                              transform="matrix(0.338781 0.940865 0.940865 -0.338781 0 26.4789)"
                              fill="url(#paint2_linear_1101_8228)"></rect>
                        <rect opacity="0.8" width="560.676" height="78.1585" rx="39.0792"
                              transform="matrix(0.338781 0.940865 0.940865 -0.338781 161.499 26.4789)"
                              fill="url(#paint3_linear_1101_8228)"></rect>
                        <path opacity="0.8"
                              d="M361.939 13.402C341.81 20.6499 331.252 42.7365 338.252 62.9529L396.856 232.208C403.978 252.779 426.538 263.563 447.019 256.188C467.148 248.94 477.706 226.854 470.707 206.637L412.103 37.3822C404.98 16.8113 382.42 6.02707 361.939 13.402Z"
                              fill="url(#paint4_linear_1101_8228)"></path>
                        <path
                                d="M661.12 342.274L703.483 174.421H732.858L727.063 225.776L684.899 390.632H658.922L661.12 342.274ZM643.336 174.421L675.707 342.874L675.907 390.632H646.733L598.175 174.421H643.336ZM767.028 340.876L798.001 174.421H843.361L794.803 390.632H765.629L767.028 340.876ZM737.853 174.421L779.617 340.076L782.614 390.632H756.637L713.674 225.776L708.079 174.421H737.853ZM913.708 215.985V473.76H867.348V174.421H909.911L913.708 215.985ZM1030.01 273.934V291.119C1030.01 308.038 1028.47 322.958 1025.41 335.88C1022.35 348.802 1017.82 359.659 1011.82 368.452C1005.96 377.111 998.7 383.638 990.041 388.034C981.382 392.431 971.257 394.629 959.667 394.629C948.744 394.629 939.219 392.231 931.092 387.435C923.099 382.639 916.372 375.912 910.91 367.253C905.448 358.594 901.052 348.336 897.722 336.479C894.524 324.49 892.193 311.435 890.728 297.314V270.937C892.193 256.016 894.458 242.428 897.522 230.172C900.719 217.783 905.048 207.126 910.51 198.2C916.106 189.275 922.9 182.414 930.893 177.618C938.886 172.822 948.411 170.425 959.468 170.425C971.058 170.425 981.249 172.489 990.041 176.619C998.833 180.749 1006.16 187.077 1012.02 195.603C1018.02 204.128 1022.48 214.919 1025.41 227.974C1028.47 240.896 1030.01 256.216 1030.01 273.934ZM983.447 291.119V273.934C983.447 262.877 982.714 253.352 981.249 245.359C979.916 237.233 977.652 230.572 974.455 225.377C971.391 220.181 967.461 216.318 962.665 213.787C958.002 211.256 952.207 209.99 945.28 209.99C939.152 209.99 933.757 211.256 929.094 213.787C924.432 216.318 920.502 219.848 917.304 224.377C914.107 228.774 911.576 234.036 909.711 240.164C907.846 246.158 906.647 252.686 906.114 259.747V308.704C907.313 317.23 909.311 325.089 912.109 332.283C914.907 339.344 918.97 345.005 924.298 349.268C929.76 353.531 936.887 355.663 945.68 355.663C952.474 355.663 958.269 354.331 963.064 351.666C967.86 349.002 971.724 345.005 974.654 339.677C977.718 334.348 979.916 327.687 981.249 319.694C982.714 311.568 983.447 302.043 983.447 291.119ZM1110.54 174.421V390.632H1063.98V174.421H1110.54ZM1061.59 117.87C1061.59 110.41 1063.85 104.216 1068.38 99.2866C1072.91 94.2244 1079.24 91.6933 1087.36 91.6933C1095.49 91.6933 1101.82 94.2244 1106.35 99.2866C1110.88 104.216 1113.14 110.41 1113.14 117.87C1113.14 125.064 1110.88 131.125 1106.35 136.055C1101.82 140.984 1095.49 143.448 1087.36 143.448C1079.24 143.448 1072.91 140.984 1068.38 136.055C1063.85 131.125 1061.59 125.064 1061.59 117.87ZM1213.06 390.632H1166.5V153.839C1166.5 137.72 1169.17 124.198 1174.5 113.274C1179.96 102.217 1187.62 93.8247 1197.48 88.0964C1207.47 82.3681 1219.26 79.5039 1232.84 79.5039C1236.97 79.5039 1241.04 79.8369 1245.03 80.503C1249.03 81.0359 1252.83 81.9018 1256.42 83.1008L1255.43 121.068C1253.56 120.535 1251.23 120.135 1248.43 119.869C1245.77 119.602 1243.17 119.469 1240.64 119.469C1234.78 119.469 1229.78 120.801 1225.65 123.466C1221.52 126.13 1218.39 129.993 1216.26 135.055C1214.13 140.118 1213.06 146.379 1213.06 153.839V390.632ZM1248.23 174.421V210.39H1139.33V174.421H1248.23ZM1328.37 366.853L1374.73 174.421H1423.69L1352.35 423.404C1350.75 428.865 1348.49 434.727 1345.55 440.988C1342.76 447.249 1339.09 453.178 1334.56 458.773C1330.17 464.501 1324.64 469.097 1317.98 472.561C1311.45 476.158 1303.66 477.956 1294.6 477.956C1290.87 477.956 1287.27 477.623 1283.81 476.957C1280.34 476.291 1277.01 475.491 1273.82 474.559V436.992C1274.88 437.125 1276.08 437.258 1277.41 437.391C1278.75 437.525 1279.95 437.591 1281.01 437.591C1287.54 437.591 1292.93 436.592 1297.2 434.594C1301.46 432.729 1304.99 429.665 1307.79 425.402C1310.59 421.272 1313.05 415.744 1315.18 408.816L1328.37 366.853ZM1307.79 174.421L1345.75 335.281L1353.95 387.435L1321.98 395.628L1257.63 174.421H1307.79Z"
                                fill="#191E23" fill-opacity="0.9"></path>
                        <defs>
                            <linearGradient id="paint0_linear_1101_8228" x1="8.79553" y1="7.08742" x2="168.854"
                                            y2="274.752" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#00A0D2"></stop>
                                <stop offset="1" stop-color="#826EB4"></stop>
                            </linearGradient>
                            <linearGradient id="paint1_linear_1101_8228" x1="8.72787" y1="7.08742" x2="169.369"
                                            y2="273.659" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#00A0D2"></stop>
                                <stop offset="1" stop-color="#826EB4"></stop>
                            </linearGradient>
                            <linearGradient id="paint2_linear_1101_8228" x1="9.17569" y1="7.08742" x2="153.168"
                                            y2="338.084" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#03C5FF"></stop>
                                <stop offset="1" stop-color="#7F54B3"></stop>
                            </linearGradient>
                            <linearGradient id="paint3_linear_1101_8228" x1="-31.232" y1="-62.453" x2="98.4039"
                                            y2="269.464" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#03C5FF"></stop>
                                <stop offset="1" stop-color="#7F54B3"></stop>
                            </linearGradient>
                            <linearGradient id="paint4_linear_1101_8228" x1="233.88" y1="-46.7434" x2="549.504"
                                            y2="65.3096" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#03C5FF"></stop>
                                <stop offset="1" stop-color="#7F54B3"></stop>
                            </linearGradient>
                        </defs>
                    </svg>
                </a>
            </div>
        </div>
		<?php
		if ( ! empty( $data['sections'] ) && is_array( $data['sections'] ) ) {
			?>
            <div class="wpify__menu-section-bar">
				<?php foreach ( $data['sections'] as $id => $section ) { ?>
                    <a class="wpify__menu-section-bar-item <?php echo $current_page === $section['menu_slug'] ? 'current' : '' ?>"
                       href="<?php echo $section['url'] ?>"
                       title="<?php echo $section['title'] ?>"><?php echo $section['title'] ?></a>
				<?php } ?>
            </div>
			<?php
		}
	}
}
