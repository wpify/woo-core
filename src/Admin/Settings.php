<?php

namespace Wpify\WooCore\Admin;

use Wpify\Asset\AssetFactory;
use Wpify\CustomFields\CustomFields;
use Wpify\WooCore\Managers\ModulesManager;

/**
 * Class Settings
 *
 * Core settings class handling plugin/module settings registration.
 *
 * @package WpifyWooCore\Admin
 */
if ( class_exists( __NAMESPACE__ . '\\Settings', false ) ) {
	return;
}

class Settings {

	const OPTION_NAME = 'wpify-woo-settings';

	private array $pages = [];
	private ?array $plugins_cache = null;
	private array $sections_cache = [];

	private CustomFields $custom_fields;
	private ModulesManager $modules_manager;
	private AssetFactory $asset_factory;

	private ?DashboardPage $dashboard_page = null;
	private ?SupportPage $support_page = null;
	private ?MenuBar $menu_bar = null;

	public function __construct(
		CustomFields $custom_fields,
		ModulesManager $modules_manager,
		AssetFactory $asset_factory
	) {
		$this->custom_fields   = $custom_fields;
		$this->modules_manager = $modules_manager;
		$this->asset_factory   = $asset_factory;

		$allow_initialization = apply_filters( 'wpify_core_allow_initialization', true, static::class );
		if ( ! $allow_initialization ) {
			return;
		}

		// Block old core (filter-based) from initializing.
		// Use own callback (not __return_true) so we can detect old core in initialize_core().
		add_filter( 'wpify_core_settings_initialized', [ $this, 'is_initialized' ] );

		// Version-aware initialization: highest woo-core version wins
		$my_version = $this->get_core_version();

		global $wpify_woo_core_active_version, $wpify_woo_core_active_instance;

		if ( ! isset( $wpify_woo_core_active_version ) || version_compare( $my_version, $wpify_woo_core_active_version, '>' ) ) {
			$wpify_woo_core_active_version  = $my_version;
			$wpify_woo_core_active_instance = $this;
		}

		// Register deferred init once — runs after all plugins have loaded (priority 99)
		// so we know which version is the highest
		global $wpify_woo_core_init_registered;
		if ( ! $wpify_woo_core_init_registered ) {
			$wpify_woo_core_init_registered = true;
			add_action( 'plugins_loaded', function () {
				global $wpify_woo_core_active_instance;
				$wpify_woo_core_active_instance->initialize_core();
			}, 99 );
		}
	}

