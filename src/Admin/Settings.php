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
 * @package WpifyWooCore\Admin
 */
class Settings {
	const OPTION_NAME    = 'wpify-woo-settings';
	const DASHBOARD_SLUG = 'wpify';

	private $id;
	private $label;
	private $pages;

	/** @var CustomFields */
	private $custom_fields;

	/** @var WooCommerceIntegration */
	private $woocommerce_integration;

	/** @var Premium */
	private $premium;

	/** @var ModulesManager */
	private $modules_manager;

	/** @var AssetFactory */
	private $asset_factory;

	private $initialized;

	public function __construct( CustomFields $custom_fields, WooCommerceIntegration $woocommerce_integration, Premium $premium, ModulesManager $modules_manager, AssetFactory $asset_factory ) {
		$this->custom_fields           = $custom_fields;
		$this->woocommerce_integration = $woocommerce_integration;
		$this->premium                 = $premium;
		$this->modules_manager         = $modules_manager;
		$this->asset_factory           = $asset_factory;
		$this->id                      = $this::OPTION_NAME;
		$this->label                   = __( 'WPify Woo', 'wpify-woo' );

		add_action( 'init', array( $this, 'register_settings' ) );

		// Check if the WpifyWoo Core settings have been initialized already
		$this->initialized = apply_filters( 'wpify_woo_core_settings_initialized', false );

		if ( ! $this->initialized ) {
			add_filter( 'wpify_woo_core_settings_initialized', '__return_true' );
			add_filter( 'admin_body_class', array( $this, 'add_admin_body_class' ), 9999 );
			add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
			add_action( 'wcf_before_fields', array( $this, 'render_before_settings' ) );
			add_action( 'wcf_after_fields', array( $this, 'render_after_settings' ) );
			add_action( 'admin_menu', [ $this, 'register_menu_page' ] );
			//add_action( 'wpifycf_before_options', [ $this, 'render_menu_bar' ] );
			add_action( 'in_admin_header', [ $this, 'render_menu_bar' ] );

			/** Handle activation/deactivation messages */
			if ( ! empty( $_REQUEST['wpify-woo-license-activated'] ) && $_REQUEST['wpify-woo-license-activated'] === '1' ) {
				WC_Admin_Settings::add_message( __( 'Your license has been activated.', 'wpify-woo' ) );
			}
			if ( ! empty( $_REQUEST['wpify-woo-license-deactivated'] ) && $_REQUEST['wpify-woo-license-deactivated'] === '1' ) {
				WC_Admin_Settings::add_message( __( 'Your license has been deactivated.', 'wpify-woo' ) );
			}
		}
	}

	public function removable_query_args( array $args ) {
		$args[] = 'wpify-woo-license-activated';
		$args[] = 'wpify-woo-license-deactivated';

		return $args;
	}

