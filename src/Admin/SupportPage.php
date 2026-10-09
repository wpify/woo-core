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
	private ?array $log_files_cache = null;

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
		$debug_link = add_query_arg( array(
			'utm_source'   => 'plugin-support',
			'utm_medium'   => 'plugin-link',
			'utm_campaign' => 'troubleshooting-link'
		), $doc_base . 'general/' );

		$faqs = apply_filters( 'wpify_dashboard_support_faqs', array(
			array(
				'title'   => __( 'Where do I find the license key?', 'wpify-core' ),
				'content' => __( 'There is no key to enter. Click "Activate domain" in the plugin settings and the site connects to your WPify account. If the button is missing, the license field tells you that the server blocks the plugin files and which file it is.', 'wpify-core' ),
			),
			array(
				'title'   => __( 'How do I move the license to a new domain?', 'wpify-core' ),
				'content' => __( 'On the old site, click "Deactivate domain" in the plugin settings, then click "Activate domain" on the new site. If the old site is no longer available, contact us.', 'wpify-core' ),
			),
			array(
				'title'   => __( 'Why is a plugin update not available?', 'wpify-core' ),
				'content' => __( 'Updates need a valid license for this domain. The license field in the plugin settings and the plugin list show the reason, e.g. an expired subscription or a license activated for another domain.', 'wpify-core' ),
			),
			array(
				'title'   => __( 'Will the plugin work if I do not renew my license?', 'wpify-core' ),
				'content' => __( 'Yes, the plugin will continue to work, but you will no longer have access to updates and support.', 'wpify-core' ),
			),
			array(
				'title'   => __( 'My settings do not apply in another language.', 'wpify-core' ),
				'content' => __( 'On multilingual sites (WPML, Polylang) the main settings apply to every language without settings of its own. When you change and save the settings with one language selected, that language gets its own copy. The notice at the top of the settings page tells you which settings you are editing and lets you delete the settings of a language.', 'wpify-core' ),
			),
			array(
				'title'   => __( 'I need a feature that the plugin does not currently support.', 'wpify-core' ),
				'content' => __( 'Let us know, and we will consider adding the requested functionality.', 'wpify-core' ),
			),
		) );
		$active_plugins = $this->get_active_wpify_plugins();
		$log_files      = $this->get_log_files();
		$logs_url       = $this->get_logs_page_url();
		$diagnostics    = $this->get_diagnostics( 'general', $active_plugins );
		unset( $diagnostics['License status'], $diagnostics['License key'] );
		?>
		<div class="wpify-dashboard__wrap wrap wpify-support">
			<div class="wpify-dashboard__content">
				<h1><?php _e( 'Support page', 'wpify-core' ); ?></h1>

				<?php
				$sent_status = isset( $_GET['wpify_support_sent'] ) ? sanitize_text_field( wp_unslash( $_GET['wpify_support_sent'] ) ) : null;
				if ( $sent_status !== null ) {
					?>
					<div class="wpify-notice wpify-notice-<?php echo $sent_status === '1' ? 'success' : 'error'; ?>">
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

				<p class="wpify-support__intro">
					<?php _e( 'Most problems can be solved in a few minutes with the steps and documentation below. Please go through them before sending a request — you will get the answer faster.', 'wpify-core' ); ?>
				</p>

				<div class="wpify-card">
					<div class="wpify-card__header">
						<h2 class="wpify-card__title"><?php _e( 'Quick debugging checklist', 'wpify-core' ); ?></h2>
					</div>
					<div class="wpify-card__body">
						<ol class="wpify-support__steps">
							<li class="wpify-section">
								<div class="wpify-section__head">
									<strong class="wpify-section__title"><?php _e( 'Check order notes', 'wpify-core' ); ?></strong>
									<p class="wpify-section__description"><?php _e( 'In the WooCommerce order detail, you’ll find notes that plugins automatically add. Look for messages about errors or failed operations.', 'wpify-core' ); ?></p>
								</div>
							</li>
							<li class="wpify-section">
								<div class="wpify-section__head">
									<strong class="wpify-section__title"><?php _e( 'Review logs', 'wpify-core' ); ?></strong>
									<p class="wpify-section__description"><?php _e( 'Most plugins log communication in WPify → WPify Logs. Select the relevant plugin and date, look for records marked as ERROR.', 'wpify-core' ); ?></p>
								</div>
								<?php if ( ! empty( $log_files ) && $logs_url ) { ?>
									<a class="button" href="<?php echo esc_url( $logs_url ); ?>"><?php _e( 'Open WPify Logs', 'wpify-core' ); ?></a>
								<?php } ?>
							</li>
							<li class="wpify-section">
								<div class="wpify-section__head">
									<strong class="wpify-section__title"><?php _e( 'Check plugin documentation', 'wpify-core' ); ?></strong>
									<p class="wpify-section__description"><?php _e( 'Each plugin has its own troubleshooting section with descriptions of common errors and their solutions.', 'wpify-core' ); ?></p>
								</div>
								<?php if ( $active_plugins ) { ?>
									<a href="#wpify-support-plugins"><?php _e( 'Find your plugin in the list below', 'wpify-core' ); ?></a>
								<?php } ?>
							</li>
							<li class="wpify-section">
								<div class="wpify-section__head">
									<strong class="wpify-section__title"><?php _e( 'Contact support', 'wpify-core' ); ?></strong>
									<p class="wpify-section__description"><?php _e( 'If the problem persists, send us a request with the form below. Describe the problem and the steps to reproduce it — diagnostics and the selected logs are attached for you.', 'wpify-core' ); ?></p>
								</div>
								<a href="#wpify-support-form"><?php _e( 'Go to the support form', 'wpify-core' ); ?></a>
							</li>
						</ol>
					</div>
					<div class="wpify-card__footer">
						<a href="<?php echo esc_url( $debug_link ); ?>" target="_blank"><?php _e( 'Full debugging guide', 'wpify-core' ); ?></a>
					</div>
				</div>

				<div class="wpify-card">
					<div class="wpify-card__header">
						<h2 class="wpify-card__title"><?php _e( 'Frequently Asked Questions', 'wpify-core' ); ?></h2>
					</div>
					<div class="wpify-accordion wpify-support__faqs">
						<?php foreach ( $faqs as $faq ) { ?>
							<details class="wpify-accordion__item">
								<summary class="wpify-accordion__toggle">
									<span class="wpify-accordion__title"><?php echo wp_kses_post( $faq['title'] ?? '' ); ?></span>
									<span class="wpify-accordion__caret" aria-hidden="true">▾</span>
								</summary>
								<div class="wpify-accordion__content">
									<p><?php echo wp_kses_post( $faq['content'] ?? '' ); ?></p>
								</div>
							</details>
						<?php } ?>
					</div>
					<div class="wpify-card__footer">
						<p><?php _e( 'Check out the plugin documentation to see if your question is already answered.', 'wpify-core' ); ?></p>
						<a href="<?php echo esc_url( $doc_link ); ?>" target="_blank" class="button button-primary"><?php _e( 'Documentation', 'wpify-core' ); ?></a>
					</div>
				</div>

				<?php if ( $active_plugins ) { ?>
					<div class="wpify-card" id="wpify-support-plugins">
						<div class="wpify-card__header">
							<h2 class="wpify-card__title"><?php _e( 'Your WPify plugins', 'wpify-core' ); ?></h2>
							<p class="wpify-card__description"><?php _e( 'Open the documentation of the plugin you have trouble with — most common problems are described there.', 'wpify-core' ); ?></p>
						</div>
						<table class="wpify-support__plugins">
							<thead>
								<tr>
									<th><?php _e( 'Plugin', 'wpify-core' ); ?></th>
									<th><?php _e( 'Version', 'wpify-core' ); ?></th>
									<th><?php _e( 'License', 'wpify-core' ); ?></th>
									<th><?php _e( 'Documentation', 'wpify-core' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $active_plugins as $slug => $plugin ) {
									$license = $this->get_license_badge( $plugin['license'] ?? true );
									?>
									<tr>
										<td><strong><?php echo esc_html( $plugin['title'] ?? $slug ); ?></strong></td>
										<td><?php echo esc_html( $plugin['version'] ?? '' ); ?></td>
										<td>
											<?php if ( $license ) { ?>
												<span class="wpify-badge <?php echo esc_attr( $license['class'] ); ?>"><?php echo esc_html( $license['label'] ); ?></span>
											<?php } else { ?>
												<span class="wpify-text-muted">—</span>
											<?php } ?>
										</td>
										<td>
											<?php if ( ! empty( $plugin['doc_link'] ) ) { ?>
												<a href="<?php echo esc_url( $plugin['doc_link'] ); ?>" target="_blank"><?php _e( 'Open documentation', 'wpify-core' ); ?></a>
											<?php } else { ?>
												<span class="wpify-text-muted">—</span>
											<?php } ?>
										</td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
				<?php } ?>

				<div class="wpify-card" id="wpify-support-form">
					<div class="wpify-card__header">
						<h2 class="wpify-card__title"><?php _e( 'Send a support request', 'wpify-core' ); ?></h2>
						<p class="wpify-card__description"><?php _e( 'Did not find the answer in the steps and documentation above? Write to us.', 'wpify-core' ); ?></p>
					</div>
					<div class="wpify-card__body">
						<form class="wpify-support__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
							<?php wp_nonce_field( 'wpify_support_request' ); ?>
							<input type="hidden" name="action" value="wpify_support_request">

							<div class="wpify-support__field">
								<label for="wpify-support-plugin"><?php _e( 'Plugin', 'wpify-core' ); ?></label>
								<select id="wpify-support-plugin" name="plugin">
									<option value="general"><?php _e( 'General', 'wpify-core' ); ?></option>
									<?php foreach ( $active_plugins as $slug => $plugin ) { ?>
										<option value="<?php echo esc_attr( $slug ); ?>" data-doc="<?php echo esc_url( $plugin['doc_link'] ?? '' ); ?>"><?php echo esc_html( $plugin['title'] ?? $slug ); ?></option>
									<?php } ?>
								</select>
								<p class="wpify-support__doc-hint" hidden>
									<?php _e( 'Have you gone through the documentation of this plugin?', 'wpify-core' ); ?>
									<a href="#" target="_blank"><?php _e( 'Open documentation', 'wpify-core' ); ?></a>
								</p>
							</div>
							<div class="wpify-support__field">
								<label for="wpify-support-subject"><?php _e( 'Subject (optional)', 'wpify-core' ); ?></label>
								<input id="wpify-support-subject" type="text" name="subject" placeholder="<?php esc_attr_e( 'Short summary of the issue', 'wpify-core' ); ?>">
							</div>
							<div class="wpify-support__field">
								<label for="wpify-support-email"><?php _e( 'Contact email', 'wpify-core' ); ?></label>
								<input id="wpify-support-email" type="email" name="email" value="<?php echo esc_attr( wp_get_current_user()->user_email ?? '' ); ?>" required>
							</div>
							<div class="wpify-support__field">
								<label for="wpify-support-message"><?php _e( 'Message', 'wpify-core' ); ?></label>
								<textarea id="wpify-support-message" rows="6" name="message" required></textarea>
							</div>
							<?php if ( ! empty( $log_files ) ) { ?>
								<div class="wpify-support__field">
									<label for="wpify-support-log-count"><?php _e( 'Attach logs', 'wpify-core' ); ?></label>
									<p class="wpify-support__logs-empty description"><?php _e( 'Select a plugin above to attach its logs.', 'wpify-core' ); ?></p>
									<p class="wpify-support__logs-none description" hidden><?php _e( 'This plugin has no logs.', 'wpify-core' ); ?></p>
									<div class="wpify-support__logs" hidden>
										<select id="wpify-support-log-count" name="log_count">
											<option value="0"><?php _e( 'Do not attach logs', 'wpify-core' ); ?></option>
											<option value="1"><?php _e( 'The latest log', 'wpify-core' ); ?></option>
											<option value="3" selected><?php _e( 'The latest 3 logs', 'wpify-core' ); ?></option>
											<option value="all" data-label="<?php /* translators: %d: number of log files kept for the plugin */ esc_attr_e( 'All kept logs (%d)', 'wpify-core' ); ?>"><?php _e( 'All kept logs', 'wpify-core' ); ?></option>
										</select>
										<details class="wpify-support__details">
											<summary><?php _e( 'Choose specific files', 'wpify-core' ); ?></summary>
											<select id="wpify-support-log" name="log_files[]" multiple size="6">
												<?php foreach ( $log_files as $log ) { ?>
													<option value="<?php echo esc_attr( $log['file'] ); ?>" data-channel="<?php echo esc_attr( $log['channel'] ); ?>"><?php echo esc_html( $log['label'] ); ?></option>
												<?php } ?>
											</select>
											<p class="description"><?php _e( 'Selected files are attached in addition to the latest logs. Hold Ctrl (Windows) or Cmd (Mac) to select multiple logs.', 'wpify-core' ); ?></p>
										</details>
									</div>
								</div>
							<?php } ?>
							<div class="wpify-support__field">
								<label for="wpify-support-files"><?php _e( 'Attach files (optional)', 'wpify-core' ); ?></label>
								<input id="wpify-support-files" type="file" name="support_files[]" multiple>
								<p class="description"><?php _e( 'Screenshots or documents that help explain the issue.', 'wpify-core' ); ?></p>
							</div>
							<details class="wpify-support__details">
								<summary><?php _e( 'What is sent automatically', 'wpify-core' ); ?></summary>
								<table class="wpify-support__diagnostics">
									<?php foreach ( $diagnostics as $label => $value ) { ?>
										<tr>
											<th><?php echo esc_html( $label ); ?></th>
											<td><?php echo esc_html( $value !== '' ? (string) $value : '-' ); ?></td>
										</tr>
									<?php } ?>
								</table>
								<p class="description"><?php _e( 'With a plugin selected, also its license status and key.', 'wpify-core' ); ?></p>
							</details>
							<div>
								<?php submit_button( __( 'Send request', 'wpify-core' ), 'primary', 'submit', false ); ?>
							</div>
						</form>
					</div>
				</div>

				<?php do_action( 'wpify_dashboard_support_cards' ); ?>

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
		<script>
			(function () {
				const pluginSelect = document.getElementById('wpify-support-plugin');
				if (!pluginSelect) {
					return;
				}

				const docHint = document.querySelector('.wpify-support__doc-hint');
				const logs = document.querySelector('.wpify-support__logs');
				const logsEmpty = document.querySelector('.wpify-support__logs-empty');
				const logsNone = document.querySelector('.wpify-support__logs-none');
				const logSelect = document.getElementById('wpify-support-log');
				const allOption = document.querySelector('#wpify-support-log-count option[value="all"]');
				const options = logSelect ? Array.from(logSelect.options) : [];
				const normalizeChannel = (value) => (value || '').replace(/-/g, '_');

				const sync = () => {
					const plugin = pluginSelect.value || 'general';
					const selected = pluginSelect.options[pluginSelect.selectedIndex];
					const doc = selected ? selected.getAttribute('data-doc') : '';

					if (docHint) {
						docHint.hidden = !doc;
						docHint.querySelector('a').href = doc || '#';
					}

					if (!logs) {
						return;
					}

					const channel = plugin === 'general' ? '' : normalizeChannel(plugin);
					let count = 0;
					options.forEach(option => {
						option.hidden = !channel || normalizeChannel(option.getAttribute('data-channel')) !== channel;
						if (option.hidden) {
							option.selected = false;
						} else {
							count++;
						}
					});

					logsEmpty.hidden = !!channel;
					logsNone.hidden = !channel || count > 0;
					logs.hidden = !channel || count === 0;
					if (allOption) {
						allOption.textContent = allOption.getAttribute('data-label').replace('%d', count);
					}
				};

				pluginSelect.addEventListener('change', sync);
				sync();
			})();
		</script>
		<?php
	}

	/**
	 * Diagnostics sent with every request; also listed on the page so the user knows what is shared.
	 *
	 * @param string $plugin_slug    Plugin the request is about, or 'general'.
	 * @param array  $active_plugins Active WPify plugins.
	 *
	 * @return array<string, string>
	 */
	private function get_diagnostics( string $plugin_slug, array $active_plugins ): array {
		$license_data = $this->get_license_details( $plugin_slug );

		$woo_version = '';
		if ( defined( 'WC_VERSION' ) ) {
			$woo_version = WC_VERSION;
		} else {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$woo_active = function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce/woocommerce.php' );
			if ( $woo_active ) {
				$woo_version = (string) get_option( 'woocommerce_version', '' );
				if ( $woo_version === '' ) {
					$woo_version = 'active';
				}
			}
		}
		if ( $woo_version === '' ) {
			$woo_version = 'not active';
		}
		$theme      = wp_get_theme();
		$theme_name = $theme ? $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) : '';

		return apply_filters( 'wpify_dashboard_support_email_diagnostics', array(
			'Site URL'               => site_url(),
			'Home URL'               => home_url(),
			'WP Version'             => get_bloginfo( 'version' ),
			'PHP Version'            => PHP_VERSION,
			'Locale'                 => get_locale(),
			'WooCommerce'            => $woo_version,
			'Active Theme'           => $theme_name,
			'WPify Plugins (active)' => $this->format_plugin_list( $active_plugins ),
			'License status'         => $license_data['status'],
			'License key'            => $license_data['key'],
		), $plugin_slug );
	}

	/**
	 * Badge for the license state the plugin reports to the dashboard.
	 *
	 * @param mixed $license True when the plugin needs no activation, the stored activation, or false.
	 *
	 * @return array{class: string, label: string}|null Null when the plugin needs no license.
	 */
	private function get_license_badge( $license ): ?array {
		if ( true === $license ) {
			return null;
		}

		if ( ! is_array( $license ) || empty( $license['license'] ) ) {
			return array( 'class' => 'wpify-badge-warning', 'label' => __( 'Not activated', 'wpify-core' ) );
		}

		if ( array_key_exists( 'valid', $license ) && ! $license['valid'] ) {
			return array( 'class' => 'wpify-badge-error', 'label' => __( 'Invalid', 'wpify-core' ) );
		}

		return array( 'class' => 'wpify-badge-success', 'label' => __( 'Active', 'wpify-core' ) );
	}

	/**
	 * Newest logs of a plugin — the user picks how many, not which files.
	 *
	 * @param string     $plugin_slug Plugin slug from the form.
	 * @param string|int $count       Number of logs, or 'all'.
	 *
	 * @return string[]
	 */
	private function get_latest_log_paths( string $plugin_slug, $count ): array {
		if ( $plugin_slug === 'general' || $count === '0' || $count === 0 || $count === '' ) {
			return [];
		}

		$channel = str_replace( '-', '_', $plugin_slug );
		$files   = array();
		foreach ( $this->get_log_files() as $log ) {
			if ( str_replace( '-', '_', $log['channel'] ) === $channel ) {
				$files[] = $log['file'];
			}
		}

		// get_log_files() is sorted newest first within a channel (the label ends with the date).
		if ( $count !== 'all' ) {
			$files = array_slice( $files, 0, max( 0, (int) $count ) );
		}

		return array_values( array_filter( $files, 'is_readable' ) );
	}

	private function get_docs_base_url(): string {
		$domain = 'https://docs.wpify.cz/';
		if ( in_array( determine_locale(), array( 'cs_CZ', 'sk_SK' ), true ) ) {
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

		$plugin_slug  = isset( $_POST['plugin'] ) ? sanitize_key( wp_unslash( $_POST['plugin'] ) ) : 'general';
		$subject_input = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message_input = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$email_input   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$log_files     = isset( $_POST['log_files'] ) ? (array) wp_unslash( $_POST['log_files'] ) : [];
		$log_count     = isset( $_POST['log_count'] ) ? sanitize_key( wp_unslash( $_POST['log_count'] ) ) : '0';
		$upload_files  = isset( $_FILES['support_files'] ) ? $_FILES['support_files'] : null;

		if ( empty( $message_input ) || empty( $email_input ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'wpify_support_sent' => '0' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		$active_plugins = $this->get_active_wpify_plugins();
		$plugin_title   = $active_plugins[ $plugin_slug ]['title'] ?? __( 'General', 'wpify-core' );

		$site_host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$subject    = $subject_input ? $subject_input : $plugin_title;
		$subject    = sprintf( 'WPify Support | %s | %s', $subject, $site_host ?: home_url() );

		$diagnostics = $this->get_diagnostics( $plugin_slug, $active_plugins );

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

		$attachments = [];
		$temp_paths  = [];

		$log_paths = array_values( array_unique( array_merge(
			$this->get_latest_log_paths( $plugin_slug, $log_count ),
			$this->get_log_attachment_paths( $log_files )
		) ) );
		if ( $log_paths ) {
			$attachments   = array_merge( $attachments, $log_paths );
			$body_lines[]  = '';
			$body_lines[]  = 'Log attachments: ' . implode( ', ', array_map( 'basename', $log_paths ) );
		}

		$upload_result = $this->handle_support_uploads( $upload_files );
		if ( ! empty( $upload_result['paths'] ) ) {
			$names         = $this->add_named_attachments( $attachments, $upload_result['paths'] );
			$body_lines[]  = '';
			$body_lines[]  = 'File attachments: ' . implode( ', ', $names );
		}

		$generated = apply_filters( 'wpify_dashboard_support_email_generated_attachments', [], $plugin_slug );
		if ( is_array( $generated ) && $generated ) {
			$generated_paths = $this->create_generated_attachments( $generated );
			if ( ! empty( $generated_paths['paths'] ) ) {
				$names        = $this->add_named_attachments( $attachments, $generated_paths['paths'] );
				$temp_paths   = array_merge( $temp_paths, array_column( $generated_paths['paths'], 'path' ) );
				$body_lines[] = '';
				$body_lines[] = 'Generated attachments: ' . implode( ', ', $names );
			}
		}

		$attachments = apply_filters( 'wpify_dashboard_support_email_attachments', $attachments, $plugin_slug );

		$headers = array( 'Reply-To: ' . $email_input );
		$sent    = wp_mail( 'support@wpify.io', $subject, implode( "\n", $body_lines ), $headers, $attachments );

		if ( ! empty( $upload_result['paths'] ) ) {
			foreach ( $upload_result['paths'] as $file ) {
				if ( $file['path'] !== '' ) {
					@unlink( $file['path'] );
				}
			}
		}

		if ( $temp_paths ) {
			foreach ( $temp_paths as $path ) {
				if ( is_string( $path ) && $path !== '' ) {
					@unlink( $path );
				}
			}
		}

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

	private function get_log_files(): array {
		if ( $this->log_files_cache !== null ) {
			return $this->log_files_cache;
		}

		$logs  = apply_filters( 'wpify_logs', [] );
		$files = [];

		foreach ( $logs as $log ) {
			if ( ! is_object( $log ) || ! method_exists( $log, 'getHandlers' ) ) {
				continue;
			}
			$channel = method_exists( $log, 'get_channel' ) ? $log->get_channel() : '';
			foreach ( $log->getHandlers() as $handler ) {
				if ( ! method_exists( $handler, 'get_glob_pattern' ) ) {
					continue;
				}
				$log_files = glob( $handler->get_glob_pattern() );
				if ( ! is_array( $log_files ) ) {
					continue;
				}
				foreach ( $log_files as $file ) {
					$files[] = array(
						'file'    => $file,
						'channel' => $channel,
						'label'   => $this->format_log_label( $file, $channel ),
					);
				}
			}
		}

		usort( $files, static function ( $left, $right ) {
			return strcmp( $right['label'], $left['label'] );
		} );

		$this->log_files_cache = $files;

		return $this->log_files_cache;
	}

	private function format_log_label( string $file, string $channel ): string {
		$file_name = basename( $file );
		$date      = '';

		if ( preg_match( '/-(\d{4}-\d{2}-\d{2})\.log$/', $file_name, $matches ) ) {
			$date      = ' ' . $matches[1];
			$file_name = preg_replace( '/-\d{4}-\d{2}-\d{2}\.log$/', '', $file_name );
		} else {
			$file_name = str_replace( '.log', '', $file_name );
		}

		$cleared_name = str_replace( 'wpify_log_', '', $file_name );
		$cleared_name = preg_replace( '/_[a-f0-9]{32}$/', '', $cleared_name );
		$label        = $cleared_name ?: $channel;
		$label        = str_replace( '_', ' ', $label );
		$label        = ucwords( $label );

		return trim( $label . ' –' . $date );
	}

	private function get_log_attachment_paths( array $selected_files ): array {
		$selected_files = array_filter( array_map( 'strval', $selected_files ) );
		if ( empty( $selected_files ) ) {
			return [];
		}

		$available = [];
		foreach ( $this->get_log_files() as $log ) {
			$available[ $log['file'] ] = true;
		}

		$paths = [];
		foreach ( $selected_files as $file ) {
			if ( empty( $available[ $file ] ) ) {
				continue;
			}
			if ( is_readable( $file ) ) {
				$paths[] = $file;
			}
		}

		return $paths;
	}

	private function create_generated_attachments( array $generated ): array {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$paths = [];
		foreach ( $generated as $item ) {
			if ( ! is_array( $item ) || empty( $item['content'] ) ) {
				continue;
			}
			$name    = isset( $item['name'] ) ? sanitize_file_name( (string) $item['name'] ) : 'attachment.txt';
			$content = (string) $item['content'];
			$tmp     = wp_tempnam( $name );
			if ( ! $tmp ) {
				continue;
			}
			$written = file_put_contents( $tmp, $content );
			if ( $written === false ) {
				@unlink( $tmp );
				continue;
			}
			$paths[] = array( 'name' => $name, 'path' => $tmp );
		}

		return array( 'paths' => $paths );
	}

	/**
	 * Temp files have no extension, so the original name is passed to wp_mail() as the array key.
	 *
	 * @param array $attachments Attachments passed to wp_mail(), extended in place.
	 * @param array $files       List of [ 'name' => string, 'path' => string ].
	 *
	 * @return string[] Names actually used.
	 */
	private function add_named_attachments( array &$attachments, array $files ): array {
		$names = [];
		foreach ( $files as $file ) {
			$name     = $file['name'] !== '' ? $file['name'] : 'attachment';
			$base     = pathinfo( $name, PATHINFO_FILENAME );
			$ext      = pathinfo( $name, PATHINFO_EXTENSION );
			$counter  = 1;
			$unique   = $name;
			while ( isset( $attachments[ $unique ] ) ) {
				$unique = $base . '-' . ( ++$counter ) . ( $ext !== '' ? '.' . $ext : '' );
			}
			$attachments[ $unique ] = $file['path'];
			$names[]                = $unique;
		}

		return $names;
	}

	private function handle_support_uploads( ?array $files ): array {
		if ( empty( $files ) || empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
			return array( 'paths' => [], 'errors' => [] );
		}

		$max_size = (int) apply_filters( 'wpify_dashboard_support_upload_max_size', 10 * 1024 * 1024 );
		$mimes    = apply_filters( 'wpify_dashboard_support_upload_mimes', array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'gif'      => 'image/gif',
			'pdf'      => 'application/pdf',
			'txt'      => 'text/plain',
			'log'      => 'text/plain',
			'json'     => 'application/json',
			'csv'      => 'text/csv',
		) );

		$paths  = [];
		$errors = [];

		foreach ( $files['name'] as $index => $name ) {
			if ( empty( $name ) ) {
				continue;
			}
			if ( ! empty( $files['error'][ $index ] ) ) {
				$errors[] = $name;
				continue;
			}
			if ( isset( $files['size'][ $index ] ) && $files['size'][ $index ] > $max_size ) {
				$errors[] = $name;
				continue;
			}

			$tmp_name = $files['tmp_name'][ $index ] ?? '';
			if ( empty( $tmp_name ) || ! is_uploaded_file( $tmp_name ) ) {
				$errors[] = $name;
				continue;
			}

			$check = wp_check_filetype_and_ext( $tmp_name, $name, $mimes );
			if ( empty( $check['type'] ) ) {
				$errors[] = $name;
				continue;
			}

			$paths[] = array( 'name' => sanitize_file_name( $name ), 'path' => $tmp_name );
		}

		return array( 'paths' => $paths, 'errors' => $errors );
	}

	private function get_logs_page_url(): ?string {
		global $submenu;

		if ( ! is_array( $submenu ) ) {
			return null;
		}

		$parents = array( 'wpify', 'tools.php' );
		foreach ( $parents as $parent ) {
			if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
				continue;
			}
			foreach ( $submenu[ $parent ] as $item ) {
				if ( isset( $item[2] ) && $item[2] === 'wpify-logs' ) {
					return admin_url( 'admin.php?page=wpify-logs' );
				}
			}
		}

		return null;
	}

	private function get_license_details( string $plugin_slug ): array {
		if ( empty( $plugin_slug ) || $plugin_slug === 'general' ) {
			return array(
				'status' => 'n/a',
				'key'    => '-',
			);
		}

		$option_key = $plugin_slug . '_license';
		if ( is_multisite() ) {
			$data = get_network_option( get_current_network_id(), $option_key );
		} else {
			$data = get_option( $option_key );
		}

		if ( ! is_array( $data ) || empty( $data['license'] ) ) {
			return array(
				'status' => 'inactive',
				'key'    => '-',
			);
		}

		if ( array_key_exists( 'valid', $data ) ) {
			$status = $data['valid'] ? 'valid' : 'invalid';
		} else {
			$status = 'active';
		}

		return array(
			'status' => $status,
			'key'    => $data['license'],
		);
	}
}
