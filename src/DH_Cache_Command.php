<?php

/**
 * Page cache detection and WP Super Cache configuration.
 */
class DH_Cache_Command {

	/**
	 * Detects presence of a page cache.
	 *
	 * Page cache detection happens in two steps:
	 *
	 * 1. If the `WP_CACHE` constant is true and `advanced-cache.php` exists,
	 * then `page_cache=enabled`. However, if `advanced-cache.php` is missing,
	 * then `page_cache=broken`.
	 * 2. Scans `active_plugins` options for known page cache plugins, and
	 * reports them if found.
	 *
	 * See 'Examples' section for demonstrations of usage.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a specific format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # WP Super Cache detected.
	 *     $ wp dh-cache detect
	 *     +-------------------+----------------+
	 *     | key               | value          |
	 *     +-------------------+----------------+
	 *     | page_cache        | enabled        |
	 *     | page_cache_plugin | wp-super-cache |
	 *     +-------------------+----------------+
	 *
	 *     # Page cache detected but plugin is unknown.
	 *     $ wp dh-cache detect
	 *     +-------------------+---------+
	 *     | key               | value   |
	 *     +-------------------+---------+
	 *     | page_cache        | enabled |
	 *     | page_cache_plugin | unknown |
	 *     +-------------------+---------+
	 *
	 *     # No page cache detected.
	 *     $ wp dh-cache detect
	 *     +-------------------+----------+
	 *     | key               | value    |
	 *     +-------------------+----------+
	 *     | page_cache        | disabled |
	 *     | page_cache_plugin | none     |
	 *     +-------------------+----------+
	 *
	 * @subcommand detect
	 */
	public function detect( $_, $assoc_args ) {

		$status = array(
			'page_cache'        => 'disabled',
			'page_cache_plugin' => 'none',
		);

		if ( WP_CACHE ) {
			if ( is_readable( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
				$status['page_cache'] = 'enabled';
			} else {
				$status['page_cache'] = 'broken';
			}
			$status['page_cache_plugin'] = 'unknown';
		}

		$plugins = self::detect_page_cache_plugins();
		if ( ! empty( $plugins ) ) {
			$status['page_cache_plugin'] = implode( ',', $plugins );
		}

		self::format_items( $assoc_args['format'], $status );
	}

	/**
	 * Configures WP Super Cache settings.
	 *
	 * Imposes expected value for the following settings:
	 *
	 * * Enabled: Full-page caching.
	 * * Enabled: Expert cache delivery method.
	 * * Enabled: .htaccess rewrite rules for expert cache.
	 * * Disabled: Caching pages for logged-in users.
	 * * Disabled: Caching pages with GET parameters.
	 * * Enabled: Serve existing cache while being generated.
	 * * Disabled: Make known users anonymous and serve supercached files.
	 * * Disabled: Proudly tell the world your server is Stephen Fry proof.
	 * * Enabled: Mobile device support.
	 *
	 * See 'Examples' section for demonstrations of usage.
	 *
	 * ## EXAMPLES
	 *
	 *     # Three settings are incorrect and updated.
	 *     $ wp dh-cache configure-super-cache-settings
	 *     Updated 'Don't cache pages for known users' to 'enabled'.
	 *     Updated 'Don't cache pages with GET parameters' to 'enabled'.
	 *     Updated 'Serve existing cache while being generated' to 'enabled'.
	 *     Success: Updated 3 WP Super Cache settings.
	 *
	 *     # No cache settings are incorrect.
	 *     $ wp dh-cache configure-super-cache-settings
	 *     Success: All WP Super Cache settings are correctly configured without changes.
	 *
	 * @subcommand configure-super-cache-settings
	 */
	public function configure_super_cache_settings() {
		self::verify_wp_super_cache_setup();

		$updated = 0;
		foreach ( self::get_wp_super_cache_settings() as $sc_setting ) {

			$actual = self::get_wp_super_cache_actual_value( $sc_setting );

			// WP Super Cache does a mix of strict and non-strict comparisons.
			if ( $actual == $sc_setting['expected'] ) {
				continue;
			}

			$expected_display = $sc_setting['expected'];
			if ( is_bool( $expected_display ) || is_numeric( $expected_display ) ) {
				$expected_display = $expected_display ? 'enabled' : 'disabled';
			}

			$actual_display = $actual;
			if ( is_bool( $sc_setting['expected'] ) || is_numeric( $sc_setting['expected'] ) ) {
				$actual_display = $actual_display ? 'enabled' : 'disabled';
			}

			if ( isset( $sc_setting['global'] ) ) {
				wp_cache_setting( $sc_setting['global'], $sc_setting['expected'] );
			} elseif ( isset( $sc_setting['update_callback'] ) ) {
				$sc_setting['update_callback']();
				$actual = $sc_setting['get_callback']();
				if ( $actual != $sc_setting['expected'] ) {
					WP_CLI::error( "Failed to update '{$sc_setting['label']}'." );
				}
			} else {
				WP_CLI::error( 'Invalid WP Super Cache setting key.' );
			}

			WP_CLI::log( "Updated '{$sc_setting['label']}' to '{$expected_display}'." );
			$updated++;
		}

		if ( $updated ) {
			$message = "Updated {$updated} WP Super Cache settings.";
		} else {
			$message = 'All WP Super Cache settings are correctly configured without changes.';
		}
		WP_CLI::success( $message );
	}

	/**
	 * Verifies WP Super Cache configuration settings.
	 *
	 * Checks the following configuration settings for correct values:
	 *
	 * * Enabled: Full-page caching.
	 * * Enabled: Expert cache delivery method.
	 * * Enabled: .htaccess rewrite rules for expert cache.
	 * * Disabled: Caching pages for logged-in users.
	 * * Disabled: Caching pages with GET parameters.
	 * * Enabled: Serve existing cache while being generated.
	 * * Disabled: Make known users anonymous and serve supercached files.
	 * * Disabled: Proudly tell the world your server is Stephen Fry proof.
	 * * Enabled: Mobile device support.
	 *
	 * See 'Examples' section for demonstrations of usage.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a specific format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # One cache setting is incorrect.
	 *     $ wp dh-cache verify-super-cache-settings
	 *     +-----------------------------------+----------+----------+
	 *     | setting                           | actual   | expected |
	 *     +-----------------------------------+----------+----------+
	 *     | Caching enabled                   | enabled  | enabled  |
	 *     | Don't cache pages for known users | disabled | enabled  |
	 *     | [...]                             |          |          |
	 *     +-----------------------------------+----------+----------+
	 *     Error: 1 WP Super Cache setting is incorrect.
	 *
	 * @subcommand verify-super-cache-settings
	 */
	public function verify_super_cache_settings( $_, $assoc_args ) {
		self::verify_wp_super_cache_setup();
		$settings = array();
		$incorrect = 0;
		foreach ( self::get_wp_super_cache_settings() as $sc_setting ) {
			$expected_display = $sc_setting['expected'];
			if ( is_bool( $expected_display ) || is_numeric( $expected_display ) ) {
				$expected_display = $expected_display ? 'enabled' : 'disabled';
			}

			$actual         = self::get_wp_super_cache_actual_value( $sc_setting );
			$actual_display = $actual;
			if ( is_bool( $sc_setting['expected'] ) || is_numeric( $sc_setting['expected'] ) ) {
				$actual_display = $actual_display ? 'enabled' : 'disabled';
			}

			// WP Super Cache does a mix of strict and non-strict comparisons.
			if ( $actual != $sc_setting['expected'] ) {
				$incorrect++;
			}

			$setting_data = array(
				'setting'  => $sc_setting['label'],
				'expected' => $expected_display,
				'actual'   => $actual_display,
			);
			$settings[] = $setting_data;
		}

		WP_CLI\Utils\format_items( $assoc_args['format'], $settings, array( 'setting', 'actual', 'expected' ) );
		if ( 'table' === $assoc_args['format'] ) {
			if ( $incorrect ) {
				$grammar = $incorrect > 1 ? 'settings are' : 'setting is';
				WP_CLI::error( "{$incorrect} WP Super Cache {$grammar} incorrect." );
			} else {
				WP_CLI::success( "All WP Super Cache settings are correct." );
			}
		} else {
			WP_CLI::halt( $incorrect ? 1 : 0 );
		}
	}

	/**
	 * Verifies that the basics of WP Super Cache are functional.
	 */
	private static function verify_wp_super_cache_setup() {
		$page_cache_plugins = self::detect_page_cache_plugins();
		if ( ! in_array( 'wp-super-cache', $page_cache_plugins ) ) {
			WP_CLI::error( 'WP Super Cache is not active.' );
		}
		if ( ! WP_CACHE ) {
			WP_CLI::error( 'WP_CACHE constant is false and should be true.' );
		}
		if ( ! is_readable( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
			WP_CLI::error( "advanced-cache.php isn't readable and likely misconfigured." );
		}
	}

	/**
	 * Detects any active page cache plugins.
	 *
	 * @return array
	 */
	private static function detect_page_cache_plugins() {
		$page_cache_plugins        = array(
			'comet-cache/comet-cache.php',
			'comet-cache-pro/comet-cache-pro.php',
			'wp-fast-cache/wp-fast-cache.php',
			'quick-cache/quick-cache.php',
			'simple-cache/simple-cache.php',
			'wp-cache/wp-cache.php',
			'wp-fastest-cache-premium/wpFastestCachePremium.php',
			'wp-fastest-cache/wpFastestCache.php',
			'w3-total-cache/w3-total-cache.php',
			'wp-super-cache/wp-cache.php',
		);
		$active_plugins            = get_option( 'active_plugins' );
		$active_page_cache_plugins = array_intersect( $page_cache_plugins, $active_plugins );

		return array_map( function( $plugin ) {
			$bits = explode( '/', $plugin );
			return $bits[0];
		}, $active_page_cache_plugins );
	}

	/**
	 * Expected WP Super Cache settings.
	 */
	private static function get_wp_super_cache_settings() {
		$settings = array(
			array(
				'label'    => 'Caching enabled',
				'global'   => 'super_cache_enabled',
				'expected' => true,
			),
			array(
				'label'    => 'Expert cache delivery method',
				'global'   => 'wp_cache_mod_rewrite',
				'expected' => 1,
			),
			array(
				'label'    => "Don't cache pages for known users",
				'global'   => 'wp_cache_not_logged_in',
				'expected' => 1,
			),
			array(
				'label'    => "Don't cache pages with GET parameters",
				'global'   => 'wp_cache_no_cache_for_get',
				'expected' => 1,
			),
			array(
				'label'    => 'Serve existing cache while being generated',
				'global'   => 'cache_rebuild_files',
				'expected' => 1,
			),
			array(
				'label'    => 'Make known users anonymous and serve supercached files',
				'global'   => 'wp_cache_make_known_anon',
				'expected' => 0,
			),
			array(
				'label'    => 'Proudly tell the world your server is Stephen Fry proof',
				'global'   => 'wp_cache_hello_world',
				'expected' => 0,
			),
			array(
				'label'    => 'Mobile device support',
				'global'   => 'wp_cache_mobile_enabled',
				'expected' => 1,
			),
			// Always needs to be last, because it's dependent on the other
			// settings.
			array(
				'label'           => '.htaccess rewrite rules for expert cache',
				'expected'        => 'configured',
				'get_callback'    => function() {
					$home_path = ABSPATH;
					$home_path = trailingslashit( $home_path );
					if ( ! file_exists( $home_path . '.htaccess' ) ) {
						return 'missing-htaccess';
					}
					if ( ! function_exists( 'extract_from_markers' ) ) {
						include_once( ABSPATH . 'wp-admin/includes/misc.php' );
					}
					$generated_rules = wpsc_get_htaccess_info();
					$existing_rules  = implode( "\n", extract_from_markers( $home_path . '.htaccess', 'WPSuperCache' ) );
					if ( $generated_rules['rules'] !== $existing_rules ) {
						return 'missing-rules';
					}
					return 'configured';
				},
				'update_callback' => function() {
					global $update_mod_rewrite_rules_error;
					update_mod_rewrite_rules();
					if ( $update_mod_rewrite_rules_error ) {
						WP_CLI::warning( "Mod rewrite update failure: {$update_mod_rewrite_rules_error}" );
					}
				},
			),
		);
		// Load global variables into scope because they aren't handled by WP-CLI.
		foreach ( $settings as $setting ) {
			if ( isset( $setting['global'] ) ) {
				global ${$setting['global']};
			}
		}
		@include WP_CONTENT_DIR . '/wp-cache-config.php';
		return $settings;
	}

	/**
	 * Get the actual value for a WP Super Cache setting.
	 *
	 * @param array $setting Details about the setting.
	 * @return mixed
	 */
	private static function get_wp_super_cache_actual_value( $setting ) {
		$actual = null;
		if ( isset( $setting['global'] ) ) {
			$actual = $GLOBALS[ $setting['global'] ];
		} elseif ( isset( $setting['get_callback'] ) ) {
			$actual = $setting['get_callback']();
		}
		return $actual;
	}

	/**
	 * Modified version of WP_CLI\Formatter to accommodate
	 * for a single array of data.
	 *
	 * @param string $format Format to display data as.
	 * @param array  $items  Single array of data.
	 */
	private static function format_items( $format, $items ) {
		switch ( $format ) {
			case 'json':
				echo json_encode( $items );
				break;
			case 'table':
				$table = new \cli\Table();
				$enabled = \cli\Colors::shouldColorize();
				if ( $enabled ) {
					\cli\Colors::disable( true );
				}

				$table->setHeaders( array( 'key', 'value' ) );

				foreach ( $items as $key => $value ) {
					$table->addRow( array( $key, $value ) );
				}

				foreach ( $table->getDisplayLines() as $line ) {
					\WP_CLI::line( $line );
				}

				if ( $enabled ) {
					\cli\Colors::enable( true );
				}
				break;
			case 'yaml':
				echo Mustangostang\Spyc::YAMLDump( $items, 2, 0 );
				break;
		}
	}

}
