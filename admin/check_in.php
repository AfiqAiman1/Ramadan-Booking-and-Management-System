<?php
// check_in.php - manage guest arrivals

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin_auth.php';

require_admin_roles(['admin', 'staff', 'assistant', 'entry_duty', 'ENT_ADMIN']);
$csrfToken = admin_csrf_token();

$sidebarRoleLabel = match (strtolower(admin_get_role())) {
    'banquet' => 'Banquet',
    'assistant' => 'Assistant',
    'staff' => 'Sales',
    'ent_admin' => 'ENT',
    'entry_duty' => 'Entry Staff',
    default => 'Admin',
};

$mysqli = null;

$flashMessage = '';
$flashClass = 'alert-info';
$selectedBooking = null;
$searchedRef = trim($_GET['ref'] ?? '');
$settings = [];

$checkinAllowedNow = true;
$checkinStartTime = null;
$checkinStartText = '';

$viewDate = trim((string) ($_GET['view_date'] ?? ''));
if ($viewDate !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $viewDate);
    $viewDate = $dt ? $dt->format('Y-m-d') : '';
}

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
    ensure_entry_logs_schema($mysqli);

    $settings = load_global_settings($mysqli);
    $checkinAllowedNow = true;
    $checkinStartTime = null;
    $checkinStartText = '';
} catch (Throwable $e) {
    die('<h2>Database connection failed.</h2>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfTokenPost = (string) ($_POST['csrf_token'] ?? '');
    if (!admin_verify_csrf($csrfTokenPost)) {
        $flashMessage = 'Invalid request. Please refresh and try again.';
        $flashClass = 'alert-danger';
    } else {
        $bookingRef = trim($_POST['booking_reference'] ?? '');

        if ($bookingRef === '') {
            $flashMessage = 'Booking reference is required.';
            $flashClass = 'alert-danger';
        } else {
            // Confirmation is handled via AJAX modal. This POST is kept for fallback.
            $flashMessage = 'Please use the confirmation modal to mark entry.';
            $flashClass = 'alert-warning';
            $searchedRef = $bookingRef;
        }
    }
}

function entry_total_pax_from_booking_row(array $row): int
{
    $dewasa = (int) ($row['quantity_dewasa'] ?? 0) + (int) ($row['free_quantity_dewasa'] ?? 0);
    $kanak = (int) ($row['quantity_kanak'] ?? 0) + (int) ($row['free_quantity_kanak'] ?? 0);
    $kanakFoc = (int) ($row['quantity_kanak_foc'] ?? 0) + (int) ($row['free_quantity_kanak_foc'] ?? 0);
    $warga = (int) ($row['quantity_warga_emas'] ?? 0) + (int) ($row['free_quantity_warga_emas'] ?? 0);
    $atm = (int) ($row['quantity_atm'] ?? 0) + (int) ($row['free_quantity_atm'] ?? 0);
    $staff = (int) ($row['staff_blanket_qty'] ?? 0);
    $living = (int) ($row['living_in_qty'] ?? 0);
    $ajk = (int) ($row['ajk_qty'] ?? 0);
    $comp = (int) ($row['comp_qty'] ?? 0);
    return $dewasa + $kanak + $kanakFoc + $warga + $atm + $staff + $living + $ajk + $comp;
}

function entry_already_entered_totals(mysqli $mysqli, string $bookingRef): array
{
    $totals = [
        'dewasa' => 0,
        'kanak' => 0,
        'kanak_foc' => 0,
        'warga' => 0,
        'atm' => 0,
        'staff_blanket' => 0,
        'living_in' => 0,
        'ajk' => 0,
        'comp' => 0,
        'total' => 0,
    ];

    $sql = "
        SELECT
            SUM(entered_quantity_dewasa) AS entered_dewasa,
            SUM(entered_quantity_kanak) AS entered_kanak,
            SUM(entered_quantity_kanak_foc) AS entered_kanak_foc,
            SUM(entered_quantity_warga_emas) AS entered_warga,
            SUM(entered_quantity_atm) AS entered_atm,
            SUM(entered_staff_blanket_qty) AS entered_staff,
            SUM(entered_living_in_qty) AS entered_living,
            SUM(entered_ajk_qty) AS entered_ajk,
            SUM(entered_comp_qty) AS entered_comp
        FROM entry_logs
        WHERE booking_reference = ?
    ";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return $totals;
    }
    $stmt->bind_param('s', $bookingRef);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($res) {
            $res->free();
        }
        if ($row) {
            $totals['dewasa'] = (int) ($row['entered_dewasa'] ?? 0);
            $totals['kanak'] = (int) ($row['entered_kanak'] ?? 0);
            $totals['kanak_foc'] = (int) ($row['entered_kanak_foc'] ?? 0);
            $totals['warga'] = (int) ($row['entered_warga'] ?? 0);
            $totals['atm'] = (int) ($row['entered_atm'] ?? 0);
            $totals['staff_blanket'] = (int) ($row['entered_staff'] ?? 0);
            $totals['living_in'] = (int) ($row['entered_living'] ?? 0);
            $totals['ajk'] = (int) ($row['entered_ajk'] ?? 0);
            $totals['comp'] = (int) ($row['entered_comp'] ?? 0);
        }
    }
    $stmt->close();

    $totals['total'] =
        $totals['dewasa'] + $totals['kanak'] + $totals['kanak_foc'] + $totals['warga'] + $totals['atm']
        + $totals['staff_blanket'] + $totals['living_in'] + $totals['ajk'] + $totals['comp'];
    return $totals;
}

