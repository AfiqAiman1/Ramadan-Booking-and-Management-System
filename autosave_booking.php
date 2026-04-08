<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (empty($_SESSION['autosave_token']) || !is_string($_SESSION['autosave_token'])) {
    $_SESSION['autosave_token'] = bin2hex(random_bytes(16));
}

$token = (string) ($_SERVER['HTTP_X_AUTOSAVE_TOKEN'] ?? ($_POST['autosave_token'] ?? ($_GET['autosave_token'] ?? '')));
if ($token === '' || !hash_equals((string) $_SESSION['autosave_token'], $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $draft = $_SESSION['booking_autosave'] ?? null;
    echo json_encode(['ok' => true, 'draft' => $draft], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowed = [
    'slot_date',
    'quantity_dewasa',
    'quantity_kanak',
    'quantity_warga_emas',
    'total_price',
    'remark',
];

$draft = [];
foreach ($allowed as $key) {
    if (array_key_exists($key, $data)) {
        $draft[$key] = $data[$key];
    }
}
$draft['_saved_at'] = date('c');

$_SESSION['booking_autosave'] = $draft;

echo json_encode(['ok' => true, 'saved_at' => $draft['_saved_at']], JSON_UNESCAPED_UNICODE);
