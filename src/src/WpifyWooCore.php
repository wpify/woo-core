<?php

namespace WpifyWooCore;

class WpifyWooCore {
	/**
	 * @var WooCommerceIntegration
	 */
	private $woocommerce_integration;

	public function __construct( WooCommerceIntegration $woocommerce_integration ) {
		$this->woocommerce_integration = $woocommerce_integration;
	}
}