if (isset($_GET['ajax_entry_preview']) && (string) ($_GET['ajax_entry_preview'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $ref = trim((string) ($_GET['ref'] ?? ''));
    if ($ref === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Booking reference is required.']);
        exit;
    }

    $detailSql = "
        SELECT
            booking_reference,
            full_name,
            phone,
            slot_date,
            quantity_dewasa,
            quantity_kanak,
            quantity_kanak_foc,
            quantity_warga_emas,
            quantity_atm,
            free_quantity_dewasa,
            free_quantity_kanak,
            free_quantity_kanak_foc,
            free_quantity_warga_emas,
            free_quantity_atm,
            staff_blanket_qty,
            living_in_qty,
            ajk_qty,
            comp_qty,
            payment_status,
            checkin_status
        FROM bookings
        WHERE booking_reference = ?
        LIMIT 1
    ";
    $stmt = $mysqli->prepare($detailSql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to load booking details.']);
        exit;
    }
    $stmt->bind_param('s', $ref);
    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to load booking details.']);
        exit;
    }
    $res = $stmt->get_result();
    $booking = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'No booking found for this reference.']);
        exit;
    }

    $paymentStatus = strtoupper(trim((string) ($booking['payment_status'] ?? '')));
    if ($paymentStatus !== 'PAID') {
        echo json_encode(['ok' => false, 'message' => 'Payment must be PAID before allowing entry.']);
        exit;
    }

    $purchased = [
        'dewasa' => (int) ($booking['quantity_dewasa'] ?? 0) + (int) ($booking['free_quantity_dewasa'] ?? 0),
        'kanak' => (int) ($booking['quantity_kanak'] ?? 0) + (int) ($booking['free_quantity_kanak'] ?? 0),
        'kanak_foc' => (int) ($booking['quantity_kanak_foc'] ?? 0) + (int) ($booking['free_quantity_kanak_foc'] ?? 0),
        'warga' => (int) ($booking['quantity_warga_emas'] ?? 0) + (int) ($booking['free_quantity_warga_emas'] ?? 0),
        'atm' => (int) ($booking['quantity_atm'] ?? 0) + (int) ($booking['free_quantity_atm'] ?? 0),
        'staff_blanket' => (int) ($booking['staff_blanket_qty'] ?? 0),
        'living_in' => (int) ($booking['living_in_qty'] ?? 0),
        'ajk' => (int) ($booking['ajk_qty'] ?? 0),
        'comp' => (int) ($booking['comp_qty'] ?? 0),
    ];
    $already = entry_already_entered_totals($mysqli, $ref);

    $remaining = [];
    $totalPurchased = 0;
    $totalRemaining = 0;
    foreach ($purchased as $k => $qty) {
        $totalPurchased += (int) $qty;
        $rem = (int) $qty - (int) ($already[$k] ?? 0);
        if ($rem < 0) {
            $rem = 0;
        }
        $remaining[$k] = $rem;
        $totalRemaining += $rem;
    }
    $fullyEntered = ($totalPurchased > 0 && $totalRemaining === 0);

    if ($fullyEntered && (string) ($booking['checkin_status'] ?? '') !== 'Checked') {
        $up = $mysqli->prepare("UPDATE bookings SET checkin_status='Checked' WHERE booking_reference = ? LIMIT 1");
        if ($up) {
            $up->bind_param('s', $ref);
            $up->execute();
            $up->close();
        }
        $booking['checkin_status'] = 'Checked';
    }

    echo json_encode([
        'ok' => true,
        'booking' => [
            'booking_reference' => (string) ($booking['booking_reference'] ?? ''),
            'full_name' => (string) ($booking['full_name'] ?? ''),
            'phone' => (string) ($booking['phone'] ?? ''),
            'slot_date' => (string) ($booking['slot_date'] ?? ''),
            'payment_status' => $paymentStatus,
            'checkin_status' => (string) ($booking['checkin_status'] ?? ''),
        ],
        'purchased' => $purchased,
        'already_entered' => $already,
        'remaining' => $remaining,
        'total_purchased' => $totalPurchased,
        'total_already_entered' => (int) ($already['total'] ?? 0),
        'total_remaining' => $totalRemaining,
        'fully_entered' => $fullyEntered,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_POST['ajax_entry_confirm']) && (string) ($_POST['ajax_entry_confirm'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $csrfTokenPost = (string) ($_POST['csrf_token'] ?? '');
    if (!admin_verify_csrf($csrfTokenPost)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
        exit;
    }

    $ref = trim((string) ($_POST['booking_reference'] ?? ''));
    if ($ref === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Booking reference is required.']);
        exit;
    }

    // Load booking (and ensure PAID)
    $detailSql = "
        SELECT
            booking_reference,
            slot_date,
            quantity_dewasa,
            quantity_kanak,
            quantity_kanak_foc,
            quantity_warga_emas,
            quantity_atm,
            free_quantity_dewasa,
            free_quantity_kanak,
            free_quantity_kanak_foc,
            free_quantity_warga_emas,
            free_quantity_atm,
            staff_blanket_qty,
            living_in_qty,
            ajk_qty,
            comp_qty,
            payment_status
        FROM bookings
        WHERE booking_reference = ?
        LIMIT 1
    ";
    $stmt = $mysqli->prepare($detailSql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to load booking.']);
        exit;
    }
    $stmt->bind_param('s', $ref);
    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to load booking.']);
        exit;
    }
    $res = $stmt->get_result();
    $booking = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Booking not found.']);
        exit;
    }
    $paymentStatus = strtoupper(trim((string) ($booking['payment_status'] ?? '')));
    if ($paymentStatus !== 'PAID') {
        echo json_encode(['ok' => false, 'message' => 'Payment must be PAID before allowing entry.']);
        exit;
    }

    $purchased = [
        'dewasa' => (int) ($booking['quantity_dewasa'] ?? 0) + (int) ($booking['free_quantity_dewasa'] ?? 0),
        'kanak' => (int) ($booking['quantity_kanak'] ?? 0) + (int) ($booking['free_quantity_kanak'] ?? 0),
        'kanak_foc' => (int) ($booking['quantity_kanak_foc'] ?? 0) + (int) ($booking['free_quantity_kanak_foc'] ?? 0),
        'warga' => (int) ($booking['quantity_warga_emas'] ?? 0) + (int) ($booking['free_quantity_warga_emas'] ?? 0),
        'atm' => (int) ($booking['quantity_atm'] ?? 0) + (int) ($booking['free_quantity_atm'] ?? 0),
        'staff_blanket' => (int) ($booking['staff_blanket_qty'] ?? 0),
        'living_in' => (int) ($booking['living_in_qty'] ?? 0),
        'ajk' => (int) ($booking['ajk_qty'] ?? 0),
        'comp' => (int) ($booking['comp_qty'] ?? 0),
    ];
    $already = entry_already_entered_totals($mysqli, $ref);

    $getInt = static function (string $key): int {
        $v = $_POST[$key] ?? 0;
        if (is_array($v)) {
            return 0;
        }
        return max(0, (int) $v);
    };

    $enterNow = [
        'dewasa' => $getInt('enter_dewasa'),
        'kanak' => $getInt('enter_kanak'),
        'kanak_foc' => $getInt('enter_kanak_foc'),
        'warga' => $getInt('enter_warga'),
        'atm' => $getInt('enter_atm'),
        'staff_blanket' => $getInt('enter_staff_blanket'),
        'living_in' => $getInt('enter_living_in'),
        'ajk' => $getInt('enter_ajk'),
        'comp' => $getInt('enter_comp'),
    ];

    $remaining = [];
    $totalRemaining = 0;
    $totalPurchased = 0;
    foreach ($purchased as $k => $qty) {
        $totalPurchased += (int) $qty;
        $rem = (int) $qty - (int) ($already[$k] ?? 0);
        if ($rem < 0) {
            $rem = 0;
        }
        $remaining[$k] = $rem;
        $totalRemaining += $rem;
    }

    foreach ($enterNow as $k => $qty) {
        if ($qty > (int) ($remaining[$k] ?? 0)) {
            echo json_encode(['ok' => false, 'message' => 'Entered qty exceeds remaining for ' . $k . '.']);
            exit;
        }
    }

    $enteredAny = false;
    foreach ($enterNow as $qty) {
        if ((int) $qty > 0) {
            $enteredAny = true;
            break;
        }
    }
    if (!$enteredAny) {
        echo json_encode(['ok' => false, 'message' => 'Please enter at least one quantity.']);
        exit;
    }

    $adminUsername = trim((string) ($_SESSION['admin_username'] ?? ''));
    $slotDate = (string) ($booking['slot_date'] ?? '');
    $ins = $mysqli->prepare('INSERT INTO entry_logs (booking_reference, slot_date, entered_quantity_dewasa, entered_quantity_kanak, entered_quantity_kanak_foc, entered_quantity_warga_emas, entered_quantity_atm, entered_staff_blanket_qty, entered_living_in_qty, entered_ajk_qty, entered_comp_qty, entered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$ins) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to save entry log.']);
        exit;
    }
    $ins->bind_param(
        'ssiiiiiiiiis',
        $ref,
        $slotDate,
        $enterNow['dewasa'],
        $enterNow['kanak'],
        $enterNow['kanak_foc'],
        $enterNow['warga'],
        $enterNow['atm'],
        $enterNow['staff_blanket'],
        $enterNow['living_in'],
        $enterNow['ajk'],
        $enterNow['comp'],
        $adminUsername
    );
    $ins->execute();
    $ins->close();

    // Recompute remaining after insert
    $afterAlready = entry_already_entered_totals($mysqli, $ref);
    $afterTotalRemaining = 0;
    foreach ($purchased as $k => $qty) {
        $rem = (int) $qty - (int) ($afterAlready[$k] ?? 0);
        if ($rem < 0) {
            $rem = 0;
        }
        $afterTotalRemaining += $rem;
    }

    $fullyEntered = ($totalPurchased > 0 && $afterTotalRemaining === 0);
    if ($fullyEntered) {
        $up = $mysqli->prepare("UPDATE bookings SET checkin_status='Checked' WHERE booking_reference = ? AND payment_status='PAID' LIMIT 1");
        if ($up) {
            $up->bind_param('s', $ref);
            $up->execute();
            $up->close();
        }
    }

    echo json_encode([
        'ok' => true,
        'message' => $fullyEntered ? 'Fully entered.' : 'Entry recorded.',
        'fully_entered' => $fullyEntered,
        'total_entered' => (int) ($afterAlready['total'] ?? 0),
        'total_remaining' => $afterTotalRemaining,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['ajax_search_booking']) && (string) ($_GET['ajax_search_booking'] ?? '') === '1') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $ref = trim((string) ($_GET['ref'] ?? ''));
    if ($ref === '') {
        echo '<div class="alert alert-danger mb-0">Booking reference is required.</div>';
        exit;
    }

    $detailStmt = $mysqli->prepare("SELECT booking_reference, full_name, phone, slot_date, quantity_dewasa, quantity_kanak, quantity_kanak_foc, quantity_warga_emas, quantity_atm, free_quantity_dewasa, free_quantity_kanak, free_quantity_kanak_foc, free_quantity_warga_emas, free_quantity_atm, staff_blanket_qty, living_in_qty, ajk_qty, comp_qty, total_price, payment_status, checkin_status FROM bookings WHERE booking_reference = ? LIMIT 1");
    if (!$detailStmt) {
        echo '<div class="alert alert-danger mb-0">Unable to load booking details.</div>';
        exit;
    }
    $detailStmt->bind_param('s', $ref);
    if (!$detailStmt->execute()) {
        $detailStmt->close();
        echo '<div class="alert alert-danger mb-0">Unable to load booking details.</div>';
        exit;
    }
    $detailResult = $detailStmt->get_result();
    $row = $detailResult ? $detailResult->fetch_assoc() : null;
    if ($detailResult) {
        $detailResult->free();
    }
    $detailStmt->close();

    if (!$row) {
        echo '<div class="alert alert-warning mb-0">No booking found for reference ' . htmlspecialchars($ref) . '.</div>';
        exit;
    }

    $paymentStatus = strtoupper(trim((string) ($row['payment_status'] ?? '')));
    $totalPax = entry_total_pax_from_booking_row($row);
    $already = entry_already_entered_totals($mysqli, (string) ($row['booking_reference'] ?? ''));

    echo '<div class="mt-3 p-3 rounded-3 border bg-white">';
    echo '<div class="d-flex justify-content-between align-items-center mb-2">';
    echo '<h3 class="h6 mb-0">Booking Details</h3>';
    echo '<span class="badge ' . htmlspecialchars(match ($paymentStatus) {
        'PAID' => 'text-bg-success',
        'FAILED' => 'text-bg-danger',
        default => 'text-bg-warning'
    }) . '">' . htmlspecialchars($paymentStatus !== '' ? $paymentStatus : '-') . '</span>';
    echo '</div>';
    echo '<p class="mb-1"><strong>Reference:</strong> ' . htmlspecialchars((string) ($row['booking_reference'] ?? '')) . '</p>';
    echo '<p class="mb-1"><strong>Name:</strong> ' . htmlspecialchars((string) ($row['full_name'] ?? '')) . '</p>';
    echo '<p class="mb-1"><strong>Phone:</strong> ' . htmlspecialchars((string) ($row['phone'] ?? '')) . '</p>';
    echo '<p class="mb-1"><strong>Buffet Date:</strong> ' . htmlspecialchars((string) ($row['slot_date'] ?? '')) . '</p>';
    echo '<p class="mb-1"><strong>Total Pax:</strong> ' . number_format($totalPax) . '</p>';
    echo '<p class="mb-1"><strong>Already Entered:</strong> ' . number_format((int) ($already['total'] ?? 0)) . '</p>';
    echo '<p class="mb-1"><strong>Total:</strong> RM ' . number_format((float) ($row['total_price'] ?? 0), 2) . '</p>';
    echo '</div>';
    exit;
}

