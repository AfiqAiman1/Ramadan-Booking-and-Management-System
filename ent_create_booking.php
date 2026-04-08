<?php
// ent_create_booking.php - create an ENT complimentary booking (RM0, auto PAID)

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/ent_auth.php';

require_ent_roles(['ent_admin']);

function render_error_page(array $errors): void
{
    http_response_code(400);
    $safeErrors = array_filter(array_map('strval', $errors));
    ?>
    <!doctype html>
    <html lang="en">
      <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Booking Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="assets/css/main.css" rel="stylesheet">
      </head>
      <body>
        <main class="container py-5">
          <div class="alert alert-danger">
            <div class="fw-semibold mb-2">Unable to process booking:</div>
            <ul class="mb-0">
              <?php foreach ($safeErrors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <a class="btn btn-secondary" href="ent_index.php">Back</a>
        </main>
      </body>
    </html>
    <?php
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ent_index.php');
    exit;
}

function sanitize_int($value): int
{
    return max(0, filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]));
}

function generate_booking_reference(string $prefix): string
{
    $prefix = preg_replace('/[^A-Za-z0-9\-]/', '', $prefix);
    $prefix = trim($prefix);
    if ($prefix === '') {
        $prefix = 'ENT' . substr(date('Y'), -2);
    }
    return $prefix . '-' . random_int(10000, 99999);
}

$fullName = trim((string) ($_SESSION['ent_full_name'] ?? ''));
$phone = trim((string) ($_SESSION['ent_phone'] ?? ''));

$remarkSession = trim((string) ($_SESSION['ent_remark'] ?? ''));
$remarkPost = trim((string) ($_POST['remark'] ?? ''));
$remark = $remarkPost !== '' ? $remarkPost : $remarkSession;

$slotDate = trim((string) ($_POST['slot_date'] ?? ''));
$qtyDewasa = sanitize_int($_POST['quantity_dewasa'] ?? 0);
$qtyKanak = sanitize_int($_POST['quantity_kanak'] ?? 0);
$qtyKanakFoc = sanitize_int($_POST['quantity_kanak_foc'] ?? 0);
$qtyWarga = sanitize_int($_POST['quantity_warga_emas'] ?? 0);
$qtyAtm = 0;

$totalTickets = $qtyDewasa + $qtyKanak + $qtyKanakFoc + $qtyWarga + $qtyAtm;

$compQty = $totalTickets;

$freeQtyDewasa = 0;
$freeQtyKanak = 0;
$freeQtyKanakFoc = 0;
$freeQtyWarga = 0;
$freeQtyAtm = 0;

$qtyDewasa = 0;
$qtyKanak = 0;
$qtyKanakFoc = 0;
$qtyWarga = 0;

$errors = [];
if ($fullName === '' || $phone === '') {
    $errors[] = 'Missing name or phone. Please start from ENT home page.';
}
if ($slotDate === '') {
    $errors[] = 'Booking date is required.';
}
if ($totalTickets <= 0) {
    $errors[] = 'Please select at least one ticket.';
}

