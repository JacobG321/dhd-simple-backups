<?php

if (!defined('WPINC')) {
    die;
}

function php_export_database($dump_file) {
    global $wpdb;
    
    // Start the SQL dump content with the mode and timezone settings
    $sql_data = "-- PHP-based MySQL Dump\n";
    $sql_data .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sql_data .= "SET time_zone = \"+00:00\";\n";
    $sql_data .= "--\n";
    $sql_data .= '-- Created: ' . date('Y-m-d H:i:s') . "\n";
    $sql_data .= '-- Database: `' . DB_NAME . "`\n\n";

    $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
    foreach ($tables as $table) {
        $table_name = $table[0];
        $sql_data .= "-- Table: `{$table_name}`\n";
        $sql_data .= "DROP TABLE IF EXISTS `{$table_name}`;\n";
        $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table_name}`", ARRAY_N);
        $sql_data .= $create_table[1] . ";\n\n";

        $rows = $wpdb->get_results("SELECT * FROM `{$table_name}`", ARRAY_A);
        if ($rows) {
            $sql_data .= "INSERT INTO `{$table_name}` VALUES \n";
            $row_entries = [];
            foreach ($rows as $row) {
                $row_vals = [];
                foreach ($row as $key => $value) {
                    $value = addslashes($value);
                    $value = str_replace("\n","\\n", $value);
                    $row_vals[] = "'$value'";
                }
                $row_entries[] = "(" . implode(", ", $row_vals) . ")";
            }
            $sql_data .= implode(",\n", $row_entries);
            $sql_data .= ";\n\n";
        }
    }

    // Save the SQL to a file
    if (!file_put_contents($dump_file, $sql_data)) {
        return new WP_Error('backup_db_error', __('Could not save database dump.'));
    }
    
    return true;
}

function backup_wp_content_and_db()
{
    $uploads = wp_upload_dir();
    $backup_dir = $uploads['basedir'] . '/sb_backups';
    if (!file_exists($backup_dir)) {
        wp_mkdir_p($backup_dir);
    }

    $date = new DateTime();
    $date_format = $date->format('Y-m-d_H-i-s');
    $wp_content_dir = ABSPATH . 'wp-content';
    $backup_file_name = 'wp-backup-' . $date_format;
    $zip_file = $backup_dir . '/' . $backup_file_name . '.zip';

    // Zip wp-content
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
        $iterator = new RecursiveDirectoryIterator($wp_content_dir);
        $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($wp_content_dir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
    } else {
        return new WP_Error('backup_zip_error', __('Could not create zip file.'));
    }

    // Export Database
    $dump_file = $backup_dir . '/' . 'db-backup-' . $date_format . '.sql';
    $db_export_result = php_export_database($dump_file);
    if (is_wp_error($db_export_result)) {
        return $db_export_result; // Return the error from database export
    }

    // Add DB dump to the zip
    if ($zip->open($zip_file) === TRUE) {
        $zip->addFile($dump_file, basename($dump_file));
        $zip->close();
        // Optionally, delete the DB dump file if you don't want it outside the zip
        @unlink($dump_file);
    } else {
        return new WP_Error('backup_zip_error', __('Could not add database dump to zip file.'));
    }

    return $zip_file;
}

// Hook into WordPress to perform backup on a specific action, e.g., an admin post request
add_action('admin_post_sb_backup_action', 'handle_sb_backup_action');
function handle_sb_backup_action()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $backup_file = backup_wp_content_and_db();
    if (is_wp_error($backup_file)) {
        wp_die($backup_file->get_error_message());
    } else {
        wp_safe_redirect(admin_url('admin.php?page=simple-backups&backup_success=1'));
        exit;
    }
}