$approvedSummary = [];
$approvedSummarySql = "
    SELECT
        slot_date,
        COUNT(*) AS total_bookings,
        SUM(
            quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
            + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
            + staff_blanket_qty + living_in_qty + ajk_qty + comp_qty
        ) AS total_people
    FROM bookings
    WHERE payment_status = 'PAID'
    GROUP BY slot_date
    ORDER BY slot_date ASC
";
if ($res = $mysqli->query($approvedSummarySql)) {
    while ($row = $res->fetch_assoc()) {
        $approvedSummary[] = $row;
    }
    $res->free();
}

$result = null;
if ($viewDate !== '') {
    $query = "
        SELECT booking_reference, full_name, slot_date, payment_status, checkin_status,
               quantity_dewasa, quantity_kanak, quantity_kanak_foc, quantity_warga_emas, quantity_atm,
               free_quantity_dewasa, free_quantity_kanak, free_quantity_kanak_foc, free_quantity_warga_emas, free_quantity_atm,
               staff_blanket_qty, living_in_qty, ajk_qty, comp_qty
        FROM bookings
        WHERE payment_status = 'PAID' AND slot_date = ?
        ORDER BY booking_reference ASC
    ";
    $stmt = $mysqli->prepare($query);
    if ($stmt) {
        $stmt->bind_param('s', $viewDate);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
        }
        $stmt->close();
    }
}

