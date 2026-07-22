<?php
/**
 * Plugin Name: MetaPress AI
 * Plugin URI:  https://github.com/binjuhor/metapress-ai
 * Description: Generate SEO and social metadata with AI for posts, pages, and custom post types.
 * Version:     1.0.0
 * Author:      Binjuhor
 * Author URI:  https://binjuhor.com
 * License:     MIT
 * Text Domain: metapress-ai
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'METAPRESS_AI_VERSION', '1.0.0' );
define( 'METAPRESS_AI_FILE', __FILE__ );
define( 'METAPRESS_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'METAPRESS_AI_URL', plugin_dir_url( __FILE__ ) );

require_once METAPRESS_AI_DIR . 'includes/class-metapress-ai-settings.php';
require_once METAPRESS_AI_DIR . 'includes/class-metapress-ai-provider-client.php';
require_once METAPRESS_AI_DIR . 'includes/class-metapress-ai-plugin.php';

register_activation_hook(
	__FILE__,
	static function () {
		if ( false === get_option( 'metapress_ai_settings', false ) ) {
			add_option( 'metapress_ai_settings', MetaPress_AI_Settings::defaults(), '', false );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		MetaPress_AI_Plugin::instance()->boot();
	}
);
