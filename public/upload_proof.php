<?php
// upload_proof.php - manual payment proof flow (show summary + upload proof)

require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
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

$fullName  = trim($_POST['full_name'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$email     = trim($_POST['email'] ?? '');
$visitorRole = strtoupper(trim((string) ($_POST['visitor_role'] ?? '')));
$visitorRoleMain = strtoupper(trim((string) ($_POST['visitor_role_main'] ?? '')));
$militaryNo = trim($_POST['military_no'] ?? '');
$remark = trim($_POST['remark'] ?? '');
$slotDate  = trim($_POST['slot_date'] ?? '');
$qtyDewasa = sanitize_int($_POST['quantity_dewasa'] ?? 0);
$qtyKanak  = sanitize_int($_POST['quantity_kanak'] ?? 0);
$qtyKanakFoc = sanitize_int($_POST['quantity_kanak_foc'] ?? 0);
$qtyWarga  = sanitize_int($_POST['quantity_warga_emas'] ?? 0);
$postedTotal = sanitize_amount($_POST['total_price'] ?? ($_POST['total_amount'] ?? 0));
$focDeclaration = (string) ($_POST['foc_declaration'] ?? '');

$qtyAtm = 0;
if ($visitorRoleMain === 'ATM') {
    $qtyAtm = $qtyWarga;
    $qtyWarga = 0;
}

$totalTickets = $qtyDewasa + $qtyKanak + $qtyKanakFoc + $qtyWarga + $qtyAtm;

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

if ($qtyKanakFoc > 0 && $focDeclaration !== '1') {
    $errors[] = 'Please confirm the declaration for FOC children under 6 years old.';
}

$settings = [];
$paymentInstructions = '';
$paymentMethodName = '';
$paymentBankName = '';
$paymentAccountHolder = '';
$paymentQrPath = '';
$calculatedTotal = 0.0;

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
        $mysqli->close();
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
            $slotRow = $slotRes->fetch_assoc();
            $slotRes->free();
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

    $paymentInstructions = (string) ($settings['payment_instructions'] ?? '');
    $paymentMethodName = (string) ($settings['payment_method_name'] ?? '');
    $paymentBankName = (string) ($settings['payment_bank_name'] ?? '');
    $paymentAccountHolder = (string) ($settings['payment_account_holder'] ?? '');
    $paymentQrPath = (string) ($settings['payment_qr_path'] ?? '');

    $mysqli->close();
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
    $errors[] = 'Unable to load booking summary.';
}

if ($errors) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo implode("\n", $errors);
    exit;
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment - Upload Proof</title>
    <link rel="icon" type="image/png" href="../assets/img/Logo%20ATM.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <style>
      :root {
        --ramadan-green: #0e3e32;
        --ramadan-deep: #092821;
        --ramadan-gold: #d9b45a;
        --ramadan-cream: #fff8ec;
        --text-dark: #0b1e1a;
      }
      body {
        font-family: 'Cairo', system-ui, -apple-system, 'Segoe UI', sans-serif;
        background:
          radial-gradient(circle at 20% 20%, rgba(217,180,90,0.18), transparent 55%),
          radial-gradient(circle at 80% 0%, rgba(217,180,90,0.15), transparent 45%),
          linear-gradient(180deg, var(--ramadan-deep) 0%, var(--ramadan-green) 60%, #051b15 100%);
        min-height: 100vh;
        color: var(--text-dark);
      }
      .page-shell { padding: 3rem 0 4rem; }
      .hero-card {
        background: linear-gradient(135deg, rgba(5, 41, 33, 0.65), rgba(11, 62, 48, 0.9));
        border-radius: 1.5rem;
        border: 1px solid rgba(217,180,90,0.45);
        box-shadow: 0 18px 36px rgba(5, 22, 18, 0.45);
        overflow: hidden;
        position: relative;
      }
      .hero-card h1, .hero-card p { color: #fdf8ec; }
      .icon-decor { color: var(--ramadan-gold); text-shadow: 0 6px 10px rgba(0,0,0,0.45); }
      .content-card {
        background: var(--ramadan-cream);
        border-radius: 1.25rem;
        padding: 2.25rem;
        border: 1px solid rgba(217,180,90,0.35);
        margin-top: -60px;
        position: relative;
        z-index: 2;
        box-shadow: 0 22px 45px rgba(7, 20, 17, 0.25);
      }
      @media (max-width: 576px) {
        .content-card { padding: 1.5rem; }
      }
      .btn-ramadan {
        background: linear-gradient(140deg, #f8d687, #d4a842);
        border: none;
        border-radius: 999px;
        padding: 0.85rem 1.4rem;
        font-weight: 800;
        color: #2d1c01;
      }
      .btn-ramadan:hover { color: #2d1c01; }
      .form-control:focus {
        border-color: var(--ramadan-gold);
        box-shadow: 0 0 0 0.2rem rgba(217,180,90,0.25);
      }
      .summary-box {
        background: rgba(15, 62, 48, 0.06);
        border-radius: 1rem;
        border: 1px solid rgba(31, 122, 77, 0.25);
        padding: 1rem 1.25rem;
      }
    </style>
  </head>
  <body>
    <main class="page-shell">
      <div class="container">
        <div class="hero-card p-4 p-md-5 text-center mb-4">
          <div class="row align-items-center g-4">
            <div class="col-md-3 text-md-end">
              <i class="bi bi-stars display-5 icon-decor d-none d-md-inline"></i>
            </div>
            <div class="col-md-6">
              <p class="text-uppercase fw-semibold letter-spacing-1 text-light mb-1">Ramadan Kareem</p>
              <h1 class="display-6 fw-bold mb-2">Pembayaran</h1>
              <p class="mb-0">Hantar bukti pembayaran untuk mendapatkan tempahan anda.</p>
            </div>
            <div class="col-md-3 text-md-start">
              <i class="bi bi-moon-stars-fill display-5 icon-decor d-none d-md-inline"></i>
            </div>
          </div>
        </div>

        <section class="content-card col-lg-8 mx-auto">

          <?php if ($paymentInstructions !== '' || $paymentQrPath !== ''): ?>
            <div class="card mb-4 border-0 shadow-sm rounded-4">
              <div class="card-body p-4">
                <h2 class="h6 mb-3">Cara Pembayaran</h2>

                <div class="row g-4 align-items-start">
                  <div class="col-12 <?= $paymentQrPath !== '' ? 'col-lg-7' : 'col-lg-12' ?>">
                    <?php if ($paymentMethodName !== '' || $paymentBankName !== '' || $paymentAccountHolder !== ''): ?>
                      <div class="small text-muted mb-2">
                        <?= htmlspecialchars($paymentMethodName) ?>
                        <?= $paymentBankName !== '' ? ('• ' . htmlspecialchars($paymentBankName)) : '' ?>
                      </div>
                      <?php if ($paymentAccountHolder !== ''): ?>
                        <div class="fw-semibold mb-3"><?= htmlspecialchars($paymentAccountHolder) ?></div>
                      <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($paymentInstructions !== ''): ?>
                      <div class="small" style="white-space: pre-line;"><?= htmlspecialchars($paymentInstructions) ?></div>
                    <?php endif; ?>
                  </div>

                  <?php if ($paymentQrPath !== ''): ?>
                    <div class="col-12 col-lg-5 text-center">
                      <div class="p-3 border rounded-4 bg-white shadow-sm">
                        <img src="<?= $paymentQrPath !== '' ? '../' . htmlspecialchars($paymentQrPath) : '' ?>" alt="Payment QR" class="img-fluid" style="max-width: 220px;">
                        <div class="small text-muted mt-2">Scan QR to pay</div>
                        <div class="small text-muted">Bank name: CIMB Bank</div>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <div class="summary-box mb-4">
            <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
              <div class="fw-bold">Booking Summary</div>
              <div class="fw-semibold">RM <?= number_format((float) $calculatedTotal, 2) ?></div>
            </div>
            <div class="small text-muted mt-2">Name: <?= htmlspecialchars($fullName) ?> • Phone: <?= htmlspecialchars($phone) ?></div>
            <div class="small text-muted">Date: <?= htmlspecialchars($slotDate) ?></div>
            <div class="small text-muted mt-2">
              <?php if ($qtyDewasa > 0): ?>Dewasa: <?= (int) $qtyDewasa ?><?php endif; ?>
              <?php if ($qtyKanak > 0): ?><?= $qtyDewasa > 0 ? ' • ' : '' ?>Kanak-kanak: <?= (int) $qtyKanak ?><?php endif; ?>
              <?php if ($qtyKanakFoc > 0): ?><?= ($qtyDewasa + $qtyKanak) > 0 ? ' • ' : '' ?>Kanak-kanak (&lt; 7) FOC: <?= (int) $qtyKanakFoc ?><?php endif; ?>
              <?php if ($qtyWarga > 0): ?><?= ($qtyDewasa + $qtyKanak) > 0 ? ' • ' : '' ?>Warga Emas: <?= (int) $qtyWarga ?><?php endif; ?>
              <?php if ($qtyAtm > 0): ?><?= ($qtyDewasa + $qtyKanak + $qtyWarga) > 0 ? ' • ' : '' ?>ATM: <?= (int) $qtyAtm ?><?php endif; ?>
            </div>
          </div>

          <form action="../save_booking.php" method="POST" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="visitor_role" value="<?= htmlspecialchars($visitorRole) ?>">
            <input type="hidden" name="visitor_role_main" value="<?= htmlspecialchars($visitorRoleMain) ?>">
            <input type="hidden" name="full_name" value="<?= htmlspecialchars($fullName) ?>">
            <input type="hidden" name="phone" value="<?= htmlspecialchars($phone) ?>">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
            <input type="hidden" name="military_no" value="<?= htmlspecialchars($militaryNo) ?>">
            <input type="hidden" name="remark" value="<?= htmlspecialchars($remark) ?>">
            <input type="hidden" name="slot_date" value="<?= htmlspecialchars($slotDate) ?>">
            <input type="hidden" name="quantity_dewasa" value="<?= (int) $qtyDewasa ?>">
            <input type="hidden" name="quantity_kanak" value="<?= (int) $qtyKanak ?>">
            <input type="hidden" name="quantity_kanak_foc" value="<?= (int) $qtyKanakFoc ?>">
            <input type="hidden" name="quantity_warga_emas" value="<?= (int) ($visitorRoleMain === 'ATM' ? $qtyAtm : $qtyWarga) ?>">
            <input type="hidden" name="foc_declaration" value="<?= htmlspecialchars($focDeclaration) ?>">
            <input type="hidden" name="total_amount" value="<?= htmlspecialchars(number_format((float) $calculatedTotal, 2, '.', '')) ?>">

            <div class="col-12">
              <label class="form-label">Upload Payment Proof (JPG/PNG/PDF, max 5MB)</label>
              <input type="file" class="form-control" name="payment_proof" accept="image/jpeg,image/png,application/pdf" required>
            </div>

            <div class="col-12 d-flex gap-2">
              <a href="index.php" class="btn btn-outline-dark">Kembali</a>
              <button type="submit" class="btn btn-ramadan flex-grow-1">Hantar</button>
            </div>
          </form>
        </section>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
  </body>
</html>
