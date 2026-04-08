<?php
// save_booking.php - handles payment proof upload and booking persistence

require_once __DIR__ . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: public/index.php');
    exit;
}

function sanitize_int($value): int
{
    return max(0, filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]));
}

function sanitize_amount($value): float
{
    $amount = filter_var($value, FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0]]);
    return max(0, (float) $amount);
}

function generate_booking_reference(string $prefix): string
{
    $prefix = preg_replace('/[^A-Za-z0-9\-]/', '', $prefix);
    $prefix = trim($prefix);
    if ($prefix === '') {
        $prefix = 'WP' . substr(date('Y'), -2);
    }
    return $prefix . '-' . random_int(10000, 99999);
}

$fullName      = trim($_POST['full_name'] ?? '');
$phone         = trim($_POST['phone'] ?? '');
$email         = trim($_POST['email'] ?? '');
$visitorRole   = strtoupper(trim((string) ($_POST['visitor_role'] ?? '')));
$visitorRoleMain = strtoupper(trim((string) ($_POST['visitor_role_main'] ?? '')));
$militaryNo    = trim($_POST['military_no'] ?? '');
$remark        = trim($_POST['remark'] ?? '');
$slotDate      = trim($_POST['slot_date'] ?? '');
$qtyDewasa     = sanitize_int($_POST['quantity_dewasa'] ?? 0);
$qtyKanak      = sanitize_int($_POST['quantity_kanak'] ?? 0);
$qtyKanakFoc   = sanitize_int($_POST['quantity_kanak_foc'] ?? 0);
$qtyWarga      = sanitize_int($_POST['quantity_warga_emas'] ?? 0);
$totalAmount   = sanitize_amount($_POST['total_amount'] ?? 0);
$focDeclaration = (string) ($_POST['foc_declaration'] ?? '');

$qtyAtm = 0;
if ($visitorRoleMain === 'ATM') {
    $qtyAtm = $qtyWarga;
    $qtyWarga = 0;
}

$totalTickets  = $qtyDewasa + $qtyKanak + $qtyKanakFoc + $qtyWarga + $qtyAtm;

$errors = [];
if ($fullName === '') {
    $errors[] = 'Full name is required.';
}
if ($phone === '') {
    $errors[] = 'Phone number is required.';
}
if ($visitorRole !== '' && $visitorRole !== 'AWAM' && $militaryNo === '') {
    $errors[] = 'Military number is required.';
}
if ($slotDate === '') {
    $errors[] = 'Booking date is required.';
}
if ($totalTickets <= 0) {
    $errors[] = 'At least one ticket quantity must be greater than zero.';
}

if ($qtyKanakFoc > 0 && $focDeclaration !== '1') {
    $errors[] = 'You must confirm the declaration for FOC children under 7 years old.';
}
if ($totalAmount <= 0) {
    $errors[] = 'Invalid total amount.';
}

$mysqli = null;
$slotRow = null;

if (!$errors) {
    try {
        $mysqli = db_connect();
        ensure_bookings_schema($mysqli);
        ensure_booking_slots_schema($mysqli);
    } catch (Throwable $e) {
        $errors[] = 'Database connection failed.';
    }
}

// Validate requested slot against database capacity (Ramadan internship note)
if (!$errors) {
    $slotStmt = $mysqli->prepare('SELECT id, slot_date, max_capacity, booked_count FROM booking_slots WHERE slot_date = ?');
    if (!$slotStmt) {
        $errors[] = 'Failed to validate the selected booking date.';
    } else {
        $slotStmt->bind_param('s', $slotDate);
        $slotStmt->execute();
        $slotResult = $slotStmt->get_result();
        $slotRow = $slotResult->fetch_assoc();
        $slotResult->free();
        $slotStmt->close();

        if (!$slotRow) {
            $errors[] = 'Selected booking date is unavailable.';
        } else {
            $remainingCapacity = (int)$slotRow['max_capacity'] - (int)$slotRow['booked_count'];
            if ($remainingCapacity < $totalTickets) {
                $errors[] = 'Selected booking date is full. Please choose another date.';
            }
        }
    }
}

if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = 'Payment proof is required.';
}

$fileInfo = $_FILES['payment_proof'] ?? null;
$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'application/pdf' => 'pdf'
];
$allowedExtensions = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'pdf'  => 'application/pdf'
];
$maxFileSize = 5 * 1024 * 1024; // 5 MB
$relativePath = '';
$proofHash = '';
$duplicateProofRef = '';

