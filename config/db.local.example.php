<?php
/**
 * Database credential template.
 *
 * Copy this file to config/db.local.php on every machine that runs the app and
 * fill in that machine's real values. config/db.local.php is gitignored, so the
 * password never reaches the repository — which is the whole point: the previous
 * arrangement carried the production password inside config/database.php, and
 * that file is version-controlled.
 *
 * The `config/` directory is blocked over HTTP by .htaccess, so this file is not
 * reachable through the web server even on a shared host. Keep it outside the
 * document root as well if your host allows that.
 *
 * Rotate the password that was committed before this change. It is still in the
 * repository history at config/database.php.
 */

return [
    'host'       => 'localhost',
    'name'       => 'your_database_name',
    'user'       => 'your_database_user',
    'pass'       => 'your_database_password',

    // true silences PHP output and logs errors instead. Set false for local
    // development so failures are visible in the browser.
    'production' => true,
];
