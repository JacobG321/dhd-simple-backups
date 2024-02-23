<?php

if (!defined('WPINC')) {
    die;
}

add_action('admin_post_sb_import_action', 'handle_sb_import_action');
function handle_sb_import_action() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    if ($_FILES['backup_file']['size'] > wp_max_upload_size()) {
        wp_die('The uploaded file exceeds the maximum upload size for this site.');
    }

    // Validate uploaded file type
    $uploadedfile = $_FILES['backup_file'];
    if ($uploadedfile['type'] != 'application/zip') {
        wp_die('Please upload a valid .zip file.'); // Ensure only zip files are processed
    }

    $upload_overrides = ['test_form' => false];
    $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

    if ($movefile && !isset($movefile['error'])) {
        WP_Filesystem();
        $unzipfile = unzip_file($movefile['file'], ABSPATH); // Unzip to WordPress root, adjust as needed

        if ($unzipfile) {
            $sql_file = ABSPATH . 'db-backup.sql'; // Ensure this path matches your backup's SQL file location

            if (!file_exists($sql_file)) {
                wp_die('SQL file does not exist. Please check your zip structure.'); // Verify SQL file exists
            }

            $sql_contents = file_get_contents($sql_file);
            $queries = explode(';', $sql_contents);
            global $wpdb;
            $wpdb->show_errors(); // Show errors for debugging

            foreach ($queries as $query) {
                if (!empty(trim($query))) {
                    $result = $wpdb->query($query);
                    if ($result === false) {
                        error_log('Error executing query: ' . $wpdb->last_error);
                        wp_die('Error executing database query: ' . $wpdb->last_error); // Detailed error
                    }
                }
            }

            $wpdb->hide_errors(); // Hide errors post-debugging

            // Cleanup: Delete uploaded zip and SQL file after import
            unlink($movefile['file']);
            unlink($sql_file);

            wp_safe_redirect(admin_url('admin.php?page=simple-backups-import&import_success=1'));
            exit;
        } else {
            wp_die('There was an error unzipping the file. Check file permissions and server configuration.');
        }
    } else {
        wp_die('There was an error uploading the file: ' . $movefile['error']); // Provide upload error detail
    }
}
