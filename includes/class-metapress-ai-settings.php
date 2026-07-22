<?php

defined( 'ABSPATH' ) || exit;

final class MetaPress_AI_Settings {
	public static function defaults() {
		return array(
			'provider'   => 'openai',
			'providers'  => array(
				'openai'   => array( 'api_key' => '', 'model' => 'gpt-5.6-sol', 'base_url' => '' ),
				'deepseek' => array( 'api_key' => '', 'model' => 'deepseek-v4-flash', 'base_url' => '' ),
				'gemini'   => array( 'api_key' => '', 'model' => 'gemini-3.5-flash', 'base_url' => '' ),
				'claude'   => array( 'api_key' => '', 'model' => 'claude-haiku-4-5-20251001', 'base_url' => '' ),
				'ollama'   => array( 'api_key' => '', 'model' => 'gpt-oss', 'base_url' => 'https://ollama.com' ),
			),
			'post_types' => array( 'post', 'page' ),
			'language'   => '',
			'brand_voice' => '',
		);
	}

	public static function get() {
		$stored = (array) get_option( 'metapress_ai_settings', array() );
		$defaults = self::defaults();
		$settings = wp_parse_args( $stored, $defaults );
		$settings['providers'] = array_replace_recursive( $defaults['providers'], isset( $stored['providers'] ) ? (array) $stored['providers'] : array() );
		if ( isset( $stored['api_key'] ) && empty( $settings['providers']['openai']['api_key'] ) ) $settings['providers']['openai']['api_key'] = $stored['api_key'];
		if ( isset( $stored['model'] ) && ! empty( $stored['model'] ) ) $settings['providers']['openai']['model'] = $stored['model'];
		return $settings;
	}

	public static function providers() {
		return array( 'openai' => 'OpenAI', 'deepseek' => 'DeepSeek', 'gemini' => 'Google Gemini', 'claude' => 'Anthropic Claude', 'ollama' => 'Ollama' );
	}

	public static function provider_label( $provider ) {
		$providers = self::providers();
		return isset( $providers[ $provider ] ) ? $providers[ $provider ] : $provider;
	}

	public static function provider_config( $provider ) {
		$settings = self::get();
		$config = isset( $settings['providers'][ $provider ] ) ? $settings['providers'][ $provider ] : array( 'api_key' => '', 'model' => '', 'base_url' => '' );
		$constant = 'METAPRESS_AI_' . strtoupper( $provider ) . '_API_KEY';
		if ( defined( $constant ) && constant( $constant ) ) $config['api_key'] = (string) constant( $constant );
		return $config;
	}

	public static function register() {
		register_setting(
			'metapress_ai',
			'metapress_ai_settings',
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);

		add_settings_section( 'metapress_ai_main', __( 'AI provider', 'metapress-ai' ), '__return_false', 'metapress-ai' );
		add_settings_field( 'provider', __( 'Active provider', 'metapress-ai' ), array( __CLASS__, 'provider_field' ), 'metapress-ai', 'metapress_ai_main' );
		add_settings_field( 'provider_config', __( 'Provider credentials and models', 'metapress-ai' ), array( __CLASS__, 'provider_config_field' ), 'metapress-ai', 'metapress_ai_main' );
		add_settings_field( 'post_types', __( 'Enabled post types', 'metapress-ai' ), array( __CLASS__, 'post_types_field' ), 'metapress-ai', 'metapress_ai_main' );
		add_settings_field( 'language', __( 'Default language', 'metapress-ai' ), array( __CLASS__, 'language_field' ), 'metapress-ai', 'metapress_ai_main' );
		add_settings_field( 'brand_voice', __( 'Brand voice', 'metapress-ai' ), array( __CLASS__, 'brand_voice_field' ), 'metapress-ai', 'metapress_ai_main' );
	}