	public function register_settings() {
		$sections = $this->get_sections();
		foreach ( $sections as $id => $label ) {
			if ( ! $this->initialized && ! $id || in_array( $id, $this->get_enabled_modules() ) && $this->modules_manager->get_module_by_id( $id ) ) {
				$module = $this->modules_manager->get_module_by_id( $id );
				if ( empty( $id ) || $module->get_settings_version() === 2 ) {
					$id                 = $id ?: 'general';
					$this->pages[ $id ] = $this->custom_fields->create_options_page( array(
						'page_title'  => $module ? $module->name() : 'General',
						'menu_title'  => $module ? $module->name() : 'General',
						'menu_slug'   => sprintf( 'wpify/%s', $id ),
						'id'          => $id ?: 'general',
						'parent_slug' => $this::DASHBOARD_SLUG,
						'class'       => 'wpify-woo-settings',
						'option_name' => $this->get_settings_name( $id ?: 'general' ),
						'tabs'        => $this->is_current( '', $id ) ? $this->get_settings_tabs() : array(),
						'items'       => $this->is_current( '', $id ) ? $this->get_settings_items() : array()
					) );
				} else {
					$this->pages[ $id ] = $this->custom_fields->create_woocommerce_settings( array(
						'tab'         => array(
							'id'    => $this->id,
							'label' => $this->label
						),
						'section'     => array(
							'id'    => $id,
							'label' => $label
						),
						'id'          => $id ?: 'general',
						'class'       => 'wpify-woo-settings',
						'option_name' => $this->get_settings_name( $id ?: 'general' ),
						'tabs'        => $this->is_current( $this->id, $id ) ? $this->get_settings_tabs() : array(),
						'items'       => $this->is_current( $this->id, $id ) ? $this->get_settings_items() : array()
					) );
				}
			}
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
	 * @return array
	 */
	public function get_sections(): array {
		$current_page = empty( $_REQUEST['page'] ) ? '' : $_REQUEST['page'];
		if ( ! str_contains( $current_page, 'wpify/' ) ) {
			return [];
		}
		$subpage = explode( '/', $current_page )[1] ?? '';

		$sections = array( '' => __( 'General', 'wpify-woo' ) );
		//$sections = \apply_filters( 'woocommerce_get_sections_' . $this->id, [] );
		$sections = apply_filters( 'wpify_get_sections_' . $subpage, [] );

		return $sections;
	}

	/**
	 * Get an array of enabled modules
	 * @return array
	 */
	public function get_enabled_modules(): array {
		return $this->get_settings( 'general' )['enabled_modules'] ?? array();
	}

	public function enable_module( $module ) {
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
	 * @param string $module Module name.
	 *
	 * @return array
	 */
	public function get_settings( string $module ): array {
		return get_option( $this->get_settings_name( $module ), array() );
	}

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

		return false;
	}

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

		return explode( '/', $current_page )[1] ?? 'general';
	}

	/**
	 * Get settings array
	 * @return array
	 */
	public function get_settings_items() {
		global $current_section;

		$settings = array();

		if ( $current_section === null && isset( $_GET['section'] ) ) {
			$current_section = sanitize_title( $_GET['section'] );
		}

		if ( $this->get_current_module() ) {
			$current_section = $this->get_current_module();
		}

		if ( ! $current_section ) {
			$current_section = 'general';
		}

		if ( 'general' === $current_section ) {
			$settings = $this->settings_general();
		}

		$settings = apply_filters( 'wpify_woo_settings_' . $current_section, $settings );
		$settings = apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );

		return $settings;
	}

	/**
	 * Get settings tabs array
	 * @return array
	 */
	public function get_settings_tabs() {
		global $current_section;

		$tabs = array();

		if ( $current_section === null && isset( $_GET['section'] ) ) {
			$current_section = sanitize_title( $_GET['section'] );
		}

		if ( $this->get_current_module() ) {
			$current_section = $this->get_current_module();
		}

		if ( ! $current_section ) {
			$current_section = 'general';
		}

		return apply_filters( 'wpify_woo_settings_tabs_' . $current_section, $tabs );
	}

	public function settings_general() {
		return array(
			array(
				'type'    => 'multi_toggle',
				'id'      => 'enabled_modules',
				'label'   => __( 'Enabled modules', 'wpify-woo' ),
				'options' => $this->woocommerce_integration->get_modules(),
				'desc'    => __( 'Select the modules you want to enable', 'wpify-woo' ),
			),
		);
	}

	public function render_before_settings( $args ) {
		if ( $args['object_type'] === 'woocommerce_settings' && $args['tab']['id'] === 'wpify-woo-settings' && empty( $args['section']['id'] ) ) {
			?>
            <div class="wpify-woo-settings__wrapper">
			<?php
		}
	}

	public function render_after_settings( $args ) {
		if ( $args['object_type'] === 'woocommerce_settings' && $args['tab']['id'] === 'wpify-woo-settings' && empty( $args['section']['id'] ) ) {
			?>
            <div class="wpify-woo-settings__upsells-wrapper">
                <div class="wpify-woo-settings__upsells-inner">
                    <h3>
						<?php
						_e( 'Do you enjoy this plugin?', 'wpify-woo' );
						?>
                        <a href="https://wordpress.org/support/plugin/wpify-woo/reviews/">
							<?php
							_e( 'Rate us on Wordpress.org', 'wpify-woo' );
							?>
                        </a>
                    </h3>
                    <h2><?php
						_e( 'Get premium extensions!', 'wpify-woo' );
						?></h2>
                    <div class="wpify-woo-settings__upsells">
						<?php
						foreach ( $this->premium->get_extensions() as $extension ) {
							?>
                            <div class="wpify-woo-settings__upsell">
                                <h3><?php
									echo $extension['title'];
									?></h3>
                                <ul>
									<?php
									foreach ( $extension['html_description'] as $item ) {
										?>
                                        <li><?php
											echo $item;
											?></li>
										<?php
									}
									?>
                                </ul>
                                <a href="<?php
								echo esc_url( $extension['url'] );
								?>"
                                   target="_blank"><?php
									_e( 'Get now!', 'wpify-woo' );
									?></a>
                            </div>
							<?php
						}
						?>
                    </div>
                </div>
            </div>
            </div>

			<?php
			printf( '<a href="%s">%s</a>', add_query_arg( [
				'wpify-action' => 'download-log',
				'wpify-nonce'  => wp_create_nonce( 'download-log' ),
			], admin_url() ), __( 'Download log', 'wpify-woo' ) );
		}
	}

