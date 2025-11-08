<?php
// Local configuration for php-admin (XAMPP local)
// Copy of config.example.php with values for local development.

// MySQL settings (local XAMPP)
define('MYSQL_HOST', '127.0.0.1');
define('MYSQL_USER', 'root');
define('MYSQL_PASSWORD', '');
define('MYSQL_DATABASE', 'asproservice');
define('MYSQL_PORT', 3306);

// Admin fallback password (used when no users table exists)
// Change this if you want a different fallback password.
define('ADMIN_PASSWORD', 'AsproServ!ice!');

// Public URL for metadata (local)
// Use the actual path you serve under XAMPP. Adjust the folder name/casing to match your htdocs folder.
define('SITE_URL', 'http://localhost/AsProservice');

// Uploads directory (relative to project root)
define('UPLOADS_DIR', __DIR__ . '/../public/promotions');

// Optional: explicit path to the exported CSS file. If you rebuild the site and the
// CSS filename changes, update this value or set it to null to allow automatic
// detection. Example: '/AsProservice/_next/static/css/9561675e61965850.css'
// Set to null to let the admin detect the CSS file from the local `frontend/_next/static/css/` folder
define('CSS_FILE', null);

// Ensure uploads dir exists when using local file storage
if (!is_dir(UPLOADS_DIR)) {
    @mkdir(UPLOADS_DIR, 0755, true);
}

// Path to JSON file used when no MySQL is configured
define('PROMOTIONS_JSON', __DIR__ . '/../data/promotions.json');

?>
