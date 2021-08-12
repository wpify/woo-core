<?php

namespace WpifyWooCore\Managers;

use WpifyWooCore\Api\LicenseApi;
use WpifyWooCore\Api\SettingsApi;

class ApiManager {
	public const REST_NAMESPACE = 'wpify-woo/v1';
	public const NONCE_ACTION = 'wp_rest';

	private $modules = [];

	public function __construct( LicenseApi $license_api, SettingsApi $settings_api ) {
	}

	public function get_rest_url() {
		return rest_url( $this->get_rest_namespace() );
	}

	public function get_rest_namespace() {
		return $this::REST_NAMESPACE;
	}

	public function get_nonce_action() {
		return $this::NONCE_ACTION;
	}


	public function add_module( $id, $module ) {
		$this->modules[ $id ] = $module;
	}

	/**
	 * @return array
	 */
	public function get_modules(): array {
		return $this->modules;
	}

	public function get_module_by_id( $id ) {
		return $this->modules[ $id ] ?? null;
	}
}