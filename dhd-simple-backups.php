<?php
/**
 * Plugin Name: Simple Backups
 * Description: A simple plugin for backing up WordPress content and database.
 * Version: 1.0.0
 * Author: Digital Home Developers
 * Author URI: https://digitalhomedevelopers.com
 */

if (!defined('WPINC')) {
    die;
}

$includes = ['backup_wp_content_and_db', 'import_wp_content'];
$dir = plugin_dir_path(__FILE__);
$missing_files = [];

foreach ($includes as $inc) {
    $file_path = "{$dir}/includes/{$inc}.php";
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        error_log("Failed to include '{$file_path}'. File not found.");
        $missing_files[] = $file_path;
    }
}

/**
 * Register the plugin settings link that appears on the plugins page
 */

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'simple_backups_settings_link');
function simple_backups_settings_link($links) {
    $settings_link = '<a href="admin.php?page=simple-backups">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}


// Main Back Up page
function sb_options()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Check for success flag and display message
    if (isset($_GET['backup_success'])) {
        echo '<div class="updated"><p>Backup successful!</p></div>';
    }

    echo '<div class="wrap">';
    echo '<h2>Simple Backups</h2>';
    echo '<form action="' . admin_url('admin-post.php') . '" method="post">';
    echo '<input type="hidden" name="action" value="sb_backup_action">';
    submit_button('Backup Now');
    echo '</form>';
    echo '</div>';
}

// Child page for imports
function sb_import_page()
{
    if (isset($_GET['import_success'])) {
        echo '<div class="updated"><p>Import successful!</p></div>';
    }
    echo '<div class="wrap">';
    echo '<h2>Import Backup</h2>';
    echo '<form method="post" action="' . admin_url('admin-post.php') . '" enctype="multipart/form-data">';
    echo '<input type="hidden" name="action" value="sb_import_action">';
    echo '<input type="file" name="backup_file" required>';
    submit_button('Import Backup');
    echo '</form>';
    echo '</div>';
}


// Add menu page
add_action('admin_menu', 'sb_menu');
function sb_menu()
{
    add_menu_page('Simple Backups', 'Backup', 'manage_options', 'simple-backups', 'sb_options');
    add_submenu_page('simple-backups', 'Import Backup', 'Import', 'manage_options', 'simple-backups-import', 'sb_import_page');
}