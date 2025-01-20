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
	const OPTION_NAME = 'wpify-woo-settings';

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

	public function __construct(
		CustomFields $custom_fields,
		WooCommerceIntegration $woocommerce_integration,
		Premium $premium,
		ModulesManager $modules_manager,
		AssetFactory $asset_factory
	) {
		$this->custom_fields           = $custom_fields;
		$this->woocommerce_integration = $woocommerce_integration;
		$this->premium                 = $premium;
		$this->modules_manager         = $modules_manager;
		$this->asset_factory           = $asset_factory;

		$this->id    = $this::OPTION_NAME;
		$this->label = __( 'Wpify Woo', 'wpify-woo' );

		add_action( 'init', array( $this, 'register_settings' ) );

		// Check if the WpifyWoo Core settings have been initialized already
		$this->initialized = apply_filters( 'wpify_woo_core_settings_initialized', false );
		if ( ! $this->initialized ) {
			add_filter( 'wpify_woo_core_settings_initialized', '__return_true' );
			add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
			add_action( 'wcf_before_fields', array( $this, 'render_before_settings' ) );
			add_action( 'wcf_after_fields', array( $this, 'render_after_settings' ) );
			add_action( 'admin_menu', [ $this, 'register_menu_page' ] );
			add_action('wpify_custom_fields_before_options',[$this,'render_menu_bar']);

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
                if ($module->get_settings_version() === 2) {
	                $this->pages[ $id ] = $this->custom_fields->create_options_page( array(
		                'page_title'  => $module->name(),
		                'menu_title'  => $module->name(),
		                'menu_slug'   => sprintf( 'wpify-woo/%s', $id ),
		                'id'          => $id ?: 'general',
		                'parent_slug' => 'wpify-woo',
		                'class'       => 'wpify-woo-settings',
		                'option_name' => $this->get_settings_name( $id ?: 'general' ),
		                'tabs'        => $this->is_current(  '', $id ) ? $this->get_settings_tabs() : array(),
		                'items'       => $this->is_current( '',  $id ) ? $this->get_settings_items() : array(),
	                ) );
                } else {
	                $this->pages[ $id ] = $this->custom_fields->create_woocommerce_settings(
		                array(
			                'tab'         => array(
				                'id'    => $this->id,
				                'label' => $this->label,
			                ),
			                'section'     => array(
				                'id'    => $id,
				                'label' => $label,
			                ),
			                'id'          => $id ?: 'general',
			                'class'       => 'wpify-woo-settings',
			                'option_name' => $this->get_settings_name( $id ?: 'general' ),
			                'tabs'        => $this->is_current( $this->id, $id ) ? $this->get_settings_tabs() : array(),
			                'items'       => $this->is_current( $this->id, $id ) ? $this->get_settings_items() : array(),
		                ),
	                );
                }
			}
		}
	}


	/**
	 * Get sections
	 *
	 * @return array
	 */
	public function get_sections(): array {
		$sections = \apply_filters( 'woocommerce_get_sections_' . $this->id, [] );

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
			return \true;
		}

		return false;
	}

	public function get_current_module(  ) {
		$current_page     = empty( $_REQUEST['page'] ) ? '' : $_REQUEST['page'];
		if (!str_contains($current_page, 'wpify-woo/')) {
			return false;
		}
		return explode('/', $current_page)[1] ?? 'general';
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

        if ($this->get_current_module()) {
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
			$current_section = \sanitize_title( $_GET['section'] );
		}

		if ($this->get_current_module()) {
			$current_section = $this->get_current_module();
		}

		if ( ! $current_section ) {
			$current_section = 'general';
		}

		return \apply_filters( 'wpify_woo_settings_tabs_' . $current_section, $tabs );
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

	public
	function render_before_settings(
		$args
	) {
		if ( $args['object_type'] === 'woocommerce_settings'
		     && $args['tab']['id'] === 'wpify-woo-settings'
		     && empty( $args['section']['id'] )
		) {
			?>
            <div class="wpify-woo-settings__wrapper">
			<?php
		}
	}

	public function render_after_settings( $args ) {
		if ( $args['object_type'] === 'woocommerce_settings'
		     && $args['tab']['id'] === 'wpify-woo-settings'
		     && empty( $args['section']['id'] )
		) {
			?>

            <div class="wpify-woo-settings__upsells-wrapper">
                <div class="wpify-woo-settings__upsells-inner">
                    <h3>
						<?php _e( 'Do you enjoy this plugin?', 'wpify-woo' ) ?>
                        <a href="https://wordpress.org/support/plugin/wpify-woo/reviews/">
							<?php _e( 'Rate us on Wordpress.org', 'wpify-woo' ) ?>
                        </a>
                    </h3>
                    <h2><?php _e( 'Get premium extensions!', 'wpify-woo' ) ?></h2>
                    <div class="wpify-woo-settings__upsells">
						<?php foreach ( $this->premium->get_extensions() as $extension ) { ?>
                            <div class="wpify-woo-settings__upsell">
                                <h3><?php echo $extension['title']; ?></h3>
                                <ul>
									<?php foreach ( $extension['html_description'] as $item ) { ?>
                                        <li><?php echo $item; ?></li>
									<?php } ?>
                                </ul>
                                <a href="<?php echo esc_url( $extension['url'] ); ?>"
                                   target="_blank"><?php _e( 'Get now!', 'wpify-woo' ); ?></a>
                            </div>
						<?php } ?>
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
			__( 'WPify Woo', 'wpify-woo' ),
			__( 'WPify Woo', 'wpify-woo' ),
			'manage_options', // user capabilities
			'wpify-woo-dashboard',
			[ $this, 'render_dashboard' ],
			'dashicons-images-alt2', // icon (from Dashicons for example)
			59
		);
		do_action( 'wpify_woo_settings_menu_page_registered' );
	}

	public function render_dashboard() { ?>
        <div class="wrap">
            <h1><?php _e( 'WPify Woo', 'wpify-woo' ); ?></h1>
            <p><?php _e( 'Welcome to WPify Woo!', 'wpify-woo' ); ?></p>
        </div>
	<?php }

	public function render_menu_bar( $options ) {
		if ($options->parent_slug !== 'wpify-woo') {
			return;
		} ?>
        <div style="background: white; padding: 20px;">
            <h1>WPIfy Woo</h1>
        </div>
		<?php
	}
}
