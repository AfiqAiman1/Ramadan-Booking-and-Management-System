<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin_auth.php';

require_admin_roles(['admin']);
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

$mysqli = null;
$flashMessage = '';
$flashClass = 'alert-info';

try {
    $mysqli = db_connect();
    ensure_booking_slots_schema($mysqli);
    ensure_global_settings_schema($mysqli);
} catch (Throwable $e) {
    die('<h2>Database connection failed.</h2>');
}

function normalize_slot_date(string $raw): string
{
    $raw = trim($raw);
    $dt = DateTime::createFromFormat('Y-m-d', $raw);
    return $dt ? $dt->format('Y-m-d') : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfTokenPost = (string) ($_POST['csrf_token'] ?? '');
    if (!admin_verify_csrf($csrfTokenPost)) {
        $flashMessage = 'Invalid CSRF token.';
        $flashClass = 'alert-danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $slotDate = normalize_slot_date((string) ($_POST['slot_date'] ?? ''));
        $maxCapacity = max(0, (int) ($_POST['max_capacity'] ?? 0));

        if (!in_array($action, ['add', 'update', 'delete'], true)) {
            $flashMessage = 'Invalid action.';
            $flashClass = 'alert-danger';
        } elseif ($slotDate === '') {
            $flashMessage = 'Invalid slot date.';
            $flashClass = 'alert-danger';
        } else {
            if ($action === 'add') {
                $stmt = $mysqli->prepare('INSERT INTO booking_slots (slot_date, max_capacity, booked_count) VALUES (?, ?, 0)');
                if (!$stmt) {
                    $flashMessage = 'Unable to create slot.';
                    $flashClass = 'alert-danger';
                } else {
                    $stmt->bind_param('si', $slotDate, $maxCapacity);
                    if ($stmt->execute()) {
                        $flashMessage = 'Slot added successfully.';
                        $flashClass = 'alert-success';
                    } else {
                        $flashMessage = 'Unable to create slot.';
                        $flashClass = 'alert-danger';
                    }
                    $stmt->close();
                }
            } elseif ($action === 'update') {
                $stmt = $mysqli->prepare('UPDATE booking_slots SET max_capacity = ? WHERE slot_date = ?');
                if (!$stmt) {
                    $flashMessage = 'Unable to update slot.';
                    $flashClass = 'alert-danger';
                } else {
                    $stmt->bind_param('is', $maxCapacity, $slotDate);
                    if ($stmt->execute()) {
                        $flashMessage = 'Slot updated successfully.';
                        $flashClass = 'alert-success';
                    } else {
                        $flashMessage = 'Unable to update slot.';
                        $flashClass = 'alert-danger';
                    }
                    $stmt->close();
                }
            } elseif ($action === 'delete') {
                $countStmt = $mysqli->prepare('SELECT booked_count FROM booking_slots WHERE slot_date = ? LIMIT 1');
                $bookedCount = null;
                if ($countStmt) {
                    $countStmt->bind_param('s', $slotDate);
                    $countStmt->execute();
                    $res = $countStmt->get_result();
                    $row = $res ? ($res->fetch_assoc() ?: null) : null;
                    if ($res) {
                        $res->free();
                    }
                    $countStmt->close();
                    if ($row) {
                        $bookedCount = (int) ($row['booked_count'] ?? 0);
                    }
                }

                if ($bookedCount !== null && $bookedCount > 0) {
                    $flashMessage = 'Cannot delete slot with existing bookings.';
                    $flashClass = 'alert-danger';
                } else {
                    $stmt = $mysqli->prepare('DELETE FROM booking_slots WHERE slot_date = ?');
                    if (!$stmt) {
                        $flashMessage = 'Unable to delete slot.';
                        $flashClass = 'alert-danger';
                    } else {
                        $stmt->bind_param('s', $slotDate);
                        if ($stmt->execute()) {
                            $flashMessage = 'Slot deleted successfully.';
                            $flashClass = 'alert-success';
                        } else {
                            $flashMessage = 'Unable to delete slot.';
                            $flashClass = 'alert-danger';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

$slots = [];
if ($res = $mysqli->query('SELECT slot_date, max_capacity, booked_count FROM booking_slots ORDER BY slot_date ASC')) {
    while ($row = $res->fetch_assoc()) {
        $slots[] = $row;
    }
    $res->free();
}

$settings = [];
try {
    $settings = load_global_settings($mysqli);
} catch (Throwable $e) {
    $settings = [];
}

$eventStart = (string) ($settings['event_start_date'] ?? '');
$eventEnd = (string) ($settings['event_end_date'] ?? '');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Slots</title>
    <link rel="icon" type="image/png" href="../assets/img/Logo%20ATM.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
  </head>
  <body style="background:#fff9ed;">
    <div class="d-flex layout flex-column flex-lg-row">
      <aside class="sidebar p-4 d-flex flex-column offcanvas offcanvas-lg offcanvas-start" tabindex="-1" id="sidebarMenu" style="--bs-offcanvas-width: 260px;">
        <div class="d-lg-none text-end mb-2">
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="mb-4 text-center">
          <div class="fs-2 fw-bold text-white"><?= htmlspecialchars($sidebarRoleLabel) ?></div>
        </div>
        <nav class="flex-grow-1">
          <a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2"></i>Dashboard</a>
          <a class="nav-link" href="all_bookings.php"><i class="bi bi-list-ul"></i>All Bookings</a>
          <a class="nav-link" href="check_in.php"><i class="bi bi-qr-code-scan"></i>Entry</a>
          <a class="nav-link" href="list_guests.php"><i class="bi bi-people"></i>Name List</a>
          <?php if (in_array(strtoupper(trim((string) admin_get_role())), ['ADMIN', 'BANQUET'], true)): ?>
            <a class="nav-link" href="table_no.php"><i class="bi bi-table"></i>Table No</a>
          <?php endif; ?>
          <a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-line"></i>Reports</a>
          <a class="nav-link" href="finance_confirm.php"><i class="bi bi-bank2"></i>Finance Confirm</a>
          <a class="nav-link active" href="booking_slots.php"><i class="bi bi-calendar-event"></i>Booking Slots</a>
          <a class="nav-link" href="booking_details.php"><i class="bi bi-card-text"></i>Booking Details</a>
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
          <p class="text-uppercase text-muted small mb-1">Admin</p>
          <h1 class="h3 text-dark mb-2">Booking Slots</h1>
          <div class="text-muted">Manage available dates and capacity for booking slots.</div>
        </header>

        <?php if ($flashMessage !== ''): ?>
          <div class="alert <?= htmlspecialchars($flashClass) ?> shadow-sm rounded-4"><?= htmlspecialchars($flashMessage) ?></div>
        <?php endif; ?>

        <section class="card border-0 shadow-sm rounded-4 mb-4">
          <div class="card-body">
            <h4 class="mb-3">Add Slot</h4>
            <form method="POST" class="row g-3 align-items-end">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
              <input type="hidden" name="action" value="add">
              <div class="col-md-4">
                <label class="form-label">Slot Date</label>
                <input type="date" class="form-control" name="slot_date" value="" min="<?= htmlspecialchars($eventStart) ?>" max="<?= htmlspecialchars($eventEnd) ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Max Capacity</label>
                <input type="number" class="form-control" name="max_capacity" min="0" value="0" required>
              </div>
              <div class="col-md-4">
                <button type="submit" class="btn btn-success w-100"><i class="bi bi-plus-circle me-2"></i>Add Slot</button>
              </div>
            </form>
          </div>
        </section>

        <section class="card border-0 shadow-sm rounded-4">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <h4 class="mb-1">All Slots</h4>
                <div class="text-muted">Booked count is maintained by the system.</div>
              </div>
            </div>

            <div class="table-responsive mt-3">
              <table class="table table-striped align-middle">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th class="text-center">Booked</th>
                    <th class="text-center">Max Capacity</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($slots): ?>
                    <?php foreach ($slots as $row): ?>
                      <?php
                        $rawDate = (string) ($row['slot_date'] ?? '');
                        $dt = DateTime::createFromFormat('Y-m-d', $rawDate);
                        $dateLabel = $dt ? $dt->format('d/m/Y') : $rawDate;
                        $booked = (int) ($row['booked_count'] ?? 0);
                        $maxCap = (int) ($row['max_capacity'] ?? 0);
                      ?>
                      <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($dateLabel) ?></td>
                        <td class="text-center"><?= number_format($booked) ?></td>
                        <td class="text-center">
                          <form method="POST" class="d-flex justify-content-center gap-2">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="slot_date" value="<?= htmlspecialchars($rawDate) ?>">
                            <input type="number" class="form-control form-control-sm" name="max_capacity" min="0" value="<?= htmlspecialchars((string) $maxCap) ?>" style="max-width: 120px;">
                            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-save"></i></button>
                          </form>
                        </td>
                        <td class="text-end">
                          <form method="POST" class="d-inline" onsubmit="return confirm('Delete this slot?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="slot_date" value="<?= htmlspecialchars($rawDate) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="4" class="text-center text-muted py-4">No slots found.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>
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
  </body>
</html>
<?php
$mysqli->close();
?>
