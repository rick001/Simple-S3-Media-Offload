<?php
namespace SimpleS3MediaOffload\Admin;

/**
 * Admin page handler
 */
class Admin_Page {
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'S3 Media Offload', 'simple-s3-media-offload' ), // Page title
            __( 'S3 Media Offload', 'simple-s3-media-offload' ), // Menu title
            'manage_options',
            'simple-s3-media-offload',
            [ $this, 'render_settings_page' ],
            'dashicons-cloud', // Icon
            60 // Position
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'simple_s3_media_offload_options',
            'simple_s3_media_offload_options',
            [ $this, 'sanitize_options' ]
        );

        add_settings_section(
            's3_settings',
            __( 'S3 Configuration', 'simple-s3-media-offload' ),
            [ $this, 'render_section_description' ],
            'simple-s3-media-offload'
        );

        // S3 Bucket
        add_settings_field(
            'bucket',
            __( 'S3 Bucket Name', 'simple-s3-media-offload' ),
            [ $this, 'render_bucket_field' ],
            'simple-s3-media-offload',
            's3_settings'
        );

        // AWS Region
        add_settings_field(
            'region',
            __( 'AWS Region', 'simple-s3-media-offload' ),
            [ $this, 'render_region_field' ],
            'simple-s3-media-offload',
            's3_settings'
        );

        // Access Key ID
        add_settings_field(
            'key',
            __( 'Access Key ID', 'simple-s3-media-offload' ),
            [ $this, 'render_key_field' ],
            'simple-s3-media-offload',
            's3_settings'
        );

        // Secret Access Key
        add_settings_field(
            'secret',
            __( 'Secret Access Key', 'simple-s3-media-offload' ),
            [ $this, 'render_secret_field' ],
            'simple-s3-media-offload',
            's3_settings'
        );

        // Key Prefix
        add_settings_field(
            'prefix',
            __( 'Key Prefix (optional)', 'simple-s3-media-offload' ),
            [ $this, 'render_prefix_field' ],
            'simple-s3-media-offload',
            's3_settings'
        );

        // CloudFront URL
        add_settings_field(
            'cloudfront_url',
            __( 'CloudFront URL', 'simple-s3-media-offload' ),
            [ $this, 'render_cloudfront_field' ],
            'simple-s3-media-offload',
            's3_settings'
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts( $hook ) {
        if ( 'settings_page_simple-s3-media-offload' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'simple-s3-admin',
            SIMPLE_S3_MEDIA_OFFLOAD_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            SIMPLE_S3_MEDIA_OFFLOAD_VERSION,
            true
        );

        wp_localize_script( 'simple-s3-admin', 'simpleS3Ajax', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => [
                'bulk_migrate' => wp_create_nonce( 'simple_s3_bulk_migrate' ),
                'test_connection' => wp_create_nonce( 'simple_s3_test_connection' ),
            ],
            'strings' => [
                'testing_connection' => __( 'Testing connection...', 'simple-s3-media-offload' ),
                'migrating' => __( 'Migrating files...', 'simple-s3-media-offload' ),
                'migration_complete' => __( 'Migration complete!', 'simple-s3-media-offload' ),
                'migration_failed' => __( 'Migration failed!', 'simple-s3-media-offload' ),
            ],
        ] );

        wp_enqueue_style(
            'simple-s3-admin',
            SIMPLE_S3_MEDIA_OFFLOAD_PLUGIN_URL . 'assets/css/admin.css',
            [],
            SIMPLE_S3_MEDIA_OFFLOAD_VERSION
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $options = get_option( 'simple_s3_media_offload_options', [] );
        ?>
        <div class="wrap">
            <h1><?php _e( 'S3 Media Offload Settings', 'simple-s3-media-offload' ); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'simple_s3_media_offload_options' );
                do_settings_sections( 'simple-s3-media-offload' );
                submit_button();
                ?>
            </form>

            <?php if ( $this->is_configured( $options ) ) : ?>
                <hr>
                <h2><?php _e( 'Bulk Migration', 'simple-s3-media-offload' ); ?></h2>
                <p><?php _e( 'Migrate existing media files to S3. This process will move your local media files to S3 and update the database to use CloudFront URLs.', 'simple-s3-media-offload' ); ?></p>
                
                <div class="migration-controls">
                    <button type="button" id="test-connection" class="button">
                        <?php _e( 'Test Connection', 'simple-s3-media-offload' ); ?>
                    </button>
                    <button type="button" id="start-migration" class="button button-primary">
                        <?php _e( 'Start Migration', 'simple-s3-media-offload' ); ?>
                    </button>
                </div>

                <div id="migration-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <p id="migration-status"></p>
                </div>

                <div id="migration-results"></div>
            <?php else : ?>
                <div class="notice notice-warning">
                    <p><?php _e( 'Please configure your S3 settings above to enable bulk migration.', 'simple-s3-media-offload' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render section description
     */
    public function render_section_description() {
        echo '<p>' . __( 'Configure your Amazon S3 and CloudFront settings below. Make sure your S3 bucket is publicly accessible and your CloudFront distribution is properly configured.', 'simple-s3-media-offload' ) . '</p>';
    }

    /**
     * Render bucket field
     */
    public function render_bucket_field() {
        $options = get_option( 'simple_s3_media_offload_options', [] );
        $value = isset( $options['bucket'] ) ? $options['bucket'] : '';
        ?>
        <input type="text" name="simple_s3_media_offload_options[bucket]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" required>
        <p class="description"><?php _e( 'Enter your S3 bucket name (e.g., my-wordpress-media)', 'simple-s3-media-offload' ); ?></p>
        <?php
    }

    /**
     * Render region field
     */
    public function render_region_field() {
        $options = get_option( 'simple_s3_media_offload_options', [] );
        $value = isset( $options['region'] ) ? $options['region'] : 'us-east-1';
        
        $regions = [
            'us-east-1' => 'US East (N. Virginia)',
            'us-east-2' => 'US East (Ohio)',
            'us-west-1' => 'US West (N. California)',
            'us-west-2' => 'US West (Oregon)',
            'eu-west-1' => 'Europe (Ireland)',
            'eu-central-1' => 'Europe (Frankfurt)',
            'ap-southeast-1' => 'Asia Pacific (Singapore)',
            'ap-southeast-2' => 'Asia Pacific (Sydney)',
            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
        ];
        ?>
        <select name="simple_s3_media_offload_options[region]">
            <?php foreach ( $regions as $region => $name ) : ?>
                <option value="<?php echo esc_attr( $region ); ?>" <?php selected( $value, $region ); ?>>
                    <?php echo esc_html( $name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render key field
     */
    public function render_key_field() {
        $options = get_option( 'simple_s3_media_offload_options', [] );
        $value = isset( $options['key'] ) ? $options['key'] : '';
        ?>
        <input type="text" name="simple_s3_media_offload_options[key]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" required>
        <p class="description"><?php _e( 'Your AWS Access Key ID', 'simple-s3-media-offload' ); ?></p>
        <?php
    }

    /**
     * Render secret field
     */
    public function render_secret_field() {
        $options = get_option( 'simple_s3_media_offload_options', [] );
        $value = isset( $options['secret'] ) ? $options['secret'] : '';
        ?>
        <input type="password" name="simple_s3_media_offload_options[secret]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" required>
        <p class="description"><?php _e( 'Your AWS Secret Access Key', 'simple-s3-media-offload' ); ?></p>
        <?php
    }

    /**
     * Render prefix field
     */
    public function render_prefix_field() {
        $options = get_option( 'simple_s3_media_offload_options', [] );
        $value = isset( $options['prefix'] ) ? $options['prefix'] : 'wp-content/uploads/';
        ?>
        <input type="text" name="simple_s3_media_offload_options[prefix]" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <p class="description"><?php _e( 'Optional folder prefix in your S3 bucket (e.g., wp-content/uploads/)', 'simple-s3-media-offload' ); ?></p>
        <?php
    }

    /**
     * Render CloudFront field
     */
    public function render_cloudfront_field() {
        $options = get_option( 'simple_s3_media_offload_options', [] );
        $value = isset( $options['cloudfront_url'] ) ? $options['cloudfront_url'] : '';
        ?>
        <input type="url" name="simple_s3_media_offload_options[cloudfront_url]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" required>
        <p class="description"><?php _e( 'Your CloudFront distribution URL (e.g., https://d1234567890abc.cloudfront.net)', 'simple-s3-media-offload' ); ?></p>
        <?php
    }

    /**
     * Sanitize options
     *
     * @param array $input Input options
     * @return array Sanitized options
     */
    public function sanitize_options( $input ) {
        $sanitized = [];

        if ( isset( $input['bucket'] ) ) {
            $sanitized['bucket'] = sanitize_text_field( $input['bucket'] );
        }

        if ( isset( $input['region'] ) ) {
            $sanitized['region'] = sanitize_text_field( $input['region'] );
        }

        if ( isset( $input['key'] ) ) {
            $sanitized['key'] = sanitize_text_field( $input['key'] );
        }

        if ( isset( $input['secret'] ) ) {
            $sanitized['secret'] = sanitize_text_field( $input['secret'] );
        }

        if ( isset( $input['prefix'] ) ) {
            $sanitized['prefix'] = sanitize_text_field( $input['prefix'] );
        }

        if ( isset( $input['cloudfront_url'] ) ) {
            $sanitized['cloudfront_url'] = esc_url_raw( $input['cloudfront_url'] );
        }

        $sanitized['version'] = SIMPLE_S3_MEDIA_OFFLOAD_VERSION;

        return $sanitized;
    }

    /**
     * Check if plugin is configured
     *
     * @param array $options Plugin options
     * @return bool
     */
    private function is_configured( $options ) {
        return ! empty( $options['bucket'] ) 
            && ! empty( $options['key'] ) 
            && ! empty( $options['secret'] )
            && ! empty( $options['cloudfront_url'] );
    }
} 