	public static function sanitize( $input ) {
		$current = self::get();
		$provider_names = array_keys( self::providers() );
		$provider = isset( $input['provider'] ) && in_array( $input['provider'], $provider_names, true ) ? $input['provider'] : 'openai';
		$provider_settings = array();
		foreach ( $provider_names as $name ) {
			$submitted = isset( $input['providers'][ $name ] ) ? (array) $input['providers'][ $name ] : array();
			$key = isset( $submitted['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $submitted['api_key'] ) ) ) : '';
			if ( '' === $key ) $key = $current['providers'][ $name ]['api_key'];
			$provider_settings[ $name ] = array(
				'api_key' => $key,
				'model' => isset( $submitted['model'] ) ? sanitize_text_field( wp_unslash( $submitted['model'] ) ) : $current['providers'][ $name ]['model'],
				'base_url' => 'ollama' === $name && isset( $submitted['base_url'] ) ? esc_url_raw( trim( wp_unslash( $submitted['base_url'] ) ) ) : $current['providers'][ $name ]['base_url'],
			);
		}
		$available = array_keys( get_post_types( array( 'show_ui' => true ), 'names' ) );
		$types     = isset( $input['post_types'] ) ? array_map( 'sanitize_key', (array) $input['post_types'] ) : array();

		return array(
			'provider'     => $provider,
			'providers'    => $provider_settings,
			'post_types'  => array_values( array_intersect( $types, $available ) ),
			'language'    => isset( $input['language'] ) ? sanitize_text_field( wp_unslash( $input['language'] ) ) : '',
			'brand_voice' => isset( $input['brand_voice'] ) ? sanitize_textarea_field( wp_unslash( $input['brand_voice'] ) ) : '',
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MetaPress AI', 'metapress-ai' ); ?></h1>
			<p><?php esc_html_e( 'Post content is sent to the active AI provider only when an authorized editor starts generation. Review individual suggestions before publishing; bulk generation applies the first validated suggestion.', 'metapress-ai' ); ?></p>
			<form action="options.php" method="post">
				<?php settings_fields( 'metapress_ai' ); do_settings_sections( 'metapress-ai' ); submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function provider_field() {
		$settings = self::get();
		echo '<select name="metapress_ai_settings[provider]">';
		foreach ( self::providers() as $value => $label ) printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $settings['provider'], $value, false ), esc_html( $label ) );
		echo '</select>';
	}

	public static function provider_config_field() {
		$settings = self::get();
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Provider', 'metapress-ai' ) . '</th><th>' . esc_html__( 'API key', 'metapress-ai' ) . '</th><th>' . esc_html__( 'Model', 'metapress-ai' ) . '</th><th>' . esc_html__( 'Base URL', 'metapress-ai' ) . '</th></tr></thead><tbody>';
		foreach ( self::providers() as $name => $label ) {
			$config = self::provider_config( $name );
			$constant = 'METAPRESS_AI_' . strtoupper( $name ) . '_API_KEY';
			$defined = defined( $constant ) && constant( $constant );
			printf( '<tr><th>%s</th><td><input type="password" autocomplete="new-password" name="metapress_ai_settings[providers][%s][api_key]" value="" placeholder="%s" %s></td><td><input name="metapress_ai_settings[providers][%s][model]" value="%s"></td><td>%s</td></tr>', esc_html( $label ), esc_attr( $name ), esc_attr( $config['api_key'] ? __( 'Configured; leave blank to keep', 'metapress-ai' ) : ( 'ollama' === $name ? __( 'Required for Ollama Cloud', 'metapress-ai' ) : 'API key' ) ), disabled( $defined, true, false ), esc_attr( $name ), esc_attr( $config['model'] ), 'ollama' === $name ? '<input name="metapress_ai_settings[providers][ollama][base_url]" value="' . esc_attr( $config['base_url'] ) . '">' : '—' );
		}
		echo '</tbody></table><p class="description">' . esc_html__( 'Keys remain server-side. Use https://ollama.com with an API key for Ollama Cloud, or http://localhost:11434 for local Ollama.', 'metapress-ai' ) . '</p>';
	}

	public static function post_types_field() {
		$settings = self::get();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			printf( '<label><input type="checkbox" name="metapress_ai_settings[post_types][]" value="%s" %s> %s</label><br>', esc_attr( $type->name ), checked( in_array( $type->name, $settings['post_types'], true ), true, false ), esc_html( $type->labels->singular_name ) );
		}
	}

	public static function language_field() {
		$settings = self::get();
		printf( '<input class="regular-text" name="metapress_ai_settings[language]" value="%s" placeholder="%s">', esc_attr( $settings['language'] ), esc_attr__( 'Auto-detect from content', 'metapress-ai' ) );
	}

	public static function brand_voice_field() {
		$settings = self::get();
		printf( '<textarea class="large-text" rows="4" name="metapress_ai_settings[brand_voice]">%s</textarea>', esc_textarea( $settings['brand_voice'] ) );
	}
}
