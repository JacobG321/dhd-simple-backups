<?php

if (!defined('WPINC')) {
    die;
}

function sb_check_user_permissions() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
}

function sb_validate_uploaded_file($uploadedfile) {
    if ($uploadedfile['size'] > wp_max_upload_size()) {
        wp_die('The uploaded file exceeds the maximum upload size for this site.');
    }

    // Validate file extension in addition to MIME type
    $file_extension = pathinfo($uploadedfile['name'], PATHINFO_EXTENSION);
    if (strtolower($file_extension) != 'zip') {
        wp_die("Please upload a valid .zip file. Detected file extension: {$file_extension}.");
    }
}

function sb_upload_file($uploadedfile) {
    $upload_overrides = ['test_form' => false];
    return wp_handle_upload($uploadedfile, $upload_overrides);
}

function sb_unzip_file($file_path) {
    WP_Filesystem();
    return unzip_file($file_path, ABSPATH);
}

function sb_process_sql_file($zip_file_path) {
    $zip_file_name = basename($zip_file_path);
    if (preg_match('/wp-backup-(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.zip$/', $zip_file_name, $matches)) {
        $date_format = $matches[1];
        $sql_file_name = 'db-backup-' . $date_format . '.sql';
        $sql_file_path = ABSPATH . $sql_file_name;

        if (!file_exists($sql_file_path)) {
            wp_die('SQL file does not exist. Please check your zip structure.');
        }

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
        unlink($zip_file_path); // Cleanup: Delete uploaded ZIP file
        unlink($sql_file_path); // Cleanup: Delete SQL file

        wp_safe_redirect(admin_url('admin.php?page=simple-backups-import&import_success=1'));
        exit;
    } else {
        wp_die('Could not determine the backup date from the file name. Please ensure the file name follows the correct format.');
    }
}

add_action('admin_post_sb_import_action', 'handle_sb_import_action');
function handle_sb_import_action() {
    sb_check_user_permissions();

    $uploadedfile = $_FILES['backup_file'];
    sb_validate_uploaded_file($uploadedfile);

    $movefile = sb_upload_file($uploadedfile);
    if ($movefile && !isset($movefile['error'])) {
        $unzipfile = sb_unzip_file($movefile['file']);
        if ($unzipfile) {
            sb_process_sql_file($movefile['file']);
        } else {
            wp_die('There was an error unzipping the file. Check file permissions and server configuration.');
        }
    } else {
        wp_die('There was an error uploading the file: ' . $movefile['error']);
    }
}

add_action('admin_post_sb_import_action_chunk', 'handle_sb_import_chunk_action');
function handle_sb_import_chunk_action() {
    sb_check_user_permissions();

    $chunk = $_FILES['file_chunk'];
    $chunk_number = isset($_POST['chunk_number']) ? intval($_POST['chunk_number']) : null;
    $total_chunks = isset($_POST['total_chunks']) ? intval($_POST['total_chunks']) : null;
    $upload_dir = wp_upload_dir();
    $upload_path = trailingslashit($upload_dir['path']);
    $file_name = basename($_FILES['file_chunk']['name']);
    $file_path = $upload_path . $file_name;
    $temp_file_path = $file_path . '.part';

    // Handle the upload of each chunk
    if ($chunk_number === 0 && file_exists($temp_file_path)) {
        // If first chunk, ensure temp file doesn't already exist
        unlink($temp_file_path);
    }

    $out = @fopen($temp_file_path, $chunk_number === 0 ? "wb" : "ab");
    if ($out) {
        $in = @fopen($chunk['tmp_name'], "rb");
        if ($in) {
            while ($buff = fread($in, 4096)) {
                fwrite($out, $buff);
            }   
            @fclose($in);
        }
        @fclose($out);
        @unlink($chunk['tmp_name']);

        if ($chunk_number + 1 == $total_chunks) {
            // Last chunk received, finalize and process the complete file
            rename($temp_file_path, $file_path);
            sb_finalize_chunked_upload($file_path);
        } else {
            wp_send_json_success('Chunk ' . $chunk_number . ' upload successful');
        }
    } else {
        wp_send_json_error('Error opening temp file for writing', 500);
    }
}

function sb_finalize_chunked_upload($file_path) {
    // Assuming the file is a ZIP file and needs to be processed similar to direct uploads
    $unzipfile = sb_unzip_file($file_path);
    if ($unzipfile) {
        sb_process_sql_file($file_path);
    } else {
        wp_send_json_error('There was an error unzipping the file. Check file permissions and server configuration.', 500);
    }
}

