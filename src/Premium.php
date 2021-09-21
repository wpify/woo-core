<?php

namespace Wpify\WooCore;

/**
 * Class Premium
 * @package WpifyWoo
 */
class Premium {

	/**
	 * Get the premium extensions
	 * @return array[]
	 */
	public function get_extensions(): array {
		return array(
			array(
				'title'             => __( 'Email Builder', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Easily build awesome WooCommerce emails using Gutenberg editor', 'wpify-woo' ),
					__( 'Custom blocks, rules for emails, dozens of merge tags', 'wpify-woo' ),
					__( 'Live preview on many devices, tested on 60+ devices', 'wpify-woo' ),
				),
				'short_description' => __( 'Easily build awesome WooCommerce emails using Gutenberg editor', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-email-builder/',
				'id'                => 'email_builder',
			),
			array(
				'title'             => __( 'DPD for WooCommerce', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Create packages and generate labels directly from administration', 'wpify-woo' ),
					__( 'Enable ParcelShops selection on checkout, add ParcelShop details to emails and admin', 'wpify-woo' ),
					__( 'Add parcel tracking links to emails', 'wpify-woo' ),
				),
				'short_description' => __( 'Bulk create DPD parcels and labels from administration and add ParcelShops selection to checkout.', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-dpd/',
				'id'                => 'dpd',
			),
			array(
				'title'             => __( 'Balíkovna', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Add Balíkovna shipping method to your store', 'wpify-woo' ),
				),
				'short_description' => __( 'Add Balíkovna shipping method to your store', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-balikovna/',
				'id'                => 'balikovna',
			),
			array(
				'title'             => __( 'Na poštu', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Add Napostu shipping method to your store', 'wpify-woo' ),
				),
				'short_description' => __( 'Add Na poštu shipping method to your store', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-napostu/',
				'id'                => 'napostu',
			),
			array(
				'title'             => __( 'Podání Online', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Batch export orders to Podání online directly from the Orders dashboard', 'wpify-woo' ),
					__( 'Choose fields that you want to export', 'wpify-woo' ),
					__( 'Supports Balíkovna and Na poštu shipping methods', 'wpify-woo' ),
				),
				'short_description' => __( 'Batch export orders to Podání online directly from the Orders dashboard', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-podani-online/',
				'id'                => 'podani_online',
			),
			array(
				'title'             => __( 'Fakturoid', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Generate proforma invoices and invoices automatically', 'wpify-woo' ),
					__( 'Complete orders automatically when the payment is received', 'wpify-woo' ),
					__( 'Attach invoices to emails', 'wpify-woo' ),
					__( 'And much more...', 'wpify-woo' ),
				),
				'short_description' => __( 'Automatically generate Fakturoid proforma invoices and invoices', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-fakturoid/',
				'id'                => 'fakturoid',
			),
			array(
				'title'             => __( 'Conditional shipping', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Many rules - cart amount, cart weight, product dimensions etc.', 'wpify-woo' ),
					__( 'Multiple actions - change shipping price, hide shipping, change label, set free shipping', 'wpify-woo' ),
				),
				'short_description' => __( 'Adjust shipping rates prices and visibility with many rules', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-conditional-shipping/',
				'id'                => 'conditional_shipping',
			),
			array(
				'title'             => __( 'ComGate payment gateway', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Add ComGate payment gateway to your store!', 'wpify-woo' ),
				),
				'short_description' => __( 'Add ComGate payment gateway to your store!', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-comgate/',
				'id'                => 'comgate',
			),
			array(
				'title'             => __( 'Gopay payment gateway', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Add Gopay payment gateway to your store!', 'wpify-woo' ),
				),
				'short_description' => __( 'Add Gopay payment gateway to your store!', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-gopay/',
				'id'                => 'gopay',
			),
			array(
				'title'             => __( 'ThePay payment gateway', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Add ThePay payment gateway to your store!', 'wpify-woo' ),
				),
				'short_description' => __( 'Add ThePay payment gateway to your store!', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-thepay/',
				'id'                => 'thepay',
			),
			array(
				'title'             => __( 'Phone validation', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Select prefix in the phone field', 'wpify-woo' ),
					__( 'Choose enabled prefixes', 'wpify-woo' ),
					__( 'Validate the entered phone when an order is submitted', 'wpify-woo' ),
					__( 'Automatically format the phone number that is saved to the order', 'wpify-woo' ),
				),
				'short_description' => __( 'Validate phone on checkout!', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-validace-telefonu/',
				'id'                => 'phone_validation',
			),
			array(
				'title'             => __( 'Benefit Plus', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Add Benefit plus payment gateway to your store!', 'wpify-woo' ),
				),
				'short_description' => __( 'Add Benefit plus payment gateway to your store!', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-benefit-plus/',
				'id'                => 'benefit_plus',
			),
			array(
				'title'             => __( 'Sodexo', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Add Sodexo payment gateway to your store!', 'wpify-woo' ),
				),
				'short_description' => __( 'Add Sodexo payment gateway to your store!', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-sodexo/',
				'id'                => 'sodexo',
			),
			array(
				'title'             => __( 'Zbozi.cz conversion tracking', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Tracking for Zbozi.cz conversions', 'wpify-woo' ),
					__( 'Tracks asynchronously to not slow down checkout', 'wpify-woo' ),
				),
				'short_description' => __( 'Track Zbozi.cz conversions', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-konverze-zbozi-cz/',
				'id'                => 'zbozi_conversions',
			),
			array(
				'title'             => __( 'Vivnetworks affiliate tracking', 'wpify-woo' ),
				'html_description'  => array(
					__( 'Tracking for Vivnetworks Affiliate.', 'wpify-woo' ),
					__( 'Tracks asynchronously to not slow down checkout', 'wpify-woo' ),
				),
				'short_description' => __( 'Tracking for Vivnetworks Affiliate', 'wpify-woo' ),
				'url'               => 'https://wpify.io/cs/produkt/wpify-woo-vivnetworks-affiliate/',
				'id'                => 'vivnetworks_affiliate',
			),

		);
	}
}
