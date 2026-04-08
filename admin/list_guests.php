<?php
// list_guests.php - overall guest list (filterable)

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin_auth.php';

require_admin_roles(['admin', 'staff', 'assistant', 'banquet', 'finance', 'entry_duty', 'ENT_ADMIN']);
$csrfToken = admin_csrf_token();

$sidebarRoleLabel = match (strtolower(admin_get_role())) {
    'banquet' => 'Banquet',
    'finance' => 'Finance',
    'assistant' => 'Assistant',
    'staff' => 'Sales',
    'ent_admin' => 'ENT',
    'entry_duty' => 'Entry Staff',
    default => 'Admin',
};

$adminRoleUpper = strtoupper(trim((string) admin_get_role()));
$isViewOnly = in_array($adminRoleUpper, ['BANQUET', 'ENT_ADMIN'], true);

$mysqli = null;

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
} catch (Throwable $e) {
    die('<h2>Database connection failed.</h2>');
}

function name_list_clean_remark(string $remark): string
{
    $remark = trim($remark);
    if ($remark === '') {
        return '';
    }

    $lines = preg_split('/\r\n|\r|\n/', $remark) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        $t = trim((string) $line);
        if ($t === '') {
            continue;
        }
        if (strtoupper($t) === 'READY') {
            continue;
        }
        $clean[] = $t;
    }

    return trim(implode("\n", $clean));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'add_manual_booking') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    if ($isViewOnly) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'You are not allowed to add manual bookings.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $csrfTokenPost = (string) ($_POST['csrf_token'] ?? '');
    if (!admin_verify_csrf($csrfTokenPost)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $slotDate = trim((string) ($_POST['slot_date'] ?? ''));
    $dt = DateTime::createFromFormat('Y-m-d', $slotDate);
    $slotDate = $dt ? $dt->format('Y-m-d') : '';
    if ($slotDate === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid date.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    if ($fullName === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Name is required.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $phone = trim((string) ($_POST['phone'] ?? ''));
    $remark = trim((string) ($_POST['remark'] ?? ''));
    $tableNo = trim((string) ($_POST['table_no'] ?? ''));

    $qtyDewasa = max(0, (int) ($_POST['quantity_dewasa'] ?? 0));
    $qtyKanak = max(0, (int) ($_POST['quantity_kanak'] ?? 0));
    $qtyKanakFoc = max(0, (int) ($_POST['quantity_kanak_foc'] ?? 0));
    $qtyWarga = max(0, (int) ($_POST['quantity_warga_emas'] ?? 0));
    $qtyAtm = max(0, (int) ($_POST['quantity_atm'] ?? 0));
    $atmTypeRaw = trim((string) ($_POST['atm_type'] ?? ''));
    $atmType = $atmTypeRaw;
    if (!in_array($atmType, ['', 'ATM-TDM', 'ATM_TLDM', 'ATM-TUDM'], true)) {
        $atmType = '';
    }

    $qtyStaffBlanket = max(0, (int) ($_POST['staff_blanket_qty'] ?? 0));
    $qtyLivingIn = max(0, (int) ($_POST['living_in_qty'] ?? 0));
    $qtyAjk = max(0, (int) ($_POST['ajk_qty'] ?? 0));
    $qtyFreeVoucher = max(0, (int) ($_POST['free_voucher_qty'] ?? 0));

    $paymentStatus = 'PENDING';

    $totalPeople = $qtyDewasa + $qtyKanak + $qtyKanakFoc + $qtyWarga + $qtyAtm + $qtyStaffBlanket + $qtyLivingIn + $qtyAjk + $qtyFreeVoucher;
    if ($totalPeople <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Please enter at least 1 pax.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($atmType !== '' && $qtyAtm <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Please enter ATM pax quantity.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $settings = [];
    try {
        ensure_global_settings_schema($mysqli);
        $settings = load_global_settings($mysqli);
    } catch (Throwable $e) {
        $settings = [];
    }

    $eventYear = (int) ($settings['event_year'] ?? (int) date('Y'));
    $refPrefix = 'WP' . str_pad((string) ($eventYear % 100), 2, '0', STR_PAD_LEFT);

    $bookingRef = '';
    for ($i = 0; $i < 10; $i++) {
        $candidate = $refPrefix . '-' . random_int(10000, 99999);
        $checkStmt = $mysqli->prepare('SELECT 1 FROM bookings WHERE booking_reference = ? LIMIT 1');
        if (!$checkStmt) {
            break;
        }
        $checkStmt->bind_param('s', $candidate);
        $checkStmt->execute();
        $checkStmt->store_result();
        $exists = $checkStmt->num_rows > 0;
        $checkStmt->close();
        if (!$exists) {
            $bookingRef = $candidate;
            break;
        }
    }

    if ($bookingRef === '') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to generate booking reference. Please try again.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $paymentMethod = 'MANUAL';
    $checkinStatus = 'Not Checked';
    $totalPrice = 0.00;

    if (!isset($_FILES['payment_proof']) || !is_array($_FILES['payment_proof']) || (int) ($_FILES['payment_proof']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Please upload payment proof.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fileInfo = $_FILES['payment_proof'];
    $uploadError = (int) ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Failed to upload payment proof.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tmpName = (string) ($fileInfo['tmp_name'] ?? '');
    $mime = (string) ($fileInfo['type'] ?? '');
    if ($mime === '' && is_file($tmpName)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $tmpName);
            if (is_string($detected)) {
                $mime = $detected;
            }
            finfo_close($finfo);
        }
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];
    $ext = $allowed[$mime] ?? null;
    if ($ext === null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Unsupported file type. Only JPG, PNG, and PDF are allowed.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/payment_proof/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to create upload directory.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $newFileName = 'MANUAL_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $newFileName;
    if (!move_uploaded_file($tmpName, $targetPath)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Failed to save uploaded file.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $paymentProofPath = 'uploads/payment_proof/' . $newFileName;

    $prices = [];
    try {
        $prices = load_event_settings_prices($mysqli);
        $prices = apply_special_prices($mysqli, $prices, $slotDate);
    } catch (Throwable $e) {
        $prices = [];
    }

    $priceDewasa = (float) ($prices['dewasa'] ?? 0);
    $priceKanak = (float) ($prices['kanak'] ?? 0);
    $priceWarga = (float) ($prices['warga'] ?? 0);

    $totalPrice = ($qtyDewasa * $priceDewasa)
        + ($qtyKanak * $priceKanak)
        + ($qtyWarga * $priceWarga)
        + ($qtyAtm * $priceWarga)
        + ($qtyStaffBlanket * 50)
        + ($qtyLivingIn * 0)
        + ($qtyAjk * 75)
        + ($qtyFreeVoucher * 0);
    $totalPrice = round((float) $totalPrice, 2);

    $atmBranchType = ($atmType !== '' && $qtyAtm > 0) ? $atmType : '';

    $paidAt = null;

    $insertSql = "
        INSERT INTO bookings (
            booking_reference,
            full_name,
            phone,
            remark,
            atm_branch_type,
            slot_date,
            quantity_dewasa,
            free_quantity_dewasa,
            quantity_atm,
            free_quantity_atm,
            quantity_kanak,
            free_quantity_kanak,
            quantity_kanak_foc,
            free_quantity_kanak_foc,
            quantity_warga_emas,
            free_quantity_warga_emas,
            staff_blanket_qty,
            living_in_qty,
            ajk_qty,
            free_voucher_qty,
            total_price,
            payment_status,
            payment_method,
            checkin_status,
            payment_proof,
            table_no,
            paid_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, 0, ?, 0, ?, 0, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)
    ";
    $stmt = $mysqli->prepare($insertSql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to save manual booking. (prepare failed: ' . (string) $mysqli->error . ')'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt->bind_param(
        'ssssssiiiiiiiiidsssss',
        $bookingRef,
        $fullName,
        $phone,
        $remark,
        $atmBranchType,
        $slotDate,
        $qtyDewasa,
        $qtyAtm,
        $qtyKanak,
        $qtyKanakFoc,
        $qtyWarga,
        $qtyStaffBlanket,
        $qtyLivingIn,
        $qtyAjk,
        $qtyFreeVoucher,
        $totalPrice,
        $paymentStatus,
        $paymentMethod,
        $checkinStatus,
        $paymentProofPath,
        $tableNo
    );

    if (!$stmt->execute()) {
        $stmtErr = (string) $stmt->error;
        $stmt->close();
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to save manual booking. (db: ' . $stmtErr . ')'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->close();

    $slotUpdate = $mysqli->prepare('UPDATE booking_slots SET booked_count = booked_count + ? WHERE slot_date = ?');
    if ($slotUpdate) {
        $slotUpdate->bind_param('is', $totalPeople, $slotDate);
        $slotUpdate->execute();
        $slotUpdate->close();
    }

    echo json_encode(['ok' => true, 'booking_reference' => $bookingRef], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'add_pax') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $adminUsername = trim((string) ($_SESSION['admin_username'] ?? ''));

    $csrfTokenPost = (string) ($_POST['csrf_token'] ?? '');
    if (!admin_verify_csrf($csrfTokenPost)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $bookingRef = trim((string) ($_POST['booking_reference'] ?? ''));
    if ($bookingRef === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid booking reference.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $addDewasa = max(0, (int) ($_POST['add_quantity_dewasa'] ?? 0));
    $addKanak = max(0, (int) ($_POST['add_quantity_kanak'] ?? 0));
    $addKanakFoc = max(0, (int) ($_POST['add_quantity_kanak_foc'] ?? 0));
    $addWarga = max(0, (int) ($_POST['add_quantity_warga_emas'] ?? 0));
    $addAtm = max(0, (int) ($_POST['add_quantity_atm'] ?? 0));
    $addStaffBlanket = max(0, (int) ($_POST['add_staff_blanket_qty'] ?? 0));
    $addLivingIn = max(0, (int) ($_POST['add_living_in_qty'] ?? 0));
    $addAjk = max(0, (int) ($_POST['add_ajk_qty'] ?? 0));
    $addFreeVoucher = max(0, (int) ($_POST['add_free_voucher_qty'] ?? 0));
    $addRemark = trim((string) ($_POST['add_remark'] ?? ''));

    $totalAdd = $addDewasa + $addKanak + $addKanakFoc + $addWarga + $addAtm + $addStaffBlanket + $addLivingIn + $addAjk + $addFreeVoucher;
    if ($totalAdd <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Please add at least 1 pax.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $mysqli->prepare('SELECT booking_reference, slot_date, quantity_dewasa, quantity_kanak, quantity_kanak_foc, quantity_warga_emas, quantity_atm, staff_blanket_qty, living_in_qty, ajk_qty, free_voucher_qty, total_price, payment_status, remark, payment_proof FROM bookings WHERE booking_reference = ? LIMIT 1');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to update booking right now.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->bind_param('s', $bookingRef);
    $stmt->execute();
    $res = $stmt->get_result();
    $booking = $res ? ($res->fetch_assoc() ?: null) : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Booking not found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $slotDate = (string) ($booking['slot_date'] ?? '');
    $qtyDewasa = (int) ($booking['quantity_dewasa'] ?? 0) + $addDewasa;
    $qtyKanak = (int) ($booking['quantity_kanak'] ?? 0) + $addKanak;
    $qtyKanakFoc = (int) ($booking['quantity_kanak_foc'] ?? 0) + $addKanakFoc;
    $qtyWarga = (int) ($booking['quantity_warga_emas'] ?? 0) + $addWarga;
    $qtyAtm = (int) ($booking['quantity_atm'] ?? 0) + $addAtm;
    $qtyStaffBlanket = (int) ($booking['staff_blanket_qty'] ?? 0) + $addStaffBlanket;
    $qtyLivingIn = (int) ($booking['living_in_qty'] ?? 0) + $addLivingIn;
    $qtyAjk = (int) ($booking['ajk_qty'] ?? 0) + $addAjk;
    $qtyFreeVoucher = (int) ($booking['free_voucher_qty'] ?? 0) + $addFreeVoucher;

    $allowAddAtm = ((int) ($booking['quantity_atm'] ?? 0)) > 0;
    if (!$allowAddAtm && $addAtm > 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'This booking cannot add ATM pax.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $existingRemark = trim((string) ($booking['remark'] ?? ''));
    $newRemark = $existingRemark;
    if ($addRemark !== '') {
        $newRemark = $existingRemark !== '' ? ($existingRemark . "\n" . $addRemark) : $addRemark;
    }

    $newProofPath = '';
    if (!isset($_FILES['add_payment_proof']) || !is_array($_FILES['add_payment_proof']) || (int) ($_FILES['add_payment_proof']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Please upload proof.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (isset($_FILES['add_payment_proof']) && is_array($_FILES['add_payment_proof']) && (int) ($_FILES['add_payment_proof']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $fileInfo = $_FILES['add_payment_proof'];
        $uploadError = (int) ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Failed to upload payment proof.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $tmpName = (string) ($fileInfo['tmp_name'] ?? '');
        $mime = (string) ($fileInfo['type'] ?? '');
        if ($mime === '' && is_file($tmpName)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $tmpName);
                if (is_string($detected)) {
                    $mime = $detected;
                }
                finfo_close($finfo);
            }
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
        ];
        $ext = $allowed[$mime] ?? null;
        if ($ext === null) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Unsupported file type. Only JPG, PNG, and PDF are allowed.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $uploadDir = __DIR__ . '/../uploads/payment_proof/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Unable to create upload directory.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $newFileName = 'ADD_PAX_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
        $targetPath = $uploadDir . $newFileName;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Failed to save uploaded file.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $newProofPath = 'uploads/payment_proof/' . $newFileName;
    }

    $existingProof = trim((string) ($booking['payment_proof'] ?? ''));
    $finalProof = $existingProof;
    if ($newProofPath !== '') {
        $finalProof = $existingProof !== '' ? ($existingProof . "\n" . $newProofPath) : $newProofPath;
    }

    $prices = [];
    try {
        $prices = load_event_settings_prices($mysqli);
        $prices = apply_special_prices($mysqli, $prices, $slotDate);
    } catch (Throwable $e) {
        $prices = [];
    }
    $priceDewasa = (float) ($prices['dewasa'] ?? 0);
    $priceKanak = (float) ($prices['kanak'] ?? 0);
    $priceWarga = (float) ($prices['warga'] ?? 0);

    $currentTotal = (float) ($booking['total_price'] ?? 0);
    $addTotal = ($addDewasa * $priceDewasa)
        + ($addKanak * $priceKanak)
        + ($addWarga * $priceWarga)
        + ($addAtm * $priceWarga)
        + ($addStaffBlanket * 50)
        + ($addLivingIn * 0)
        + ($addAjk * 75)
        + ($addFreeVoucher * 0);
    $newTotal = round($currentTotal + (float) $addTotal, 2);

    $up = $mysqli->prepare("UPDATE bookings SET quantity_dewasa = ?, quantity_kanak = ?, quantity_kanak_foc = ?, quantity_warga_emas = ?, quantity_atm = ?, staff_blanket_qty = ?, living_in_qty = ?, ajk_qty = ?, free_voucher_qty = ?, total_price = ?, remark = ?, payment_proof = ?, pax_added_at = NOW(), pax_added_by = ?, bank_received_status = 'PENDING', bank_confirmed_at = NULL, bank_received_by = NULL, bank_not_received_reason = NULL WHERE booking_reference = ?");
    if (!$up) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to update booking right now.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $up->bind_param('iiiiiiiiidssss', $qtyDewasa, $qtyKanak, $qtyKanakFoc, $qtyWarga, $qtyAtm, $qtyStaffBlanket, $qtyLivingIn, $qtyAjk, $qtyFreeVoucher, $newTotal, $newRemark, $finalProof, $adminUsername, $bookingRef);
    if (!$up->execute()) {
        $up->close();
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Failed to update booking.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $up->close();

    $slotUpdate = $mysqli->prepare('UPDATE booking_slots SET booked_count = booked_count + ? WHERE slot_date = ?');
    if ($slotUpdate) {
        $slotUpdate->bind_param('is', $totalAdd, $slotDate);
        $slotUpdate->execute();
        $slotUpdate->close();
    }

    try {
        ensure_add_pax_logs_schema($mysqli);
        $logStmt = $mysqli->prepare('INSERT INTO add_pax_logs (booking_reference, slot_date, added_quantity_dewasa, added_quantity_kanak, added_quantity_kanak_foc, added_quantity_warga_emas, added_quantity_atm, added_staff_blanket_qty, added_living_in_qty, added_ajk_qty, added_free_voucher_qty, added_remark, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if ($logStmt) {
            $logStmt->bind_param(
                'ssiiiiiiiiiiss',
                $bookingRef,
                $slotDate,
                $addDewasa,
                $addKanak,
                $addKanakFoc,
                $addWarga,
                $addAtm,
                $addStaffBlanket,
                $addLivingIn,
                $addAjk,
                $addFreeVoucher,
                $addRemark,
                $adminUsername
            );
            $logStmt->execute();
            $logStmt->close();
        }
    } catch (Throwable $e) {
        // ignore
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['export']) && (string) ($_GET['export'] ?? '') === '1') {
    ini_set('display_errors', '0');
    error_reporting(0);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $slotDate = trim((string) ($_GET['slot_date'] ?? ''));
    $dt = DateTime::createFromFormat('Y-m-d', $slotDate);
    $slotDate = $dt ? $dt->format('Y-m-d') : '';
    if ($slotDate === '') {
        http_response_code(400);
        echo 'Invalid date.';
        exit;
    }

    $detailQuery = "
        SELECT
            booking_reference,
            full_name,
            phone,
            remark,
            slot_date,
            quantity_dewasa,
            free_quantity_dewasa,
            quantity_atm,
            free_quantity_atm,
            quantity_kanak,
            free_quantity_kanak,
            quantity_kanak_foc,
            free_quantity_kanak_foc,
            quantity_warga_emas,
            free_quantity_warga_emas,
            staff_blanket_qty,
            living_in_qty,
            ajk_qty,
            free_voucher_qty,
            comp_qty,
            total_price,
            payment_status,
            payment_method,
            table_no
        FROM bookings
        WHERE slot_date = ?
          AND payment_status IN ('PAID','PENDING')
        ORDER BY booking_reference ASC
    ";

    $rows = [];
    $stmt = $mysqli->prepare($detailQuery);
    if ($stmt) {
        $stmt->bind_param('s', $slotDate);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }
        $stmt->close();
    }

    $filename = 'name_list_' . $slotDate . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit;
    }

    fputcsv($out, [
        'Booking Reference',
        'Name',
        'Phone',
        'AWAM',
        'ATM',
        'KANAK',
        'INFANT',
        'WARGA',
        'STAFF',
        'LIVING IN',
        'AJK',
        'FREE',
        'COMP',
        'TOTAL PAX',
        'TOTAL',
        'PAYMENT STATUS',
        'REMARK',
        'TABLE NO',
    ]);

    foreach ($rows as $row) {
        $awam = (int) ($row['quantity_dewasa'] ?? 0) + (int) ($row['free_quantity_dewasa'] ?? 0);
        $atm = (int) ($row['quantity_atm'] ?? 0) + (int) ($row['free_quantity_atm'] ?? 0);
        $kanak = (int) ($row['quantity_kanak'] ?? 0) + (int) ($row['free_quantity_kanak'] ?? 0);
        $below7 = (int) ($row['quantity_kanak_foc'] ?? 0) + (int) ($row['free_quantity_kanak_foc'] ?? 0);
        $warga = (int) ($row['quantity_warga_emas'] ?? 0) + (int) ($row['free_quantity_warga_emas'] ?? 0);
        $staffBlanket = (int) ($row['staff_blanket_qty'] ?? 0);
        $livingIn = (int) ($row['living_in_qty'] ?? 0);
        $ajk = (int) ($row['ajk_qty'] ?? 0);
        $freeVoucher = (int) ($row['free_voucher_qty'] ?? 0);
        $comp = (int) ($row['comp_qty'] ?? 0);
        $totalPax = $awam + $atm + $kanak + $below7 + $warga + $staffBlanket + $livingIn + $ajk + $freeVoucher + $comp;

        $totalPrice = (float) ($row['total_price'] ?? 0);
        $paymentStatus = strtoupper(trim((string) ($row['payment_status'] ?? '')));
        $remark = name_list_clean_remark((string) ($row['remark'] ?? ''));
        $tableNo = '';

        fputcsv($out, [
            (string) ($row['booking_reference'] ?? ''),
            (string) ($row['full_name'] ?? ''),
            (string) ($row['phone'] ?? ''),
            $awam,
            $atm,
            $kanak,
            $below7,
            $warga,
            $staffBlanket,
            $livingIn,
            $ajk,
            $freeVoucher,
            $comp,
            $totalPax,
            number_format($totalPrice, 2, '.', ''),
            $paymentStatus,
            $remark,
            '',
        ]);
    }

    fclose($out);
    exit;
}

if (isset($_GET['ajax_details']) && (string) ($_GET['ajax_details'] ?? '') === '1') {
    $slotDate = trim((string) ($_GET['slot_date'] ?? ''));
    $dt = DateTime::createFromFormat('Y-m-d', $slotDate);
    $slotDate = $dt ? $dt->format('Y-m-d') : '';
    if ($slotDate === '') {
        http_response_code(400);
        echo '<div class="alert alert-danger mb-0">Invalid date.</div>';
        exit;
    }

    $detailQuery = "
        SELECT
            booking_reference,
            full_name,
            phone,
            remark,
            slot_date,
            quantity_dewasa,
            quantity_atm,
            quantity_kanak,
            quantity_kanak_foc,
            quantity_warga_emas,
            staff_blanket_qty,
            living_in_qty,
            ajk_qty,
            free_voucher_qty,
            comp_qty,
            total_price,
            payment_status,
            payment_method,
            table_no
        FROM bookings
        WHERE slot_date = ?
          AND payment_status IN ('PAID','PENDING')
        ORDER BY
          CASE WHEN payment_status = 'PAID' THEN 0 ELSE 1 END ASC,
          created_at ASC,
          booking_reference ASC
    ";
    $rows = [];
    $stmt = $mysqli->prepare($detailQuery);
    if ($stmt) {
        $stmt->bind_param('s', $slotDate);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }
        $stmt->close();
    }

    if (!$rows) {
        echo '<div class="text-muted">No bookings found for this date.</div>';
        exit;
    }

    echo '<div class="print-area">';
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped align-middle name-list-table">';
    echo '<thead><tr>';
    echo '<th>No</th>';
    echo '<th>Booking Reference</th>';
    echo '<th>Name</th>';
    echo '<th>Phone</th>';
    echo '<th class="cat-col">AWAM</th>';
    echo '<th class="cat-col">ATM</th>';
    echo '<th class="cat-col">KANAK</th>';
    echo '<th class="cat-col">INFANT</th>';
    echo '<th class="cat-col">WARGA</th>';
    echo '<th class="cat-col">STAFF</th>';
    echo '<th class="cat-col">LIVING IN</th>';
    echo '<th class="cat-col">AJK</th>';
    echo '<th class="cat-col">FREE</th>';
    echo '<th class="cat-col">COMP</th>';
    echo '<th>TOTAL PAX</th>';
    echo '<th>TOTAL</th>';
    echo '<th>PAYMENT</th>';
    echo '<th>REMARK</th>';
    echo '<th>TABLE NO</th>';
    echo '</tr></thead><tbody>';

    $rowNo = 1;
    foreach ($rows as $row) {
        $adminRoleUpper = strtoupper(trim((string) admin_get_role()));
        $canAddPax = in_array($adminRoleUpper, ['ADMIN', 'STAFF', 'ASSISTANT', 'FINANCE'], true);

        $paymentStatus = strtoupper(trim((string) ($row['payment_status'] ?? '')));
        $paymentBadge = 'text-bg-secondary';
        if ($paymentStatus === 'PAID') {
            $paymentBadge = 'text-bg-success';
        } elseif ($paymentStatus === 'PENDING') {
            $paymentBadge = 'text-bg-warning';
        } elseif ($paymentStatus === 'FAILED') {
            $paymentBadge = 'text-bg-danger';
        }

        $awam = (int) ($row['quantity_dewasa'] ?? 0) + (int) ($row['free_quantity_dewasa'] ?? 0);
        $atm = (int) ($row['quantity_atm'] ?? 0) + (int) ($row['free_quantity_atm'] ?? 0);
        $kanak = (int) ($row['quantity_kanak'] ?? 0) + (int) ($row['free_quantity_kanak'] ?? 0);
        $below7 = (int) ($row['quantity_kanak_foc'] ?? 0) + (int) ($row['free_quantity_kanak_foc'] ?? 0);
        $warga = (int) ($row['quantity_warga_emas'] ?? 0) + (int) ($row['free_quantity_warga_emas'] ?? 0);
        $staffBlanket = (int) ($row['staff_blanket_qty'] ?? 0);
        $livingIn = (int) ($row['living_in_qty'] ?? 0);
        $ajk = (int) ($row['ajk_qty'] ?? 0);
        $freeVoucher = (int) ($row['free_voucher_qty'] ?? 0);
        $comp = (int) ($row['comp_qty'] ?? 0);
        $totalPax = $awam + $atm + $kanak + $below7 + $warga + $staffBlanket + $livingIn + $ajk + $freeVoucher + $comp;

        $totalPrice = (float) ($row['total_price'] ?? 0);
        $remark = trim((string) ($row['remark'] ?? ''));
        $tableNo = trim((string) ($row['table_no'] ?? ''));

        echo '<tr>';
        echo '<td class="fw-semibold">' . number_format($rowNo) . '</td>';
        echo '<td class="fw-semibold">' . htmlspecialchars((string) ($row['booking_reference'] ?? ''));
        if ($canAddPax) {
            echo '<div class="no-print mt-1">'
                . '<button type="button" class="btn btn-sm btn-outline-primary js-add-pax"'
                . ' data-booking-ref="' . htmlspecialchars((string) ($row['booking_reference'] ?? '')) . '"'
                . ' data-name="' . htmlspecialchars((string) ($row['full_name'] ?? '')) . '"'
                . ' data-slot-date="' . htmlspecialchars((string) ($row['slot_date'] ?? '')) . '"'
                . ' data-has-atm="' . (((int) ($row['quantity_atm'] ?? 0)) > 0 ? '1' : '0') . '"'
                . '>'
                . '<i class="bi bi-pencil-square me-1"></i>Add Pax'
                . '</button>'
                . '</div>';
        }
        echo '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['full_name'] ?? '')) . '</td>';
        echo '<td class="phone-col">' . htmlspecialchars((string) ($row['phone'] ?? '')) . '</td>';
        echo '<td class="cat-col">' . number_format($awam) . '</td>';
        echo '<td class="cat-col">' . number_format($atm) . '</td>';
        echo '<td class="cat-col">' . number_format($kanak) . '</td>';
        echo '<td class="cat-col">' . number_format($below7) . '</td>';
        echo '<td class="cat-col">' . number_format($warga) . '</td>';
        echo '<td class="cat-col">' . number_format($staffBlanket) . '</td>';
        echo '<td class="cat-col">' . number_format($livingIn) . '</td>';
        echo '<td class="cat-col">' . number_format($ajk) . '</td>';
        echo '<td class="cat-col">' . number_format($freeVoucher) . '</td>';
        echo '<td class="cat-col">' . number_format($comp) . '</td>';
        echo '<td class="fw-semibold">' . number_format($totalPax) . '</td>';
        echo '<td>RM' . number_format($totalPrice, 2) . '</td>';
        echo '<td><span class="badge ' . htmlspecialchars($paymentBadge) . '">' . htmlspecialchars($paymentStatus !== '' ? $paymentStatus : '-') . '</span></td>';
        echo '<td class="remark-cell">' . ($remark !== '' ? htmlspecialchars($remark) : '<span class="text-muted">-</span>') . '</td>';
        echo '<td class="table-no-cell"><div class="table-box"></div></td>';
        echo '</tr>';
        $rowNo++;
    }

    echo '</tbody></table>';
    echo '</div>';
    echo '</div>';
    exit;
}

$dateSummary = [];
$summaryQuery = "
    SELECT
        slot_date,
        COUNT(*) AS total_bookings,
        SUM(
            COALESCE(quantity_dewasa,0)
          + COALESCE(free_quantity_dewasa,0)
          + COALESCE(quantity_atm,0)
          + COALESCE(free_quantity_atm,0)
          + COALESCE(quantity_kanak,0)
          + COALESCE(free_quantity_kanak,0)
          + COALESCE(quantity_kanak_foc,0)
          + COALESCE(free_quantity_kanak_foc,0)
          + COALESCE(quantity_warga_emas,0)
          + COALESCE(free_quantity_warga_emas,0)
          + COALESCE(staff_blanket_qty,0)
          + COALESCE(living_in_qty,0)
          + COALESCE(ajk_qty,0)
          + COALESCE(free_voucher_qty,0)
          + COALESCE(comp_qty,0)
        ) AS total_pax
    FROM bookings
    WHERE payment_status IN ('PAID','PENDING')
";
$summaryQuery .= " GROUP BY slot_date ORDER BY slot_date ASC";
if ($result = $mysqli->query($summaryQuery)) {
    while ($row = $result->fetch_assoc()) {
        $dateSummary[] = $row;
    }
    $result->free();
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Name List - Buffet Ramadan</title>
    <link rel="icon" type="image/png" href="../assets/img/Logo%20ATM.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <style>
      :root {
        --ramadan-green: #08372b;
        --ramadan-gold: #d8b45c;
        --ramadan-cream: #fff9ed;
      }
      body { font-family: 'Cairo', system-ui, sans-serif; background: var(--ramadan-cream); }
      .layout { min-height: 100vh; }
      .sidebar { background: linear-gradient(180deg, var(--ramadan-green), #041f18); color: #fef6dd; width: 260px; }
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
      .sidebar .nav-link { color: #f5e9c8; border-radius: 0.75rem; padding: 0.65rem 1rem; display: flex; align-items: center; gap: 0.5rem; }
      .sidebar .nav-link.active,
      .sidebar .nav-link:hover { background: rgba(216,180,92,0.18); color: var(--ramadan-gold); }
      .logout-btn { background: rgba(220,53,69,0.12); border: 1px solid rgba(220,53,69,0.4); color: #ffb6b6; }
      .main-content { background: var(--ramadan-cream); padding: 2rem; }
      .table-box { width: 320px; height: 44px; border: 1px solid #f9f9f9ff; background: #fff; }
      .name-list-table th.cat-col,
      .name-list-table td.cat-col { width: 56px; white-space: nowrap; }
      .name-list-table th.phone-col,
      .name-list-table td.phone-col { max-width: 120px; width: 120px; white-space: nowrap; }

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
          <p class="text-muted small mb-0">Name List</p>
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
            <a class="nav-link" href="check_in.php"><i class="bi bi-qr-code-scan"></i>Entry</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'STAFF','FINANCE', 'BANQUET', 'ASSISTANT', 'ENTRY_DUTY', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link active" href="list_guests.php"><i class="bi bi-people"></i>Name List</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'BANQUET'], true)): ?>
            <a class="nav-link" href="table_no.php"><i class="bi bi-table"></i>Table No</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'STAFF', 'FINANCE', 'ASSISTANT', 'ENT_ADMIN'], true)): ?>
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

      <main class="main-content flex-grow-1" id="print-area">
        <header class="mb-4">
          <button class="btn btn-outline-secondary d-lg-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
            <i class="bi bi-list"></i>
          </button>
          <h1 class="h3 text-dark mb-2">Name List</h1>
        </header>

        <section class="card border-0 shadow-sm rounded-4" id="guest-list">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <h4 class="mb-1">Name List</h4>
                <div class="text-muted">Click <span class="fw-semibold">View</span> to see full booking details for that date.</div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-striped align-middle">
                <thead> 
                  <tr>
                    <th>Date</th>
                    <th>Total Bookings</th>
                    <th>Total PAX</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($dateSummary): ?>
                    <?php foreach ($dateSummary as $row): ?>
                      <?php
                        $dateLabel = '';
                        $rawDate = (string) ($row['slot_date'] ?? '');
                        if ($rawDate !== '') {
                            $dt = DateTime::createFromFormat('Y-m-d', $rawDate);
                            $dateLabel = $dt ? $dt->format('d/m/Y') : $rawDate;
                        }
                        $totalBookings = (int) ($row['total_bookings'] ?? 0);
                        $totalPax = (int) ($row['total_pax'] ?? 0);
                      ?>
                      <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($dateLabel) ?></td>
                        <td><?= number_format($totalBookings) ?></td>
                        <td class="fw-semibold"><?= number_format($totalPax) ?></td>
                        <td class="text-end">
                          <button type="button" class="btn btn-sm btn-outline-primary js-view-date" data-slot-date="<?= htmlspecialchars($rawDate) ?>" data-slot-label="<?= htmlspecialchars($dateLabel) ?>">
                            <i class="bi bi-eye me-1"></i>View
                          </button>
                          <?php if (!in_array($adminRole, ['BANQUET', 'ENT_ADMIN'], true)): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-add-manual" data-slot-date="<?= htmlspecialchars($rawDate) ?>" data-slot-label="<?= htmlspecialchars($dateLabel) ?>">
                              <i class="bi bi-plus-circle me-1"></i>Add
                            </button>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="4" class="text-center text-muted py-4">No bookings found.</td>
                    </tr>
                  <?php endif; ?>
                    </tbody>
                  </table>
                </div>
          </div>
        </section>
      </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
      (function () {
        if (typeof bootstrap === 'undefined') return;
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (el) {
          new bootstrap.Tooltip(el);
        });
      })();

      function printGuestDetailsModal() {
        const body = document.getElementById('guestDetailsBody');
        const titleEl = document.getElementById('guestDetailsTitle');
        const html = body ? String(body.innerHTML || '') : '';
        const title = titleEl ? String(titleEl.textContent || 'Name List') : 'Name List';
        if (!html.trim()) return;

        const iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        document.body.appendChild(iframe);

        const doc = iframe.contentWindow ? iframe.contentWindow.document : null;
        if (!doc) {
          try { document.body.removeChild(iframe); } catch (_) {}
          return;
        }

        doc.open();
        doc.write('<!doctype html><html><head><meta charset="utf-8">');
        doc.write('<meta name="viewport" content="width=device-width, initial-scale=1">');
        doc.write('<title>' + title.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</title>');
        doc.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">');
        doc.write('<style>'
          + '@page{size:A4 landscape;margin:6mm}'
          + 'html,body{margin:0;padding:0}'
          + 'body{padding:10px;color:#000}'
          + 'h1{font-size:16px;margin:0 0 10px}'
          + '.table-responsive{overflow:visible !important}'
          + '.no-print{display:none !important}'
          + 'table{width:100% !important;table-layout:fixed !important;border-collapse:collapse !important}'
          + 'th,td{border:1px solid #000 !important;padding:3px 4px !important;vertical-align:middle !important;font-size:9px !important;line-height:1.1 !important}'
          + 'thead th{font-weight:700 !important}'
          + 'tr{break-inside:avoid !important;page-break-inside:avoid !important}'
          + '.remark-cell{white-space:normal !important;overflow-wrap:anywhere !important;word-break:break-word !important;vertical-align:top !important}'
          + '.table-no-cell{white-space:normal !important;overflow-wrap:anywhere !important;word-break:break-word !important;vertical-align:top !important}'
          + '.table-box{width:100% !important;min-height:44px !important;border:0 !important;border-radius:0 !important;background:transparent !important}'
          + '.cat-col{padding:2px 2px !important;font-size:8.2px !important;text-align:center !important;white-space:nowrap !important}'
          + 'td:nth-child(1),th:nth-child(1){width:2% !important;white-space:nowrap !important;text-align:center !important}'
          + 'td:nth-child(2),th:nth-child(2){width:8% !important;white-space:nowrap !important;overflow:hidden !important;text-overflow:ellipsis !important}'
          + 'td:nth-child(3),th:nth-child(3){width:9% !important;white-space:normal !important;word-break:break-word !important}'
          + 'td:nth-child(4),th:nth-child(4){width:6% !important;white-space:nowrap !important;overflow:hidden !important;text-overflow:ellipsis !important}'
          + 'td:nth-child(5),th:nth-child(5),'
          + 'td:nth-child(6),th:nth-child(6),'
          + 'td:nth-child(7),th:nth-child(7),'
          + 'td:nth-child(8),th:nth-child(8),'
          + 'td:nth-child(9),th:nth-child(9),'
          + 'td:nth-child(10),th:nth-child(10),'
          + 'td:nth-child(11),th:nth-child(11),'
          + 'td:nth-child(12),th:nth-child(12),'
          + 'td:nth-child(13),th:nth-child(13),'
          + 'td:nth-child(14),th:nth-child(14){width:3.25% !important;text-align:center !important}'
          + 'td:nth-child(15),th:nth-child(15){width:5.2% !important;text-align:center !important;white-space:nowrap !important}'
          + 'td:nth-child(16),th:nth-child(16){width:5.8% !important;text-align:center !important;white-space:nowrap !important}'
          + 'td:nth-child(17),th:nth-child(17){width:5.8% !important;text-align:center !important;white-space:nowrap !important}'
          + 'td:nth-child(18),th:nth-child(18){width:10.5% !important;white-space:normal !important;overflow-wrap:anywhere !important;word-break:break-word !important;vertical-align:top !important}'
          + 'td:nth-child(19),th:nth-child(19){width:12.5% !important;min-width:0 !important;white-space:normal !important;overflow-wrap:anywhere !important;word-break:break-word !important;vertical-align:top !important}'
          + '</style>');
        doc.write('</head><body>');
        doc.write('<h1>' + title.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</h1>');
        doc.write(html);
        doc.write('</body></html>');
        doc.close();

        setTimeout(() => {
          try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
          } finally {
            setTimeout(() => {
              try { document.body.removeChild(iframe); } catch (_) {}
            }, 500);
          }
        }, 50);
      }

      let currentGuestSlotDate = '';
      let currentGuestSlotLabel = '';
      let currentGuestPaymentStatus = 'PAID';

      function setGuestPaymentFilter(status) {
        const paidBtn = document.getElementById('guestFilterPaid');
        const pendingBtn = document.getElementById('guestFilterPending');
        const s = String(status || 'PAID').toUpperCase();
        if (paidBtn) paidBtn.classList.toggle('active', s === 'PAID');
        if (pendingBtn) pendingBtn.classList.toggle('active', s === 'PENDING');
      }

      async function loadGuestDetails(slotDate, slotLabel, paymentStatus) {
        const title = document.getElementById('guestDetailsTitle');
        const body = document.getElementById('guestDetailsBody');
        const printTarget = document.getElementById('guestDetailsPrint');
        const status = String(paymentStatus || 'PAID').toUpperCase();
        if (title) title.textContent = slotLabel ? ('Name List - ' + slotLabel) : 'Name List';
        if (body) body.innerHTML = '<div class="text-muted">Loading...</div>';
        if (printTarget) printTarget.setAttribute('data-slot-date', slotDate || '');

        currentGuestSlotDate = slotDate || '';
        currentGuestSlotLabel = slotLabel || '';
        currentGuestPaymentStatus = status;

        try {
          const url = 'list_guests.php?ajax_details=1&slot_date=' + encodeURIComponent(slotDate || '');
          const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
          if (!res.ok) throw new Error('Request failed');
          const html = await res.text();
          if (body) body.innerHTML = html;
        } catch (e) {
          if (body) body.innerHTML = '<div class="alert alert-danger mb-0">Unable to load details.</div>';
        }
      }

      function openManualModal(slotDate, slotLabel) {
        const modalEl = document.getElementById('manualBookingModal');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        const form = document.getElementById('manualBookingForm');
        const title = document.getElementById('manualBookingTitle');
        const dateInput = document.getElementById('manualSlotDate');
        const slotLabelEl = document.getElementById('manualSlotLabel');
        const alertBox = document.getElementById('manualBookingAlert');
        if (alertBox) alertBox.innerHTML = '';

        if (title) title.textContent = slotLabel ? ('Add Manual Booking - ' + slotLabel) : 'Add Manual Booking';
        if (slotLabelEl) slotLabelEl.textContent = slotLabel || '';
        if (dateInput) dateInput.value = slotDate || '';
        if (form) form.reset();
        if (dateInput) dateInput.value = slotDate || '';

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
      }

      function showToast(message, variant) {
        const toastEl = document.getElementById('globalToast');
        if (!toastEl || typeof bootstrap === 'undefined' || !bootstrap.Toast) {
          return;
        }

        const bodyEl = toastEl.querySelector('.toast-body');
        if (bodyEl) bodyEl.textContent = String(message || '');

        toastEl.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-warning', 'text-bg-info', 'text-bg-primary');
        const v = String(variant || 'success');
        if (v === 'danger') toastEl.classList.add('text-bg-danger');
        else if (v === 'warning') toastEl.classList.add('text-bg-warning');
        else if (v === 'info') toastEl.classList.add('text-bg-info');
        else if (v === 'primary') toastEl.classList.add('text-bg-primary');
        else toastEl.classList.add('text-bg-success');

        const inst = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3500 });
        inst.show();
      }

      async function submitManualBooking() {
        const alertBox = document.getElementById('manualBookingAlert');
        const form = document.getElementById('manualBookingForm');
        if (!form) return;

        const submitBtn = document.getElementById('manualBookingSubmit');
        if (submitBtn && submitBtn.disabled) return;
        const submitBtnHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Adding...';
        }

        const fd = new FormData(form);
        fd.append('action', 'add_manual_booking');
        fd.append('csrf_token', '<?= htmlspecialchars($csrfToken) ?>');

        if (alertBox) alertBox.innerHTML = '';

        try {
          const res = await fetch('list_guests.php', {
            method: 'POST',
            body: fd,
            cache: 'no-store',
            credentials: 'same-origin'
          });
          const rawText = await res.text();
          let data = null;
          try {
            data = rawText ? JSON.parse(rawText) : null;
          } catch (_) {
            data = null;
          }

          if (!res.ok || !data || !data.ok) {
            let msg = (data && data.message) ? String(data.message) : 'Failed to save manual booking.';
            if (!data && rawText) {
              const snippet = String(rawText).replace(/</g, '&lt;').replace(/>/g, '&gt;').slice(0, 800);
              msg += '<div class="mt-2"><div class="small text-muted">Server response:</div><pre class="small mb-0" style="white-space:pre-wrap">' + snippet + '</pre></div>';
            }
            if (alertBox) alertBox.innerHTML = '<div class="alert alert-danger">' + msg + '</div>';
            return;
          }

          const bookingRef = String((data && data.booking_reference) ? data.booking_reference : '');
          if (alertBox) {
            const msg = bookingRef ? ('Successfully added booking: ' + bookingRef) : 'Successfully added booking.';
            alertBox.innerHTML = '<div class="alert alert-success">' + msg + '</div>';
          }

          showToast(bookingRef ? ('Successfully added booking: ' + bookingRef) : 'Successfully added booking.', 'success');

          const modalEl = document.getElementById('manualBookingModal');
          if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
          }

          const slotDate = String(fd.get('slot_date') || '');
          const slotLabel = String(document.getElementById('manualSlotLabel')?.textContent || '');
          const detailsModalEl = document.getElementById('guestDetailsModal');
          if (detailsModalEl && bootstrap?.Modal) {
            // If details modal is open, reload it.
            const inst = bootstrap.Modal.getInstance(detailsModalEl);
            if (inst) {
              loadGuestDetails(slotDate, slotLabel, currentGuestPaymentStatus);
            }
          }
        } catch (e) {
          if (alertBox) alertBox.innerHTML = '<div class="alert alert-danger">Failed to save manual booking.</div>';
        } finally {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = submitBtnHtml;
          }
        }
      }

      function openAddPaxModal(bookingRef, name, slotDate, hasAtm) {
        const modalEl = document.getElementById('addPaxModal');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;

        const alertBox = document.getElementById('addPaxAlert');
        const form = document.getElementById('addPaxForm');
        const refEl = document.getElementById('addPaxBookingRef');
        const slotEl = document.getElementById('addPaxSlotDate');
        const labelRef = document.getElementById('addPaxBookingLabel');
        const labelName = document.getElementById('addPaxNameLabel');

        if (alertBox) alertBox.innerHTML = '';
        if (form) form.reset();
        if (refEl) refEl.value = bookingRef || '';
        if (slotEl) slotEl.value = slotDate || '';
        if (labelRef) labelRef.textContent = bookingRef || '';
        if (labelName) labelName.textContent = name || '';

        const atmWrap = document.getElementById('addPaxAtmWrap');
        const atmInput = form ? form.querySelector('input[name="add_quantity_atm"]') : null;
        if (atmWrap) {
          const showAtm = String(hasAtm || '') === '1';
          atmWrap.style.display = showAtm ? '' : 'none';
          if (!showAtm && atmInput) {
            atmInput.value = '0';
          }
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
      }

      async function submitAddPax() {
        const alertBox = document.getElementById('addPaxAlert');
        const form = document.getElementById('addPaxForm');
        if (!form) return;

        const submitBtn = document.getElementById('addPaxSubmit');
        if (submitBtn && submitBtn.disabled) return;
        const submitBtnHtml = submitBtn ? submitBtn.innerHTML : '';

        const proofInput = form.querySelector('input[type="file"][name="add_payment_proof"]');
        if (!proofInput || !proofInput.files || proofInput.files.length === 0) {
          if (alertBox) alertBox.innerHTML = '<div class="alert alert-danger">Please upload proof.</div>';
          return;
        }

        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Saving...';
        }

        const fd = new FormData(form);
        fd.append('action', 'add_pax');
        fd.append('csrf_token', '<?= htmlspecialchars($csrfToken) ?>');

        if (alertBox) alertBox.innerHTML = '';

        try {
          const res = await fetch('list_guests.php', {
            method: 'POST',
            body: fd,
            cache: 'no-store',
            credentials: 'same-origin'
          });
          const data = await res.json();
          if (!res.ok || !data || !data.ok) {
            const msg = (data && data.message) ? data.message : 'Failed to update booking.';
            if (alertBox) alertBox.innerHTML = '<div class="alert alert-danger">' + msg + '</div>';
            return;
          }

          const pageAlert = document.getElementById('pageAlert');
          if (pageAlert) {
            pageAlert.innerHTML = '<div class="alert alert-success alert-dismissible fade show mb-0" role="alert">Successfully added pax.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
          }

          const modalEl = document.getElementById('addPaxModal');
          if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
          }

          const detailsModalEl = document.getElementById('guestDetailsModal');
          if (detailsModalEl && bootstrap?.Modal) {
            const inst = bootstrap.Modal.getInstance(detailsModalEl);
            if (inst) {
              loadGuestDetails(currentGuestSlotDate, currentGuestSlotLabel, currentGuestPaymentStatus);
            }
          }
        } catch (e) {
          if (alertBox) alertBox.innerHTML = '<div class="alert alert-danger">Failed to update booking.</div>';
        } finally {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = submitBtnHtml;
          }
        }
      }

      window.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('click', function (e) {
          const viewBtn = e.target && e.target.closest ? e.target.closest('.js-view-date') : null;
          if (viewBtn) {
            const slotDate = viewBtn.getAttribute('data-slot-date') || '';
            const slotLabel = viewBtn.getAttribute('data-slot-label') || '';
            const modalEl = document.getElementById('guestDetailsModal');
            if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
              const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
              modal.show();
              loadGuestDetails(slotDate, slotLabel, 'PAID');
            }
            return;
          }

          const addBtn = e.target && e.target.closest ? e.target.closest('.js-add-manual') : null;
          if (addBtn) {
            const slotDate = addBtn.getAttribute('data-slot-date') || '';
            const slotLabel = addBtn.getAttribute('data-slot-label') || '';
            openManualModal(slotDate, slotLabel);
          }

          const addPaxBtn = e.target && e.target.closest ? e.target.closest('.js-add-pax') : null;
          if (addPaxBtn) {
            const bookingRef = addPaxBtn.getAttribute('data-booking-ref') || '';
            const name = addPaxBtn.getAttribute('data-name') || '';
            const slotDate = addPaxBtn.getAttribute('data-slot-date') || '';
            const hasAtm = addPaxBtn.getAttribute('data-has-atm') || '0';
            openAddPaxModal(bookingRef, name, slotDate, hasAtm);
          }
        });

        const manualBtn = document.getElementById('manualBookingBtn');
        if (manualBtn) {
          manualBtn.addEventListener('click', function () {
            openManualModal('', '');
          });
        }

        const manualSubmit = document.getElementById('manualBookingSubmit');
        if (manualSubmit) {
          manualSubmit.addEventListener('click', function () {
            submitManualBooking();
          });
        }

        const detailsPrintBtn = document.getElementById('guestDetailsPrintBtn');
        if (detailsPrintBtn) {
          detailsPrintBtn.addEventListener('click', function () {
            printGuestDetailsModal();
          });
        }

        const addPaxSubmit = document.getElementById('addPaxSubmit');
        if (addPaxSubmit) {
          addPaxSubmit.addEventListener('click', function () {
            submitAddPax();
          });
        }

      });

      window.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('confirmLogoutBtn');
        const form = document.getElementById('logoutForm');
        if (!btn || !form) return;
        btn.addEventListener('click', () => form.submit());
      });
    </script>

    <div id="pageAlert" class="position-fixed top-0 end-0 p-3" style="z-index: 2000; max-width: 360px;"></div>

    <div class="modal fade" id="guestDetailsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title" id="guestDetailsTitle"><i class="bi bi-people me-2" style="color:#d8b45c;"></i>Name List</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="guestDetailsPrint" style="background: #fff9ed;">
            <div id="guestDetailsBody"></div>
          </div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-outline-secondary" id="guestDetailsPrintBtn"><i class="bi bi-printer me-2"></i>Print</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="manualBookingModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title" id="manualBookingTitle"><i class="bi bi-plus-circle me-2" style="color:#d8b45c;"></i>Add Manual Booking</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <div id="manualBookingAlert"></div>
            <form id="manualBookingForm">
              <input type="hidden" name="slot_date" id="manualSlotDate" value="">
              <div class="mb-3">
                <div class="text-muted small">Date: <span class="fw-semibold" id="manualSlotLabel"></span></div>
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Name</label>
                  <input type="text" class="form-control" name="full_name" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input type="text" class="form-control" name="phone">
                </div>
                <div class="col-12">
                  <label class="form-label">Payment Proof (JPG/PNG/PDF)</label>
                  <input type="file" class="form-control" name="payment_proof" accept="image/jpeg,image/png,application/pdf" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Remark</label>
                  <input type="text" class="form-control" name="remark">
                </div>
                <div class="col-12">
                  <label class="form-label">Table No</label>
                  <input type="text" class="form-control" name="table_no" placeholder="(Optional)">
                </div>
              </div>

              <hr class="my-3">

              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">STAFF BLANKET</label>
                  <input type="number" min="0" class="form-control" name="staff_blanket_qty" value="0">
                </div>
                <div class="col-md-4">
                  <label class="form-label">LIVING IN</label>
                  <input type="number" min="0" class="form-control" name="living_in_qty" value="0">
                </div>
                <div class="col-md-4">
                  <label class="form-label">AJK</label>
                  <input type="number" min="0" class="form-control" name="ajk_qty" value="0">
                </div>
              </div>

              <hr class="my-3">

              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">AWAM</label>
                  <input type="number" min="0" class="form-control" name="quantity_dewasa" value="0">
                </div>
                <div class="col-md-3">
                  <label class="form-label">ATM Type</label>
                  <select class="form-select" name="atm_type">
                    <option value="" selected>-</option>
                    <option value="ATM-TDM">ATM-TDM</option>
                    <option value="ATM_TLDM">ATM_TLDM</option>
                    <option value="ATM-TUDM">ATM-TUDM</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">ATM PAX</label>
                  <input type="number" min="0" class="form-control" name="quantity_atm" value="0">
                </div>
                <div class="col-md-3">
                  <label class="form-label">KANAK-KANAK</label>
                  <input type="number" min="0" class="form-control" name="quantity_kanak" value="0">
                </div>
                <div class="col-md-3">
                  <label class="form-label">INFANT</label>
                  <input type="number" min="0" class="form-control" name="quantity_kanak_foc" value="0">
                </div>
                <div class="col-md-3">
                  <label class="form-label">WARGA EMAS</label>
                  <input type="number" min="0" class="form-control" name="quantity_warga_emas" value="0">
                </div>
                <div class="col-md-3">
                  <label class="form-label">FREE VOUCHER</label>
                  <input type="number" min="0" class="form-control" name="free_voucher_qty" value="0">
                </div>
              </div>

            </form>
          </div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" id="manualBookingSubmit"><i class="bi bi-check2-circle me-2"></i>Add Booking</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="addPaxModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title" id="addPaxTitle"><i class="bi bi-pencil-square me-2" style="color:#d8b45c;"></i>Add Pax</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <div id="addPaxAlert"></div>
            <form id="addPaxForm" enctype="multipart/form-data">
              <input type="hidden" name="booking_reference" id="addPaxBookingRef" value="">
              <input type="hidden" name="slot_date" id="addPaxSlotDate" value="">
              <div class="mb-3">
                <div class="text-muted small">Booking: <span class="fw-semibold" id="addPaxBookingLabel"></span></div>
                <div class="text-muted small">Name: <span class="fw-semibold" id="addPaxNameLabel"></span></div>
              </div>

              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">Add AWAM</label>
                  <input type="number" min="0" class="form-control" name="add_quantity_dewasa" value="0">
                </div>
                <div class="col-md-3" id="addPaxAtmWrap">
                  <label class="form-label">Add ATM</label>
                  <input type="number" min="0" class="form-control" name="add_quantity_atm" value="0">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Add STAFF BLANKET</label>
                  <input type="number" min="0" class="form-control" name="add_staff_blanket_qty" value="0">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Add LIVING IN</label>
                  <input type="number" min="0" class="form-control" name="add_living_in_qty" value="0">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Add AJK</label>
                  <input type="number" min="0" class="form-control" name="add_ajk_qty" value="0">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Add FREE VOUCHER</label>
                  <input type="number" min="0" class="form-control" name="add_free_voucher_qty" value="0">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Add KANAK-KANAK</label>
                  <input type="number" min="0" class="form-control" name="add_quantity_kanak" value="0">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Add INFANT</label>
                  <input type="number" min="0" class="form-control" name="add_quantity_kanak_foc" value="0">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Add WARGA EMAS</label>
                  <input type="number" min="0" class="form-control" name="add_quantity_warga_emas" value="0">
                </div>
                <div class="col-12">
                  <label class="form-label">Remark</label>
                  <textarea class="form-control" name="add_remark" rows="2" placeholder="(Optional)"></textarea>
                </div>
                <div class="col-12">
                  <label class="form-label">Add Proof (JPG/PNG/PDF)</label>
                  <input type="file" class="form-control" name="add_payment_proof" accept="image/jpeg,image/png,application/pdf">
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="addPaxSubmit"><i class="bi bi-check2-circle me-2"></i>Save</button>
          </div>
        </div>
      </div>
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

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
      <div id="globalToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body"></div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    </div>
  </body>
</html>
<?php
$mysqli->close();
?>
