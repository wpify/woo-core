<?php

namespace Wpify\WpifyWooCore\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Wpify\WpifyWooCore\License;
use Wpify\WpifyWooCore\Managers\ApiManager;

/**
 */
class LicenseApi extends \WP_REST_Controller {
	/**
	 * @var License
	 */
	private $license;

	/**
	 * ExampleApi constructor.
	 */
	public function __construct( License $license ) {
		$this->license = $license;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			ApiManager::REST_NAMESPACE,
			'license/activate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'activate_license' ),
					'permission_callback' => function () {
						return current_user_can( 'administrator' );
					},
				),
			)
		);
		register_rest_route(
			ApiManager::REST_NAMESPACE,
			'license/deactivate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'deactivate_license' ),
					'permission_callback' => function () {
						return current_user_can( 'administrator' );
					},
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Request|WP_REST_Response | bool
	 */
	public function activate_license( $request ) {
		$data             = $request->get_params();
		$data['site-url'] = defined( 'ICL_LANGUAGE_CODE' ) ? get_option( 'siteurl' ) : site_url();

		$result = $this->license->activate_license( $request->get_param( 'license' ), $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Request|WP_REST_Response | bool
	 */
	public function deactivate_license( $request ) {
		$data             = $request->get_params();
		$data['site-url'] = defined( 'ICL_LANGUAGE_CODE' ) ? get_option( 'siteurl' ) : site_url();

		$result = $this->license->deactivate_license( $request->get_param( 'license' ), $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Check if a given request has access to create items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		return true;
	}

	/**
	 * Prepare the item for the REST response
	 *
	 * @param mixed $item WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return mixed
	 */
	public function prepare_item_for_response( $item, $request ) {
		return array();
	}
}