if (isset($_GET['ajax_booking_details']) && (string) ($_GET['ajax_booking_details'] ?? '') === '1') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $ref = trim((string) ($_GET['ref'] ?? ''));
    if ($ref === '') {
        echo '<div class="alert alert-danger mb-0">Booking reference is required.</div>';
        exit;
    }

    $detailStmt = $mysqli->prepare("SELECT booking_reference, full_name, phone, slot_date, quantity_dewasa, quantity_kanak, quantity_kanak_foc, quantity_warga_emas, quantity_atm, free_quantity_dewasa, free_quantity_kanak, free_quantity_kanak_foc, free_quantity_warga_emas, free_quantity_atm, staff_blanket_qty, living_in_qty, ajk_qty, comp_qty, total_price, payment_status, checkin_status FROM bookings WHERE booking_reference = ?");
    if (!$detailStmt) {
        echo '<div class="alert alert-danger mb-0">Unable to load booking details.</div>';
        exit;
    }

    $detailStmt->bind_param('s', $ref);
    if (!$detailStmt->execute()) {
        $detailStmt->close();
        echo '<div class="alert alert-danger mb-0">Unable to load booking details.</div>';
        exit;
    }

    $detailResult = $detailStmt->get_result();
    $row = $detailResult ? $detailResult->fetch_assoc() : null;
    if ($detailResult) $detailResult->free();
    $detailStmt->close();

    if (!$row) {
        echo '<div class="alert alert-warning mb-0">No booking found for reference ' . htmlspecialchars($ref) . '.</div>';
        exit;
    }

    $paymentStatus = strtoupper(trim((string) ($row['payment_status'] ?? '')));
    $checkinStatus = (string) ($row['checkin_status'] ?? '');
    $badge = match ($paymentStatus) {
        'PAID' => 'text-bg-success',
        'FAILED' => 'text-bg-danger',
        default => 'text-bg-warning'
    };

    $dewasa = (int) ($row['quantity_dewasa'] ?? 0) + (int) ($row['free_quantity_dewasa'] ?? 0);
    $kanak = (int) ($row['quantity_kanak'] ?? 0) + (int) ($row['free_quantity_kanak'] ?? 0);
    $kanakFoc = (int) ($row['quantity_kanak_foc'] ?? 0) + (int) ($row['free_quantity_kanak_foc'] ?? 0);
    $warga = (int) ($row['quantity_warga_emas'] ?? 0) + (int) ($row['free_quantity_warga_emas'] ?? 0);
    $atm = (int) ($row['quantity_atm'] ?? 0) + (int) ($row['free_quantity_atm'] ?? 0);
    $staff = (int) ($row['staff_blanket_qty'] ?? 0);
    $living = (int) ($row['living_in_qty'] ?? 0);
    $ajk = (int) ($row['ajk_qty'] ?? 0);
    $comp = (int) ($row['comp_qty'] ?? 0);
    $total = $dewasa + $kanak + $kanakFoc + $warga + $atm + $staff + $living + $ajk + $comp;
    $already = entry_already_entered_totals($mysqli, (string) ($row['booking_reference'] ?? ''));

    echo '<div class="d-flex justify-content-between align-items-center mb-2">';
    echo '<div class="fw-semibold">Booking Details</div>';
    echo '<div class="d-flex gap-2">';
    echo '<span class="badge ' . htmlspecialchars($badge) . '">' . htmlspecialchars($paymentStatus !== '' ? $paymentStatus : '-') . '</span>';
    echo '<span class="badge ' . ($checkinStatus === 'Checked' ? 'text-bg-success' : 'text-bg-secondary') . '">' . htmlspecialchars($checkinStatus === 'Checked' ? 'Entered' : 'Not Entered') . '</span>';
    echo '</div>';
    echo '</div>';

    echo '<div class="row g-2">';
    echo '<div class="col-12"><strong>Reference:</strong> ' . htmlspecialchars((string) ($row['booking_reference'] ?? '')) . '</div>';
    echo '<div class="col-12"><strong>Name:</strong> ' . htmlspecialchars((string) ($row['full_name'] ?? '')) . '</div>';
    echo '<div class="col-12 col-md-6"><strong>Phone:</strong> ' . htmlspecialchars((string) ($row['phone'] ?? '')) . '</div>';
    echo '<div class="col-12"><strong>Buffet Date:</strong> ' . htmlspecialchars((string) ($row['slot_date'] ?? '')) . '</div>';
    echo '<div class="col-12"><strong>Tickets:</strong> Dewasa ' . number_format($dewasa) . ' | Kanak ' . number_format($kanak) . ' | Kanak bawah 6 tahun ' . number_format($kanakFoc) . ' | Warga ' . number_format($warga) . ' | ATM ' . number_format($atm) . ' | Staff ' . number_format($staff) . ' | Living In ' . number_format($living) . ' | AJK ' . number_format($ajk) . ' | COMP ' . number_format($comp) . '</div>';
    echo '<div class="col-12"><strong>Total Pax:</strong> ' . number_format($total) . '</div>';
    echo '<div class="col-12"><strong>Already Entered:</strong> ' . number_format((int) ($already['total'] ?? 0)) . '</div>';
    echo '<div class="col-12"><strong>Total:</strong> RM ' . number_format((float) ($row['total_price'] ?? 0), 2) . '</div>';
    echo '</div>';
    exit;
}

 if (isset($_GET['ajax_approved_details']) && (string) ($_GET['ajax_approved_details'] ?? '') === '1') {
     header('Content-Type: text/html; charset=utf-8');
     header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

     $ajaxDate = trim((string) ($_GET['slot_date'] ?? ''));
     if ($ajaxDate !== '') {
         $dt = DateTime::createFromFormat('Y-m-d', $ajaxDate);
         $ajaxDate = $dt ? $dt->format('Y-m-d') : '';
     }

     if ($ajaxDate === '') {
         echo '<div class="alert alert-warning mb-0">Invalid date.</div>';
         exit;
     }

     $rows = [];
     $stmt = $mysqli->prepare("SELECT booking_reference, full_name, payment_status, checkin_status, quantity_dewasa, quantity_kanak, quantity_kanak_foc, quantity_warga_emas, quantity_atm, free_quantity_dewasa, free_quantity_kanak, free_quantity_kanak_foc, free_quantity_warga_emas, free_quantity_atm, staff_blanket_qty, living_in_qty, ajk_qty, comp_qty FROM bookings WHERE payment_status = 'PAID' AND slot_date = ? ORDER BY booking_reference ASC");
     if ($stmt) {
         $stmt->bind_param('s', $ajaxDate);
         if ($stmt->execute()) {
             $res = $stmt->get_result();
             while ($r = $res->fetch_assoc()) {
                 $rows[] = $r;
             }
             $res->free();
         }
         $stmt->close();
     }

     if (!$rows) {
         echo '<div class="alert alert-light mb-0">No PAID bookings found for this date.</div>';
         exit;
     }

     echo '<div class="table-responsive">';
     echo '<table class="table table-striped align-middle mb-0">';
     echo '<thead><tr><th>Reference</th><th>Name</th><th class="text-end">Total Pax</th><th class="text-end">Already Entered</th><th>Payment</th><th>Entry</th><th></th></tr></thead><tbody>';
     foreach ($rows as $row) {
         $paymentStatus = strtoupper(trim((string) ($row['payment_status'] ?? '')));
         $bookingRef = (string) ($row['booking_reference'] ?? '');
         $already = $bookingRef !== '' ? entry_already_entered_totals($mysqli, $bookingRef) : ['total' => 0];
         $totalPax = entry_total_pax_from_booking_row($row);
         $isFullyEntered = ($totalPax > 0 && ((int) ($already['total'] ?? 0)) >= $totalPax);
         if ($isFullyEntered && (string) ($row['checkin_status'] ?? '') !== 'Checked' && $bookingRef !== '') {
             $up = $mysqli->prepare("UPDATE bookings SET checkin_status='Checked' WHERE booking_reference = ? LIMIT 1");
             if ($up) {
                 $up->bind_param('s', $bookingRef);
                 $up->execute();
                 $up->close();
             }
             $row['checkin_status'] = 'Checked';
         }
         $isEntered = ((string) ($row['checkin_status'] ?? '') === 'Checked');
         echo '<tr>';
         echo '<td>' . htmlspecialchars((string) ($row['booking_reference'] ?? '')) . '</td>';
         echo '<td>' . htmlspecialchars((string) ($row['full_name'] ?? '')) . '</td>';
         echo '<td class="text-end fw-semibold">' . number_format($totalPax) . '</td>';
         echo '<td class="text-end">' . number_format((int) ($already['total'] ?? 0)) . '</td>';
         echo '<td><span class="badge ' . htmlspecialchars(match ($paymentStatus) {
             'PAID' => 'text-bg-success',
             'FAILED' => 'text-bg-danger',
             default => 'text-bg-warning'
         }) . '">' . htmlspecialchars($paymentStatus !== '' ? $paymentStatus : '-') . '</span></td>';
         echo '<td><span class="badge ' . ($isEntered ? 'text-bg-success' : 'text-bg-secondary') . '">' . htmlspecialchars($isEntered ? 'Entered' : 'Not Entered') . '</span></td>';
         echo '<td><button type="button" class="btn btn-sm btn-outline-primary js-view-booking" data-booking-ref="' . htmlspecialchars((string) ($row['booking_reference'] ?? '')) . '" data-bs-toggle="modal" data-bs-target="#bookingDetailsModal">View</button></td>';
         echo '</tr>';
     }
     echo '</tbody></table></div>';
     exit;
 }

