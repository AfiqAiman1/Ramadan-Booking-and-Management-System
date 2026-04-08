<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin_auth.php';

require_admin_roles(['admin', 'banquet']);
$csrfToken = admin_csrf_token();

$mysqli = null;
try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
} catch (Throwable $e) {
    die('<h2>Database connection failed.</h2>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_table_no') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $csrfTokenPost = (string) ($_POST['csrf_token'] ?? '');
    if (!admin_verify_csrf($csrfTokenPost)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $bookingRef = trim((string) ($_POST['booking_reference'] ?? ''));
    if ($bookingRef === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Missing booking reference.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tableNo = trim((string) ($_POST['table_no'] ?? ''));
    if ($tableNo === '') {
        $tableNo = null;
    }

    if ($tableNo !== null) {
        $stmt = $mysqli->prepare("UPDATE bookings SET table_no = ? WHERE booking_reference = ?");
    } else {
        $stmt = $mysqli->prepare('UPDATE bookings SET table_no = ? WHERE booking_reference = ?');
    }
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to save right now.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt->bind_param('ss', $tableNo, $bookingRef);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Failed to update table no.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$slotDate = trim((string) ($_GET['slot_date'] ?? ''));
$dt = DateTime::createFromFormat('Y-m-d', $slotDate);
$slotDate = $dt ? $dt->format('Y-m-d') : '';
if ($slotDate === '') {
    $slotDate = date('Y-m-d');
}

$rows = [];
$stmt = $mysqli->prepare("SELECT booking_reference, full_name, phone, remark, slot_date, table_no FROM bookings WHERE slot_date = ? ORDER BY booking_reference ASC");
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

$adminRoleUpper = strtoupper(trim((string) admin_get_role()));
$sidebarRoleLabel = match (strtolower(admin_get_role())) {
    'banquet' => 'Banquet',
    'finance' => 'Finance',
    'assistant' => 'Assistant',
    'staff' => 'Sales',
    'ent_admin' => 'ENT',
    'entry_duty' => 'Entry Staff',
    default => 'Admin',
};
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Table No - Buffet Ramadan</title>
    <link rel="icon" type="image/png" href="../assets/img/Logo%20ATM.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <style>
      :root { --ramadan-green: #08372b; --ramadan-gold: #d8b45c; --ramadan-cream: #fff9ed; }
      body { font-family: 'Cairo', system-ui, sans-serif; background: var(--ramadan-cream); }
      .layout { min-height: 100vh; }
      .sidebar { background: linear-gradient(180deg, var(--ramadan-green), #041f18); color: #fef6dd; width: 260px; }
      @media (min-width: 992px) {
        .sidebar { position: sticky; top: 0; height: 100vh; overflow-y: auto; align-self: flex-start; }
        .sidebar.offcanvas { visibility: visible !important; transform: none !important; position: sticky; }
      }
      .sidebar .nav-link { color: #f5e9c8; border-radius: 0.75rem; padding: 0.65rem 1rem; display: flex; align-items: center; gap: 0.5rem; }
      .sidebar .nav-link.active, .sidebar .nav-link:hover { background: rgba(216,180,92,0.18); color: var(--ramadan-gold); }
      .logout-btn { background: rgba(220,53,69,0.12); border: 1px solid rgba(220,53,69,0.4); color: #ffb6b6; }
      .main-content { background: var(--ramadan-cream); padding: 2rem; }
      .table-no-input { max-width: 320px; }
      .table-no-cell { min-width: 380px; }
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
          <p class="text-muted small mb-0">Table No</p>
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
            <a class="nav-link" href="list_guests.php"><i class="bi bi-people"></i>Name List</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'BANQUET'], true)): ?>
            <a class="nav-link active" href="table_no.php"><i class="bi bi-table"></i>Table No</a>
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
          <a class="btn btn-outline-light w-100" href="../public/live_display.php" target="_blank" rel="noopener">Open Live Display</a>
        </div>
      </aside>

      <main class="main-content flex-grow-1">
        <header class="mb-4">
          <button class="btn btn-outline-secondary d-lg-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
            <i class="bi bi-list"></i>
          </button>
          <h1 class="h3 text-dark mb-2">Table No</h1>
          <div class="text-muted">Key in table numbers. Saved automatically.</div>
        </header>

        <section class="card border-0 shadow-sm rounded-4">
          <div class="card-body">
            <form class="row g-3 align-items-end" method="get" action="table_no.php">
              <div class="col-sm-4">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="slot_date" value="<?= htmlspecialchars($slotDate) ?>">
              </div>
              <div class="col-sm-3">
                <button type="submit" class="btn btn-primary w-100">Search</button>
              </div>
            </form>

            <hr class="my-4">

            <div id="saveAlert"></div>

            <div class="table-responsive">
              <table class="table table-striped align-middle">
                <thead>
                  <tr>
                    <th>Booking Ref</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Table No</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $r): ?>
                    <tr>
                      <td class="fw-semibold"><?= htmlspecialchars((string) ($r['booking_reference'] ?? '')) ?></td>
                      <td><?= htmlspecialchars((string) ($r['full_name'] ?? '')) ?></td>
                      <td><?= htmlspecialchars((string) ($r['phone'] ?? '')) ?></td>
                      <td>
                        <div class="input-group table-no-cell">
                          <input
                            type="text"
                            class="form-control table-no-input"
                            value="<?= htmlspecialchars((string) ($r['table_no'] ?? '')) ?>"
                            data-booking-ref="<?= htmlspecialchars((string) ($r['booking_reference'] ?? '')) ?>"
                            onblur="saveTableNo(this)"
                          >
                          <button type="button" class="btn btn-success" onclick="updateRow(this)">Update</button>
                          <span class="input-group-text d-none" data-update-status="1">Updated</span>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (count($rows) === 0): ?>
                    <tr><td colspan="4" class="text-center text-muted">No bookings found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
              <button type="button" class="btn btn-success" id="updateAllBtn" onclick="updateAllRows()">Update All</button>
            </div>
          </div>
        </section>
      </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      let saving = new Map();

      function getRowStatusEl(input) {
        if (!input) return null;
        const group = input.closest('.input-group');
        if (!group) return null;
        return group.querySelector('[data-update-status="1"]');
      }

      function showRowUpdated(input) {
        const el = getRowStatusEl(input);
        if (!el) return;
        el.classList.remove('d-none');
        setTimeout(() => { el.classList.add('d-none'); }, 2000);
      }

      async function saveTableNo(input, opts = {}) {
        const bookingRef = input.getAttribute('data-booking-ref') || '';
        if (!bookingRef) return;

        const tableNo = input.value || '';
        const key = bookingRef;
        const last = saving.get(key);
        if (last === tableNo && opts.force !== true) {
          return;
        }

        saving.set(key, tableNo);

        const fd = new FormData();
        fd.append('action', 'save_table_no');
        fd.append('csrf_token', '<?= htmlspecialchars($csrfToken) ?>');
        fd.append('booking_reference', bookingRef);
        fd.append('table_no', tableNo);

        try {
          const res = await fetch('table_no.php', { method: 'POST', body: fd, cache: 'no-store', credentials: 'same-origin' });
          const data = await res.json();
          if (!data || !data.ok) {
            showSaveError(data && data.message ? data.message : 'Save failed');
            return;
          }

          if (opts.showUpdated === true) {
            showRowUpdated(input);
          }
        } catch (e) {
          showSaveError('Save failed');
        }
      }

      function updateRow(btn) {
        const group = btn.closest('.input-group');
        const input = group ? group.querySelector('input') : null;
        if (!input) return;
        saveTableNo(input, { showUpdated: true, force: true });
      }

      async function updateAllRows() {
        const btn = document.getElementById('updateAllBtn');
        if (btn) {
          btn.disabled = true;
          btn.textContent = 'Updating...';
        }

        const inputs = Array.from(document.querySelectorAll('input.table-no-input[data-booking-ref]'));
        
        const promises = inputs.map(input => saveTableNo(input, { showUpdated: false, force: true }));
        await Promise.all(promises);

        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Update All';
        }

        showSaveSuccess('All table numbers updated successfully!');
      }

      function showSaveError(msg) {
        const box = document.getElementById('saveAlert');
        if (!box) return;
        box.innerHTML = '<div class="alert alert-danger">' + String(msg || 'Save failed') + '</div>';
        setTimeout(() => { box.innerHTML = ''; }, 4000);
      }

      function showSaveSuccess(msg) {
        const box = document.getElementById('saveAlert');
        if (!box) return;
        box.innerHTML = '<div class="alert alert-success">' + String(msg || 'All updates completed') + '</div>';
        setTimeout(() => { box.innerHTML = ''; }, 4000);
      }
    </script>
  </body>
</html>
