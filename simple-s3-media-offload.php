<?php
/**
 * Plugin Name: Simple S3 Media Offload
 * Plugin URI: https://www.techbreeze.in/wordpress-s3-media-offload-plugin/
 * Description: Moves existing media library to Amazon S3 in batches and automatically uploads newly-uploaded media. Rewrites URLs to use your CloudFront distribution for better performance.
 * Version: 1.0.0
 * Author: Sayantan Roy
 * Author URI: https://www.techbreeze.in/author/techlab1/
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: simple-s3-media-offload
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'SIMPLE_S3_MEDIA_OFFLOAD_VERSION', '1.0.0' );
define( 'SIMPLE_S3_MEDIA_OFFLOAD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_S3_MEDIA_OFFLOAD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SIMPLE_S3_MEDIA_OFFLOAD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader for the plugin
spl_autoload_register( function ( $class ) {
    $prefix = 'SimpleS3MediaOffload\\';
    $base_dir = SIMPLE_S3_MEDIA_OFFLOAD_PLUGIN_DIR . 'includes/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

// Load Composer autoloader if present
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize the plugin
add_action( 'plugins_loaded', function() {
    // Check if AWS SDK is available
    if ( ! class_exists( 'Aws\S3\S3Client' ) ) {
        add_action( 'admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e( 'Simple S3 Media Offload requires the AWS SDK for PHP. Please install it via Composer or contact your hosting provider.', 'simple-s3-media-offload' ); ?></p>
            </div>
            <?php
        } );
        return;
    }

    // Initialize the main plugin class
    SimpleS3MediaOffload\Plugin::get_instance();
} );

// Activation hook
register_activation_hook( __FILE__, function() {
    // Create default options
    $default_options = [
        'bucket' => '',
        'region' => 'us-east-1',
        'key'    => '',
        'secret' => '',
        'prefix' => 'wp-content/uploads/',
        'cloudfront_url' => '',
        'version' => SIMPLE_S3_MEDIA_OFFLOAD_VERSION,
    ];
    
    add_option( 'simple_s3_media_offload_options', $default_options );
    
    // Flush rewrite rules
    flush_rewrite_rules();
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function() {
    // Flush rewrite rules
    flush_rewrite_rules();
} );

// Uninstall hook (when plugin is deleted)
function simple_s3_media_offload_uninstall() {
    // Remove plugin options
    delete_option( 'simple_s3_media_offload_options' );
}
register_uninstall_hook( __FILE__, 'simple_s3_media_offload_uninstall' ); 