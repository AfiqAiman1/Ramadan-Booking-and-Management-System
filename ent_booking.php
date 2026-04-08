<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/ent_auth.php';

require_ent_roles(['ent_admin']);

$csrfToken = ent_csrf_token();

$flashMessage = '';
$flashClass = 'alert-info';

$mysqli = null;
$settings = [];

function sanitize_int($value): int
{
    return max(0, filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]));
}

function generate_booking_reference(string $prefix): string
{
    $prefix = preg_replace('/[^A-Za-z0-9\-]/', '', $prefix);
    $prefix = trim($prefix);
    if ($prefix === '') {
        $prefix = 'BP' . date('Y');
    }
    return $prefix . '-' . random_int(10000, 99999);
}

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
    ensure_booking_slots_schema($mysqli);
    ensure_global_settings_schema($mysqli);
    $settings = load_global_settings($mysqli);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $slotDate = trim((string) ($_POST['slot_date'] ?? ''));
        $remark = trim((string) ($_POST['remark'] ?? ''));

        $qtyDewasa = sanitize_int($_POST['quantity_dewasa'] ?? 0);
        $qtyKanak = sanitize_int($_POST['quantity_kanak'] ?? 0);
        $qtyWarga = sanitize_int($_POST['quantity_warga_emas'] ?? 0);
        $qtyAtm = sanitize_int($_POST['quantity_atm'] ?? 0);

        $totalTickets = $qtyDewasa + $qtyKanak + $qtyWarga + $qtyAtm;

        $compQty = $totalTickets;

        $freeQtyDewasa = 0;
        $freeQtyKanak = 0;
        $freeQtyKanakFoc = 0;
        $freeQtyWarga = 0;
        $freeQtyAtm = 0;

        $qtyDewasa = 0;
        $qtyKanak = 0;
        $qtyWarga = 0;
        $qtyAtm = 0;

        if ($fullName === '' || $phone === '' || $slotDate === '') {
            $flashMessage = 'Please fill in name, phone, and date.';
            $flashClass = 'alert-danger';
        } elseif ($totalTickets <= 0) {
            $flashMessage = 'Please select at least one ticket.';
            $flashClass = 'alert-danger';
        } else {
            $slotStmt = $mysqli->prepare('SELECT max_capacity, booked_count FROM booking_slots WHERE slot_date = ?');
            $slotRow = null;
            if ($slotStmt) {
                $slotStmt->bind_param('s', $slotDate);
                $slotStmt->execute();
                $res = $slotStmt->get_result();
                $slotRow = $res ? $res->fetch_assoc() : null;
                if ($res) {
                    $res->free();
                }
                $slotStmt->close();
            }

            if (!$slotRow) {
                $flashMessage = 'Selected booking date is unavailable.';
                $flashClass = 'alert-danger';
            } else {
                $remaining = (int) $slotRow['max_capacity'] - (int) $slotRow['booked_count'];
                if ($remaining < $totalTickets) {
                    $flashMessage = 'Selected booking date is full.';
                    $flashClass = 'alert-danger';
                } else {
                    $eventYear = (int) ($settings['event_year'] ?? (int) date('Y'));
                    $prefix = 'ENT' . str_pad((string) ($eventYear % 100), 2, '0', STR_PAD_LEFT);

                    $bookingRef = '';
                    for ($i = 0; $i < 5; $i++) {
                        $bookingRef = generate_booking_reference($prefix);
                        $check = $mysqli->prepare('SELECT 1 FROM bookings WHERE booking_reference = ? LIMIT 1');
                        if ($check) {
                            $check->bind_param('s', $bookingRef);
                            $check->execute();
                            $r = $check->get_result();
                            $exists = $r && $r->num_rows > 0;
                            if ($r) {
                                $r->free();
                            }
                            $check->close();
                            if (!$exists) {
                                break;
                            }
                        }
                    }

                    $paymentStatus = 'PAID';
                    $checkinStatus = 'Not Checked';
                    $totalPrice = 0.00;

                    $sql = 'INSERT INTO bookings (
                                booking_reference, full_name, phone, military_no, email, remark, slot_date,
                                quantity_dewasa, quantity_kanak, quantity_warga_emas, quantity_atm,
                                free_quantity_dewasa, free_quantity_kanak, free_quantity_kanak_foc, free_quantity_warga_emas, free_quantity_atm,
                                comp_qty,
                                total_price, payment_proof, payment_status, payment_method, checkin_status, created_at, paid_at
                            ) VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, NOW(), NOW())';

                    $stmt = $mysqli->prepare($sql);
                    if (!$stmt) {
                        $flashMessage = 'Failed to prepare statement.';
                        $flashClass = 'alert-danger';
                    } else {
                        $paymentMethod = 'ENT';
                        $stmt->bind_param(
                            'sssssiiiiiiiiiidisss',
                            $bookingRef,
                            $fullName,
                            $phone,
                            $remark,
                            $slotDate,
                            $qtyDewasa,
                            $qtyKanak,
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

                        if ($stmt->execute()) {
                            $slotUpdate = $mysqli->prepare('UPDATE booking_slots SET booked_count = booked_count + ? WHERE slot_date = ?');
                            if ($slotUpdate) {
                                $slotUpdate->bind_param('is', $compQty, $slotDate);
                                $slotUpdate->execute();
                                $slotUpdate->close();
                            }

                            $flashMessage = 'Complimentary booking created: ' . $bookingRef;
                            $flashClass = 'alert-success';
                        } else {
                            $flashMessage = 'Unable to save booking.';
                            $flashClass = 'alert-danger';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
} catch (Throwable $e) {
    $flashMessage = 'Database connection failed.';
    $flashClass = 'alert-danger';
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ENT Complimentary Booking</title>
    <link rel="icon" type="image/png" href="assets/img/Logo%20ATM.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
      :root { --ramadan-green: #08372b; --ramadan-gold: #d8b45c; --ramadan-cream: #fff9ed; }
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
      }
      .sidebar .nav-link { color: #f5e9c8; border-radius: 0.75rem; padding: 0.65rem 1rem; display: flex; align-items: center; gap: 0.5rem; }
      .sidebar .nav-link.active,
      .sidebar .nav-link:hover { background: rgba(216,180,92,0.18); color: var(--ramadan-gold); }
      .logout-btn { background: rgba(220,53,69,0.12); border: 1px solid rgba(220,53,69,0.4); color: #ffb6b6; }
      .main-content { background: var(--ramadan-cream); padding: 2rem; }
    </style>
  </head>
  <body>
    <div class="d-flex layout flex-column flex-lg-row">
      <aside class="sidebar p-4 d-flex flex-column offcanvas-lg offcanvas-start" tabindex="-1" id="sidebarMenu" style="--bs-offcanvas-width: 260px;">
        <div class="d-lg-none text-end mb-2">
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="mb-4 text-center">
          <div class="fs-2 fw-bold text-white">ENT</div>
          <p class="text-muted small mb-0">Complimentary</p>
        </div>
        <nav class="flex-grow-1">
          <a class="nav-link active" href="ent_booking.php"><i class="bi bi-gift"></i>ENT Booking</a>
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
          <p class="text-uppercase text-muted small mb-1">ENT</p>
          <h1 class="h3 text-dark mb-2">Manual Complimentary Booking</h1>
          <p class="text-muted mb-0">For VIP / relatives / friends. Total price will be RM0.00.</p>
        </header>

        <?php if ($flashMessage): ?>
          <div class="alert <?= htmlspecialchars($flashClass) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-body">
            <form method="POST" class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" name="full_name" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" class="form-control" name="phone" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Slot Date</label>
                <input type="date" class="form-control" name="slot_date" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Remark</label>
                <input type="text" class="form-control" name="remark" placeholder="VIP / ENT">
              </div>

              <div class="col-12">
                <div class="row g-3">
                  <div class="col-6 col-md-3">
                    <label class="form-label">Dewasa</label>
                    <input type="number" class="form-control" min="0" name="quantity_dewasa" value="0">
                  </div>
                  <div class="col-6 col-md-3">
                    <label class="form-label">Kanak-kanak</label>
                    <input type="number" class="form-control" min="0" name="quantity_kanak" value="0">
                  </div>
                  <div class="col-6 col-md-3">
                    <label class="form-label">Warga Emas</label>
                    <input type="number" class="form-control" min="0" name="quantity_warga_emas" value="0">
                  </div>
                  <div class="col-6 col-md-3">
                    <label class="form-label">ATM</label>
                    <input type="number" class="form-control" min="0" name="quantity_atm" value="0">
                  </div>
                </div>
              </div>

              <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-success">Create Complimentary Booking</button>
              </div>
            </form>
          </div>
        </div>
      </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <form method="POST" action="ent_logout.php" id="logoutForm" class="d-none">
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

    <script>
      (function () {
        const btn = document.getElementById('confirmLogoutBtn');
        const form = document.getElementById('logoutForm');
        if (!btn || !form) return;
        btn.addEventListener('click', () => form.submit());
      })();
    </script>
  </body>
</html>
<?php
if ($mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