	public function register_menu_page() {
		add_menu_page(
			__( 'WPify Plugins', 'wpify' ),
			__( 'WPify', 'wpify' ),
			'manage_options',
			$this::DASHBOARD_SLUG,
			[ $this, 'render_dashboard' ],
			'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTUwIiBoZWlnaHQ9IjU1MCIgdmlld0JveD0iMCAwIDU1MCA1NTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IG9wYWNpdHk9IjAuMyIgd2lkdGg9IjUzMiIgaGVpZ2h0PSI3OCIgcng9IjM5IiB0cmFuc2Zvcm09Im1hdHJpeCgtNC4zNzExNGUtMDggMSAxIDQuMzcxMTRlLTA4IDM2Ny43NSA5LjAwMDEyKSIgZmlsbD0id2hpdGUiLz4KPHJlY3Qgb3BhY2l0eT0iMC4zIiB3aWR0aD0iNTMwIiBoZWlnaHQ9Ijc4IiByeD0iMzkiIHRyYW5zZm9ybT0ibWF0cml4KC00LjM3MTE0ZS0wOCAxIDEgNC4zNzExNGUtMDggMjA0Ljc1IDkuMDAwMTIpIiBmaWxsPSJ3aGl0ZSIvPgo8cmVjdCBvcGFjaXR5PSIwLjgiIHdpZHRoPSI1NTcuODgyIiBoZWlnaHQ9Ijc4LjE1ODUiIHJ4PSIzOS4wNzkyIiB0cmFuc2Zvcm09Im1hdHJpeCgwLjMzODc4MSAwLjk0MDg2NSAwLjk0MDg2NSAtMC4zMzg3ODEgMzEuNzUgMjQuNDc4OCkiIGZpbGw9IndoaXRlIi8+CjxyZWN0IG9wYWNpdHk9IjAuOCIgd2lkdGg9IjU2MC42NzYiIGhlaWdodD0iNzguMTU4NSIgcng9IjM5LjA3OTIiIHRyYW5zZm9ybT0ibWF0cml4KDAuMzM4NzgxIDAuOTQwODY1IDAuOTQwODY1IC0wLjMzODc4MSAxOTMuMjQ5IDI0LjQ3ODgpIiBmaWxsPSJ3aGl0ZSIvPgo8cmVjdCBvcGFjaXR5PSIwLjgiIHdpZHRoPSIyNTkuNjQ2IiBoZWlnaHQ9Ijc4LjE1ODUiIHJ4PSIzOS4wNzkyIiB0cmFuc2Zvcm09Im1hdHJpeCgwLjMzODc4MSAwLjk0MDg2NSAwLjk0MDg2NSAtMC4zMzg3ODEgMzU2Ljc1IDI0LjQ3ODYpIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4=', // icon (from Dashicons for example)
			59,
		);

		add_submenu_page(
			$this::DASHBOARD_SLUG,
			__( 'WPify Plugins Dashboard', 'wpify' ),
			__( 'Dashboard', 'wpify' ),
			'manage_options',
			$this::DASHBOARD_SLUG,
			[ $this, 'render_dashboard' ],
		);

		do_action( 'wpify_woo_settings_menu_page_registered' );
	}

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

