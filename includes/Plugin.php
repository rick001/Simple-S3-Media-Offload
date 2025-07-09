<?php
namespace SimpleS3MediaOffload;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * Main plugin class
 */
class Plugin {
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

    /**
     * S3 client instance
     *
     * @var S3Client|null
     */
    private $s3_client = null;

    /**
     * Get plugin instance
     *
     * @return Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize the plugin
     */
    private function init() {
        // Load options
        $this->options = get_option( 'simple_s3_media_offload_options', [] );

        // Initialize admin
        if ( is_admin() ) {
            new Admin\Admin_Page();
        }

        // Initialize S3 functionality if configured
        if ( $this->is_configured() ) {
            $this->init_s3_client();
            $this->init_hooks();
        }

        // Initialize WP-CLI commands
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            new CLI\CLI_Commands();
        }
    }

    /**
     * Initialize S3 client
     */
    private function init_s3_client() {
        try {
            $this->s3_client = new S3Client( [
                'version'     => 'latest',
                'region'      => $this->options['region'],
                'credentials' => [
                    'key'    => $this->options['key'],
                    'secret' => $this->options['secret'],
                ],
            ] );
        } catch ( \Exception $e ) {
            error_log( 'Simple S3 Media Offload: Failed to initialize S3 client - ' . $e->getMessage() );
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Handle new uploads
        add_filter( 'wp_handle_upload', [ $this, 'handle_upload' ], 20 );
        
        // Filter upload directory URLs
        add_filter( 'upload_dir', [ $this, 'filter_upload_dir' ] );
        
        // Update attachment metadata URLs
        add_filter( 'wp_generate_attachment_metadata', [ $this, 'update_metadata_urls' ], 99, 2 );
        
        // Add AJAX handlers for bulk operations
        add_action( 'wp_ajax_simple_s3_bulk_migrate', [ $this, 'ajax_bulk_migrate' ] );
        add_action( 'wp_ajax_simple_s3_test_connection', [ $this, 'ajax_test_connection' ] );
    }

    /**
     * Check if plugin is properly configured
     *
     * @return bool
     */
    public function is_configured() {
        return ! empty( $this->options['bucket'] ) 
            && ! empty( $this->options['key'] ) 
            && ! empty( $this->options['secret'] )
            && ! empty( $this->options['cloudfront_url'] );
    }

    /**
     * Get plugin options
     *
     * @return array
     */
    public function get_options() {
        return $this->options;
    }

    /**
     * Get S3 client
     *
     * @return S3Client|null
     */
    public function get_s3_client() {
        return $this->s3_client;
    }

    /**
     * Handle file upload to S3
     *
     * @param array $upload Upload data
     * @return array Modified upload data
     */
    public function handle_upload( $upload ) {
        if ( ! $this->is_configured() || empty( $upload['file'] ) ) {
            return $upload;
        }

        $file_path = $upload['file'];
        
        // Skip if file doesn't exist
        if ( ! file_exists( $file_path ) ) {
            return $upload;
        }

        try {
            // Create S3 key
            $prefix = ! empty( $this->options['prefix'] ) ? rtrim( $this->options['prefix'], '/' ) . '/' : '';
            $key = $prefix . wp_basename( $file_path );

            // Upload to S3
            $this->upload_to_s3( $key, $file_path );

            // Remove local file
            @unlink( $file_path );

            // Update upload data to point to CloudFront
            $cloudfront_url = $this->get_cloudfront_url( wp_basename( $file_path ) );
            $upload['url'] = $cloudfront_url;
            $upload['file'] = $cloudfront_url;

        } catch ( \Exception $e ) {
            error_log( 'Simple S3 Media Offload: Upload failed - ' . $e->getMessage() );
        }

        return $upload;
    }

    /**
     * Filter upload directory to use CloudFront URLs
     *
     * @param array $dirs Upload directory data
     * @return array Modified directory data
     */
    public function filter_upload_dir( $dirs ) {
        if ( ! $this->is_configured() ) {
            return $dirs;
        }

        $cloudfront_url = $this->get_cloudfront_base_url();
        
        $dirs['baseurl'] = $cloudfront_url;
        $dirs['url'] = $cloudfront_url . $dirs['subdir'];

        return $dirs;
    }

    /**
     * Update attachment metadata URLs
     *
     * @param array $metadata Attachment metadata
     * @param int $attachment_id Attachment ID
     * @return array Modified metadata
     */
    public function update_metadata_urls( $metadata, $attachment_id ) {
        if ( ! $this->is_configured() ) {
            return $metadata;
        }

        $cloudfront_url = $this->get_cloudfront_base_url();

        if ( ! empty( $metadata['sizes'] ) ) {
            foreach ( $metadata['sizes'] as &$size ) {
                $size['file'] = $cloudfront_url . '/' . wp_basename( $size['file'] );
            }
        }

        return $metadata;
    }

    /**
     * Upload file to S3
     *
     * @param string $key S3 key
     * @param string $file_path Local file path
     * @throws \Exception
     */
    public function upload_to_s3( $key, $file_path ) {
        if ( ! $this->s3_client ) {
            throw new \Exception( 'S3 client not initialized' );
        }

        $this->s3_client->putObject( [
            'Bucket' => $this->options['bucket'],
            'Key'    => $key,
            'SourceFile' => $file_path,
            'ACL'    => 'public-read',
        ] );
    }

    /**
     * Get CloudFront base URL
     *
     * @return string
     */
    public function get_cloudfront_base_url() {
        $cloudfront_url = $this->options['cloudfront_url'] . '/' . ltrim( $this->options['prefix'], '/' );
        return untrailingslashit( $cloudfront_url );
    }

    /**
     * Get CloudFront URL for a specific file
     *
     * @param string $filename Filename
     * @return string
     */
    public function get_cloudfront_url( $filename ) {
        return $this->get_cloudfront_base_url() . '/' . $filename;
    }

    /**
     * AJAX handler for bulk migration
     */
    public function ajax_bulk_migrate() {
        check_ajax_referer( 'simple_s3_bulk_migrate', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'simple-s3-media-offload' ) );
        }

        $migrator = new Bulk_Migrator();
        $result = $migrator->migrate_batch();

        wp_send_json( $result );
    }

    /**
     * AJAX handler for testing S3 connection
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'simple_s3_test_connection', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'simple-s3-media-offload' ) );
        }

        try {
            $this->s3_client->headBucket( [
                'Bucket' => $this->options['bucket']
            ] );
            
            wp_send_json_success( __( 'Connection successful!', 'simple-s3-media-offload' ) );
        } catch ( AwsException $e ) {
            wp_send_json_error( __( 'Connection failed: ', 'simple-s3-media-offload' ) . $e->getMessage() );
        }
    }
} 