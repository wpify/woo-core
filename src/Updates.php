<?php

namespace Wpify\WpifyWooCore;

use Puc_v4_Factory;

class Updates {
	public function init_updates_check( $plugin_slug, $plugin_file, $extra_data = [] ) {
		$url = sprintf( 'https://wpify.io/?update_action=get_metadata&update_slug=%s&site_url=%s', $plugin_slug, site_url() );
		$url = add_query_arg( $extra_data, $url );

		Puc_v4_Factory::buildUpdateChecker(
			$url,
			$plugin_file,
			$plugin_slug
		);
	}
}