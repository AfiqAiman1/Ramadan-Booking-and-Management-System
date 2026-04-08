<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin_auth.php';

require_admin_roles(['admin', 'staff', 'assistant', 'finance', 'ENT_ADMIN']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$sinceId = (int) ($_GET['since_id'] ?? 0);

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
    ensure_admin_notifications_schema($mysqli);

    $metricSql = "
        SELECT
            COUNT(*) AS total_bookings,
            SUM(
                quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
                + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
                + staff_blanket_qty + living_in_qty + ajk_qty + free_voucher_qty + comp_qty
            ) AS total_people,
            SUM(CASE WHEN payment_status = 'PAID' THEN total_price ELSE 0 END) AS revenue,
            SUM(CASE WHEN payment_status = 'PENDING' AND payment_proof IS NOT NULL AND payment_proof <> '' THEN 1 ELSE 0 END) AS pending_proofs
        FROM bookings
        WHERE payment_status IN ('PAID','PENDING')
    ";
    $metrics = [
        'total_bookings' => 0,
        'total_people' => 0,
        'revenue' => 0,
        'pending_proofs' => 0,
    ];

    if ($res = $mysqli->query($metricSql)) {
        $row = $res->fetch_assoc() ?: [];
        $metrics['total_bookings'] = (int) ($row['total_bookings'] ?? 0);
        $metrics['total_people'] = (int) ($row['total_people'] ?? 0);
        $metrics['revenue'] = (float) ($row['revenue'] ?? 0);
        $metrics['pending_proofs'] = (int) ($row['pending_proofs'] ?? 0);
        $res->free();
    }

    $ticketSql = "
        SELECT
            SUM(quantity_dewasa) AS dewasa,
            SUM(quantity_kanak) AS kanak,
            SUM(quantity_kanak_foc) AS kanak_foc,
            SUM(quantity_warga_emas) AS warga,
            SUM(quantity_atm) AS atm,
            SUM(CASE
                WHEN military_no IS NULL OR military_no = '' THEN 0
                WHEN military_no REGEXP '^(30|75)[0-9]{5}$' OR military_no REGEXP '^1[0-9]{6}$' THEN quantity_atm
                ELSE 0
            END) AS atm_tdm,
            SUM(CASE
                WHEN military_no IS NULL OR military_no = '' THEN 0
                WHEN military_no REGEXP '^(N40[0-9]{4}|NV870[0-9]{4})$' OR military_no REGEXP '^8[0-9]{5}$' THEN quantity_atm
                ELSE 0
            END) AS atm_tldm,
            SUM(CASE
                WHEN military_no IS NULL OR military_no = '' THEN 0
                WHEN military_no REGEXP '^37[0-9]{4}$' OR military_no REGEXP '^7[0-9]{5}$' THEN quantity_atm
                ELSE 0
            END) AS atm_tudm
        FROM bookings
    ";

    $ticketCounts = [
        'dewasa' => 0,
        'kanak' => 0,
        'kanak_foc' => 0,
        'warga' => 0,
        'atm' => 0,
        'atm_tdm' => 0,
        'atm_tldm' => 0,
        'atm_tudm' => 0,
    ];

    if ($res = $mysqli->query($ticketSql)) {
        $row = $res->fetch_assoc() ?: [];
        foreach ($ticketCounts as $k => $_) {
            $ticketCounts[$k] = (int) ($row[$k] ?? 0);
        }
        $res->free();
    }

    $recent = [];
    $recentSql = "
        SELECT booking_reference, full_name, slot_date, payment_status, total_price, created_at
        FROM bookings
        ORDER BY created_at DESC
        LIMIT 5
    ";
    if ($res = $mysqli->query($recentSql)) {
    while ($row = $res->fetch_assoc()) {
        $dateLabel = '';
        if (!empty($row['slot_date'])) {
            $dt = DateTime::createFromFormat('Y-m-d', (string) $row['slot_date']);
            $dateLabel = $dt ? $dt->format('d/m/Y') : (string) $row['slot_date'];
        }
        $row['slot_date'] = $dateLabel;
        $recent[] = $row;
    }
    $res->free();
}

    $notifications = [];
    $stmt = $mysqli->prepare('SELECT id, type, message, booking_reference, created_at FROM admin_notifications WHERE id > ? ORDER BY id ASC LIMIT 30');
    if ($stmt) {
        $stmt->bind_param('i', $sinceId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['id'] = (int) $row['id'];
            $notifications[] = $row;
        }
        $res->free();
        $stmt->close();
    }

    $mysqli->close();

    echo json_encode([
        'ok' => true,
        'server_time' => date('c'),
        'metrics' => $metrics,
        'ticket_counts' => $ticketCounts,
        'recent_bookings' => $recent,
        'notifications' => $notifications,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Unable to load live data.',
    ], JSON_UNESCAPED_UNICODE);
}
