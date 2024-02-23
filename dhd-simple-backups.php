<?php
/**
 * Plugin Name: Simple Backups
 * Description: A simple plugin for backing up WordPress content and database.
 * Version: 1.0.2
 * Author: Digital Home Developers
 * Author URI: https://digitalhomedevelopers.com
 */

if (!defined('WPINC')) {
    die;
}

$includes = ['backup_wp_content_and_db'];
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


// Add menu page
add_action('admin_menu', 'sb_menu');
function sb_menu()
{
    add_menu_page('Simple Backups', 'Backup', 'manage_options', 'simple-backups', 'sb_options');
}