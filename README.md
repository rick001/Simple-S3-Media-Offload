# Simple S3 Media Offload

A WordPress plugin that automatically uploads media files to Amazon S3 and serves them through CloudFront for better performance.

**Author:** Sayantan Roy  
**Support Email:** rick@techbreeze.in  
**GitHub Repository:** [https://github.com/rick001/Simple-S3-Media-Offload](https://github.com/rick001/Simple-S3-Media-Offload.git)

## Features

- **Automatic Upload**: New media files are automatically uploaded to S3 and served via CloudFront
- **Bulk Migration**: Migrate existing media library to S3 with progress tracking
- **CloudFront Integration**: Serve files through CloudFront for improved performance
- **WP-CLI Support**: Command-line tools for bulk operations
- **Admin Interface**: User-friendly settings page with connection testing
- **Batch Processing**: Process large media libraries in manageable batches
- **Error Handling**: Comprehensive error handling and logging

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- AWS SDK for PHP (installed via Composer)
- Amazon S3 bucket with public read access
- CloudFront distribution (optional but recommended)

## Installation

### 1. Install the Plugin

1. Download the plugin files
2. Upload the `simple-s3-media-offload` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

### 2. Install AWS SDK

The plugin requires the AWS SDK for PHP. You can install it in several ways:

#### Option A: Composer (Recommended)
```bash
composer require aws/aws-sdk-php
```

#### Option B: Manual Installation
Download the AWS SDK from [GitHub](https://github.com/aws/aws-sdk-php) and place it in your project.

### 3. Configure S3 Settings

1. Go to **Settings > S3 Media Offload** in your WordPress admin
2. Enter your AWS credentials:
   - **S3 Bucket Name**: Your S3 bucket name
   - **AWS Region**: The region where your bucket is located
   - **Access Key ID**: Your AWS access key
   - **Secret Access Key**: Your AWS secret key
   - **Key Prefix**: Optional folder prefix (e.g., `wp-content/uploads/`)
   - **CloudFront URL**: Your CloudFront distribution URL

3. Click **Save Changes**

## Configuration

### S3 Bucket Setup

1. Create an S3 bucket in your AWS account
2. Configure the bucket for public read access:
   ```json
   {
       "Version": "2012-10-17",
       "Statement": [
           {
               "Sid": "PublicReadGetObject",
               "Effect": "Allow",
               "Principal": "*",
               "Action": "s3:GetObject",
               "Resource": "arn:aws:s3:::your-bucket-name/*"
           }
       ]
   }
   ```

### CloudFront Setup (Recommended)

1. Create a CloudFront distribution
2. Set the origin to your S3 bucket
3. Configure caching settings as needed
4. Update the CloudFront URL in the plugin settings

### IAM Permissions

Your AWS user needs the following permissions:
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:PutObjectAcl",
                "s3:GetObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::your-bucket-name",
                "arn:aws:s3:::your-bucket-name/*"
            ]
        }
    ]
}
```

## Usage

### Automatic Upload

Once configured, the plugin will automatically:
- Upload new media files to S3
- Remove local copies to save disk space
- Update URLs to use CloudFront

### Bulk Migration

1. Go to **Settings > S3 Media Offload**
2. Click **Test Connection** to verify your settings
3. Click **Start Migration** to begin migrating existing files
4. Monitor progress in the admin interface

### WP-CLI Commands

The plugin provides several WP-CLI commands:

```bash
# Test S3 connection
wp s3-media test

# Check migration status
wp s3-media status

# Migrate all files
wp s3-media migrate

# Dry run migration (no files uploaded)
wp s3-media migrate --dry-run

# Migrate with limit
wp s3-media migrate --limit=100
```

## Troubleshooting

### Common Issues

#### 1. "AWS SDK not found" Error
- Ensure the AWS SDK is properly installed
- Check that the autoloader can find the AWS classes

#### 2. "Access Denied" Errors
- Verify your AWS credentials are correct
- Check that your IAM user has the required permissions
- Ensure your S3 bucket allows public read access

#### 3. Files Not Uploading
- Check your S3 bucket region matches the setting
- Verify the bucket name is correct
- Ensure your CloudFront distribution is properly configured

#### 4. Migration Fails
- Check server memory limits for large files
- Verify file permissions on your WordPress uploads directory
- Check error logs for specific error messages

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
// In wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

### Logs

The plugin logs errors to the WordPress error log. Check `/wp-content/debug.log` for detailed error messages.

## Security Considerations

- Store AWS credentials securely
- Use IAM roles when possible instead of access keys
- Regularly rotate your AWS access keys
- Consider using AWS KMS for additional security

## Performance Tips

- Use CloudFront for better global performance
- Configure appropriate cache headers in CloudFront
- Consider using S3 Transfer Acceleration for faster uploads
- Monitor your S3 costs and usage

## Support

For support and bug reports, please create an issue on the plugin's GitHub repository: [https://github.com/rick001/Simple-S3-Media-Offload](https://github.com/rick001/Simple-S3-Media-Offload.git) or email rick@techbreeze.in

## Changelog

### Version 1.0.0
- Initial release
- Automatic S3 upload for new media
- Bulk migration functionality
- CloudFront integration
- WP-CLI support
- Admin interface with progress tracking

## License

This project is licensed under the MIT License.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request at [https://github.com/rick001/Simple-S3-Media-Offload](https://github.com/rick001/Simple-S3-Media-Offload.git) or contact Sayantan Roy at rick@techbreeze.in. 