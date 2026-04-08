<?php
// create_booking.php - create a pending booking without requiring payment proof

require_once __DIR__ . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: public/index.php');
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
        $prefix = 'WP' . substr(date('Y'), -2);
    }
    return $prefix . '-' . random_int(10000, 99999);
}

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$visitorRole = strtoupper(trim((string) ($_POST['visitor_role'] ?? '')));
$visitorRoleMain = strtoupper(trim((string) ($_POST['visitor_role_main'] ?? '')));
$militaryNo = trim((string) ($_POST['military_no'] ?? ''));
$remark = trim((string) ($_POST['remark'] ?? ''));
$slotDate = trim((string) ($_POST['slot_date'] ?? ''));
$qtyDewasa = sanitize_int($_POST['quantity_dewasa'] ?? 0);
$qtyKanak = sanitize_int($_POST['quantity_kanak'] ?? 0);
$qtyKanakFoc = sanitize_int($_POST['quantity_kanak_foc'] ?? 0);
$qtyWarga = sanitize_int($_POST['quantity_warga_emas'] ?? 0);
$qtyStaffBlanket = sanitize_int($_POST['staff_blanket_qty'] ?? 0);
$qtyLivingIn = sanitize_int($_POST['living_in_qty'] ?? 0);
$qtyAjk = sanitize_int($_POST['ajk_qty'] ?? 0);
$qtyFreeVoucher = sanitize_int($_POST['free_voucher_qty'] ?? 0);
$qtyComp = sanitize_int($_POST['comp_qty'] ?? 0);

$qtyAtm = 0;
if ($visitorRoleMain === 'ATM') {
    $qtyAtm = $qtyWarga;
    $qtyWarga = 0;
}

$totalTickets = $qtyDewasa + $qtyKanak + $qtyKanakFoc + $qtyWarga + $qtyAtm
    + $qtyStaffBlanket + $qtyLivingIn + $qtyAjk + $qtyFreeVoucher + $qtyComp;

$errors = [];
if ($fullName === '') {
    $errors[] = 'Full name is required.';
}
if ($phone === '') {
    $errors[] = 'Phone number is required.';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address.';
}
if ($visitorRole !== '' && $visitorRole !== 'AWAM' && $militaryNo === '') {
    $errors[] = 'Military number is required.';
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

    $allowSameDay = !empty($settings['allow_same_day_booking']);
    if (!$allowSameDay && $slotDate !== '') {
        $today = (new DateTime('today'))->format('Y-m-d');
        if ($slotDate === $today) {
            $errors[] = 'Same-day booking is not allowed. Please choose another date.';
        }
    }

    $prices = load_event_settings_prices($mysqli);
    $prices = apply_special_prices($mysqli, $prices, $slotDate);
    $calculatedTotal = ($qtyDewasa * $prices['dewasa']) + ($qtyKanak * $prices['kanak']) + ($qtyWarga * $prices['warga']) + ($qtyAtm * $prices['warga']);
    $calculatedTotal = round($calculatedTotal, 2);

    if ($calculatedTotal <= 0) {
        $errors[] = 'Invalid total amount.';
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
    $prefix = 'WP' . str_pad((string) ($eventYear % 100), 2, '0', STR_PAD_LEFT);

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

    $paymentStatus = 'PENDING';
    $checkinStatus = 'Not Checked';

    $sql = 'INSERT INTO bookings (
                booking_reference, full_name, phone, military_no, email, remark, slot_date,
                quantity_dewasa, quantity_kanak, quantity_kanak_foc, quantity_warga_emas, quantity_atm,
                staff_blanket_qty, living_in_qty, ajk_qty, free_voucher_qty, comp_qty,
                total_price, payment_proof, payment_status, checkin_status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, NOW())';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        render_error_page(['Failed to prepare database statement.']);
        exit;
    }

    $stmt->bind_param(
        'sssssssiiiiiiiiiidss',
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
        $qtyStaffBlanket,
        $qtyLivingIn,
        $qtyAjk,
        $qtyFreeVoucher,
        $qtyComp,
        $calculatedTotal,
        $paymentStatus,
        $checkinStatus
    );

    if (!$stmt->execute()) {
        $stmt->close();
        render_error_page(['Unable to save booking: ' . htmlspecialchars($stmt->error)]);
        exit;
    }
    $stmt->close();

    $slotUpdate = $mysqli->prepare('UPDATE booking_slots SET booked_count = booked_count + ? WHERE slot_date = ?');
    if ($slotUpdate) {
        $slotUpdate->bind_param('is', $totalTickets, $slotDate);
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
    render_error_page(['Database connection failed.']);
    exit;
}

function render_error_page(array $errors): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Booking Error</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"><link rel="stylesheet" href="assets/css/main.css">';
    echo '</head><body class="bg-light"><div class="container py-5"><div class="col-lg-8 mx-auto"><div class="card border-danger">';
    echo '<div class="card-body"><h1 class="h4 text-danger mb-3">We could not complete your booking</h1><ul class="text-danger">';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul><a href="index.php" class="btn btn-outline-danger mt-3">Back to Booking Form</a></div></div></div></div></body></html>';
}