if ($fileInfo && $fileInfo['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Error uploading payment proof.';
}

if (!$errors && $fileInfo) {
    if ($fileInfo['size'] > $maxFileSize) {
        $errors[] = 'Payment proof must be less than 5MB.';
    } else {
        $detectedExtension = null;

        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($fileInfo['tmp_name']);
            if (isset($allowedMime[$mimeType])) {
                $detectedExtension = $allowedMime[$mimeType];
            }
        } else {
            $originalExt = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
            if (isset($allowedExtensions[$originalExt])) {
                // normalise jpeg extension to jpg
                $detectedExtension = $originalExt === 'jpeg' ? 'jpg' : $originalExt;
            }
        }

        if ($detectedExtension === null) {
            $errors[] = 'Unsupported file type. Only JPG, PNG, and PDF are allowed.';
        } else {
            $uploadDir = __DIR__ . '/uploads/payment_proof/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
                $errors[] = 'Unable to create upload directory.';
            } else {
                $newFileName = 'PENDING_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $detectedExtension;
                $targetPath = $uploadDir . $newFileName;
                if (!move_uploaded_file($fileInfo['tmp_name'], $targetPath)) {
                    $errors[] = 'Failed to save uploaded file.';
                } else {
                    $relativePath = 'uploads/payment_proof/' . $newFileName;
                    $proofHash = (string) (hash_file('sha256', $targetPath) ?: '');
                }
            }
        }
    }
}

if ($errors) {
    if ($mysqli instanceof mysqli) {
        $mysqli->close();
    }
    render_error_page($errors);
    exit;
}

$bookingRef = '';
try {
    ensure_global_settings_schema($mysqli);
    $settings = load_global_settings($mysqli);
    $eventYear = (int) ($settings['event_year'] ?? (int) date('Y'));
    $prefix = 'WP' . str_pad((string) ($eventYear % 100), 2, '0', STR_PAD_LEFT);

    for ($i = 0; $i < 5; $i++) {
        $bookingRef = generate_booking_reference($prefix);
        $check = $mysqli->prepare('SELECT 1 FROM bookings WHERE booking_reference = ? LIMIT 1');
        if ($check) {
            $check->bind_param('s', $bookingRef);
            $check->execute();
            $res = $check->get_result();
            $exists = $res && $res->num_rows > 0;
            if ($res) {
                $res->free();
            }
            $check->close();
            if (!$exists) {
                break;
            }
        }
    }
} catch (Throwable $e) {
    $bookingRef = '';
}

if ($bookingRef === '') {
    $mysqli->close();
    render_error_page(['Unable to generate booking reference. Please try again.']);
    exit;
}

// Recalculate server-side total for safety
$prices = load_event_settings_prices($mysqli);
$prices = apply_special_prices($mysqli, $prices, $slotDate);
$calculatedTotal = ($qtyDewasa * $prices['dewasa']) + ($qtyKanak * $prices['kanak']) + ($qtyWarga * $prices['warga']) + ($qtyAtm * $prices['warga']);
$calculatedTotal = round($calculatedTotal, 2);
if ($calculatedTotal <= 0) {
    $mysqli->close();
    render_error_page(['Invalid total amount.']);
    exit;
}

$sql = 'INSERT INTO bookings (
            booking_reference, full_name, phone, military_no, email, remark, slot_date,
            quantity_dewasa, quantity_kanak, quantity_kanak_foc, quantity_warga_emas, quantity_atm,
            total_price, payment_proof, payment_proof_hash, payment_status, checkin_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    render_error_page(['Failed to prepare database statement.']);
    exit;
}

$paymentStatus = 'PENDING';
$checkinStatus = 'Not Checked';
$stmt->bind_param(
    'sssssssiiiiidssss',
    $bookingRef,
    $fullName,
    $phone,
    $militaryNo,
    $email,
    $remark,
    $slotDate,
    $qtyDewasa,
    $qtyKanak,
    $qtyKanakFoc,
    $qtyWarga,
    $qtyAtm,
    $calculatedTotal,
    $relativePath,
    $proofHash,
    $paymentStatus,
    $checkinStatus
);

if (!$stmt->execute()) {
    render_error_page(['Unable to save booking: ' . htmlspecialchars($stmt->error)]);
    $stmt->close();

    if ($mysqli instanceof mysqli) {
        $mysqli->close();
    }
    exit;
}

$stmt->close();

if ($proofHash !== '') {
    $dupStmt = $mysqli->prepare('SELECT booking_reference FROM bookings WHERE payment_proof_hash = ? AND booking_reference <> ? LIMIT 1');
    if ($dupStmt) {
        $dupStmt->bind_param('ss', $proofHash, $bookingRef);
        $dupStmt->execute();
        $dupRes = $dupStmt->get_result();
        $dupRow = $dupRes ? $dupRes->fetch_assoc() : null;
        if ($dupRes) {
            $dupRes->free();
        }
        $dupStmt->close();

        if ($dupRow && !empty($dupRow['booking_reference'])) {
            $duplicateProofRef = (string) $dupRow['booking_reference'];
        }
    }
}

admin_create_notification(
    $mysqli,
    'proof_uploaded',
    'New payment proof uploaded for ' . $bookingRef . '.',
    $bookingRef,
    ['slot_date' => $slotDate]
);

