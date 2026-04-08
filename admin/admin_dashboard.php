<?php
// admin_dashboard.php - Ramadan themed template

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin_auth.php';

require_admin_roles(['admin', 'staff', 'assistant', 'finance', 'ENT_ADMIN']);
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

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
} catch (Throwable $e) {
    die('<h2>Database connection failed.</h2>');
}

$totalBookings = 0;
$totalPeople = 0;
$totalRevenue = 0;
$recentBookings = [];

$metricQuery = "
    SELECT
      COUNT(*) AS total_bookings,
      SUM(
        quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
        + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
        + staff_blanket_qty + living_in_qty + ajk_qty + free_voucher_qty + comp_qty
      ) AS total_people,
      SUM(CASE WHEN payment_status = 'PAID' THEN total_price ELSE 0 END) AS revenue
    FROM bookings
    WHERE payment_status IN ('PAID','PENDING')
";
if ($metricResult = $mysqli->query($metricQuery)) {
    $data = $metricResult->fetch_assoc();
    $totalBookings   = (int) ($data['total_bookings'] ?? 0);
    $totalPeople     = (int) ($data['total_people'] ?? 0);
    $totalRevenue    = (float) ($data['revenue'] ?? 0);
    $metricResult->free();
}

$recentQuery = "
    SELECT booking_reference, full_name, slot_date, payment_status, total_price
    FROM bookings
    ORDER BY created_at DESC
    LIMIT 5
