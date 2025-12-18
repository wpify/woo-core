<?php

namespace Wpify\WooCore\Admin;

/**
 * Class SupportPage
 *
 * Handles rendering and registration of the WPify Support page.
 *
 * @package Wpify\WooCore\Admin
 */
class SupportPage {

	const SLUG = 'wpify/support';

	private DashboardPage $dashboard_page;

	public function __construct( DashboardPage $dashboard_page ) {
		$this->dashboard_page = $dashboard_page;

		add_action( 'admin_menu', [ $this, 'register' ] );
	}

	/**
	 * Register support submenu page
	 *
	 * @return void
	 */
	public function register(): void {
		add_submenu_page(
			DashboardPage::SLUG,
			__( 'WPify Plugins Support', 'wpify-core' ),
			__( 'Support', 'wpify-core' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ],
			99
		);
	}

	/**
	 * Render Support page html
	 *
	 * @return void
	 */
	public function render(): void {
		$doc_link = add_query_arg( array(
			'utm_source'   => 'plugin-support',
			'utm_medium'   => 'plugin-link',
			'utm_campaign' => 'documentation-link'
		), 'https://wpify.cz/dokumentace/' );

		$faqs = apply_filters( 'wpify_dashboard_support_faqs', array(
			array(
				'title'   => __( 'How do the pricing plans work?', 'wpify-core' ),
				'content' => __( 'When you purchase the plugin, you receive support and updates for one year. After this period, the license will automatically renew.', 'wpify-core' ),
			),
			array(
				'title'   => __( 'Will the plugin work if I do not renew my license?', 'wpify-core' ),
				'content' => __( 'Yes, the plugin will continue to work, but you will no longer have access to updates and support.', 'wpify-core' ),
			),
			array(
				'title'   => __( 'I need a feature that the plugin does not currently support.', 'wpify-core' ),
				'content' => __( 'Let us know, and we will consider adding the requested functionality.', 'wpify-core' ),
			)
		) );
		?>
		<div class="wpify-dashboard__wrap wrap">
			<div class="wpify-dashboard__content">
				<h1><?php _e( 'Support page', 'wpify-core' ); ?></h1>

				<?php do_action( 'wpify_dashboard_before_support_content' ); ?>

				<div class="wpify__cards">
					<div class="wpify__card" style="max-width:100%">
						<div class="wpify__card-body">
							<h2><?php _e( 'Frequently Asked Questions', 'wpify-core' ); ?></h2>

							<?php foreach ( $faqs as $faq ) { ?>
								<div class="faq">
									<h3><?php echo $faq['title'] ?? '' ?></h3>
									<p><?php echo $faq['content'] ?? '' ?></p>
								</div>
							<?php } ?>
						</div>
					</div>
					<div class="wpify__card">
						<div class="wpify__card-body">
							<h3><?php _e( 'Do you have any other questions?', 'wpify-core' ); ?></h3>
							<p><?php _e( 'Check out the plugin documentation to see if your question is already answered.', 'wpify-core' ); ?></p>
							<p><a href="<?php echo esc_url( $doc_link ); ?>" target="_blank"
							      class="button button-primary"><?php _e( 'Documentation', 'wpify-core' ); ?></a></p>
						</div>
					</div>

					<div class="wpify__card">
						<div class="wpify__card-body">
							<h3><?php _e( 'If you haven't found the answer, email us at:', 'wpify-core' ); ?></h3>
							<p><a href="mailto:support@wpify.io">support@wpify.io</a></p>
						</div>
					</div>
					<?php do_action( 'wpify_dashboard_support_cards' ); ?>

				</div>

				<?php do_action( 'wpify_dashboard_after_support_content' ); ?>

			</div>
			<div class="wpify-dashboard__sidebar">
				<?php
				do_action( 'wpify_dashboard_before_news_posts' );

				$this->dashboard_page->render_news_posts();

				do_action( 'wpify_dashboard_after_news_posts' );
				?>
			</div>
		</div>
		<?php
	}
}
