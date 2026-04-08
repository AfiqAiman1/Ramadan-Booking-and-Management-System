<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin_auth.php';

require_admin_roles(['admin']);

$flashMessage = '';
$flashClass = 'alert-info';

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
    ensure_booking_slots_schema($mysqli);

    $mysqli->begin_transaction();

    $mysqli->query('UPDATE booking_slots SET booked_count = 0');

    $sumSql = "
        SELECT
            slot_date,
            SUM(
                quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
                + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
                + staff_blanket_qty + living_in_qty + ajk_qty + free_voucher_qty + comp_qty
            ) AS total_tickets
        FROM bookings
        GROUP BY slot_date
    ";

    $sums = [];
    if ($res = $mysqli->query($sumSql)) {
        while ($row = $res->fetch_assoc()) {
            $slotDate = (string) ($row['slot_date'] ?? '');
            $total = (int) ($row['total_tickets'] ?? 0);
            if ($slotDate !== '') {
                $sums[] = [$slotDate, $total];
            }
        }
        $res->free();
    }

    $insertSlot = $mysqli->prepare('INSERT IGNORE INTO booking_slots (slot_date, max_capacity, booked_count) VALUES (?, 0, 0)');
    $updateSlot = $mysqli->prepare('UPDATE booking_slots SET booked_count = ? WHERE slot_date = ?');

    foreach ($sums as [$slotDate, $total]) {
        if ($insertSlot) {
            $insertSlot->bind_param('s', $slotDate);
            $insertSlot->execute();
        }
        if ($updateSlot) {
            $updateSlot->bind_param('is', $total, $slotDate);
            $updateSlot->execute();
        }
    }

    if ($insertSlot) {
        $insertSlot->close();
    }
    if ($updateSlot) {
        $updateSlot->close();
    }

    $mysqli->commit();

    $flashMessage = 'Slots recalculated successfully.';
    $flashClass = 'alert-success';
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        try {
            if ($mysqli->errno === 0) {
                // no-op
            }
            $mysqli->rollback();
        } catch (Throwable $rollbackError) {
            // ignore
        }
        $mysqli->close();
    }
    $flashMessage = 'Failed to recalculate slots.';
    $flashClass = 'alert-danger';
}

if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recalculate Slots</title>
    <link rel="icon" type="image/png" href="../assets/img/Logo%20ATM.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <style>
      :root { --ramadan-green: #08372b; --ramadan-gold: #d8b45c; --ramadan-cream: #fff9ed; }
      body { font-family: 'Cairo', system-ui, sans-serif; background: var(--ramadan-cream); }
      .card { border-radius: 1.25rem; }
      .btn-ramadan { background: var(--ramadan-gold); border: none; color: #2d1c01; font-weight: 800; }
      .btn-ramadan:hover { color: #2d1c01; opacity: 0.92; }
    </style>
  </head>
  <body>
    <main class="container py-5">
      <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
          <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(8,55,43,0.1);">
                  <i class="bi bi-arrow-repeat" style="color: var(--ramadan-green);"></i>
                </div>
                <div>
                  <div class="text-uppercase text-muted small">Admin Utility</div>
                  <h1 class="h4 mb-0">Recalculate Slot Booked Count</h1>
                </div>
              </div>

              <div class="alert <?= htmlspecialchars($flashClass) ?> mb-4" role="alert">
                <?= htmlspecialchars($flashMessage) ?>
              </div>

              <div class="d-flex flex-column flex-sm-row gap-2">
                <a class="btn btn-ramadan" href="settings.php"><i class="bi bi-gear me-2"></i>Back to Settings</a>
                <a class="btn btn-outline-secondary" href="all_bookings.php"><i class="bi bi-list-ul me-2"></i>All Bookings</a>
              </div>

              <div class="mt-4 text-muted small">
                This will set all slot booked counts to 0, then recalculate them from current bookings.
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
  </body>
</html>
