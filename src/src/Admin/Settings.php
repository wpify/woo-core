<?php

namespace WpifyWooCore\Admin;

use WC_Admin_Settings;
use WpifyWooCore\Plugin;
use WpifyWooCore\WooCommerceIntegration;
use \Wpify\Core\Abstracts\AbstractComponent;

/**
 * Class Settings
 * @package WpifyWooCore\Admin
 * @property Plugin $plugin
 */
class Settings {
	private $id;
	private $label;
	private $pages;

	public function __construct(  ) {
		$this->id    = WooCommerceIntegration::OPTION_NAME;
		$this->label = __( 'Wpify Woo', 'wpify-woo' );

		add_action( 'init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
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

	public function removable_query_args( array $args ) {
		$args[] = 'wpify-woo-license-activated';
		$args[] = 'wpify-woo-license-deactivated';

		return $args;
	}

	public function register_settings() {
		$sections = $this->get_sections();

		foreach ( $sections as $id => $label ) {
			$this->pages[ $id ] = $this->plugin->get_wcf()->add_woocommerce_settings( array(
					'tab'     => array(
							'id'    => $this->id,
							'label' => $this->label,
					),
					'section' => array(
							'id'    => $id,
							'label' => $label,
					),
					'class'   => 'wpify-woo-settings',
					'items'   => $this->is_current( $this->id, $id ) ? $this->get_settings() : array(),
			) );
		}
	}

	/**
	 *  Get sections
	 * @return array
	 */
	public function get_sections(): array {
		$sections = array(
				'' => __( 'General', 'wpify-woo' ),
		);

		$sections = apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );

		return $sections;
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
	 *  Get settings array
	 * @return array
	 */
	public function get_settings() {
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
				$module       = $this->plugin->get_modules_manager()->get_module_by_id( $current_section );
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
						'id'    => $this->get_plugin()->get_woocommerce_integration()->get_settings_name( $current_section ),
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
						'options' => $this->get_plugin()->get_woocommerce_integration()->get_modules(),
						'desc'    => __( 'Select the modules you want to enable', 'wpify-woo' ),
				),
		);
	}

	public function enqueue_admin_scripts() {
		$rest_url = $this->get_plugin()->get_api_manager()->get_rest_url();
		$handle   = $this->get_plugin()->get_webpack_manifest()->register_asset(
				'settings.js',
				'wpify-settings',
				array(
						'WpifyWooCoreSettings' => array(
								'publicPath'    => $this->plugin->get_asset_url( 'build/' ),
								'restUrl'       => $this->plugin->get_api_manager()->get_rest_url(),
								'nonce'         => wp_create_nonce( $this->get_plugin()->get_api_manager()->get_nonce_action() ),
								'activateUrl'   => $rest_url . '/license/activate',
								'deactivateUrl' => $rest_url . '/license/deactivate',
								'apiKey'        => $this->plugin->get_license()::API_KEY,
								'apiSecret'     => $this->plugin->get_license()::API_SECRET,
						),
				)
		);

		wp_enqueue_script( $handle );
		wp_set_script_translations( $handle, 'wpify-woo', $this->get_plugin()->get_asset_path( 'languages' ) );
		wp_enqueue_style( $this->get_plugin()->get_webpack_manifest()->register_asset( 'settings.css' ) );
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
						<?php foreach ( $this->plugin->get_premium()->get_extensions() as $extension ) { ?>
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
			printf( '<a href="%s">%s</a>', add_query_arg( [ 'wpify-action' => 'download-log', 'wpify-nonce' => wp_create_nonce( 'download-log' ) ], admin_url() ), __( 'Download log', 'wpify-woo' ) );
		}
	}
}