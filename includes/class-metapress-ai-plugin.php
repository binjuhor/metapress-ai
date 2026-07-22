<?php

defined( 'ABSPATH' ) || exit;

final class MetaPress_AI_Plugin {
	private static $instance;
	private $fields = array(
		'focus_keyphrase'     => '_yoast_wpseo_focuskw',
		'seo_title'           => '_yoast_wpseo_title',
		'meta_description'    => '_yoast_wpseo_metadesc',
		'og_title'            => '_yoast_wpseo_opengraph-title',
		'og_description'      => '_yoast_wpseo_opengraph-description',
		'twitter_title'       => '_yoast_wpseo_twitter-title',
		'twitter_description' => '_yoast_wpseo_twitter-description',
	);

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot() {
		add_action( 'admin_init', array( 'MetaPress_AI_Settings', 'register' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		$settings = MetaPress_AI_Settings::get();
		foreach ( $settings['post_types'] as $post_type ) {
			add_filter( 'bulk_actions-edit-' . $post_type, array( $this, 'register_bulk_action' ) );
			add_filter( 'handle_bulk_actions-edit-' . $post_type, array( $this, 'handle_bulk_action' ), 10, 3 );
		}
	}

	public function admin_menu() {
		add_options_page( __( 'MetaPress AI', 'metapress-ai' ), __( 'MetaPress AI', 'metapress-ai' ), 'manage_options', 'metapress-ai', array( 'MetaPress_AI_Settings', 'render_page' ) );
		add_management_page( __( 'MetaPress AI bulk generation', 'metapress-ai' ), __( 'MetaPress AI bulk generation', 'metapress-ai' ), 'edit_posts', 'metapress-ai-bulk', array( $this, 'render_bulk_page' ) );
	}

	public function register_bulk_action( $actions ) {
		$actions['metapress_ai_generate'] = __( 'Generate SEO metadata with AI', 'metapress-ai' );
		return $actions;
	}

	public function handle_bulk_action( $redirect_url, $action, $post_ids ) {
		if ( 'metapress_ai_generate' !== $action ) return $redirect_url;
		$settings = MetaPress_AI_Settings::get();
		$allowed = array();
		foreach ( array_map( 'absint', $post_ids ) as $post_id ) {
			$post = get_post( $post_id );
			if ( $post && current_user_can( 'edit_post', $post_id ) && in_array( $post->post_type, $settings['post_types'], true ) ) $allowed[] = $post_id;
		}
		$job = wp_generate_password( 20, false, false );
		set_transient( 'metapress_ai_job_' . get_current_user_id() . '_' . $job, $allowed, HOUR_IN_SECONDS );
		return add_query_arg( array( 'page' => 'metapress-ai-bulk', 'job' => $job ), admin_url( 'tools.php' ) );
	}

	public function render_bulk_page() {
		$job = isset( $_GET['job'] ) ? sanitize_key( wp_unslash( $_GET['job'] ) ) : '';
		$ids = get_transient( 'metapress_ai_job_' . get_current_user_id() . '_' . $job );
		if ( ! is_array( $ids ) ) $ids = array();
		echo '<div class="wrap"><h1>' . esc_html__( 'MetaPress AI bulk generation', 'metapress-ai' ) . '</h1>';
		if ( ! $ids ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'This bulk job is empty or has expired.', 'metapress-ai' ) . '</p></div></div>';
			return;
		}
		printf( '<p>%s</p><div class="metapress-ai-progress"><div id="metapress-ai-progress-bar"></div></div><p id="metapress-ai-bulk-status"></p><ol id="metapress-ai-bulk-results"></ol></div>', esc_html( sprintf( _n( '%d selected item will be processed.', '%d selected items will be processed.', count( $ids ), 'metapress-ai' ), count( $ids ) ) ) );
		wp_enqueue_style( 'metapress-ai-editor', METAPRESS_AI_URL . 'assets/editor.css', array(), METAPRESS_AI_VERSION );
		wp_enqueue_script( 'metapress-ai-bulk', METAPRESS_AI_URL . 'assets/bulk.js', array(), METAPRESS_AI_VERSION, true );
		wp_localize_script( 'metapress-ai-bulk', 'MetaPressAIBulk', array( 'endpoint' => esc_url_raw( rest_url( 'metapress-ai/v1/bulk-item' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'job' => $job, 'ids' => array_values( $ids ) ) );
	}

	public function add_meta_boxes() {
		$settings = MetaPress_AI_Settings::get();
		foreach ( $settings['post_types'] as $post_type ) {
			add_meta_box( 'metapress-ai', __( 'MetaPress AI', 'metapress-ai' ), array( $this, 'render_meta_box' ), $post_type, 'normal', 'high' );
		}
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'metapress_ai_save_metadata', 'metapress_ai_nonce' );
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Yoast SEO is not active. Generated values can be saved, but MetaPress AI will not output frontend tags by itself.', 'metapress-ai' ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Generate three editable SEO and social metadata sets. Content is sent to the active AI provider only when you click Generate.', 'metapress-ai' ) . '</p>';
		echo '<p><button type="button" class="button button-primary" id="metapress-ai-generate">' . esc_html__( 'Generate suggestions', 'metapress-ai' ) . '</button> <span class="spinner" id="metapress-ai-spinner"></span></p>';
		echo '<div id="metapress-ai-message" role="status" aria-live="polite"></div><div id="metapress-ai-suggestions"></div>';
		echo '<p><button type="button" class="button button-link" id="metapress-ai-toggle-fields" aria-expanded="false">' . esc_html__( 'Show/edit metadata fields', 'metapress-ai' ) . '</button></p>';
		echo '<div class="metapress-ai-fields" id="metapress-ai-fields" hidden>';
		foreach ( $this->fields as $field => $meta_key ) {
			$value = get_post_meta( $post->ID, $meta_key, true );
			printf( '<p><label for="metapress-ai-%1$s"><strong>%2$s</strong></label><br><textarea id="metapress-ai-%1$s" name="metapress_ai[%1$s]" rows="2" class="widefat">%3$s</textarea><small class="metapress-ai-count" data-for="metapress-ai-%1$s"></small></p>', esc_attr( $field ), esc_html( $this->label( $field ) ), esc_textarea( $value ) );
		}
		echo '</div>';
	}

	public function enqueue_editor_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		$settings = MetaPress_AI_Settings::get();
		if ( ! $screen || ! in_array( $screen->post_type, $settings['post_types'], true ) ) {
			return;
		}
		wp_enqueue_style( 'metapress-ai-editor', METAPRESS_AI_URL . 'assets/editor.css', array(), METAPRESS_AI_VERSION );
		wp_enqueue_script( 'metapress-ai-editor', METAPRESS_AI_URL . 'assets/editor.js', array(), METAPRESS_AI_VERSION, true );
		wp_localize_script( 'metapress-ai-editor', 'MetaPressAI', array(
			'endpoint' => esc_url_raw( rest_url( 'metapress-ai/v1/generate' ) ),
			'applyEndpoint' => esc_url_raw( rest_url( 'metapress-ai/v1/apply' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'postId'   => isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0,
			'labels'   => array( 'apply' => __( 'Apply this set to Yoast', 'metapress-ai' ), 'applying' => __( 'Applying metadata to Yoast…', 'metapress-ai' ), 'generating' => __( 'Generating metadata…', 'metapress-ai' ), 'applied' => __( 'Metadata saved to Yoast.', 'metapress-ai' ), 'refresh' => __( 'Refresh the page to see it in the Yoast metabox', 'metapress-ai' ), 'showFields' => __( 'Show/edit metadata fields', 'metapress-ai' ), 'hideFields' => __( 'Hide metadata fields', 'metapress-ai' ) ),
		) );
	}

	public function register_routes() {
		register_rest_route( 'metapress-ai/v1', '/generate', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => function ( $request ) {
				$post_id = absint( $request->get_param( 'post_id' ) );
				return $post_id && current_user_can( 'edit_post', $post_id );
			},
			'callback'            => array( $this, 'generate' ),
			'args'                => array(
				'post_id'         => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'focus_keyphrase' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );
		register_rest_route( 'metapress-ai/v1', '/bulk-item', array(
			'methods' => WP_REST_Server::CREATABLE,
			'permission_callback' => function ( $request ) { $post_id = absint( $request->get_param( 'post_id' ) ); return $post_id && current_user_can( 'edit_post', $post_id ); },
			'callback' => array( $this, 'bulk_item' ),
			'args' => array( 'post_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ), 'job' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ) ),
		) );
		register_rest_route( 'metapress-ai/v1', '/apply', array(
			'methods' => WP_REST_Server::CREATABLE,
			'permission_callback' => function ( $request ) { $post_id = absint( $request->get_param( 'post_id' ) ); return $post_id && current_user_can( 'edit_post', $post_id ); },
			'callback' => array( $this, 'apply_metadata' ),
			'args' => array( 'post_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ), 'metadata' => array( 'required' => true, 'type' => 'object' ) ),
		) );
	}

	public function generate( WP_REST_Request $request ) {
		$post = get_post( $request->get_param( 'post_id' ) );
		$settings = MetaPress_AI_Settings::get();
		if ( ! $post || ! in_array( $post->post_type, $settings['post_types'], true ) ) {
			return new WP_Error( 'metapress_ai_invalid_post', __( 'This content type is not enabled for MetaPress AI.', 'metapress-ai' ), array( 'status' => 400 ) );
		}
		$rate_key = 'metapress_ai_rate_' . get_current_user_id();
		if ( get_transient( $rate_key ) ) {
			return new WP_Error( 'metapress_ai_rate_limit', __( 'Please wait a few seconds before generating again.', 'metapress-ai' ), array( 'status' => 429 ) );
		}
		set_transient( $rate_key, 1, 5 );
		$result = ( new MetaPress_AI_Provider_Client() )->generate( $post, $request->get_param( 'focus_keyphrase' ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'suggestions' => $result ) );
	}

	public function bulk_item( WP_REST_Request $request ) {
		$post_id = $request->get_param( 'post_id' );
		$job_key = 'metapress_ai_job_' . get_current_user_id() . '_' . $request->get_param( 'job' );
		$ids = get_transient( $job_key );
		if ( ! is_array( $ids ) || ! in_array( $post_id, $ids, true ) ) return new WP_Error( 'metapress_ai_invalid_job', __( 'This item is not part of the bulk job.', 'metapress-ai' ), array( 'status' => 403 ) );
		$post = get_post( $post_id );
		$settings = MetaPress_AI_Settings::get();
		if ( ! $post || ! in_array( $post->post_type, $settings['post_types'], true ) ) return new WP_Error( 'metapress_ai_invalid_post', __( 'This content is no longer available or enabled.', 'metapress-ai' ), array( 'status' => 400 ) );
		$result = ( new MetaPress_AI_Provider_Client() )->generate( $post, get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );
		if ( is_wp_error( $result ) ) return $result;
		$this->save_metadata( $post_id, $result[0] );
		$ids = array_values( array_diff( $ids, array( $post_id ) ) );
		if ( $ids ) set_transient( $job_key, $ids, HOUR_IN_SECONDS ); else delete_transient( $job_key );
		return rest_ensure_response( array( 'post_id' => $post_id, 'title' => get_the_title( $post_id ), 'edit_url' => get_edit_post_link( $post_id, 'raw' ) ) );
	}

	public function apply_metadata( WP_REST_Request $request ) {
		$post_id = $request->get_param( 'post_id' );
		$post = get_post( $post_id );
		$settings = MetaPress_AI_Settings::get();
		if ( ! $post || ! in_array( $post->post_type, $settings['post_types'], true ) ) return new WP_Error( 'metapress_ai_invalid_post', __( 'This content is no longer available or enabled.', 'metapress-ai' ), array( 'status' => 400 ) );
		$metadata = (array) $request->get_param( 'metadata' );
		$this->save_metadata( $post_id, $metadata );
		return rest_ensure_response( array( 'saved' => true, 'post_id' => $post_id ) );
	}

	public function save_post( $post_id, $post ) {
		if ( ! isset( $_POST['metapress_ai_nonce'], $_POST['metapress_ai'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['metapress_ai_nonce'] ) ), 'metapress_ai_save_metadata' ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$settings = MetaPress_AI_Settings::get();
		if ( ! in_array( $post->post_type, $settings['post_types'], true ) ) {
			return;
		}
		$submitted = (array) wp_unslash( $_POST['metapress_ai'] );
		$this->save_metadata( $post_id, $submitted );
	}

	private function save_metadata( $post_id, $values ) {
		foreach ( $this->fields as $field => $meta_key ) {
			$value = isset( $values[ $field ] ) ? sanitize_text_field( $values[ $field ] ) : '';
			if ( '' === $value ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}

	private function label( $field ) {
		$labels = array(
			'focus_keyphrase' => __( 'Focus keyphrase', 'metapress-ai' ), 'seo_title' => __( 'SEO title', 'metapress-ai' ), 'meta_description' => __( 'Meta description', 'metapress-ai' ), 'og_title' => __( 'Open Graph title', 'metapress-ai' ), 'og_description' => __( 'Open Graph description', 'metapress-ai' ), 'twitter_title' => __( 'X/Twitter title', 'metapress-ai' ), 'twitter_description' => __( 'X/Twitter description', 'metapress-ai' ),
		);
		return $labels[ $field ];
	}
}
