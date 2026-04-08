<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (empty($_SESSION['autosave_token']) || !is_string($_SESSION['autosave_token'])) {
    $_SESSION['autosave_token'] = bin2hex(random_bytes(16));
}

echo json_encode(['ok' => true, 'autosave_token' => (string) $_SESSION['autosave_token']], JSON_UNESCAPED_UNICODE);
