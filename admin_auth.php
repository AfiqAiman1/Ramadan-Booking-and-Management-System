<?php
require_once __DIR__ . '/config/config.php';

function project_base_path(): string
{
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '.' || $dir === '/') {
        $dir = '';
    }
    $dir = rtrim($dir, '/');
    if (preg_match('~/(admin|public)$~', $dir)) {
        $dir = rtrim(str_replace('\\', '/', dirname($dir)), '/');
    }
    return $dir;
}

function admin_get_scope(): string
{
    $scope = strtolower(trim((string) ($_GET['scope'] ?? '')));
    if ($scope === '') {
        $scope = strtolower(trim((string) ($_POST['scope'] ?? '')));
    }
    return in_array($scope, ['ent', 'banquet', 'staff', 'entry'], true) ? $scope : '';
}

function admin_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function admin_is_logged_in(): bool
{
    admin_start_session();
    return !empty($_SESSION['admin_user_id']);
}

function require_admin_login(): void
{
    if (!admin_is_logged_in()) {
        $redirect = trim((string) ($_SERVER['REQUEST_URI'] ?? ''));
        $redirectParam = $redirect !== '' ? ('?redirect=' . urlencode($redirect)) : '';
        $base = project_base_path();
        header('Location: ' . $base . '/public/login.php' . $redirectParam);
        exit;
    }
}

function admin_get_role(): string
{
    admin_start_session();
    $role = trim((string) ($_SESSION['admin_role'] ?? ''));
    return $role !== '' ? $role : 'admin';
}

function require_admin_roles(array $allowedRoles): void
{
    require_admin_login();
    $role = admin_get_role();
    $normalizedRole = strtoupper(trim((string) $role));
    $normalizedAllowed = array_map(static fn($r) => strtoupper(trim((string) $r)), $allowedRoles);
    if (!in_array($normalizedRole, $normalizedAllowed, true)) {
        http_response_code(403);
        echo '<main class="container text-center mt-5"><h3>Access Denied</h3><p>You do not have permission to view this page.</p></main>';
        exit;
    }
}

function admin_csrf_token(): string
{
    admin_start_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function admin_verify_csrf(?string $token): bool
{
    admin_start_session();
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($sessionToken, $token);
}

function admin_logout(): void
{
    admin_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }

    session_destroy();
}
