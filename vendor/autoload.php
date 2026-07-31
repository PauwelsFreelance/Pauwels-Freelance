<?php

declare(strict_types=1);

$base = __DIR__ . '/phpmailer/phpmailer/src/';

foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $file) {
    $path = $base . $file;
    if (!is_file($path)) {
        http_response_code(500);
        error_log("Missing PHPMailer file: {$path}");
        exit('Mail library is not installed correctly. See vendor/autoload.php for what is expected.');
    }
    require_once $path;
}