	public function get_wpify_plugins_overwiev() {
		$installed_plugins = $this->get_plugins();
		$extensions        = get_transient( 'wpify_woo_extensions' );

		if ( false === $extensions ) {
			$response = wp_remote_get( 'https://wpify.io/wp-json/wpify/v1/plugins-list' );

			if ( ! is_wp_error( $response ) ) {
				$extensions = json_decode( $response['body'], true )['plugins'];
				set_transient( 'wpify_woo_extensions', $extensions, DAY_IN_SECONDS );
			}
		}

		$extensions_map = array();
		foreach ( $extensions as $extension ) {
			$extensions_map[ $extension['slug'] ] = $extension;
		}

		foreach ( $installed_plugins as $slug => $plugin ) {
			if ( isset( $extensions_map[ $slug ] ) ) {
				$installed_plugins[ $slug ] = array_merge( $plugin, $extensions_map[ $slug ] );
				unset( $extensions_map[ $slug ] );
			}
		}

		$html = sprintf( '<h2>%s</h2>', __( 'Installed plugins', 'wpify' ) );
		$html .= $this->get_wpify_modules_blocks( $installed_plugins, true );

		if ( $extensions ) {
			$html .= sprintf( '<h2>%s</h2>', __( 'Our other plugins', 'wpify' ) );
			$html .= $this->get_wpify_modules_blocks( $extensions_map );
		}

		return $html;
	}

