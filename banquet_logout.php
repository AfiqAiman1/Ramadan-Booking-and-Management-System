<?php
require_once __DIR__ . '/banquet_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!admin_verify_csrf($csrfToken)) {
    http_response_code(403);
    exit;
}

banquet_logout();
header('Location: public/login.php');
exit;
