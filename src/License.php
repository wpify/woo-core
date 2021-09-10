<?php

namespace Wpify\WpifyWooCore;

use Wpify\Log\Log;
use Wpify\WpifyWooCore\Abstracts\AbstractModule;
use Wpify\WpifyWooCore\Admin\Settings;
use Wpify\WpifyWooCore\Managers\ModulesManager;
use Wpify\Log\RotatingFileLog;

/**
 * Class License
 * @package WpifyWoo
 */
class License {
	const API_KEY = 'ck_b543732d2aa924962757690d0d929c043c3f37c1';
	const API_SECRET = 'cs_5d3605fd909d8e6c1aed7ad19ee0c569ca50d32a';
	/**
	 * @var Log
	 */
	private $logger;
	/**
	 * @var ModulesManager
	 */
	private $modules_manager;
	/**
	 * @var Settings
	 */
	private $settings;

	public function __construct( RotatingFileLog $logger, ModulesManager $modules_manager ) {
		$this->logger          = $logger;
		$this->modules_manager = $modules_manager;

		add_action( 'init', [ $this, 'validate_modules_licenses' ] );
	}

	public function validate_modules_licenses() {
		foreach ( $this->modules_manager->get_modules() as $module ) {
			if ( $module->requires_activation() && $module->is_activated() ) {
				$this->maybe_schedule_as_validate_action( $module );
				add_action( "wpify_woo_check_activation_{$module->id()}", array( $this, 'validate_license' ), 10, 2 );
			}
		}
	}


	/**
	 * Maybe schedule the license validation AS event
	 */
	public function maybe_schedule_as_validate_action( $module ) {
		$option_activated      = $module->decrypt_option_activated();
		$next_scheduled_action = as_next_scheduled_action( "wpify_woo_check_activation_{$module->id()}" );

		if ( $module->id() && false === $next_scheduled_action && $option_activated ) {
			$data              = (array) $option_activated;
			$data['slug']      = $data['plugin'];
			$data['module_id'] = $module->id();
			$data['site-url']  = defined( 'ICL_LANGUAGE_CODE' ) ? get_option( 'siteurl' ) : site_url();

			$args = array(
				'license' => $option_activated->license,
				'data'    => $data,
			);
			as_schedule_recurring_action( strtotime( 'tomorrow' ), DAY_IN_SECONDS, "wpify_woo_check_activation_{$module->id()}", $args );
		}
	}


	/**
	 * Activate the license
	 *
	 * @param $license
	 * @param $data
	 *
	 * @return \WP_Error
	 */
	public function activate_license( $license, $data ) {
		$response = wp_remote_get(
			add_query_arg( $data, $this->get_activation_url() . $license ),
			$this->get_request_args()
		);

		$result = json_decode( wp_remote_retrieve_body( $response ) );
		$code   = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new \WP_Error( $code, $result->message );
		}

		$module = $this->modules_manager->get_module_by_id( $data['module_id'] );

		if ( $module ) {
			/** @var AbstractModule $module */
			$module->save_option_activated( $result->data->crypted_message );
			$module->save_option_public_key( $result->data->public_key );
			$module->save_option_license( $license );
		}

		return $result;
	}

	/**
	 * Get Activation URL
	 * @return string
	 */
	public function get_activation_url(): string {
		return $this->get_license_url() . 'activate/';
	}

	/**
	 * Get License API URL
	 * @return string
	 */
	public function get_license_url(): string {
		return $this->get_base_url() . 'licenses/';
	}

	/**
	 * Get Base URL
	 * @return string
	 */
	public function get_base_url(): string {
		return 'https://wpify.io/wp-json/lmfwc/v2/';
	}

	/**
	 * Get request args
	 * @return array
	 */
	public function get_request_args(): array {
		return array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this::API_KEY . ':' . $this::API_SECRET ),
			),
			'timeout' => 30,
		);
	}

	/**
	 * Deactivate the license
	 *
	 * @param $license
	 * @param $data
	 *
	 * @return \WP_Error
	 */
	public function deactivate_license( $license, $data ) {
		$response = wp_remote_get(
			add_query_arg( $data, $this->get_deactivation_url() . $license ),
			$this->get_request_args()
		);

		$result = json_decode( wp_remote_retrieve_body( $response ) );
		$code   = wp_remote_retrieve_response_code( $response );
		$module = $this->modules_manager->get_module_by_id( $data['module_id'] );

		if ( $module ) {
			/** @var AbstractModule $module */
			$module->delete_option_activated();
			$module->delete_option_public_key();
		}

		if ( 200 !== $code ) {
			return new \WP_Error( $code, $result->message );
		}

		return $result;
	}

	/**
	 * Get Deactivation URL
	 * @return string
	 */
	public function get_deactivation_url(): string {
		return $this->get_license_url() . 'deactivate/';
	}

	/**
	 * Validate license
	 *
	 * @param $license
	 * @param $data
	 */
	public function validate_license( $license, $data ) {
		$response = wp_remote_get(
			add_query_arg( $data, $this->get_license_url() . $license ),
			$this->get_request_args()
		);

		if ( is_wp_error( $response ) ) {
			// Don't do anything, the request failed for some reason
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );

		$this->logger->info( 'Revalidated license',
			[
				'data' =>
					[
						'license'       => $license,
						'data'          => $data,
						'response_code' => $code,
						'response_body' => wp_remote_retrieve_body( $response ),
					],
			]
		);

		if ( 200 !== $code ) {
			// If we don't get response 200, the license is not valid!
			$module = $this->modules_manager->get_module_by_id( $data['module_id'] );
			if ( $module ) {
				/** Abstract Module @var AbstractModule $module */
				$module->delete_option_activated();
				$module->delete_option_public_key();
			}
		}
	}
}
