<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/admin/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('pf_admin_sess');
    session_start();
}

function current_admin_id(): ?int
{
    admin_session_start();
    return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
}

/** Call at the top of every protected admin page. */
function require_login(): void
{
    if (current_admin_id() === null) {
        header('Location: /admin/login.php');
        exit;
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Returns true if this IP has failed too many logins recently. */
function login_is_throttled(): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)'
    );
    $stmt->execute([client_ip()]);
    return (int)$stmt->fetchColumn() >= 8;
}

function record_failed_login(): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)');
    $stmt->execute([client_ip()]);
}

function clear_failed_logins(): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE ip_address = ?');
    $stmt->execute([client_ip()]);
}

/** Returns true if this IP has requested too many new accounts recently. */
function registration_is_throttled(): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM registration_attempts WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL 1 HOUR)'
    );
    $stmt->execute([client_ip()]);
    return (int)$stmt->fetchColumn() >= 5;
}

function record_registration_attempt(): void
{
    $stmt = db()->prepare('INSERT INTO registration_attempts (ip_address) VALUES (?)');
    $stmt->execute([client_ip()]);
}

/** ---------- CSRF ---------- */

function csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify(): void
{
    admin_session_start();
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid or expired form submission. Go back and try again.');
    }
}
