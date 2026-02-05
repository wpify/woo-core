<?php

// Prefer the newest wpify/woo-core autoloader when multiple WPify plugins are active.

if ( ! function_exists( 'wpify_woo_core_prefer_latest' ) ) {
	function wpify_woo_core_prefer_latest(): void {
		static $last_count = 0;

		if ( ! class_exists( \Composer\Autoload\ClassLoader::class, false ) ) {
			return;
		}

		$loaders = \Composer\Autoload\ClassLoader::getRegisteredLoaders();
		if ( ! is_array( $loaders ) || empty( $loaders ) ) {
			return;
		}

		$count = count( $loaders );
		if ( $count === $last_count ) {
			return;
		}
		$last_count = $count;

		$best_vendor  = null;
		$best_version = null;

		foreach ( $loaders as $vendor_dir => $loader ) {
			$version = wpify_woo_core_get_version_from_vendor( $vendor_dir );
			if ( ! $version ) {
				continue;
			}
			if ( $best_vendor === null || version_compare( $version, $best_version, '>' ) ) {
				$best_vendor  = $vendor_dir;
				$best_version = $version;
			}
		}

	if ( $best_vendor && isset( $loaders[ $best_vendor ] ) ) {
		$best_loader = $loaders[ $best_vendor ];
		if ( $best_loader instanceof \Composer\Autoload\ClassLoader ) {
			$best_loader->unregister();
			$best_loader->register( true );
		}
	}
}
}

if ( ! function_exists( 'wpify_woo_core_get_version_from_vendor' ) ) {
	function wpify_woo_core_get_version_from_vendor( string $vendor_dir ): ?string {
		static $cache = array();

		if ( array_key_exists( $vendor_dir, $cache ) ) {
			return $cache[ $vendor_dir ];
		}

		$version = null;
		$installed_php = $vendor_dir . '/composer/installed.php';
		if ( is_file( $installed_php ) ) {
			$data = @include $installed_php;
			if ( is_array( $data ) ) {
				$version = wpify_woo_core_extract_version_from_installed( $data );
			}
		}

		if ( ! $version ) {
			$installed_json = $vendor_dir . '/composer/installed.json';
			if ( is_file( $installed_json ) ) {
				$content = file_get_contents( $installed_json );
				if ( $content !== false ) {
					$data = json_decode( $content, true );
					if ( is_array( $data ) ) {
						$version = wpify_woo_core_extract_version_from_installed( $data );
					}
				}
			}
		}

		$cache[ $vendor_dir ] = $version;

		return $version;
	}
}

if ( ! function_exists( 'wpify_woo_core_extract_version_from_installed' ) ) {
	function wpify_woo_core_extract_version_from_installed( array $data ): ?string {
		$packages = array();

		if ( isset( $data['versions'] ) && is_array( $data['versions'] ) ) {
			$packages = $data['versions'];
		} elseif ( isset( $data['packages'] ) && is_array( $data['packages'] ) ) {
			foreach ( $data['packages'] as $package ) {
				if ( isset( $package['name'] ) ) {
					$packages[ $package['name'] ] = $package;
				}
			}
		} elseif ( isset( $data[0] ) && is_array( $data[0] ) ) {
			foreach ( $data as $set ) {
				if ( isset( $set['versions'] ) && is_array( $set['versions'] ) ) {
					$packages = array_merge( $packages, $set['versions'] );
				} elseif ( isset( $set['packages'] ) && is_array( $set['packages'] ) ) {
					foreach ( $set['packages'] as $package ) {
						if ( isset( $package['name'] ) ) {
							$packages[ $package['name'] ] = $package;
						}
					}
				}
			}
		}

		$core = $packages['wpify/woo-core'] ?? null;
		if ( ! is_array( $core ) ) {
			return null;
		}

		$version = $core['pretty_version'] ?? $core['version'] ?? null;

		return is_string( $version ) && $version !== '' ? $version : null;
	}
}