	public function get_wpify_modules_blocks( $plugins, $installed = false ) {
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
							if ( ! $installed && isset( $plugin['price'] ) ) {
								$metas[] = $plugin['price'];
							}
							if ( isset( $plugin['rating'] ) ) {
								$metas[] = sprintf( '⭐ %s/5', $plugin['rating'] );
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
						if ( isset( $plugin['doc_link'] ) && $plugin['doc_link'] ) {
							?>
                            <a href="<?php
							echo esc_url( $plugin['doc_link'] );
							?>"
                               target="_blank"><?php
								_e( 'Documentation', 'wpify' );
								?></a>
							<?php
						}
						?>
                        <div style="flex: 1"></div>
						<?php
						if ( $installed && isset( $plugin['settings'] ) ) {
							?>
                            <span><a class="button" href="<?php
								echo esc_url( $plugin['settings'] );
								?>"
                                     role="button"><?php
									_e( 'Settings', 'wpify' );
									?></a></span>
							<?php
						} else {
							?>
                            <span><a class="install-now button" href="<?php
								echo esc_url( $plugin['link'] );
								?>"
                                     role="button"><?php
									_e( 'Get plugin', 'wpify' );
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

	public function get_wpify_posts() {
		$response = wp_remote_get( 'https://wpify.io/wp-json/wp/v2/posts?per_page=4&_embed' );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$posts = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $posts ) ) {
			return '';
		}

		?>
        <h2><?php _e( 'Wpify News', 'wpify' ) ?></h2>
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

	public function render_dashboard() {
		?>
        <div class="wpify-dashboard__wrap wrap">
            <div class="wpify-dashboard__content">
				<?php
				echo $this->get_wpify_plugins_overwiev();
				?>
            </div>
            <div class="wpify-dashboard__sidebar">
				<?php $this->get_wpify_posts(); ?>
            </div>
        </div>
		<?php
	}

	public function render_menu_bar() {
		/** @var \WP_Screen $screen */
		$screen       = get_current_screen();
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		if ( ! $screen || ! str_contains( $current_page, 'wpify' ) ) {
			return;
		}
		$sections = $this->get_sections();
		global $title;
		$data = array(
			'title'    => $title,
			'icon'     => '',
			'menu'     => array(
				array(
					'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 6.75c0-1.768 0-2.652.55-3.2C4.097 3 4.981 3 6.75 3s2.652 0 3.2.55c.55.548.55 1.432.55 3.2s0 2.652-.55 3.2c-.548.55-1.432.55-3.2.55s-2.652 0-3.2-.55C3 9.403 3 8.519 3 6.75m0 10.507c0-1.768 0-2.652.55-3.2c.548-.55 1.432-.55 3.2-.55s2.652 0 3.2.55c.55.548.55 1.432.55 3.2s0 2.652-.55 3.2c-.548.55-1.432.55-3.2.55s-2.652 0-3.2-.55C3 19.91 3 19.026 3 17.258M13.5 6.75c0-1.768 0-2.652.55-3.2c.548-.55 1.432-.55 3.2-.55s2.652 0 3.2.55c.55.548.55 1.432.55 3.2s0 2.652-.55 3.2c-.548.55-1.432.55-3.2.55s-2.652 0-3.2-.55c-.55-.548-.55-1.432-.55-3.2m0 10.507c0-1.768 0-2.652.55-3.2c.548-.55 1.432-.55 3.2-.55s2.652 0 3.2.55c.55.548.55 1.432.55 3.2s0 2.652-.55 3.2c-.548.55-1.432.55-3.2.55s-2.652 0-3.2-.55c-.55-.548-.55-1.432-.55-3.2"/></svg>',
					'label' => __( 'Dashboard', 'wpify' ),
					'link'  => add_query_arg( [ 'page' => $this::DASHBOARD_SLUG ], admin_url( 'admin.php' ) )
				)
			),
			'sections' => $sections,
			'doc_link' => 'https://wpify.io/dokumentace/'
		);
		$data = apply_filters( 'wpify_admin_menu_bar_data', $data );
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
            }

            .wpify__menu-bar-column {
                display: flex;
                align-content: center;
                flex-wrap: wrap;
                gap: 20px;
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
                justify-content: start;
                align-content: center;
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

            form {
                display: flex;
                background: white;
                gap: 20px;
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

            .form-table {
                @media screen and (min-width: 783px) {
                    width: 75%;
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
                    margin-top: 20px;
                }

                .wpify__card {
                    max-width: 300px;
                }
            }
        </style>
        <div class="wpify__menu-bar">
            <div class="wpify__menu-bar-column">
				<?php
				if ( $data['icon'] ) {
					?>
                    <div class="wpify__plugin-icon"><?php
						echo $data['icon'];
						?></div>
					<?php
				}
				?>
                <div class="wpify__menu-bar-name"><?php
					echo esc_html( $title );
					?></div>
				<?php
				foreach ( $data['menu'] as $item ) {
					$url_components = parse_url( $item['link'] );
					parse_str( $url_components['query'], $query_params );
					$menu_page    = isset( $query_params['page'] ) ? $query_params['page'] : '';
					$active_class = ( $current_page === $menu_page ) ? ' current' : '';
					printf( '<a class="wpify__menu-bar-item%s" href="%s">%s<span>%s</span></a>', esc_attr( $active_class ), esc_url( $item['link'] ), $item['icon'], esc_html( $item['label'] ) );
				}
				if ( $data['doc_link'] ) {
					$doc_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"><path d="M5 20.25c0 .414.336.75.75.75h10.652C17.565 21 18 20.635 18 19.4v-1.445M5 20.25A2.25 2.25 0 0 1 7.25 18h10.152q.339 0 .598-.045M5 20.25V6.2c0-1.136-.072-2.389 1.092-2.982C6.52 3 7.08 3 8.2 3h9.2c1.236 0 1.6.437 1.6 1.6v11.8c0 .995-.282 1.425-1 1.555"/><path d="m9.6 10.323l1.379 1.575a.3.3 0 0 0 .466-.022L14.245 8"/></g></svg>';
					printf( '<a class="wpify__menu-bar-item" href="%s" target="_blank">%s<span>%s</span></a>', esc_url( $data['doc_link'] ), $doc_icon, __( 'Documentation', 'wpify' ) );
				}
				$support_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"><path d="M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0"/><path d="M12 13.496c0-2.003 2-1.503 2-3.506c0-2.659-4-2.659-4 0m2 6.007v-.5"/></g></svg>';
				printf( '<a class="wpify__menu-bar-item" href="%s">%s<span>%s</span></a>', esc_url( '#' ), $support_icon, __( 'Support', 'wpify' ) );
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
				<?php
				foreach ( $data['sections'] as $id => $section ) {
					$link = add_query_arg( array( 'page' => sprintf( 'wpify/%s', $id ) ), admin_url( 'admin.php' ) );
					?>
                    <a class="wpify__menu-section-bar-item <?php echo str_contains( $current_page, $id ) ? 'current' : '' ?>"
                       href="<?php echo $link ?>"
                       title="<?php echo $section ?>"><?php echo $section ?></a>
				<?php } ?>
            </div>
			<?php
		}
	}
}
