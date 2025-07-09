<?php
namespace SimpleS3MediaOffload;

/**
 * Bulk migration handler
 */
class Bulk_Migrator {
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private $plugin;

    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin = Plugin::get_instance();
    }

    /**
     * Migrate a batch of files
     *
     * @param int $batch_size Number of files to process per batch
     * @return array Migration result
     */
    public function migrate_batch( $batch_size = 10 ) {
        global $wpdb;

        if ( ! $this->plugin->is_configured() ) {
            return [
                'success' => false,
                'message' => __( 'Plugin is not configured', 'simple-s3-media-offload' ),
            ];
        }

        // Get local attachments that haven't been migrated yet
        $attachments = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM $wpdb->posts p 
             INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'attachment' 
             AND pm.meta_key = '_wp_attached_file' 
             AND pm.meta_value NOT LIKE %s 
             LIMIT %d",
            '%amazonaws.com%',
            $batch_size
        ) );

        if ( empty( $attachments ) ) {
            return [
                'success' => true,
                'message' => __( 'No more files to migrate', 'simple-s3-media-offload' ),
                'migrated' => 0,
                'total' => 0,
            ];
        }

        $migrated = 0;
        $errors = [];

        foreach ( $attachments as $attachment_id ) {
            try {
                $result = $this->migrate_single_attachment( $attachment_id );
                if ( $result ) {
                    $migrated++;
                }
            } catch ( \Exception $e ) {
                $errors[] = sprintf( 'Attachment %d: %s', $attachment_id, $e->getMessage() );
            }
        }

        return [
            'success' => true,
            'migrated' => $migrated,
            'total' => count( $attachments ),
            'errors' => $errors,
        ];
    }

    /**
     * Migrate all files
     *
     * @param bool $dry_run Whether to perform a dry run
     * @param int $limit Maximum number of files to migrate
     * @return array Migration result
     */
    public function migrate_all( $dry_run = false, $limit = 0 ) {
        global $wpdb;

        if ( ! $this->plugin->is_configured() ) {
            return [
                'success' => false,
                'message' => __( 'Plugin is not configured', 'simple-s3-media-offload' ),
            ];
        }

        // Get all local attachments
        $query = "SELECT p.ID FROM $wpdb->posts p 
                  INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id 
                  WHERE p.post_type = 'attachment' 
                  AND pm.meta_key = '_wp_attached_file' 
                  AND pm.meta_value NOT LIKE %s";
        
        $params = [ '%amazonaws.com%' ];

        if ( $limit > 0 ) {
            $query .= ' LIMIT %d';
            $params[] = $limit;
        }

        $attachments = $wpdb->get_col( $wpdb->prepare( $query, ...$params ) );

        if ( empty( $attachments ) ) {
            return [
                'success' => true,
                'message' => __( 'No files to migrate', 'simple-s3-media-offload' ),
                'migrated' => 0,
                'total' => 0,
            ];
        }

        $migrated = 0;
        $errors = [];

        foreach ( $attachments as $attachment_id ) {
            try {
                if ( $dry_run ) {
                    $file = get_attached_file( $attachment_id );
                    if ( $file && file_exists( $file ) ) {
                        $migrated++;
                    }
                } else {
                    $result = $this->migrate_single_attachment( $attachment_id );
                    if ( $result ) {
                        $migrated++;
                    }
                }
            } catch ( \Exception $e ) {
                $errors[] = sprintf( 'Attachment %d: %s', $attachment_id, $e->getMessage() );
            }
        }

        return [
            'success' => true,
            'migrated' => $migrated,
            'total' => count( $attachments ),
            'errors' => $errors,
        ];
    }

    /**
     * Migrate a single attachment
     *
     * @param int $attachment_id Attachment ID
     * @return bool Success status
     */
    private function migrate_single_attachment( $attachment_id ) {
        $file = get_attached_file( $attachment_id );

        if ( ! $file || ! file_exists( $file ) ) {
            return false;
        }

        // Skip if already on S3
        if ( strpos( $file, '.amazonaws.com' ) !== false ) {
            return false;
        }

        $options = $this->plugin->get_options();
        
        // Create S3 key
        $prefix = ! empty( $options['prefix'] ) ? rtrim( $options['prefix'], '/' ) . '/' : '';
        $key = $prefix . wp_basename( $file );

        // Upload to S3
        $this->plugin->upload_to_s3( $key, $file );

        // Update attachment metadata
        $cloudfront_url = $this->plugin->get_cloudfront_url( wp_basename( $file ) );
        update_attached_file( $attachment_id, $cloudfront_url );

        // Update attachment metadata URLs
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( $metadata ) {
            $metadata = $this->plugin->update_metadata_urls( $metadata, $attachment_id );
            wp_update_attachment_metadata( $attachment_id, $metadata );
        }

        // Remove local file
        @unlink( $file );

        return true;
    }

    /**
     * Get migration statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        global $wpdb;

        $total_attachments = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'attachment'" );
        
        $local_attachments = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->posts p 
             INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'attachment' 
             AND pm.meta_key = '_wp_attached_file' 
             AND pm.meta_value NOT LIKE %s",
            '%amazonaws.com%'
        ) );

        $s3_attachments = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->posts p 
             INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'attachment' 
             AND pm.meta_key = '_wp_attached_file' 
             AND pm.meta_value LIKE %s",
            '%amazonaws.com%'
        ) );

        return [
            'total' => (int) $total_attachments,
            'local' => (int) $local_attachments,
            's3' => (int) $s3_attachments,
            'percentage' => $total_attachments > 0 ? round( ( $s3_attachments / $total_attachments ) * 100, 2 ) : 0,
        ];
    }
} 