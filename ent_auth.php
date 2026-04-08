<?php
require_once __DIR__ . '/admin_auth.php';

function ent_start_session(): void
{
    admin_start_session();
}

function ent_is_logged_in(): bool
{
    ent_start_session();
    return !empty($_SESSION['admin_user_id']);
}

function require_ent_login(): void
{
    if (!ent_is_logged_in()) {
        $redirect = trim((string) ($_SERVER['REQUEST_URI'] ?? ''));
        $redirectParam = $redirect !== '' ? ('?redirect=' . urlencode($redirect)) : '';
        header('Location: public/login.php' . $redirectParam);
        exit;
    }
}

function ent_get_role(): string
{
    ent_start_session();
    $role = trim((string) ($_SESSION['admin_role'] ?? ''));
    return $role !== '' ? $role : 'ent_admin';
}

function require_ent_roles(array $allowedRoles): void
{
    require_ent_login();
    $role = ent_get_role();
    $normalizedRole = strtoupper(trim((string) $role));
    $normalizedAllowed = array_map(static fn($r) => strtoupper(trim((string) $r)), $allowedRoles);
    if (!in_array($normalizedRole, $normalizedAllowed, true)) {
        http_response_code(403);
        echo '<main class="container text-center mt-5"><h3>Access Denied</h3><p>You do not have permission to view this page.</p></main>';
        exit;
    }
}

function ent_logout(): void
{
    admin_logout();
}

function ent_csrf_token(): string
{
    return admin_csrf_token();
}

function ent_verify_csrf(?string $token): bool
{
    return admin_verify_csrf($token);
}
