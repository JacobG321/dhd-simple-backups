# Simple Backups

## Features:
- Backup WordPress content directory.
- Export database tables securely.
- Simple interface for creating backups.
- Easy-to-use import functionality.
- Seamless integration with WordPress admin panel.

## Installation:
1. Download the plugin ZIP file.
2. Upload the plugin ZIP file via the WordPress admin dashboard.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Navigate to the plugin settings to create and manage backups.

## Usage:
1. Go to the plugin settings page in the WordPress admin panel.
2. Click on the "Backup Now" button to create a backup of your website's content and database.
3. The file's will be backed up in a folder called sb_backups in your Uploads directory.

## Warning:
This plugin is currently in development and may not be suitable for use in production environments. Use at your own risk.

## Notes
This may not work with all database setups and will likely require a manual edit of the sql file for the import to work. The main sticking point for me was the "SQL_MODE". This is what will likely need to be adjusted. The issue could be addressed by an import feature by grabbing the current settings and altering the current SQL file to use them.

## Ideas/New Features
1. A list of already created backups.
2. The ability to delete, or export those backups from the menu.
3. Import feature
4. Better encryption/security
5. Automated backups
6. Remote storage location

## Contributing:
Contributions are welcome! If you find any issues or have suggestions for improvements, please feel free to open an issue or submit a pull request.

## License:
This project is licensed under the [MIT License](LICENSE).