if ($searchedRef !== '') {
    $detailStmt = $mysqli->prepare("SELECT booking_reference, full_name, phone, slot_date, quantity_dewasa, quantity_kanak, quantity_kanak_foc, quantity_warga_emas, quantity_atm, total_price, payment_status, checkin_status FROM bookings WHERE booking_reference = ?");
    if ($detailStmt) {
        $detailStmt->bind_param('s', $searchedRef);
        if ($detailStmt->execute()) {
            $detailResult = $detailStmt->get_result();
            $selectedBooking = $detailResult->fetch_assoc();
            $detailResult->free();
        }
        $detailStmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tickets - Ramadan Buffet</title>
    <link rel="icon" type="image/png" href="../assets/img/Logo%20ATM.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <style>
      :root {
        --ramadan-green: #08372b;
        --ramadan-gold: #d8b45c;
        --ramadan-cream: #fff9ed;
      }
      body {
        font-family: 'Cairo', system-ui, sans-serif;
        background: var(--ramadan-cream);
      }
      .layout { min-height: 100vh; }
      .sidebar {
        background: linear-gradient(180deg, var(--ramadan-green), #041f18);
        color: #fef6dd;
        width: 260px;
      }
      @media (min-width: 992px) {
        .sidebar {
          position: sticky;
          top: 0;
          height: 100vh;
          overflow-y: auto;
          align-self: flex-start;
        }
        .sidebar.offcanvas {
          visibility: visible !important;
          transform: none !important;
          position: sticky;
        }
      }
      .sidebar .nav-link {
        color: #f5e9c8;
        border-radius: 0.75rem;
        padding: 0.65rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      .sidebar .nav-link.active,
      .sidebar .nav-link:hover {
        background: rgba(216, 180, 92, 0.18);
        color: var(--ramadan-gold);
      }
      .logout-btn {
        background: rgba(220, 53, 69, 0.12);
        border: 1px solid rgba(220, 53, 69, 0.4);
        color: #ffb6b6;
      }
      .main-content { background: var(--ramadan-cream); padding: 2rem; }
    </style>
  </head>
  <body>
    <div class="d-flex layout flex-column flex-lg-row">
      <aside class="sidebar p-4 d-flex flex-column offcanvas offcanvas-lg offcanvas-start" tabindex="-1" id="sidebarMenu" style="--bs-offcanvas-width: 260px;">
        <div class="d-lg-none text-end mb-2">
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="mb-4 text-center">
          <div class="fs-2 fw-bold text-white"><?= htmlspecialchars($sidebarRoleLabel) ?></div>
          <p class="text-muted small mb-0"><?= htmlspecialchars((string)($settings['event_name'] ?? '')) ?></p>
        </div>
        <nav class="flex-grow-1">
          <?php $adminRole = strtoupper(trim((string) admin_get_role())); ?>
          <?php if (in_array($adminRole, ['ADMIN', 'STAFF', 'FINANCE', 'ASSISTANT', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2"></i>Dashboard</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'STAFF', 'BANQUET', 'ASSISTANT', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="all_bookings.php"><i class="bi bi-list-ul"></i>All Bookings</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'ASSISTANT', 'ENTRY_DUTY', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link active" href="check_in.php"><i class="bi bi-qr-code-scan"></i>Entry</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'STAFF', 'ASSISTANT', 'ENTRY_DUTY', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="list_guests.php"><i class="bi bi-people"></i>Name List</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'BANQUET'], true)): ?>
            <a class="nav-link" href="table_no.php"><i class="bi bi-table"></i>Table No</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'STAFF', 'ASSISTANT', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-line"></i>Reports</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'FINANCE', 'ASSISTANT', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="finance_confirm.php"><i class="bi bi-bank2"></i>Finance Confirm</a>
          <?php endif; ?>
          <?php if ($adminRole === 'ENT_ADMIN'): ?>
            <a class="nav-link" href="../ent_home.php"><i class="bi bi-box-arrow-in-right"></i>ENT</a>
          <?php endif; ?>
          <?php if ($adminRole === 'ADMIN'): ?>
            <a class="nav-link" href="booking_details.php"><i class="bi bi-card-text"></i>Booking Details</a>
          <?php endif; ?>
          <?php if (strtolower(admin_get_role()) === 'admin'): ?>
            <a class="nav-link" href="settings.php"><i class="bi bi-gear"></i>Settings</a>
          <?php endif; ?>
          <?php if (strtolower(admin_get_role()) === 'admin'): ?>
            <a class="nav-link" href="backup_payment_proofs.php"><i class="bi bi-cloud-download"></i>Backup Proofs</a>
          <?php endif; ?>
        </nav>
        <div class="mt-4">
          <button type="button" class="btn logout-btn w-100" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">
            <i class="bi bi-box-arrow-right me-2"></i>Logout
          </button>
        </div>
      </aside>

      <main class="main-content flex-grow-1">
        <header class="mb-4">
          <button class="btn btn-outline-secondary d-lg-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
            <i class="bi bi-list"></i>
          </button>
          <p class="text-uppercase text-muted small mb-1">Entry</p>
          <h1 class="h3 text-dark mb-2">Admission Gate</h1>
          <p class="text-muted mb-0">Search booking references and mark guests as entered.</p>
        </header>

        <div id="entryPageAlert"></div>

        <?php if ($flashMessage): ?>
          <div class="alert <?= $flashClass ?> alert-dismissible fade show" role="alert">
            <?= $flashMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
          <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
              <div class="card-body">
                <h2 class="h5 mb-3">Entry</h2>
                <form method="POST" class="row g-3" id="entryForm">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                  <div class="col-12">
                    <label class="form-label">Booking Reference</label>
                    <input type="text" class="form-control" name="booking_reference" placeholder="WP26-12345" required id="entryBookingRef">
                  </div>
                  <div class="col-12 d-flex justify-content-end">
                    <button type="button" class="btn btn-success w-100" id="markEnteredBtn">Mark Entered</button>
                  </div>
                </form>
                <p class="text-muted small mt-3 mb-0">Tip: ensure payment is marked Paid before allowing entry.</p>

                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $searchedRef !== '' && $selectedBooking): ?>
                  <?php
                    $dewasa = (int) ($selectedBooking['quantity_dewasa'] ?? 0);
                    $kanak = (int) ($selectedBooking['quantity_kanak'] ?? 0);
                    $kanakFoc = (int) ($selectedBooking['quantity_kanak_foc'] ?? 0);
                    $warga = (int) ($selectedBooking['quantity_warga_emas'] ?? 0);
                    $atm = (int) ($selectedBooking['quantity_atm'] ?? 0);
                    $total = $dewasa + $kanak + $kanakFoc + $warga + $atm;
                    $isCollected = ((string) ($selectedBooking['checkin_status'] ?? '') === 'Checked');
                  ?>
                  <div class="mt-3 p-3 rounded-4 border bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <h3 class="h6 mb-0">Ticket Categories</h3>
                      <span class="badge <?= $isCollected ? 'text-bg-success' : 'text-bg-secondary' ?>">
                        <?= htmlspecialchars($isCollected ? 'Entered' : 'Not Entered') ?>
                      </span>
                    </div>
                    <div class="row g-2">
                      <div class="col-12 col-md-4"><strong>Dewasa:</strong> <?= $dewasa ?></div>
                      <div class="col-12 col-md-4"><strong>Kanak:</strong> <?= $kanak ?></div>
                      <div class="col-12 col-md-4"><strong>Kanak bawah 6 tahun:</strong> <?= $kanakFoc ?></div>
                      <div class="col-12 col-md-4"><strong>Warga Emas:</strong> <?= $warga ?></div>
                      <div class="col-12 col-md-4"><strong>ATM:</strong> <?= $atm ?></div>
                      <div class="col-12"><strong>Total People:</strong> <?= $total ?></div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
              <div class="card-body">
                <h2 class="h5 mb-3">Search Booking</h2>
                <form method="GET" class="row g-3" id="searchBookingForm">
                  <div class="col-12">
                    <label class="form-label">Booking Reference</label>
                    <input type="text" class="form-control" name="ref" placeholder="WP26-12345" value="<?= htmlspecialchars($searchedRef) ?>" id="searchBookingRef">
                  </div>
                  <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="searchBookingClearBtn">Clear</button>
                    <button type="submit" class="btn btn-primary">Search</button>
                  </div>
                </form>
                <div id="searchBookingResult">
                  <?php if ($searchedRef !== ''): ?>
                    <?php if ($selectedBooking): ?>
                      <?php
                        $totalPax = entry_total_pax_from_booking_row($selectedBooking);
                        $already = entry_already_entered_totals($mysqli, (string) ($selectedBooking['booking_reference'] ?? ''));
                      ?>
                      <div class="mt-3 p-3 rounded-3 border bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                          <h3 class="h6 mb-0">Booking Details</h3>
                          <span class="badge <?= match ((string) ($selectedBooking['payment_status'] ?? '')) {
                            'PAID' => 'text-bg-success',
                            'FAILED' => 'text-bg-danger',
                            default => 'text-bg-warning'
                          } ?>">
                            <?= htmlspecialchars((string) ($selectedBooking['payment_status'] ?? '')) ?>
                          </span>
                        </div>
                        <p class="mb-1"><strong>Reference:</strong> <?= htmlspecialchars($selectedBooking['booking_reference']) ?></p>
                        <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($selectedBooking['full_name']) ?></p>
                        <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($selectedBooking['phone']) ?></p>
                        <p class="mb-1"><strong>Buffet Date:</strong> <?= htmlspecialchars($selectedBooking['slot_date']) ?></p>
                        <p class="mb-1"><strong>Total Pax:</strong> <?= number_format($totalPax) ?></p>
                        <p class="mb-1"><strong>Already Entered:</strong> <?= number_format((int) ($already['total'] ?? 0)) ?></p>
                        <p class="mb-1"><strong>Total:</strong> RM <?= number_format((float) $selectedBooking['total_price'], 2) ?></p>
                      </div>
                    <?php else: ?>
                      <div class="alert alert-warning mt-3 mb-0">No booking found for reference <?= htmlspecialchars($searchedRef) ?>.</div>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4" id="approved-guests">
          <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
              <div>
                <h2 class="h5 mb-1">Approved Guests (Allowed Entry)</h2>
                <p class="text-muted mb-0">Only PAID bookings are shown.</p>
              </div>
            </div>

            <?php if ($viewDate !== ''): ?>
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 no-print">
                <div class="fw-semibold">Viewing date: <?= htmlspecialchars($viewDate) ?></div>
                <a href="check_in.php#approved-guests" class="btn btn-outline-secondary">Back</a>
              </div>
              <div class="table-responsive">
                <table class="table table-striped align-middle">
                  <thead>
                    <tr>
                      <th>Reference</th>
                      <th>Name</th>
                      <th class="text-end">Total Pax</th>
                      <th class="text-end">Already Entered</th>
                      <th>Payment</th>
                      <th>Entry</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                      <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                          $paymentStatus = strtoupper(trim((string) ($row['payment_status'] ?? '')));
                          $bookingRef = (string) ($row['booking_reference'] ?? '');
                          $already = $bookingRef !== '' ? entry_already_entered_totals($mysqli, $bookingRef) : ['total' => 0];
                          $totalPax = entry_total_pax_from_booking_row($row);
                          $isFullyEntered = ($totalPax > 0 && ((int) ($already['total'] ?? 0)) >= $totalPax);
                          if ($isFullyEntered && (string) ($row['checkin_status'] ?? '') !== 'Checked' && $bookingRef !== '') {
                              $up = $mysqli->prepare("UPDATE bookings SET checkin_status='Checked' WHERE booking_reference = ? LIMIT 1");
                              if ($up) {
                                  $up->bind_param('s', $bookingRef);
                                  $up->execute();
                                  $up->close();
                              }
                              $row['checkin_status'] = 'Checked';
                          }
                        ?>
                        <tr>
                          <td><?= htmlspecialchars($row['booking_reference']) ?></td>
                          <td><?= htmlspecialchars($row['full_name']) ?></td>
                          <td class="text-end fw-semibold"><?= number_format($totalPax) ?></td>
                          <td class="text-end"><?= number_format((int) ($already['total'] ?? 0)) ?></td>
                          <td>
                            <span class="badge <?= match ($paymentStatus) {
                              'PAID' => 'text-bg-success',
                              'FAILED' => 'text-bg-danger',
                              default => 'text-bg-warning'
                            } ?>">
                              <?= htmlspecialchars((string) ($row['payment_status'] ?? '')) ?>
                            </span>
                          </td>
                          <td>
                            <span class="badge <?= $row['checkin_status'] === 'Checked' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                              <?= htmlspecialchars($row['checkin_status'] === 'Checked' ? 'Entered' : 'Not Entered') ?>
                            </span>
                          </td>
                          <td>
                            <button type="button" class="btn btn-sm btn-outline-primary js-view-booking" data-booking-ref="<?= htmlspecialchars($row['booking_reference']) ?>" data-bs-toggle="modal" data-bs-target="#bookingDetailsModal">View</button>
                          </td>
                        </tr>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="7" class="text-center text-muted py-4">No bookings available.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

            <?php else: ?>
              <?php if ($approvedSummary): ?>
                <div class="table-responsive">
                  <table class="table table-striped align-middle">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th class="text-end">Total Bookings</th>
                        <th class="text-end">Total People</th>
                        <th class="text-end">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($approvedSummary as $row): ?>
                        <?php
                          $dateLabel = '';
                          $rawDate = (string) ($row['slot_date'] ?? '');
                          if ($rawDate !== '') {
                              $dt = DateTime::createFromFormat('Y-m-d', $rawDate);
                              $dateLabel = $dt ? $dt->format('d/m/Y') : $rawDate;
                          }
                        ?>
                        <tr>
                          <td><?= htmlspecialchars($dateLabel) ?></td>
                          <td class="text-end"><?= number_format((int) ($row['total_bookings'] ?? 0)) ?></td>
                          <td class="text-end"><?= number_format((int) ($row['total_people'] ?? 0)) ?></td>
                          <td class="text-end">
                            <?php if ($rawDate !== ''): ?>
                              <button type="button" class="btn btn-sm btn-outline-primary js-approved-details" data-slot-date="<?= htmlspecialchars($rawDate) ?>" data-bs-toggle="modal" data-bs-target="#approvedDetailsModal">View</button>
                            <?php else: ?>
                              <span class="text-muted">-</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="alert alert-light mb-0">No bookings available.</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </main>
    </div>

    <form method="POST" action="../logout.php" id="logoutForm" class="d-none">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    </form>

    <div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title"><i class="bi bi-shield-lock me-2" style="color:#d8b45c;"></i>Confirm Logout</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">Are you sure you want to log out?</div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmLogoutBtn">Logout</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
      (function () {
        const btn = document.getElementById('confirmLogoutBtn');
        const form = document.getElementById('logoutForm');
        if (!btn || !form) return;
        btn.addEventListener('click', () => form.submit());
      })();
    </script>

    <div class="modal fade" id="bookingDetailsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title" id="bookingDetailsTitle"><i class="bi bi-eye me-2" style="color:#d8b45c;"></i>Booking Details</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <div id="bookingDetailsBody" class="bg-white border rounded-3 p-3">Loading...</div>
          </div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <script>
      document.addEventListener('click', async function (e) {
        const btn = e.target && e.target.closest ? e.target.closest('.js-view-booking') : null;
        if (!btn) return;
        const ref = btn.getAttribute('data-booking-ref') || '';
        const body = document.getElementById('bookingDetailsBody');
        const title = document.getElementById('bookingDetailsTitle');
        if (title) title.textContent = ref ? ('Booking Details - ' + ref) : 'Booking Details';
        if (body) body.innerHTML = '<div class="text-muted">Loading...</div>';
        try {
          const url = 'check_in.php?ajax_booking_details=1&ref=' + encodeURIComponent(ref);
          const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
          if (!res.ok) throw new Error('Request failed');
          const html = await res.text();
          if (body) body.innerHTML = html;
        } catch (err) {
          if (body) body.innerHTML = '<div class="alert alert-danger mb-0">Failed to load booking details.</div>';
        }
      });
    </script>

    <div class="modal fade" id="entryConfirmModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title" id="entryConfirmTitle"><i class="bi bi-check2-circle me-2" style="color:#d8b45c;"></i>Confirm Entry</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <div id="entryConfirmAlert"></div>
            <div class="bg-white border rounded-3 p-3 mb-3" id="entryConfirmSummary">
              <div class="text-muted">Loading...</div>
            </div>

            <div class="bg-white border rounded-3 p-3">
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="entryAllArrived">
                <label class="form-check-label" for="entryAllArrived">All already come (fill all remaining automatically)</label>
              </div>
              <div id="entryConfirmFormBody"></div>
            </div>
          </div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" id="entryConfirmSubmitBtn">Confirm Entry</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="approvedDetailsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title" id="approvedDetailsTitle"><i class="bi bi-people-check me-2" style="color:#d8b45c;"></i>Approved Guests</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <div id="approvedDetailsBody"></div>
          </div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <script>
      document.addEventListener('click', async function (e) {
        const btn = e.target && e.target.closest ? e.target.closest('.js-approved-details') : null;
        if (!btn) return;

        const slotDate = btn.getAttribute('data-slot-date') || '';
        const title = document.getElementById('approvedDetailsTitle');
        const body = document.getElementById('approvedDetailsBody');

        if (title) {
          let formattedDate = slotDate;
          if (slotDate) {
            const parts = slotDate.split('-');
            if (parts.length === 3) formattedDate = parts[2] + '/' + parts[1] + '/' + parts[0];
          }
          title.textContent = 'Approved Guests - ' + formattedDate;
        }
        if (body) body.innerHTML = '<div class="py-4 text-center text-muted">Loading...</div>';

        try {
          const url = 'check_in.php?ajax_approved_details=1&slot_date=' + encodeURIComponent(slotDate);
          const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
          if (!res.ok) throw new Error('Request failed');
          const html = await res.text();
          if (body) body.innerHTML = html;
        } catch (err) {
          if (body) body.innerHTML = '<div class="alert alert-danger mb-0">Failed to load details.</div>';
        }
      });
    </script>

    <script>
      (function () {
        const markBtn = document.getElementById('markEnteredBtn');
        const refInput = document.getElementById('entryBookingRef');
        const modalEl = document.getElementById('entryConfirmModal');
        const titleEl = document.getElementById('entryConfirmTitle');
        const alertEl = document.getElementById('entryConfirmAlert');
        const summaryEl = document.getElementById('entryConfirmSummary');
        const formBodyEl = document.getElementById('entryConfirmFormBody');
        const allArrivedEl = document.getElementById('entryAllArrived');
        const submitBtn = document.getElementById('entryConfirmSubmitBtn');
        const csrf = '<?= htmlspecialchars($csrfToken) ?>';
        let currentPreview = null;

        function setAlert(type, message) {
          if (!alertEl) return;
          if (!message) {
            alertEl.innerHTML = '';
            return;
          }
          const cls = type === 'success' ? 'alert-success' : (type === 'warning' ? 'alert-warning' : 'alert-danger');
          alertEl.innerHTML = '<div class="alert ' + cls + ' mb-3">' + String(message) + '</div>';
        }

        function labelForKey(key) {
          switch (key) {
            case 'dewasa': return 'Dewasa';
            case 'kanak': return 'Kanak';
            case 'kanak_foc': return 'Kanak bawah 6 tahun';
            case 'warga': return 'Warga';
            case 'atm': return 'ATM';
            case 'staff_blanket': return 'Staff';
            case 'living_in': return 'Living In';
            case 'ajk': return 'AJK';
            case 'comp': return 'COMP';
            default: return key;
          }
        }

        function inputNameForKey(key) {
          switch (key) {
            case 'dewasa': return 'enter_dewasa';
            case 'kanak': return 'enter_kanak';
            case 'kanak_foc': return 'enter_kanak_foc';
            case 'warga': return 'enter_warga';
            case 'atm': return 'enter_atm';
            case 'staff_blanket': return 'enter_staff_blanket';
            case 'living_in': return 'enter_living_in';
            case 'ajk': return 'enter_ajk';
            case 'comp': return 'enter_comp';
            default: return '';
          }
        }

        function renderPreview(preview) {
          currentPreview = preview;
          if (!summaryEl || !formBodyEl) return;
          const b = preview.booking || {};
          const purchased = preview.purchased || {};
          const already = preview.already_entered || {};
          const remaining = preview.remaining || {};

          if (titleEl) titleEl.textContent = b.booking_reference ? ('Confirm Entry - ' + b.booking_reference) : 'Confirm Entry';

          summaryEl.innerHTML = ''
            + '<div class="d-flex justify-content-between flex-wrap gap-2 mb-2">'
            + '  <div>'
            + '    <div class="fw-semibold">' + (b.full_name ? String(b.full_name) : '-') + '</div>'
            + '    <div class="text-muted small">' + (b.phone ? String(b.phone) : '-') + (b.slot_date ? (' | ' + String(b.slot_date)) : '') + '</div>'
            + '  </div>'
            + '  <div class="text-end">'
            + '    <div><strong>Total Pax:</strong> ' + String(preview.total_purchased || 0) + '</div>'
            + '    <div><strong>Already Entered:</strong> ' + String(preview.total_already_entered || 0) + '</div>'
            + '    <div><strong>Remaining:</strong> ' + String(preview.total_remaining || 0) + '</div>'
            + '  </div>'
            + '</div>';

          if (preview.fully_entered) {
            summaryEl.innerHTML += '<div class="alert alert-success mb-0">Fully entered.</div>';
          }

          const keys = ['dewasa','kanak','kanak_foc','warga','atm','staff_blanket','living_in','ajk','comp'];
          let html = '<div class="row g-2">';
          keys.forEach((k) => {
            const bought = Number(purchased[k] || 0);
            if (bought <= 0) return;
            const rem = Number(remaining[k] || 0);
            const entered = Number(already[k] || 0);
            const name = inputNameForKey(k);
            html += ''
              + '<div class="col-12 col-md-6">'
              + '  <label class="form-label mb-1">' + labelForKey(k) + ' <span class="text-muted">(Entered ' + entered + ', Remaining ' + rem + ')</span></label>'
              + '  <input type="number" min="0" max="' + rem + '" class="form-control entry-qty" data-key="' + k + '" name="' + name + '" value="0" ' + (rem === 0 ? 'disabled' : '') + '>'
              + '</div>';
          });
          html += '</div>';
          formBodyEl.innerHTML = html;

          if (allArrivedEl) allArrivedEl.checked = false;
          setAlert('', '');
        }

        function fillAllRemaining() {
          if (!currentPreview) return;
          const remaining = currentPreview.remaining || {};
          const inputs = document.querySelectorAll('#entryConfirmFormBody .entry-qty');
          inputs.forEach((inp) => {
            const key = inp.getAttribute('data-key') || '';
            const rem = Number(remaining[key] || 0);
            if (!inp.disabled) inp.value = String(rem);
          });
        }

        async function openPreview(ref) {
          if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
          const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
          if (summaryEl) summaryEl.innerHTML = '<div class="text-muted">Loading...</div>';
          if (formBodyEl) formBodyEl.innerHTML = '';
          setAlert('', '');
          modal.show();

          try {
            const url = 'check_in.php?ajax_entry_preview=1&ref=' + encodeURIComponent(ref || '');
            const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
            if (!res.ok) throw new Error('Request failed');
            const data = await res.json();
            if (!data || !data.ok) {
              setAlert('danger', (data && data.message) ? data.message : 'Unable to load entry preview.');
              return;
            }
            renderPreview(data);
          } catch (err) {
            setAlert('danger', 'Unable to load entry preview.');
          }
        }

        async function submitEntry() {
          if (!currentPreview) return;
          const ref = (currentPreview.booking && currentPreview.booking.booking_reference) ? String(currentPreview.booking.booking_reference) : '';
          if (!ref) return;

          const fd = new FormData();
          fd.set('ajax_entry_confirm', '1');
          fd.set('csrf_token', csrf);
          fd.set('booking_reference', ref);
          const inputs = document.querySelectorAll('#entryConfirmFormBody .entry-qty');
          inputs.forEach((inp) => {
            const name = inp.getAttribute('name') || '';
            if (!name) return;
            fd.set(name, String(inp.value || '0'));
          });

          if (submitBtn) submitBtn.disabled = true;
          try {
            const res = await fetch('check_in.php', { method: 'POST', body: fd, cache: 'no-store', credentials: 'same-origin' });
            if (!res.ok) throw new Error('Request failed');
            const data = await res.json();
            if (!data || !data.ok) {
              setAlert('danger', (data && data.message) ? data.message : 'Unable to confirm entry.');
              return;
            }
            setAlert('', '');

            const pageAlert = document.getElementById('entryPageAlert');
            if (pageAlert) {
              const msg = data.message ? String(data.message) : 'Entry recorded.';
              pageAlert.innerHTML = '<div class="alert alert-success alert-dismissible fade show" role="alert">'
                + msg
                + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                + '</div>';
            }

            const inst = (typeof bootstrap !== 'undefined' && bootstrap.Modal) ? bootstrap.Modal.getInstance(modalEl) : null;
            if (inst) inst.hide();
          } catch (err) {
            setAlert('danger', 'Unable to confirm entry.');
          } finally {
            if (submitBtn) submitBtn.disabled = false;
          }
        }

        if (markBtn) {
          markBtn.addEventListener('click', () => {
            const ref = refInput ? String(refInput.value || '').trim() : '';
            if (!ref) {
              setAlert('danger', 'Booking reference is required.');
              return;
            }
            openPreview(ref);
          });
        }

        if (allArrivedEl) {
          allArrivedEl.addEventListener('change', () => {
            if (allArrivedEl.checked) fillAllRemaining();
          });
        }

        if (submitBtn) {
          submitBtn.addEventListener('click', submitEntry);
        }
      })();
    </script>

    <script>
      (function () {
        const form = document.getElementById('searchBookingForm');
        const input = document.getElementById('searchBookingRef');
        const result = document.getElementById('searchBookingResult');
        const clearBtn = document.getElementById('searchBookingClearBtn');

        async function runSearch() {
          if (!input || !result) return;
          const ref = String(input.value || '').trim();
          if (!ref) {
            result.innerHTML = '';
            return;
          }
          result.innerHTML = '<div class="mt-3 text-muted">Loading...</div>';
          try {
            const url = 'check_in.php?ajax_search_booking=1&ref=' + encodeURIComponent(ref);
            const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
            if (!res.ok) throw new Error('Request failed');
            const html = await res.text();
            result.innerHTML = html;
          } catch (e) {
            result.innerHTML = '<div class="alert alert-danger mt-3 mb-0">Unable to search booking right now.</div>';
          }
        }

        if (form) {
          form.addEventListener('submit', function (e) {
            e.preventDefault();
            runSearch();
          });
        }

        if (clearBtn) {
          clearBtn.addEventListener('click', function () {
            if (input) input.value = '';
            if (result) result.innerHTML = '';
          });
        }
      })();
    </script>
  </body>
</html>
<?php
if ($result) {
    $result->free();
}
$mysqli->close();
?>
