<?php

namespace Wpify\WooCore;

use Wpify\WooCore\Admin\Settings;
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

	public function __construct(
		WooCommerceIntegration $woocommerce_integration,
		Settings $settings,
		ModulesManager $modules_manager
	) {
		$this->woocommerce_integration = $woocommerce_integration;
		$this->settings                = $settings;
		$this->modules_manager         = $modules_manager;
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