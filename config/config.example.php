<?php
/**
 * Normally you do not need this file: public/setup.php asks for these values and writes config/config.php itself.
 * To do it by hand instead, copy this file to config/config.php and fill in your values.
 * config/config.php is git-ignored; never commit real credentials.
 *
 * To keep the config elsewhere, set the APP_CONFIG environment variable
 * to the full path of the file.
 */

return [
    'app' => [
        'name'  => 'My App',
        // Show exception details in the browser. Keep false in production.
        'debug' => false,
    ],

    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'my_app',
        'user'    => 'my_app_user',
        'pass'    => '',
        'charset' => 'utf8mb4',
        // Optional full PDO DSN; overrides host/port/name/charset when set.
        'dsn'     => null,
    ],

    'session' => [
        // Unique per app: two apps on one host with the same cookie name sign each other out.
        'name'         => 'my_app_session',
        // Seconds of inactivity before the user is logged out.
        'idle_timeout' => 1800,
        // null = auto-detect HTTPS. Set true if TLS is terminated by a proxy.
        'secure'       => null,
    ],
];