";
if ($recentResult = $mysqli->query($recentQuery)) {
    while ($row = $recentResult->fetch_assoc()) {
        $recentBookings[] = $row;
    }
    $recentResult->free();
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Buffet Ramadan</title>
    <link rel="icon" type="image/png" href="../assets/img/Logo%20ATM.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <style>
      :root {
        --ramadan-green: #08372b;
        --ramadan-green-light: #0f4f3d;
        --ramadan-gold: #d8b45c;
        --ramadan-cream: #fff9ed;
      }
      body {
        font-family: 'Cairo', system-ui, -apple-system, 'Segoe UI', sans-serif;
        background: var(--ramadan-cream);
      }
      .layout {
        min-height: 100vh;
      }
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
      .main-content {
        background: var(--ramadan-cream);
        padding: 1rem;
      }
      .stat-card {
        border-radius: 1rem;
        border: none;
        box-shadow: 0 15px 30px rgba(8, 25, 20, 0.08);
        background: #ffffff;
      }
      .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(216, 180, 92, 0.2);
        color: var(--ramadan-green);
      }
      .ramadan-panel {
        border-radius: 1.25rem;
        border: 1px solid rgba(216, 180, 92, 0.35);
        background: radial-gradient(1000px 520px at 20% 10%, rgba(216, 180, 92, 0.22), transparent 60%),
          linear-gradient(160deg, rgba(8, 55, 43, 0.92), rgba(4, 31, 24, 0.98));
        color: #fef6dd;
        overflow: hidden;
      }
      .ramadan-panel .tag {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        background: rgba(216, 180, 92, 0.18);
        border: 1px solid rgba(216, 180, 92, 0.28);
        color: #f7e7bf;
        font-size: 0.85rem;
      }
      .ramadan-panel .panel-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.85rem 1rem;
        border-radius: 0.9rem;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: #fef6dd;
        text-decoration: none;
      }
      .ramadan-panel .panel-link:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fef6dd;
      }
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
        </div>
        <nav class="flex-grow-1">
          <a class="nav-link active" href="admin_dashboard.php"><i class="bi bi-speedometer2"></i>Dashboard</a>
          <?php if (in_array(strtoupper(trim((string) admin_get_role())), ['ADMIN', 'STAFF', 'ASSISTANT', 'BANQUET', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="all_bookings.php"><i class="bi bi-list-ul"></i>All Bookings</a>
          <?php endif; ?>
          <?php if (in_array(strtoupper(trim((string) admin_get_role())), ['ADMIN', 'ASSISTANT', 'ENTRY_DUTY', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="check_in.php"><i class="bi bi-qr-code-scan"></i>Entry</a>
          <?php endif; ?>

          <?php if (in_array(strtoupper(trim((string) admin_get_role())), ['ADMIN', 'STAFF', 'ASSISTANT', 'BANQUET', 'FINANCE', 'ENTRY_DUTY', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="list_guests.php"><i class="bi bi-people"></i>Name List</a>
          <?php endif; ?>
          <?php if (in_array(strtoupper(trim((string) admin_get_role())), ['ADMIN', 'BANQUET'], true)): ?>
            <a class="nav-link" href="table_no.php"><i class="bi bi-table"></i>Table No</a>
          <?php endif; ?>
          <?php if (in_array(strtoupper(trim((string) admin_get_role())), ['ADMIN', 'STAFF', 'ASSISTANT', 'FINANCE', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-line"></i>Reports</a>
          <?php endif; ?>
          <?php if (in_array(strtoupper(trim((string) admin_get_role())), ['ADMIN', 'FINANCE', 'ASSISTANT', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="finance_confirm.php"><i class="bi bi-bank2"></i>Finance Confirm</a>
          <?php endif; ?>
          <?php if (strtoupper(admin_get_role()) === 'ENT_ADMIN'): ?>
            <a class="nav-link" href="../ent_home.php"><i class="bi bi-box-arrow-in-right"></i>ENT</a>
          <?php endif; ?>
          <?php if (strtolower(admin_get_role()) === 'admin'): ?>
            <a class="nav-link" href="booking_slots.php"><i class="bi bi-calendar-event"></i>Booking Slots</a>
          <?php endif; ?>
          <?php if (strtolower(admin_get_role()) === 'admin'): ?>
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
        <header class="mb-4" id="dashboard">
          <button class="btn btn-outline-secondary d-lg-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
            <i class="bi bi-list"></i>
          </button>
          <h1 class="h3 text-dark mb-2">Dashboard</h1>
          <p class="text-muted mb-0">Monitor bookings, payments, and guest arrivals at a glance.</p>
        </header>

        <section class="row g-4 mb-4">
          <div class="col-sm-6 col-xl-3">
            <div class="card stat-card p-3">
              <div class="stat-icon mb-3"><i class="bi bi-people-fill"></i></div>
              <p class="text-muted text-uppercase small mb-1">Total Bookings</p>
              <h2 class="fw-bold mb-0"><span id="statTotalBookings"><?= number_format($totalBookings) ?></span></h2>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="card stat-card p-3">
              <div class="stat-icon mb-3"><i class="bi bi-person-lines-fill"></i></div>
              <p class="text-muted text-uppercase small mb-1">Total People</p>
              <h2 class="fw-bold mb-0"><span id="statTotalPeople"><?= number_format($totalPeople) ?></span></h2>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="card stat-card p-3">
              <div class="stat-icon mb-3"><i class="bi bi-cash-coin"></i></div>
              <p class="text-muted text-uppercase small mb-1">Total Revenue (RM)</p>
              <h2 class="fw-bold mb-0"><span id="statTotalRevenue"><?= number_format($totalRevenue, 2) ?></span></h2>
            </div>
          </div>
        </section>

        <section class="row g-4 mb-5" id="dashboard-highlights">
          <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <h2 class="h5 mb-1">Recent Bookings</h2>
                    <p class="text-muted small mb-0">Latest five submissions</p>
                  </div>
                  <span class="badge text-bg-warning">Live</span>
                </div>
                <?php if ($recentBookings): ?>
                  <div class="table-responsive">
                    <table class="table align-middle mb-0">
                      <thead>
                        <tr>
                          <th>Reference</th>
                          <th>Name</th>
                          <th>Buffet Date</th>
                          <th>Status</th>
                          <th>Total (RM)</th>
                        </tr>
                      </thead>
                      <tbody id="recentBookingsBody">
                        <?php foreach ($recentBookings as $booking): ?>
                          <?php $paymentStatus = strtoupper(trim((string) ($booking['payment_status'] ?? ''))); ?>
                          <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($booking['booking_reference']) ?></td>
                            <td><?= htmlspecialchars($booking['full_name']) ?></td>
                            <td>
                              <?php
                                $dateLabel = '';
                                if (!empty($booking['slot_date'])) {
                                    $dt = DateTime::createFromFormat('Y-m-d', (string) $booking['slot_date']);
                                    $dateLabel = $dt ? $dt->format('d/m/Y') : (string) $booking['slot_date'];
                                }
                                echo htmlspecialchars($dateLabel);
                              ?>
                            </td>
                            <td>
                              <span class="badge rounded-pill <?= match ($paymentStatus) {
                                  'PAID' => 'text-bg-success',
                                  'FAILED' => 'text-bg-danger',
                                  default => 'text-bg-warning'
                                } ?>">
                                <?= htmlspecialchars((string) ($booking['payment_status'] ?? '')) ?>
                              </span>
                            </td>
                            <td><?= number_format((float) $booking['total_price'], 2) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="alert alert-light mb-0" id="recentBookingsEmpty">No recent bookings yet.</div>
                <?php endif; ?>
              </div>
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
          <div class="modal-body" style="background: #fff9ed;">
            Are you sure you want to log out?
          </div>
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

      (function () {
        const elTotalBookings = document.getElementById('statTotalBookings');
        const elTotalPeople = document.getElementById('statTotalPeople');
        const elTotalRevenue = document.getElementById('statTotalRevenue');
        const recentBody = document.getElementById('recentBookingsBody');
        const recentEmpty = document.getElementById('recentBookingsEmpty');

        if (!elTotalBookings || !elTotalPeople || !elTotalRevenue) return;

        const lastNotificationId = 0;

        function formatNumber(n) {
          const num = Number(n) || 0;
          return new Intl.NumberFormat().format(num);
        }

        function formatMoney(n) {
          const num = Number(n) || 0;
          return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
        }

        function setRecentBookings(rows) {
          if (!recentBody) return;
          recentBody.innerHTML = '';
          if (!Array.isArray(rows) || rows.length === 0) {
            if (recentEmpty) recentEmpty.classList.remove('d-none');
            return;
          }

          if (recentEmpty) recentEmpty.classList.add('d-none');

          rows.forEach(r => {
            const status = String(r.payment_status || '').toUpperCase();
            const badge = status === 'PAID'
              ? 'text-bg-success'
              : (status === 'FAILED' ? 'text-bg-danger' : 'text-bg-warning');

            const tr = document.createElement('tr');
            tr.innerHTML =
              '<td class="fw-semibold">' + escapeHtml(r.booking_reference || '') + '</td>' +
              '<td>' + escapeHtml(r.full_name || '') + '</td>' +
              '<td>' + escapeHtml(r.slot_date || '') + '</td>' +
              '<td><span class="badge rounded-pill ' + badge + '">' + escapeHtml(r.payment_status || '') + '</span></td>' +
              '<td>' + formatMoney(r.total_price) + '</td>';
            recentBody.appendChild(tr);
          });
        }

        function escapeHtml(value) {
          return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
        }

        async function poll() {
          try {
            const url = 'admin_live_data.php?since_id=' + encodeURIComponent(lastNotificationId);
            const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            if (!data || !data.ok) return;

            if (data.metrics) {
              elTotalBookings.textContent = formatNumber(data.metrics.total_bookings);
              elTotalPeople.textContent = formatNumber(data.metrics.total_people);
              elTotalRevenue.textContent = formatMoney(data.metrics.revenue);
            }

            if (Array.isArray(data.recent_bookings)) {
              setRecentBookings(data.recent_bookings);
            }
          } catch (e) {
            // ignore
          }
        }

        poll();
        window.setInterval(poll, 8000);
      })();
    </script>
  </body>
</html>
<?php
$mysqli->close();
?>
