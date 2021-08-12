<?php

namespace WpifyWooCore\Managers;

use WpifyWooCore\Api\LicenseApi;
use WpifyWooCore\Api\SettingsApi;

class ApiManager {
	private $modules = [];

	public function __construct( LicenseApi $license_api, SettingsApi $settings_api ) {
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