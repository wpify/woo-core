<?php

namespace Wpify\WooCore;

use Wpify\WooCore\Admin\Settings;
use Wpify\WooCore\Managers\ApiManager;
use Wpify\WooCore\Managers\ModulesManager;

class WpifyWooCore {
	const PATH = __DIR__;
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
	/**
	 * @var Updates
	 */
	private $updates;

	public function __construct(
		WooCommerceIntegration $woocommerce_integration,
		Settings $settings,
		ModulesManager $modules_manager,
		ApiManager $api_manager,
		Updates $updates
	) {
		$this->woocommerce_integration = $woocommerce_integration;
		$this->settings                = $settings;
		$this->modules_manager         = $modules_manager;
		$this->api_manager             = $api_manager;
		$this->updates                 = $updates;
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

	/**
	 * @return Updates
	 */
	public function get_updates(): Updates {
		return $this->updates;
	}

}