if ($duplicateProofRef !== '') {
    admin_create_notification(
        $mysqli,
        'proof_duplicate',
        'Duplicate payment proof detected for ' . $bookingRef . ' (matches ' . $duplicateProofRef . ').',
        $bookingRef,
        ['duplicate_booking_reference' => $duplicateProofRef]
    );
}

$slotUpdate = $mysqli->prepare('UPDATE booking_slots SET booked_count = booked_count + ? WHERE slot_date = ?');
if ($slotUpdate) {
    $slotUpdate->bind_param('is', $totalTickets, $slotDate);
    $slotUpdate->execute();
    $slotUpdate->close();
}

$mysqli->close();

header('Location: public/booking_reference.php?ref=' . urlencode($bookingRef) . '&new=1');
exit;

function render_error_page(array $errors): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Booking Error</title><link rel="icon" type="image/png" href="assets/img/Logo%20ATM.png"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"><link rel="stylesheet" href="assets/css/main.css">';
    echo '</head><body class="bg-light"><div class="container py-5"><div class="col-lg-8 mx-auto"><div class="card border-danger">';
    echo '<div class="card-body"><h1 class="h4 text-danger mb-3">We could not complete your booking</h1><ul class="text-danger">';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul><a href="public/index.php" class="btn btn-outline-danger mt-3">Back to Booking Form</a></div></div></div></div></body></html>';
}

function render_success_page(string $bookingRef, float $totalAmount, string $fullName, string $eventDate): void
{
    $formattedAmount = number_format($totalAmount, 2);
    $safeRef = htmlspecialchars($bookingRef);
    $safeName = htmlspecialchars($fullName);
    $safeEventDate = htmlspecialchars($eventDate);

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Booking Received</title><link rel="icon" type="image/png" href="assets/img/Logo%20ATM.png"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"><link rel="stylesheet" href="assets/css/main.css">';
    echo '<style>
      body {
        font-family: "Cairo", system-ui, sans-serif;
        background:
          radial-gradient(circle at 10% 10%, rgba(217,180,90,0.18), transparent 55%),
          radial-gradient(circle at 80% 0%, rgba(217,180,90,0.15), transparent 45%),
          linear-gradient(180deg, #08241a 0%, #0f493c 60%, #05150f 100%);
        min-height: 100vh;
        color: #0a231c;
      }
      .summary-card {
        background: #fff8ec;
        border-radius: 1.4rem;
        border: 1px solid rgba(217,180,90,0.35);
        box-shadow: 0 25px 45px rgba(4, 12, 10, 0.35);
      }
      .badge-ref {
        background: #e0c068;
        color: #3d2803;
        border-radius: 999px;
        padding: 0.35rem 1rem;
        font-weight: 600;
      }
      .btn-download {
        background: linear-gradient(130deg, #f6db8a, #cd9b32);
        border: none;
        color: #2b1600;
        font-weight: 600;
      }
      .btn-download:hover {
        filter: brightness(0.97);
        color: #2b1600;
      }
      .lantern {
        color: rgba(246,219,138,0.9);
      }
    </style>';
    echo '</head><body>';
    echo '<div class="container py-5"><div class="col-lg-8 mx-auto">';
    echo '<div class="summary-card p-4 p-md-5 text-center">';
    echo '<div class="mb-3"><span class="badge-ref">Booking Reference</span><p class="h3 mt-2">' . $safeRef . '</p></div>';
    echo '<p class="text-muted mb-4"><i class="bi bi-lamp-fill lantern me-2"></i>Sajian Serantau Negeri Ramadan Buffet</p>';
    echo '<p class="lead mb-1"><strong>Name:</strong> ' . $safeName . '</p>';
    echo '<p class="mb-3"><strong>Event Date:</strong> ' . $safeEventDate . '</p>';
    echo '<div class="alert alert-success"><strong>Total Amount:</strong> RM ' . $formattedAmount . ' — Payment Status: <span class="text-uppercase">Pending Verification</span></div>';
    echo '<p class="text-muted mb-4">Please keep your booking reference handy on the event day. Upload was received and our team will verify it shortly.</p>';
    echo '<div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">';
    echo '<button class="btn btn-download px-4" id="downloadRef" data-ref="' . $safeRef . '" data-name="' . $safeName . '" data-date="' . $safeEventDate . '">Download Booking Reference</button>';
    echo '<a href="index.php" class="btn btn-outline-dark border px-4">Back to Home</a>';
    echo '</div>';
    echo '</div></div></div>';
    echo '<script>
      document.getElementById("downloadRef").addEventListener("click", function () {
        const ref = this.dataset.ref;
        const name = this.dataset.name;
        const date = this.dataset.date;
        const content = `Booking Reference: ${ref}\\nCustomer Name: ${name}\\nEvent Date: ${date}\\nEvent: Sajian Serantau Negeri Buka Puasa Buffet\\nStatus: Pending Verification`;
        const blob = new Blob([content], { type: "text/plain" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = ref + ".txt";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      });
    </script>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';
    echo '</body></html>';
}