	/**
	 * Initialize core hooks and page components.
	 * Called once from deferred plugins_loaded (priority 99) on the highest-version instance.
	 *
	 * @return void
	 */
	public function initialize_core(): void {
		// If old core already initialized (it uses __return_true on this filter), skip
		if ( has_filter( 'wpify_core_settings_initialized', '__return_true' ) ) {
			return;
		}

		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'hide_admin_notices' ] );
		add_filter( 'admin_body_class', [ static::class, 'add_admin_body_class' ], 9999 );

		add_action( 'activated_plugin', [ $this, 'maybe_set_redirect' ] );
		add_action( 'deactivated_plugin', [ $this, 'maybe_set_redirect' ] );
		add_action( 'admin_init', [ $this, 'maybe_redirect' ] );
		add_action( 'admin_post_wpify_core_delete_language_settings', [ $this, 'delete_language_settings' ] );

		// Initialize page components (they register themselves)
		$this->get_dashboard_page();
		$this->get_support_page();
		$this->get_menu_bar();
	}

	/**
	 * Get dashboard page instance (lazy loaded)
	 *
	 * @return DashboardPage
	 */
	public function get_dashboard_page(): DashboardPage {
		if ( $this->dashboard_page === null ) {
			$this->dashboard_page = new DashboardPage( $this );
		}

		return $this->dashboard_page;
	}

	/**
	 * Get support page instance (lazy loaded)
	 *
	 * @return SupportPage
	 */
	public function get_support_page(): SupportPage {
		if ( $this->support_page === null ) {
			$this->support_page = new SupportPage( $this->get_dashboard_page() );
		}

		return $this->support_page;
	}

	/**
	 * Get menu bar instance (lazy loaded)
	 *
	 * @return MenuBar
	 */
	public function get_menu_bar(): MenuBar {
		if ( $this->menu_bar === null ) {
			$this->menu_bar = new MenuBar( $this );
		}

		return $this->menu_bar;
	}

	/**
	 * Filter callback to signal that new core is present.
	 * Used instead of __return_true so we can detect old core separately.
	 *
	 * @return bool
	 */
	public function is_initialized(): bool {
		return true;
	}

	/**
	 * Maybe set redirect transient after plugin activation/deactivation
	 *
	 * @return void
	 */
	public function maybe_set_redirect(): void {
		if ( ! empty( $_GET['wpify_redirect'] ) ) {
			set_transient( $this->get_redirect_key(), esc_url_raw( wp_unslash( $_GET['wpify_redirect'] ) ), 3 );
		}
	}

	/**
	 * Per user, so another admin's request can't consume the redirect.
	 *
	 * @return string
	 */
	private function get_redirect_key(): string {
		return 'wpify_redirect_' . get_current_user_id();
	}

	/**
	 * Maybe redirect after plugin activation/deactivation
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		// Background requests (heartbeat) would swallow the redirect meant for the page.
		if ( wp_doing_ajax() ) {
			return;
		}

		$redirect = get_transient( $this->get_redirect_key() );
		if ( $redirect ) {
			delete_transient( $this->get_redirect_key() );
			wp_safe_redirect( $redirect );
			exit;
		}
	}

	/**
	 * Register core textdomain
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		$mo_file = self::find_translation( dirname( __DIR__, 2 ) . '/languages', 'wpify-core-', '.mo', determine_locale() );
		if ( $mo_file ) {
			load_textdomain( 'wpify-core', $mo_file );
		}
	}

	/**
	 * Translation file for the locale, or for another locale of the same language when there is none
	 * (de_AT, de_CH and de_DE_formal use de_DE; the {language}_{LANGUAGE} variant is preferred).
	 *
	 * @param string $dir    Directory with the translations.
	 * @param string $prefix File name before the locale, e.g. 'wpify-core-'.
	 * @param string $suffix File name after the locale, e.g. '.mo'.
	 * @param string $locale Locale.
	 *
	 * @return string Empty when no translation exists.
	 */
	public static function find_translation( string $dir, string $prefix, string $suffix, string $locale ): string {
		$file = $dir . '/' . $prefix . $locale . $suffix;
		if ( file_exists( $file ) ) {
			return $file;
		}

		$language  = strtolower( strtok( $locale, '_' ) );
		$preferred = $dir . '/' . $prefix . $language . '_' . strtoupper( $language ) . $suffix;
		if ( file_exists( $preferred ) ) {
			return $preferred;
		}

		$files = glob( $dir . '/' . $prefix . $language . '_*' . $suffix );

		return $files ? $files[0] : '';
	}

	/**
	 * Hide all admin notices on dashboard or hide non wpify notices on wpify settings pages
	 *
	 * @return void
	 */
	public function hide_admin_notices(): void {
		global $wp_filter;

		if ( ! isset( $wp_filter['admin_notices'] ) ) {
			return;
		}

		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( $current_page === DashboardPage::SLUG ) {
			unset( $wp_filter['admin_notices'] );
			return;
		}

		if ( ! str_contains( $current_page, 'wpify/' ) ) {
			return;
		}

		foreach ( $wp_filter['admin_notices']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $key => $callback ) {
				$function = $callback['function'];

				if ( is_array( $function ) && isset( $function[0] ) ) {
					$class_name = is_object( $function[0] ) ? get_class( $function[0] ) : $function[0];

					if ( ! str_contains( $class_name, 'Wpify' ) ) {
						unset( $wp_filter['admin_notices']->callbacks[ $priority ][ $key ] );
					}
				}
			}
		}
	}

	/**
	 * Check if current request is a wpifycf REST API request
	 *
	 * @return bool
	 */
	private function is_wpifycf_rest_request(): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only used for string matching
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		return str_contains( $request_uri, '/wpifycf/' );
	}

	/**
	 * Register admin pages and settings for plugins and modules
	 *
	 * @return void
	 */
	public function register_settings(): void {
		if ( ! $this->should_register_settings() ) {
			return;
		}

		$plugins = $this->get_plugins();
		if ( empty( $plugins ) ) {
			return;
		}

		$this->pages = [];

		foreach ( $plugins as $plugin_id => $plugin ) {
			if ( empty( $plugin['menu_slug'] ) ) {
				continue;
			}

			$this->pages[ $plugin_id ] = [
				'page_title'  => $plugin['title'],
				'menu_title'  => $plugin['title'],
				'menu_slug'   => $plugin['menu_slug'],
				'id'          => $plugin_id,
				'parent_slug' => DashboardPage::SLUG,
				'class'       => 'wpify-woo-settings',
				'option_name' => $this->get_settings_name( $plugin['option_id'] ),
				'tabs'        => $this->is_current( '', $plugin_id ) ? $plugin['tabs'] : [],
				'items'       => $this->is_current( '', $plugin_id ) ? $plugin['settings'] : [],
			];

			$sections = $this->get_sections( $plugin_id );
			foreach ( $sections as $section_id => $section ) {
				if ( empty( $section_id ) ) {
					continue;
				}

				if ( isset( $this->pages[ $section_id ] ) || $plugin['option_id'] === $section['option_id'] ) {
					$this->pages[ $plugin_id ]['page_title']  = $section['title'];
					$this->pages[ $plugin_id ]['id']          = $section_id;
					$this->pages[ $plugin_id ]['option_name'] = $section['option_name'] ?? $this->get_settings_name( $section['option_id'] );
					$this->pages[ $plugin_id ]['tabs']        = $this->is_current( '', $section_id ) ? $section['tabs'] : [];
					$this->pages[ $plugin_id ]['items']       = $this->is_current( '', $section_id ) ? $section['settings'] : [];

					$this->pages[ $plugin_id ]['language_settings'] = $section['language_settings'] ?? true;
					continue;
				}

				$this->pages[ $section_id ] = [
					'page_title'  => $section['title'],
					'menu_title'  => $section['title'],
					'menu_slug'   => $section['menu_slug'],
					'id'          => $section_id,
					'parent_slug' => $section['parent'],
					'class'       => 'wpify-woo-settings',
					'option_name' => $section['option_name'] ?? $this->get_settings_name( $section['option_id'] ),
					'tabs'        => $this->is_current( '', $section_id ) ? $section['tabs'] : [],
					'items'       => $this->is_current( '', $section_id ) ? $section['settings'] : [],

					'language_settings' => $section['language_settings'] ?? true,
				];
			}
		}

		foreach ( $this->pages as $page ) {
			$page['position'] = 1;
			$page['callback'] = function () use ( $page ) {
				$this->render_language_notice( $page );
			};
			$this->custom_fields->create_options_page( $page );
		}
	}

	/**
	 * Get plugins
	 *
	 * @return array
	 */
	public function get_plugins(): array {
		if ( $this->plugins_cache !== null ) {
			return $this->plugins_cache;
		}

		$all_plugins = get_plugins();
		$active      = apply_filters( 'wpify_installed_plugins', [] );

		$wpify_plugins = [];
		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$slug = $this->get_plugin_slug( $plugin_file );
			if ( isset( $active[ $slug ] ) ) {
				$wpify_plugins[ $slug ]                = $active[ $slug ];
				$wpify_plugins[ $slug ]['plugin_file'] = $plugin_file;
				continue;
			}

			if ( isset( $plugin_data['Author'] ) && str_contains( strtolower( $plugin_data['Author'] ), 'wpify' ) ) {
				$wpify_plugins[ $slug ] = [
					'title'        => $plugin_data['Name'],
					'desc'         => $plugin_data['Description'],
					'icon'         => '',
					'version'      => $plugin_data['Version'],
					'doc_link'     => '',
					'support_url'  => '',
					'menu_slug'    => '',
					'option_id'    => '',
					'settings_url' => '',
					'plugin_file'  => $plugin_file,
					'tabs'         => [],
					'settings'     => [],
				];
			}
		}

		$this->plugins_cache = $wpify_plugins;

		return $this->plugins_cache;
	}

	/**
	 * Get plugin slug from file path
	 *
	 * @param string $plugin_file Plugin file path
	 *
	 * @return string
	 */
	public function get_plugin_slug( string $plugin_file ): string {
		return basename( $plugin_file, '.php' );
	}

	/**
	 * Get sections
	 *
	 * @param string|null $subpage subpage slug
	 *
	 * @return array
	 */
	public function get_sections( ?string $subpage = null ): array {
		if ( ! $subpage ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only page detection
			$current_page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
			if ( ! str_contains( $current_page, 'wpify/' ) ) {
				return [];
			}

			$subpage = explode( '/', $current_page )[1] ?? '';
		}

		$subpage = sanitize_key( $subpage );
		if ( isset( $this->sections_cache[ $subpage ] ) ) {
			return $this->sections_cache[ $subpage ];
		}

		$this->sections_cache[ $subpage ] = apply_filters( 'wpify_get_sections_' . $subpage, [] );

		return $this->sections_cache[ $subpage ];
	}

	/**
	 * Get an array of enabled modules
	 *
	 * @return array
	 */
	public function get_enabled_modules(): array {
		return $this->get_settings( 'general' )['enabled_modules'] ?? [];
	}

	/**
	 * Set module as active
	 *
	 * @param string $module Module slug
	 *
	 * @return void
	 */
	public function enable_module( string $module ): void {
		$general_settings = $this->get_settings( 'general' );
		$enabled_modules  = $general_settings['enabled_modules'] ?? [];
		if ( ! in_array( $module, $enabled_modules, true ) ) {
			$enabled_modules[]                   = $module;
			$general_settings['enabled_modules'] = $enabled_modules;
			update_option( $this->get_settings_name( 'general' ), $general_settings );
		}
	}

	/**
	 * Get settings for a specific module
	 *
	 * @param string $module Module slug.
	 *
	 * @return array
	 */
	public function get_settings( string $module ): array {
		return get_option( $this->get_settings_name( $module ), [] );
	}

	/**
	 * Get settings name
	 *
	 * @param string $module Module slug
	 *
	 * @return string
	 */
	public function get_settings_name( string $module ): string {
		$key      = sprintf( '%s-%s', self::OPTION_NAME, $module );
		$language = self::get_settings_language();

		if ( 'general' !== $module && $language ) {
			$key = sprintf( '%s_%s', $key, $language );
		}

		return $key;
	}

	/**
	 * Language whose own copy of the settings is read and saved, or '' for the main settings.
	 * "All languages" in the WPML / Polylang admin switcher ('all' in WPML, no language in Polylang)
	 * and the default language both mean the main settings.
	 *
	 * @return string
	 */
	public static function get_settings_language(): string {
		if ( ! defined( 'ICL_LANGUAGE_CODE' ) ) {
			return '';
		}

		$language = (string) ICL_LANGUAGE_CODE;

		if ( '' === $language || 'all' === $language || apply_filters( 'wpml_default_language', null ) === $language ) {
			return '';
		}

		return $language;
	}

	/**
	 * Whether an option is stored. get_option() cannot tell for the settings: a registered setting and the
	 * language fallback both give a missing option a default value.
	 *
	 * @param string $option Option name.
	 *
	 * @return bool
	 */
	public static function option_exists( string $option ): bool {
		global $wpdb;

		$all = wp_load_alloptions();
		if ( isset( $all[ $option ] ) ) {
			return true;
		}

		return null !== $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Explains on a settings page whose language the settings belong to, as soon as the site is multilingual.
	 *
	 * @param array $page Options page arguments.
	 *
	 * @return void
	 */
	public function render_language_notice( array $page ): void {
		$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		$default   = apply_filters( 'wpml_default_language', null );
		$language  = self::get_settings_language();
		$option    = $page['option_name'] ?? '';

		if ( empty( $languages ) || ! is_array( $languages ) || ! $default || '' === $option || self::OPTION_NAME . '-general' === $option ) {
			return;
		}

		// The module keeps one set of settings for all languages (AbstractModule::has_language_settings()).
		if ( false === ( $page['language_settings'] ?? true ) ) {
			return;
		}

		if ( $language ) {
			$suffix = '_' . $language;
			if ( ! str_ends_with( $option, $suffix ) ) {
				// A section with its own option name that does not follow the language.
				return;
			}
			$option = substr( $option, 0, - strlen( $suffix ) );
		}

		$name = static function ( string $code ) use ( $languages ): string {
			if ( 'all' === $code ) {
				return __( 'All languages', 'wpify-core' );
			}

			// The native name reads the same whichever language the admin is switched to.
			return (string) ( $languages[ $code ]['native_name'] ?? $languages[ $code ]['translated_name'] ?? $code );
		};

		$page_url = static function ( string $code ) use ( $page ): string {
			return add_query_arg(
				array(
					'page' => $page['menu_slug'],
					'lang' => $code,
				),
				admin_url( 'admin.php' )
			);
		};

		$delete_button = function ( string $code ) use ( $option, $name ): string {
			$url = wp_nonce_url(
				add_query_arg(
					array(
						'action'   => 'wpify_core_delete_language_settings',
						'option'   => $option,
						'language' => $code,
					),
					admin_url( 'admin-post.php' )
				),
				'wpify_core_delete_language_settings_' . $option . '_' . $code
			);

			return sprintf(
				'<a href="%1$s" class="button button-small wpify-button-delete" onclick="return confirm(%2$s);">%3$s</a>',
				esc_url( $url ),
				esc_attr( wp_json_encode( sprintf(
					/* translators: %s: language name */
					__( 'Delete the settings for %s permanently? The language will use the main settings again.', 'wpify-core' ),
					$name( $code )
				) ) ),
				esc_html( 'all' === $code
					? __( 'Delete unused settings', 'wpify-core' )
					/* translators: %s: language name */
					: sprintf( __( 'Delete settings for %s', 'wpify-core' ), $name( $code ) ) )
			);
		};

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only message after the redirect.
		$deleted = isset( $_GET['wpify-language-settings-deleted'] ) ? sanitize_key( wp_unslash( $_GET['wpify-language-settings-deleted'] ) ) : '';
		if ( $deleted ) {
			printf(
				'<div class="wpify-notice wpify-notice-success"><p>%s</p></div>',
				esc_html( 'all' === $deleted
					? __( 'The unused settings saved under "All languages" were deleted.', 'wpify-core' )
					: sprintf(
						/* translators: %s: language name */
						__( 'The settings for %s were deleted. The language uses the main settings again.', 'wpify-core' ),
						$name( $deleted )
					) )
			);
		}

		if ( ! $language ) {
			$has_unused = self::option_exists( $option . '_all' );
			$own        = array();
			foreach ( array_keys( $languages ) as $code ) {
				if ( $code !== $default && self::option_exists( $option . '_' . $code ) ) {
					$own[] = sprintf( '<a href="%s">%s</a>', esc_url( $page_url( $code ) ), esc_html( $name( $code ) ) );
				}
			}

			// Nothing to explain while no language has settings of its own.
			if ( ! $own && ! $has_unused ) {
				return;
			}
			?>
			<div class="wpify-notice wpify-notice-info wpify-language-notice">
				<div class="wpify-language-notice__text">
					<p><strong><?php esc_html_e( 'You are editing the main settings', 'wpify-core' ); ?></strong> – <?php
						/* translators: %s: default language name */
						echo esc_html( sprintf( __( 'They apply to the default language (%s) and to every other language that has no settings of its own.', 'wpify-core' ), $name( $default ) ) );
					?></p>
					<?php if ( $own ) : ?>
						<p><?php
							/* translators: %s: list of language names */
							echo wp_kses_post( sprintf( __( 'Languages with their own settings (changes made here do not affect them): %s', 'wpify-core' ), implode( ', ', $own ) ) );
						?></p>
					<?php endif; ?>
					<?php if ( $has_unused ) : ?>
						<p><?php esc_html_e( 'Settings saved under "All languages" by an older version are not used anywhere.', 'wpify-core' ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( $has_unused ) : ?>
					<div class="wpify-language-notice__actions">
						<?php echo $delete_button( 'all' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>
					</div>
				<?php endif; ?>
			</div>
			<?php

			return;
		}

		$has_own = self::option_exists( $option . '_' . $language );
		?>
		<div class="wpify-notice wpify-notice-warning wpify-language-notice">
			<div class="wpify-language-notice__text">
				<p><strong><?php
					/* translators: %s: language name */
					echo esc_html( sprintf( __( 'You are editing the settings for %s only', 'wpify-core' ), $name( $language ) ) );
				?></strong> – <?php
					echo esc_html( $has_own
						/* translators: %s: language name */
						? sprintf( __( '%s has its own settings. Changes of the main settings do not apply to it.', 'wpify-core' ), $name( $language ) )
						/* translators: %s: language name */
						: sprintf( __( '%1$s uses the main settings for now. Once you change something here and save, %1$s gets its own copy of all settings on this page, and later changes of the main settings will no longer apply to it.', 'wpify-core' ), $name( $language ) ) );
				?></p>
			</div>
			<div class="wpify-language-notice__actions">
				<a href="<?php echo esc_url( $page_url( $default ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit main settings', 'wpify-core' ); ?></a>
				<?php if ( $has_own ) {
					echo $delete_button( $language ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure.
				} ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Deletes the own copy of the settings of one language, so the language uses the main settings again.
	 *
	 * @return void
	 */
	public function delete_language_settings(): void {
		$option   = isset( $_GET['option'] ) ? sanitize_text_field( wp_unslash( $_GET['option'] ) ) : '';
		$language = isset( $_GET['language'] ) ? sanitize_key( wp_unslash( $_GET['language'] ) ) : '';

		check_admin_referer( 'wpify_core_delete_language_settings_' . $option . '_' . $language );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'wpify-core' ) );
		}

		if ( '' !== $language && str_starts_with( $option, self::OPTION_NAME . '-' ) ) {
			delete_option( $option . '_' . $language );
		}

		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . DashboardPage::SLUG );
		wp_safe_redirect( add_query_arg( 'wpify-language-settings-deleted', $language, remove_query_arg( 'settings-updated', $back ) ) );
		exit;
	}

	/**
	 * Check if is a current settings page
	 *
	 * @param string $tab     tab id
	 * @param string $section section id
	 *
	 * @return bool
	 */
	public function is_current( string $tab = '', string $section = '' ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only page detection
		$current_tab = isset( $_REQUEST['tab'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['tab'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only page detection
		$current_section = isset( $_REQUEST['section'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['section'] ) ) : '';

		if ( $tab === $current_tab && $section === $current_section ) {
			return true;
		}

		$current_module = $this->get_current_module();

		if ( $current_module === $section ) {
			return true;
		}

		foreach ( $this->modules_manager->get_modules() as $module ) {
			$option_name = $this->get_settings_name( $module->get_id() );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a simple presence check
			if ( isset( $_REQUEST[ $option_name ] ) ) {
				return true;
			}
		}

		$module_id = isset( $_GET['module_id'] ) ? sanitize_text_field( wp_unslash( $_GET['module_id'] ) ) : '';
		if ( wp_is_json_request() && $module_id === $section ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is a read-only page detection
		$option_page = isset( $_POST['option_page'] ) ? sanitize_text_field( wp_unslash( $_POST['option_page'] ) ) : '';
		if ( $option_page === $this->get_settings_name( $section ) ) {
			return true;
		}

		if ( $option_page && str_contains( $option_page, $section ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get current module slug
	 *
	 * @return false|string
	 */
	public function get_current_module(): false|string {
		foreach ( $this->modules_manager->get_modules() as $module ) {
			$module_id   = $module->get_id();
			$option_name = $this->get_settings_name( $module_id );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a simple presence check
			if ( isset( $_REQUEST[ $option_name ] ) ) {
				return $module_id;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only page detection
		$current_page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';

		if ( ! str_contains( $current_page, 'wpify/' ) ) {
			return false;
		}

		$page_attributes = explode( '/', $current_page );

		return end( $page_attributes );
	}

	/**
	 * Get woo-core version from composer installed.php
	 *
	 * @return string
	 */
	private function get_core_version(): string {
		// From src/Admin/ go up to the composer dir: woo-core -> wpify -> deps|vendor -> composer
		$installed_php = dirname( __DIR__, 4 ) . '/composer/installed.php';
		if ( file_exists( $installed_php ) ) {
			$data = @include $installed_php;
			if ( is_array( $data ) && isset( $data['versions']['wpify/woo-core']['pretty_version'] ) ) {
				return $data['versions']['wpify/woo-core']['pretty_version'];
			}
		}

		return '0';
	}

	private function should_register_settings(): bool {
		if ( $this->is_wpifycf_rest_request() ) {
			return true;
		}

		/*
		 * Settings pages must be registered on every admin request.
		 * Restricting this to WPify pages breaks menu/submenu registration and makes
		 * settings pages disappear until a WPify page initializes the tree first.
		 */
		return is_admin();
	}

	/**
	 * Add custom class to admin body on wpify pages
	 *
	 * @param string $admin_body_class Existing body classes
	 *
	 * @return string
	 */
	public static function add_admin_body_class( string $admin_body_class = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only page detection
		$current_page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';

		if ( ! str_contains( $current_page, 'wpify' ) ) {
			return $admin_body_class;
		}

		$classes          = explode( ' ', trim( $admin_body_class ) );
		$classes[]        = 'wpify-admin-page';
		$admin_body_class = implode( ' ', array_unique( $classes ) );

		return " $admin_body_class ";
	}
}
