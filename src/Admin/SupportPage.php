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
		add_action( 'admin_post_wpify_support_request', [ $this, 'handle_support_request' ] );
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
		$doc_base = $this->get_docs_base_url();
		$doc_link = add_query_arg( array(
			'utm_source'   => 'plugin-support',
			'utm_medium'   => 'plugin-link',
			'utm_campaign' => 'documentation-link'
		), $doc_base );

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
		$active_plugins = $this->get_active_wpify_plugins();
		?>
		<div class="wpify-dashboard__wrap wrap">
			<div class="wpify-dashboard__content">
				<h1><?php _e( 'Support page', 'wpify-core' ); ?></h1>

				<?php
				$sent_status = isset( $_GET['wpify_support_sent'] ) ? sanitize_text_field( wp_unslash( $_GET['wpify_support_sent'] ) ) : null;
				if ( $sent_status !== null ) {
					?>
					<div class="notice notice-<?php echo $sent_status === '1' ? 'success' : 'error'; ?> is-dismissible">
						<p>
							<?php
							echo $sent_status === '1'
								? esc_html__( 'Your support request has been sent. We will get back to you shortly.', 'wpify-core' )
								: esc_html__( 'Your support request could not be sent. Please try again.', 'wpify-core' );
							?>
						</p>
					</div>
				<?php } ?>

				<?php do_action( 'wpify_dashboard_before_support_content' ); ?>

				<div class="wpify__cards">
					<div class="wpify__card" style="max-width:100%">
						<div class="wpify__card-body">
							<h2><?php _e( 'Send a support request', 'wpify-core' ); ?></h2>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'wpify_support_request' ); ?>
								<input type="hidden" name="action" value="wpify_support_request">

								<p>
									<label for="wpify-support-plugin"><strong><?php _e( 'Plugin', 'wpify-core' ); ?></strong></label><br>
									<select id="wpify-support-plugin" name="plugin" class="regular-text">
										<option value="general"><?php _e( 'General', 'wpify-core' ); ?></option>
										<?php foreach ( $active_plugins as $slug => $plugin ) { ?>
											<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $plugin['title'] ?? $slug ); ?></option>
										<?php } ?>
									</select>
								</p>
								<p>
									<label for="wpify-support-subject"><strong><?php _e( 'Subject (optional)', 'wpify-core' ); ?></strong></label><br>
									<input id="wpify-support-subject" class="regular-text" type="text" name="subject" placeholder="<?php esc_attr_e( 'Short summary of the issue', 'wpify-core' ); ?>">
								</p>
								<p>
									<label for="wpify-support-email"><strong><?php _e( 'Contact email', 'wpify-core' ); ?></strong></label><br>
									<input id="wpify-support-email" class="regular-text" type="email" name="email" value="<?php echo esc_attr( wp_get_current_user()->user_email ?? '' ); ?>" required>
								</p>
								<p>
									<label for="wpify-support-message"><strong><?php _e( 'Message', 'wpify-core' ); ?></strong></label><br>
									<textarea id="wpify-support-message" class="large-text" rows="6" name="message" required></textarea>
								</p>
								<p>
									<?php submit_button( __( 'Send request', 'wpify-core' ), 'primary', 'submit', false ); ?>
								</p>
							</form>
							<p class="description"><?php _e( 'Basic diagnostics (site, environment, active WPify plugins) are included automatically.', 'wpify-core' ); ?></p>
						</div>
					</div>
					<div class="wpify__card" style="max-width:100%">
						<div class="wpify__card-body">
							<h2><?php _e( 'Frequently Asked Questions', 'wpify-core' ); ?></h2>

							<?php foreach ( $faqs as $faq ) { ?>
								<div class="faq">
									<h3><?php echo wp_kses_post( $faq['title'] ?? '' ); ?></h3>
									<p><?php echo wp_kses_post( $faq['content'] ?? '' ); ?></p>
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
					<?php if ( ! empty( $active_plugins ) ) { ?>
						<div class="wpify__card" style="max-width:100%">
							<div class="wpify__card-body">
								<h3><?php _e( 'Documentation for active plugins', 'wpify-core' ); ?></h3>
								<ul>
									<?php foreach ( $active_plugins as $slug => $plugin ) {
										if ( empty( $plugin['doc_link'] ) ) {
											continue;
										}
										?>
										<li>
											<a href="<?php echo esc_url( $plugin['doc_link'] ); ?>" target="_blank">
												<?php echo esc_html( $plugin['title'] ?? $slug ); ?>
											</a>
										</li>
									<?php } ?>
								</ul>
							</div>
						</div>
					<?php } ?>

					<div class="wpify__card">
						<div class="wpify__card-body">
							<h3><?php _e( 'If you haven\'t found the answer, email us at:', 'wpify-core' ); ?></h3>
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

	private function get_docs_base_url(): string {
		$domain = 'https://docs.wpify.cz/';
		if ( in_array( get_locale(), array( 'cs_CZ', 'sk_SK' ), true ) ) {
			$domain = 'https://docs.wpify.cz/cs/';
		}

		return $domain;
	}

	private function get_active_wpify_plugins(): array {
		$plugins = apply_filters( 'wpify_installed_plugins', [] );

		if ( ! is_array( $plugins ) ) {
			return [];
		}

		foreach ( $plugins as $slug => $plugin ) {
			if ( isset( $plugin['doc_link'] ) && $plugin['doc_link'] ) {
				$plugins[ $slug ]['doc_link'] = add_query_arg( array(
					'utm_source'   => 'plugin-support',
					'utm_medium'   => 'plugin-link',
					'utm_campaign' => 'documentation-link'
				), $plugin['doc_link'] );
			}
		}

		return $plugins;
	}

	public function handle_support_request(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to submit support requests.', 'wpify-core' ) );
		}

		check_admin_referer( 'wpify_support_request' );

		$plugin_slug   = isset( $_POST['plugin'] ) ? sanitize_key( wp_unslash( $_POST['plugin'] ) ) : 'general';
		$subject_input = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message_input = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$email_input   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( empty( $message_input ) || empty( $email_input ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'wpify_support_sent' => '0' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		$active_plugins = $this->get_active_wpify_plugins();
		$plugin_title   = $active_plugins[ $plugin_slug ]['title'] ?? __( 'General', 'wpify-core' );

		$site_host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$subject    = $subject_input ? $subject_input : $plugin_title;
		$subject    = sprintf( 'WPify Support | %s | %s', $subject, $site_host ?: home_url() );

		$woo_version = class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' ) ? WC_VERSION : 'not active';
		$theme       = wp_get_theme();
		$theme_name  = $theme ? $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) : '';

		$diagnostics = array(
			'Site URL'             => site_url(),
			'Home URL'             => home_url(),
			'WP Version'           => get_bloginfo( 'version' ),
			'PHP Version'          => PHP_VERSION,
			'Locale'               => get_locale(),
			'WooCommerce'          => $woo_version,
			'Active Theme'          => $theme_name,
			'WPify Plugins (active)' => $this->format_plugin_list( $active_plugins ),
		);

		$body_lines = array(
			'Plugin: ' . $plugin_title,
			'From: ' . wp_get_current_user()->display_name,
			'Contact email: ' . $email_input,
			'',
			'Message:',
			$message_input,
			'',
			'Diagnostics:',
		);

		foreach ( $diagnostics as $label => $value ) {
			$body_lines[] = $label . ': ' . ( $value !== '' ? $value : '-' );
		}

		$headers = array( 'Reply-To: ' . $email_input );
		$sent    = wp_mail( 'support@wpify.io', $subject, implode( "\n", $body_lines ), $headers );

		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'wpify_support_sent' => $sent ? '1' : '0' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function format_plugin_list( array $plugins ): string {
		if ( empty( $plugins ) ) {
			return '-';
		}

		$items = [];
		foreach ( $plugins as $slug => $plugin ) {
			$title   = $plugin['title'] ?? $slug;
			$version = $plugin['version'] ?? '';
			$items[] = $version ? sprintf( '%s (%s)', $title, $version ) : $title;
		}

		return implode( ', ', $items );
	}
}
