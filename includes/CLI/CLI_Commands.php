<?php
namespace SimpleS3MediaOffload\CLI;

/**
 * WP-CLI commands for S3 Media Offload
 */
class CLI_Commands {
    /**
     * Constructor
     */
    public function __construct() {
        \WP_CLI::add_command( 's3-media', [ $this, 'main_command' ] );
    }

    /**
     * Main CLI command
     *
     * @param array $args Command arguments
     * @param array $assoc_args Command options
     */
    public function main_command( $args, $assoc_args ) {
        if ( empty( $args ) ) {
            \WP_CLI::error( 'Please specify a subcommand. Use `wp s3-media --help` for more information.' );
        }

        $subcommand = $args[0];

        switch ( $subcommand ) {
            case 'migrate':
                $this->migrate_command( $assoc_args );
                break;
            case 'test':
                $this->test_connection_command();
                break;
            case 'status':
                $this->status_command();
                break;
            default:
                \WP_CLI::error( "Unknown subcommand: $subcommand" );
        }
    }

    /**
     * Migrate media files to S3
     *
     * @param array $assoc_args Command options
     */
    private function migrate_command( $assoc_args ) {
        $plugin = \SimpleS3MediaOffload\Plugin::get_instance();

        if ( ! $plugin->is_configured() ) {
            \WP_CLI::error( 'Plugin is not properly configured. Please configure S3 settings in the admin panel.' );
        }

        $dry_run = isset( $assoc_args['dry-run'] );
        $limit = isset( $assoc_args['limit'] ) ? intval( $assoc_args['limit'] ) : 0;

        \WP_CLI::line( 'Starting media migration to S3...' );

        if ( $dry_run ) {
            \WP_CLI::line( 'DRY RUN MODE - No files will be uploaded' );
        }

        $migrator = new \SimpleS3MediaOffload\Bulk_Migrator();
        $result = $migrator->migrate_all( $dry_run, $limit );

        if ( $result['success'] ) {
            \WP_CLI::success( sprintf( 'Migration completed! %d files processed, %d migrated.', $result['total'], $result['migrated'] ) );
        } else {
            \WP_CLI::error( 'Migration failed: ' . $result['message'] );
        }
    }

    /**
     * Test S3 connection
     */
    private function test_connection_command() {
        $plugin = \SimpleS3MediaOffload\Plugin::get_instance();

        if ( ! $plugin->is_configured() ) {
            \WP_CLI::error( 'Plugin is not properly configured.' );
        }

        \WP_CLI::line( 'Testing S3 connection...' );

        try {
            $s3_client = $plugin->get_s3_client();
            $options = $plugin->get_options();

            $s3_client->headBucket( [
                'Bucket' => $options['bucket']
            ] );

            \WP_CLI::success( 'Connection successful!' );
            \WP_CLI::line( sprintf( 'Bucket: %s', $options['bucket'] ) );
            \WP_CLI::line( sprintf( 'Region: %s', $options['region'] ) );
            \WP_CLI::line( sprintf( 'CloudFront: %s', $options['cloudfront_url'] ) );

        } catch ( \Exception $e ) {
            \WP_CLI::error( 'Connection failed: ' . $e->getMessage() );
        }
    }

    /**
     * Show migration status
     */
    private function status_command() {
        global $wpdb;

        \WP_CLI::line( 'S3 Media Offload Status' );
        \WP_CLI::line( '======================' );

        // Total attachments
        $total_attachments = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'attachment'" );
        \WP_CLI::line( sprintf( 'Total attachments: %d', $total_attachments ) );

        // Local attachments
        $local_attachments = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->posts p 
             INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'attachment' 
             AND pm.meta_key = '_wp_attached_file' 
             AND pm.meta_value NOT LIKE %s",
            '%amazonaws.com%'
        ) );
        \WP_CLI::line( sprintf( 'Local attachments: %d', $local_attachments ) );

        // S3 attachments
        $s3_attachments = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->posts p 
             INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'attachment' 
             AND pm.meta_key = '_wp_attached_file' 
             AND pm.meta_value LIKE %s",
            '%amazonaws.com%'
        ) );
        \WP_CLI::line( sprintf( 'S3 attachments: %d', $s3_attachments ) );

        // Plugin configuration
        $plugin = \SimpleS3MediaOffload\Plugin::get_instance();
        if ( $plugin->is_configured() ) {
            \WP_CLI::success( 'Plugin is configured' );
        } else {
            \WP_CLI::warning( 'Plugin is not configured' );
        }
    }
} 