<?php
require_once __DIR__ . '/admin_auth.php';

function banquet_start_session(): void
{
    admin_start_session();
}

function banquet_is_logged_in(): bool
{
    banquet_start_session();
    return !empty($_SESSION['admin_user_id']);
}

function require_banquet_login(): void
{
    if (!banquet_is_logged_in()) {
        $redirect = trim((string) ($_SERVER['REQUEST_URI'] ?? ''));
        $redirectParam = $redirect !== '' ? ('?redirect=' . urlencode($redirect)) : '';
        header('Location: public/login.php' . $redirectParam);
        exit;
    }
}

function banquet_get_role(): string
{
    banquet_start_session();
    $role = trim((string) ($_SESSION['admin_role'] ?? ''));
    return $role !== '' ? $role : 'banquet';
}

function require_banquet_roles(array $allowedRoles): void
{
    require_banquet_login();
    $role = banquet_get_role();
    $normalizedRole = strtoupper(trim((string) $role));
    $normalizedAllowed = array_map(static fn($r) => strtoupper(trim((string) $r)), $allowedRoles);
    if (!in_array($normalizedRole, $normalizedAllowed, true)) {
        http_response_code(403);
        echo '<main class="container text-center mt-5"><h3>Access Denied</h3><p>You do not have permission to view this page.</p></main>';
        exit;
    }
}

function banquet_logout(): void
{
    admin_logout();
}
