<?php

namespace Wpify\WpifyWooCore\Admin;

use WC_Admin_Settings;
use Wpify\Asset\AssetFactory;
use Wpify\CustomFields\CustomFields;
use Wpify\WpifyWooCore\License;
use Wpify\WpifyWooCore\Managers\ApiManager;
use Wpify\WpifyWooCore\Managers\ModulesManager;
use Wpify\WpifyWooCore\Premium;
use Wpify\WpifyWooCore\WooCommerceIntegration;
use Wpify\WpifyWooCore\WpifyWooCore;

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

	/** @var ApiManager */
	private $api_manager;

	/** @var AssetFactory */
	private $asset_factory;

	public function __construct(
		CustomFields $custom_fields,
		WooCommerceIntegration $woocommerce_integration,
		Premium $premium,
		ModulesManager $modules_manager,
		ApiManager $api_manager,
		AssetFactory $asset_factory
	) {
		$this->custom_fields           = $custom_fields;
		$this->woocommerce_integration = $woocommerce_integration;
		$this->premium                 = $premium;
		$this->modules_manager         = $modules_manager;
		$this->api_manager             = $api_manager;
		$this->asset_factory           = $asset_factory;

		$this->id    = $this::OPTION_NAME;
		$this->label = __( 'Wpify Woo', 'wpify-woo' );

		// Check if the WpifyWoo Core settings have been initialized already
		if ( ! apply_filters( 'wpify_woo_core_settings_initialized', false ) ) {
			add_action( 'init', array( $this, 'register_settings' ) );
			add_action( 'init', array( $this, 'enqueue_admin_scripts' ) );
			add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
			add_action( 'wcf_before_fields', array( $this, 'render_before_settings' ) );
			add_action( 'wcf_after_fields', array( $this, 'render_after_settings' ) );

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
			if ( ! $id || in_array( $id, $this->get_enabled_modules() ) ) {
				$this->pages[ $id ] = $this->custom_fields->create_woocommerce_settings( array(
					'tab'     => array(
						'id'    => $this->id,
						'label' => $this->label,
					),
					'section' => array(
						'id'    => $id,
						'label' => $label,
					),
					'class'   => 'wpify-woo-settings',
					'items'   => $this->is_current( $this->id, $id ) ? $this->get_settings_items() : array(),
				) );
			}
		}
	}

	/**
	 * Get sections
	 * @return array
	 */
	public function get_sections(): array {
		$sections = array(
			'' => __( 'General', 'wpify-woo' ),
		);

		$sections = apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );

		return $sections;
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

	public function is_current( $tab = '', $section = '' ): bool {
		$current_tab     = empty( $_REQUEST['tab'] ) ? '' : $_REQUEST['tab'];
		$current_section = empty( $_REQUEST['section'] ) ? '' : $_REQUEST['section'];

		if ( $tab === $current_tab && $section === $current_section ) {
			return true;
		}

		return false;
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

		if ( ! $current_section ) {
			$current_section = 'general';
			$settings        = $this->settings_general();
		}

		$settings = apply_filters( 'wpify_woo_settings_' . $current_section, $settings );
		$settings = apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );

		foreach ( $settings as $key => $setting ) {
			if ( $setting['type'] === 'license' ) {
				$module       = $this->modules_manager->get_module_by_id( $current_section );
				$is_activated = $module->is_activated();

				$settings[ $key ]['activated'] = $is_activated ? 1 : 0;
				$settings[ $key ]['moduleId']  = $module->id();

				if ( ! $is_activated ) {
					$settings = array( $settings[ $key ] );

					break;
				}
			}
		}

		$settings = array(
			array(
				'type'  => 'group',
				'id'    => $this->get_settings_name( $current_section ),
				'title' => $this->label,
				'items' => $settings,
			),
		);

		return $settings;
	}

	public function settings_general() {
		return array(
			array(
				'type'    => 'multiswitch',
				'id'      => 'enabled_modules',
				'label'   => __( 'Enabled modules', 'wpify-woo' ),
				'options' => $this->woocommerce_integration->get_modules(),
				'desc'    => __( 'Select the modules you want to enable', 'wpify-woo' ),
			),
		);
	}

	public function enqueue_admin_scripts() {
		$rest_url = $this->api_manager->get_rest_url();

		$this->asset_factory->wp_script( dirname( WpifyWooCore::PATH ) . '/build/settings.css', [ 'is_admin' => true ] );
		$this->asset_factory->wp_script( dirname( WpifyWooCore::PATH ) . '/build/settings.js', [
			'is_admin'  => true,
			'variables' => [
				'wpifyWooCoreSettings' => array(
					'publicPath'    => dirname( WpifyWooCore::PATH ) . '/build/',
					'restUrl'       => $rest_url,
					'nonce'         => wp_create_nonce( $this->api_manager->get_nonce_action() ),
					'activateUrl'   => $rest_url . '/license/activate',
					'deactivateUrl' => $rest_url . '/license/deactivate',
					'apiKey'        => License::API_KEY,
					'apiSecret'     => License::API_SECRET,
				),
			],
		] );

		wp_set_script_translations( 'wpify-woo-settings.js', 'wpify-woo', '/languages' );
	}

	public function render_before_settings( $args ) {
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
				'wpify-nonce'  => wp_create_nonce( 'download-log' )
			], admin_url() ), __( 'Download log', 'wpify-woo' ) );
		}
	}


}
