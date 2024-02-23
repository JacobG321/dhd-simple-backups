<?php

if (!defined('WPINC')) {
    die;
}

add_action('admin_post_sb_import_action', 'handle_sb_import_action');
function handle_sb_import_action()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if ($_FILES['backup_file']['size'] > wp_max_upload_size()) {
        wp_die('The uploaded file exceeds the maximum upload size for this site.');
    }

    $uploadedfile = $_FILES['backup_file'];
    // Enhance error message to include detected MIME type
    if ($uploadedfile['type'] != 'application/zip') {
        $detected_type = esc_html($uploadedfile['type']); // Escaping for security
        wp_die("Please upload a valid .zip file. Detected file type: {$detected_type}.");
    }

    $upload_overrides = ['test_form' => false];
    $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

    if ($movefile && !isset($movefile['error'])) {
        WP_Filesystem();
        $unzipfile = unzip_file($movefile['file'], ABSPATH);

        if ($unzipfile) {
            // Assume the SQL file is named based on the date in the ZIP file name
            // Extract the date from the ZIP file name
            $zip_file_name = basename($movefile['file']);
            if (preg_match('/wp-backup-(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.zip$/', $zip_file_name, $matches)) {
                $date_format = $matches[1];
                $sql_file_name = 'db-backup-' . $date_format . '.sql';
                $sql_file_path = ABSPATH . $sql_file_name;

                if (!file_exists($sql_file_path)) {
                    wp_die('SQL file does not exist. Please check your zip structure.');
                }

                // Import SQL file contents
                $sql_contents = file_get_contents($sql_file_path);
                $queries = explode(';', $sql_contents);
                global $wpdb;
                $wpdb->show_errors();

                foreach ($queries as $query) {
                    if (!empty(trim($query))) {
                        $result = $wpdb->query($query);
                        if ($result === false) {
                            error_log('Error executing query: ' . $wpdb->last_error);
                            wp_die('Error executing database query: ' . $wpdb->last_error);
                        }
                    }
                }

                $wpdb->hide_errors();
                unlink($movefile['file']); // Cleanup: Delete uploaded ZIP file
                unlink($sql_file_path); // Cleanup: Delete SQL file

                wp_safe_redirect(admin_url('admin.php?page=simple-backups-import&import_success=1'));
                exit;
            } else {
                wp_die('Could not determine the backup date from the file name. Please ensure the file name follows the correct format.');
            }
        } else {
            wp_die('There was an error unzipping the file. Check file permissions and server configuration.');
        }
    } else {
        wp_die('There was an error uploading the file: ' . $movefile['error']);
    }
}
