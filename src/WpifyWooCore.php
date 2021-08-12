<?php

namespace WpifyWooCore;

use WpifyWooCore\Admin\Settings;
use WpifyWooCore\Managers\ApiManager;
use WpifyWooCore\Managers\ModulesManager;

class WpifyWooCore {
	/**
	 * @var WooCommerceIntegration
	 */
	private $woocommerce_integration;
	/**
	 * @var Settings
	 */
	private $settings;
	/**
	 * @var ModulesManager
	 */
	private $modules_manager;
	/**
	 * @var ApiManager
	 */
	private $api_manager;

	public function __construct(
		WooCommerceIntegration $woocommerce_integration,
		Settings $settings,
		ModulesManager $modules_manager,
		ApiManager $api_manager
	) {
		$this->woocommerce_integration = $woocommerce_integration;
		$this->settings                = $settings;
		$this->modules_manager         = $modules_manager;
		$this->api_manager             = $api_manager;
	}

	/**
	 * @return ModulesManager
	 */
	public function get_modules_manager(): ModulesManager {
		return $this->modules_manager;
	}

	/**
	 * @return Settings
	 */
	public function get_settings(): Settings {
		return $this->settings;
	}
}