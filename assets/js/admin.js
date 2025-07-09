jQuery(document).ready(function($) {
    'use strict';

    // Test connection button
    $('#test-connection').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text(simpleS3Ajax.strings.testing_connection);
        
        $.ajax({
            url: simpleS3Ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'simple_s3_test_connection',
                nonce: simpleS3Ajax.nonce.test_connection
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data);
                } else {
                    showNotice('error', response.data);
                }
            },
            error: function() {
                showNotice('error', 'Connection test failed. Please check your settings.');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Start migration button
    $('#start-migration').on('click', function() {
        var $button = $(this);
        var $progress = $('#migration-progress');
        var $status = $('#migration-status');
        var $results = $('#migration-results');
        
        if (!confirm('Are you sure you want to start the migration? This will move your media files to S3 and may take some time.')) {
            return;
        }
        
        $button.prop('disabled', true).text(simpleS3Ajax.strings.migrating);
        $progress.show();
        $results.empty();
        
        var totalMigrated = 0;
        var totalProcessed = 0;
        var hasMore = true;
        
        function migrateBatch() {
            if (!hasMore) {
                $button.prop('disabled', false).text('Start Migration');
                $progress.hide();
                showNotice('success', 'Migration completed! ' + totalMigrated + ' files migrated.');
                return;
            }
            
            $.ajax({
                url: simpleS3Ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'simple_s3_bulk_migrate',
                    nonce: simpleS3Ajax.nonce.bulk_migrate
                },
                success: function(response) {
                    if (response.success) {
                        totalMigrated += response.migrated || 0;
                        totalProcessed += response.total || 0;
                        
                        // Update progress
                        var percentage = totalProcessed > 0 ? Math.round((totalMigrated / totalProcessed) * 100) : 0;
                        $('.progress-fill').css('width', percentage + '%');
                        $status.text('Migrated: ' + totalMigrated + ' / Processed: ' + totalProcessed);
                        
                        // Check if there are more files
                        if (response.message && response.message.includes('No more files')) {
                            hasMore = false;
                        }
                        
                        // Show errors if any
                        if (response.errors && response.errors.length > 0) {
                            var errorHtml = '<div class="notice notice-error"><p>Errors occurred during migration:</p><ul>';
                            response.errors.forEach(function(error) {
                                errorHtml += '<li>' + error + '</li>';
                            });
                            errorHtml += '</ul></div>';
                            $results.append(errorHtml);
                        }
                        
                        // Continue with next batch
                        if (hasMore) {
                            setTimeout(migrateBatch, 1000); // 1 second delay between batches
                        } else {
                            migrateBatch(); // This will trigger the completion
                        }
                    } else {
                        showNotice('error', response.message || 'Migration failed');
                        $button.prop('disabled', false).text('Start Migration');
                        $progress.hide();
                    }
                },
                error: function() {
                    showNotice('error', 'Migration failed. Please try again.');
                    $button.prop('disabled', false).text('Start Migration');
                    $progress.hide();
                }
            });
        }
        
        migrateBatch();
    });

    // Helper function to show notices
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>';
        
        // Remove existing notices
        $('.notice').remove();
        
        // Add new notice
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.notice').fadeOut();
        }, 5000);
    }

    // Form validation
    $('form').on('submit', function() {
        var $form = $(this);
        var $required = $form.find('[required]');
        var isValid = true;
        
        $required.each(function() {
            var $field = $(this);
            if (!$field.val().trim()) {
                $field.addClass('error');
                isValid = false;
            } else {
                $field.removeClass('error');
            }
        });
        
        if (!isValid) {
            showNotice('error', 'Please fill in all required fields.');
            return false;
        }
        
        return true;
    });

    // Remove error class on input
    $('input, select').on('input change', function() {
        $(this).removeClass('error');
    });
}); 