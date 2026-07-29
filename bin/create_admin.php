<?php
declare(strict_types=1);

/**
 * Run this LOCALLY, never upload it to the server or make it web-accessible:
 *
 *   php bin/create_admin.php your_username your_password
 *
 * It prints a ready-to-run SQL statement. Paste that into phpMyAdmin (or
 * whatever SQL tool your hosting panel gives you) against the database you
 * created for the admin panel, from schema.sql.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only and must never be run over the web.');
}

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php bin/create_admin.php <username> <password>\n");
    exit(1);
}

[, $username, $password] = $argv;

if (strlen($password) < 10) {
    fwrite(STDERR, "Password should be at least 10 characters.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$escapedUser = str_replace("'", "''", $username);
$escapedHash = str_replace("'", "''", $hash);

echo "\nRun this against your admin panel database:\n\n";
echo "INSERT INTO admin_users (username, password_hash) VALUES ('{$escapedUser}', '{$escapedHash}');\n\n";
echo "Then log in at https://pauwels-freelance.cz/admin/login.php with the username and password you just typed.\n";