$mysqli = null;
try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
    ensure_booking_slots_schema($mysqli);
    ensure_global_settings_schema($mysqli);

    $settings = load_global_settings($mysqli);

    $bookingOpen = (($settings['booking_status'] ?? 'OPEN') === 'OPEN');
    if (!$bookingOpen) {
        $errors[] = 'Booking is currently closed.';
    }

    $eventStart = (string) ($settings['event_start_date'] ?? '');
    $eventEnd = (string) ($settings['event_end_date'] ?? '');
    if ($eventStart !== '' && $eventEnd !== '') {
        $slotDt = DateTime::createFromFormat('Y-m-d', $slotDate);
        if (!$slotDt) {
            $errors[] = 'Invalid booking date.';
        } else {
            $s = DateTime::createFromFormat('Y-m-d', $eventStart);
            $e = DateTime::createFromFormat('Y-m-d', $eventEnd);
            if ($s && $e) {
                if ($slotDt < $s || $slotDt > $e) {
                    $errors[] = 'Selected booking date is outside the allowed range.';
                }
            }
        }
    }

    $maxTickets = (int) ($settings['max_tickets_per_booking'] ?? 20);
    if ($maxTickets > 0 && $totalTickets > $maxTickets) {
        $errors[] = 'Maximum tickets per booking is ' . $maxTickets . '.';
    }

    if (!$errors) {
        $slotStmt = $mysqli->prepare('SELECT max_capacity, booked_count FROM booking_slots WHERE slot_date = ?');
        if (!$slotStmt) {
            $errors[] = 'Unable to validate the selected booking date.';
        } else {
            $slotStmt->bind_param('s', $slotDate);
            $slotStmt->execute();
            $slotRes = $slotStmt->get_result();
            $slotRow = $slotRes ? $slotRes->fetch_assoc() : null;
            if ($slotRes) {
                $slotRes->free();
            }
            $slotStmt->close();

            if (!$slotRow) {
                $errors[] = 'Selected booking date is unavailable.';
            } else {
                $remaining = (int) $slotRow['max_capacity'] - (int) $slotRow['booked_count'];
                if ($remaining < $totalTickets) {
                    $errors[] = 'Selected booking date is full. Please choose another date.';
                }
            }
        }
    }

    if ($errors) {
        render_error_page($errors);
        exit;
    }

    $eventYear = (int) ($settings['event_year'] ?? (int) date('Y'));
    $prefix = 'ENT' . str_pad((string) ($eventYear % 100), 2, '0', STR_PAD_LEFT);

    $bookingRef = '';
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

    if ($bookingRef === '') {
        render_error_page(['Unable to generate booking reference. Please try again.']);
        exit;
    }

    $paymentStatus = 'PAID';
    $paymentMethod = 'ENT';
    $checkinStatus = 'Not Checked';
    $totalPrice = 0.00;

    $sql = 'INSERT INTO bookings (
                booking_reference, full_name, phone, military_no, email, remark, slot_date,
                quantity_dewasa, quantity_kanak, quantity_kanak_foc, quantity_warga_emas, quantity_atm,
                free_quantity_dewasa, free_quantity_kanak, free_quantity_kanak_foc, free_quantity_warga_emas, free_quantity_atm,
                comp_qty,
                total_price, payment_proof, payment_status, payment_method, checkin_status, created_at, paid_at
            ) VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, NOW(), NOW())';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        render_error_page(['Failed to prepare database statement.']);
        exit;
    }

    $stmt->bind_param(
        'sssssiiiiiiiiiidisss',
        $bookingRef,
        $fullName,
        $phone,
        $remark,
        $slotDate,
        $qtyDewasa,
        $qtyKanak,
        $qtyKanakFoc,
        $qtyWarga,
        $qtyAtm,
        $freeQtyDewasa,
        $freeQtyKanak,
        $freeQtyKanakFoc,
        $freeQtyWarga,
        $freeQtyAtm,
        $compQty,
        $totalPrice,
        $paymentStatus,
        $paymentMethod,
        $checkinStatus
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        render_error_page(['Unable to save booking: ' . htmlspecialchars($err)]);
        exit;
    }
    $stmt->close();

    $slotUpdate = $mysqli->prepare('UPDATE booking_slots SET booked_count = booked_count + ? WHERE slot_date = ?');
    if ($slotUpdate) {
        $slotUpdate->bind_param('is', $compQty, $slotDate);
        $slotUpdate->execute();
        $slotUpdate->close();
    }

    $mysqli->close();

    header('Location: public/booking_reference.php?ref=' . urlencode($bookingRef) . '&new=1');
    exit;
} catch (Throwable $e) {
    if ($mysqli instanceof mysqli) {
        $mysqli->close();
    }
    render_error_page(['Database connection failed: ' . $e->getMessage()]);
    exit;
}
