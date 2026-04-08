<?php
// booking_details.php - admin-only booking details

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
$summaryRows = [];

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
    $settings = load_global_settings($mysqli);

    $startDate = trim((string) ($settings['event_start_date'] ?? '2026-02-21'));
    $endDate = trim((string) ($settings['event_end_date'] ?? '2026-03-19'));

    if (isset($_GET['ajax_date_details'])) {
        $slotDate = trim((string) ($_GET['slot_date'] ?? ''));
        $rowsHtml = '';
        if ($slotDate !== '') {
            $stmt = $mysqli->prepare(
                "SELECT booking_reference, full_name FROM bookings WHERE slot_date = ? ORDER BY created_at ASC"
            );
            if ($stmt) {
                $stmt->bind_param('s', $slotDate);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($res && $res->num_rows > 0) {
                        while ($row = $res->fetch_assoc()) {
                            $ref = (string) ($row['booking_reference'] ?? '');
                            $name = (string) ($row['full_name'] ?? '');
                            $rowsHtml .= '<tr>';
                            $rowsHtml .= '<td>' . htmlspecialchars($ref) . '</td>';
                            $rowsHtml .= '<td>' . htmlspecialchars($name) . '</td>';
                            if ($ref !== '') {
                                $rowsHtml .= '<td class="text-end">'
                                    . '<button type="button" class="btn btn-sm btn-outline-dark js-view-detail" data-ref="' . htmlspecialchars($ref) . '">'
                                    . '<i class="bi bi-eye me-1"></i>View Detail'
                                    . '</button>'
                                    . '</td>';
                            } else {
                                $rowsHtml .= '<td class="text-end"><span class="text-muted">-</span></td>';
                            }
                            $rowsHtml .= '</tr>';
                        }
                    } else {
                        $rowsHtml = '<tr><td colspan="3" class="text-center text-muted py-4">No bookings found.</td></tr>';
                    }
                    if ($res) {
                        $res->free();
                    }
                }
                $stmt->close();
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'slot_date' => $slotDate,
            'rows_html' => $rowsHtml,
        ]);
        exit;
    }

    if (isset($_GET['ajax_booking_detail'])) {
        $bookingRef = trim((string) ($_GET['booking_reference'] ?? ''));
        $detailHtml = '';
        if ($bookingRef !== '') {
            $stmt = $mysqli->prepare(
                "SELECT booking_reference, full_name, phone, email, military_no, slot_date, quantity_dewasa, quantity_kanak, quantity_kanak_foc,
                        quantity_warga_emas, quantity_atm, staff_blanket_qty, living_in_qty, comp_qty,
                        free_quantity_dewasa, free_quantity_kanak, free_quantity_kanak_foc, free_quantity_warga_emas, free_quantity_atm,
                        total_price, payment_status, payment_method, payment_approved_by, bank_received_status, bank_received_by, remark, table_no, created_at, paid_at, pax_added_at, pax_added_by
                 FROM bookings
                 WHERE booking_reference = ?
                 LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('s', $bookingRef);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    if ($row) {
                        $detailHtml .= '<div class="row g-3">';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Booking Reference</div><div>' . htmlspecialchars((string) $row['booking_reference']) . '</div></div>';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Name</div><div>' . htmlspecialchars((string) $row['full_name']) . '</div></div>';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Phone</div><div>' . htmlspecialchars((string) ($row['phone'] ?? '-')) . '</div></div>';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Email</div><div>' . htmlspecialchars((string) ($row['email'] ?? '-')) . '</div></div>';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Military No</div><div>' . htmlspecialchars((string) ($row['military_no'] ?? '-')) . '</div></div>';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Slot Date</div><div>' . htmlspecialchars((string) ($row['slot_date'] ?? '-')) . '</div></div>';

                        $detailHtml .= '<div class="col-12"><hr class="my-2"></div>';

                        $detailHtml .= '<div class="col-md-4"><div class="fw-semibold">Dewasa</div><div>' . number_format((int) ($row['quantity_dewasa'] ?? 0)) . '</div></div>';
                        $detailHtml .= '<div class="col-md-4"><div class="fw-semibold">Kanak-kanak</div><div>' . number_format((int) ($row['quantity_kanak'] ?? 0)) . '</div></div>';
                        $detailHtml .= '<div class="col-md-4"><div class="fw-semibold">Kanak-kanak bawah 6 tahun</div><div>' . number_format((int) ($row['quantity_kanak_foc'] ?? 0)) . '</div></div>';
                        $detailHtml .= '<div class="col-md-4"><div class="fw-semibold">Warga Emas</div><div>' . number_format((int) ($row['quantity_warga_emas'] ?? 0)) . '</div></div>';
                        $detailHtml .= '<div class="col-md-4"><div class="fw-semibold">ATM</div><div>' . number_format((int) ($row['quantity_atm'] ?? 0)) . '</div></div>';
                        $detailHtml .= '<div class="col-md-4"><div class="fw-semibold">Staff Blanket</div><div>' . number_format((int) ($row['staff_blanket_qty'] ?? 0)) . '</div></div>';
                        $detailHtml .= '<div class="col-md-4"><div class="fw-semibold">Living In</div><div>' . number_format((int) ($row['living_in_qty'] ?? 0)) . '</div></div>';
                        $detailHtml .= '<div class="col-md-4"><div class="fw-semibold">COMP</div><div>' . number_format((int) ($row['comp_qty'] ?? 0)) . '</div></div>';

                        $detailHtml .= '<div class="col-12"><hr class="my-2"></div>';

                        $detailHtml .= '<div class="col-md-4"><div class="fw-semibold">Free Dewasa</div><div>' . number_format((int) ($row['free_quantity_dewasa'] ?? 0)) . '</div></div>';
                        $detailHtml .= '<div class="col-md-4"><div class="fw-semibold">Free Kanak</div><div>' . number_format((int) ($row['free_quantity_kanak'] ?? 0)) . '</div></div>';
                        $detailHtml .= '<div class="col-md-4"><div class="fw-semibold">Free Kanak bawah 6</div><div>' . number_format((int) ($row['free_quantity_kanak_foc'] ?? 0)) . '</div></div>';
                        $detailHtml .= '<div class="col-md-4"><div class="fw-semibold">Free Warga</div><div>' . number_format((int) ($row['free_quantity_warga_emas'] ?? 0)) . '</div></div>';
                        $detailHtml .= '<div class="col-md-4"><div class="fw-semibold">Free ATM</div><div>' . number_format((int) ($row['free_quantity_atm'] ?? 0)) . '</div></div>';

                        $detailHtml .= '<div class="col-12"><hr class="my-2"></div>';

                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Total Price (RM)</div><div>' . number_format((float) ($row['total_price'] ?? 0), 2) . '</div></div>';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Payment Status</div><div>' . htmlspecialchars((string) ($row['payment_status'] ?? '-')) . '</div></div>';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Payment Method</div><div>' . htmlspecialchars((string) ($row['payment_method'] ?? '-')) . '</div></div>';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Approved By</div><div>' . htmlspecialchars((string) ($row['payment_approved_by'] ?? '-')) . '</div></div>';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Bank Status</div><div>' . htmlspecialchars((string) ($row['bank_received_status'] ?? '-')) . '</div></div>';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Bank Received By</div><div>' . htmlspecialchars((string) ($row['bank_received_by'] ?? '-')) . '</div></div>';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Table No</div><div>' . htmlspecialchars((string) ($row['table_no'] ?? '-')) . '</div></div>';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Created At</div><div>' . htmlspecialchars((string) ($row['created_at'] ?? '-')) . '</div></div>';
                        $detailHtml .= '<div class="col-md-6"><div class="fw-semibold">Paid At</div><div>' . htmlspecialchars((string) ($row['paid_at'] ?? '-')) . '</div></div>';

                        $logsHtml = '';
                        try {
                            ensure_add_pax_logs_schema($mysqli);
                            $logStmt = $mysqli->prepare('SELECT added_at, added_by, added_quantity_dewasa, added_quantity_kanak, added_quantity_kanak_foc, added_quantity_warga_emas, added_quantity_atm, added_staff_blanket_qty, added_living_in_qty, added_remark FROM add_pax_logs WHERE booking_reference = ? ORDER BY added_at ASC, id ASC');
                            if ($logStmt) {
                                $ref = (string) ($row['booking_reference'] ?? '');
                                $logStmt->bind_param('s', $ref);
                                if ($logStmt->execute()) {
                                    $logRes = $logStmt->get_result();
                                    if ($logRes && $logRes->num_rows > 0) {
                                        $i = 0;
                                        while ($logRow = $logRes->fetch_assoc()) {
                                            $i++;
                                            $addedAt = trim((string) ($logRow['added_at'] ?? ''));
                                            $addedBy = trim((string) ($logRow['added_by'] ?? ''));
                                            $line = ($addedAt !== '' ? $addedAt : '-') . ($addedBy !== '' ? (' (' . $addedBy . ')') : '');

                                            $qtySummary = [];
                                            $qD = (int) ($logRow['added_quantity_dewasa'] ?? 0);
                                            $qK = (int) ($logRow['added_quantity_kanak'] ?? 0);
                                            $qKF = (int) ($logRow['added_quantity_kanak_foc'] ?? 0);
                                            $qW = (int) ($logRow['added_quantity_warga_emas'] ?? 0);
                                            $qA = (int) ($logRow['added_quantity_atm'] ?? 0);
                                            $qSB = (int) ($logRow['added_staff_blanket_qty'] ?? 0);
                                            $qLI = (int) ($logRow['added_living_in_qty'] ?? 0);
                                            if ($qD > 0) { $qtySummary[] = 'AWAM: ' . $qD; }
                                            if ($qK > 0) { $qtySummary[] = 'KANAK: ' . $qK; }
                                            if ($qKF > 0) { $qtySummary[] = 'INFANT: ' . $qKF; }
                                            if ($qW > 0) { $qtySummary[] = 'WARGA: ' . $qW; }
                                            if ($qA > 0) { $qtySummary[] = 'ATM: ' . $qA; }
                                            if ($qSB > 0) { $qtySummary[] = 'STAFF BLANKET: ' . $qSB; }
                                            if ($qLI > 0) { $qtySummary[] = 'LIVING IN: ' . $qLI; }
                                            $qtyText = $qtySummary ? implode(', ', $qtySummary) : '-';

                                            $logsHtml .= '<div class="mb-2">'
                                                . '<div class="fw-semibold">Add Pax ' . $i . '</div>'
                                                . '<div class="text-muted">' . htmlspecialchars($line) . '</div>'
                                                . '<div>' . htmlspecialchars($qtyText) . '</div>';

                                            $addedRemark = trim((string) ($logRow['added_remark'] ?? ''));
                                            if ($addedRemark !== '') {
                                                $logsHtml .= '<div class="small text-muted">' . nl2br(htmlspecialchars($addedRemark)) . '</div>';
                                            }
                                            $logsHtml .= '</div>';
                                        }
                                    }
                                    if ($logRes) {
                                        $logRes->free();
                                    }
                                }
                                $logStmt->close();
                            }
                        } catch (Throwable $e) {
                            $logsHtml = '';
                        }

                        if ($logsHtml !== '') {
                            $detailHtml .= '<div class="col-12"><hr class="my-2"></div>';
                            $detailHtml .= '<div class="col-12"><div class="fw-semibold">Add Pax History</div><div>' . $logsHtml . '</div></div>';
                        }
                        $detailHtml .= '<div class="col-12"><div class="fw-semibold">Remark</div><div>' . htmlspecialchars((string) ($row['remark'] ?? '-')) . '</div></div>';
                        $detailHtml .= '</div>';
                    }
                    if ($res) {
                        $res->free();
                    }
                }
                $stmt->close();
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'booking_reference' => $bookingRef,
            'detail_html' => $detailHtml !== '' ? $detailHtml : '<div class="text-muted">No details found.</div>',
        ]);
        exit;
    }

    $summarySql = "
        SELECT
            slot_date,
            COUNT(*) AS total_bookings
        FROM bookings
        WHERE slot_date BETWEEN ? AND ?
        GROUP BY slot_date
        ORDER BY slot_date ASC
    ";
    $stmt = $mysqli->prepare($summarySql);
    if ($stmt) {
        $stmt->bind_param('ss', $startDate, $endDate);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $summaryRows[] = $row;
            }
            $res->free();
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    die('<h2>Database connection failed.</h2>');
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Details - Buffet Ramadan</title>
    <link rel="icon" type="image/png" href="../assets/img/Logo%20ATM.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <style>
      :root {
        --ramadan-green: #08372b;
        --ramadan-gold: #d8b45c;
        --ramadan-cream: #fff9ed;
      }
      body {
        font-family: 'Cairo', system-ui, sans-serif;
        background: var(--ramadan-cream);
      }
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
      .sidebar .nav-link:hover { background: rgba(216, 180, 92, 0.18); color: var(--ramadan-gold); }
      .logout-btn { background: rgba(220, 53, 69, 0.12); border: 1px solid rgba(220, 53, 69, 0.4); color: #ffb6b6; }
      .main-content { background: var(--ramadan-cream); padding: 2rem; }
      .ramadan-table { border-collapse: separate; border-spacing: 0; }
      .ramadan-table thead th {
        background: linear-gradient(180deg, var(--ramadan-green), #041f18);
        color: #fef6dd;
        border: none;
        padding-top: 0.9rem;
        padding-bottom: 0.9rem;
      }
      .ramadan-table thead th:first-child { border-top-left-radius: 0.85rem; }
      .ramadan-table thead th:last-child { border-top-right-radius: 0.85rem; }
      .ramadan-table tbody tr { background: #ffffff; }
      .ramadan-table tbody tr:nth-child(even) { background: rgba(216, 180, 92, 0.08); }
      .ramadan-table tbody td { border-top: 1px solid rgba(8, 55, 43, 0.08); vertical-align: middle; }
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
          <p class="text-muted small mb-0">Booking Details</p>
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
          <?php if (strtolower(admin_get_role()) === 'admin'): ?>
            <a class="nav-link" href="backup_payment_proofs.php"><i class="bi bi-cloud-download"></i>Backup Proofs</a>
          <?php endif; ?>
          <a class="nav-link active" href="booking_details.php"><i class="bi bi-card-text"></i>Booking Details</a>
          <?php if (strtolower(admin_get_role()) === 'admin'): ?>
            <a class="nav-link" href="settings.php"><i class="bi bi-gear"></i>Settings</a>
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
          <h1 class="h3 text-dark mb-2">Booking Details</h1>
          <p class="text-muted mb-0">View bookings by date and inspect full booking details.</p>
        </header>

        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-body">
            <div class="table-responsive">
              <table class="table align-middle ramadan-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Total Bookings</th>
                    <th class="text-end">View</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($summaryRows)): ?>
                    <?php foreach ($summaryRows as $row): ?>
                      <?php
                        $rawDate = (string) ($row['slot_date'] ?? '');
                        $dateLabel = $rawDate;
                        if ($rawDate !== '') {
                            $dt = DateTime::createFromFormat('Y-m-d', $rawDate);
                            $dateLabel = $dt ? $dt->format('d/m/Y') : $rawDate;
                        }
                      ?>
                      <tr>
                        <td><?= htmlspecialchars($dateLabel !== '' ? $dateLabel : '-') ?></td>
                        <td><?= number_format((int) ($row['total_bookings'] ?? 0)) ?></td>
                        <td class="text-end">
                          <?php if ($rawDate !== ''): ?>
                            <button type="button" class="btn btn-sm btn-outline-dark btn-view-date" data-slot-date="<?= htmlspecialchars($rawDate) ?>">
                              <i class="bi bi-eye me-1"></i>View
                            </button>
                          <?php else: ?>
                            <span class="text-muted">-</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="3" class="text-center text-muted py-4">No bookings found.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>
    </div>

    <div class="modal fade" id="viewDateModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title"><i class="bi bi-calendar3 me-2" style="color:#d8b45c;"></i>Bookings for <span id="viewDateTitle"></span></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <div class="table-responsive">
              <table class="table align-middle ramadan-table mb-0">
                <thead>
                  <tr>
                    <th>Booking Ref</th>
                    <th>Name</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody id="viewDateTbody">
                  <tr>
                    <td colspan="3" class="text-center text-muted py-4">Loading...</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title"><i class="bi bi-card-text me-2" style="color:#d8b45c;"></i>Booking Details</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <div id="detailBody" class="small">Loading...</div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
      (function () {
        const viewDateModalEl = document.getElementById('viewDateModal');
        const viewDateTitle = document.getElementById('viewDateTitle');
        const viewDateTbody = document.getElementById('viewDateTbody');
        const detailModalEl = document.getElementById('detailModal');
        const detailBody = document.getElementById('detailBody');
        if (!viewDateModalEl || !viewDateTbody || !detailModalEl || !detailBody) return;

        const viewModal = new bootstrap.Modal(viewDateModalEl);
        const detailModal = new bootstrap.Modal(detailModalEl);

        function formatDate(iso) {
          if (!iso) return '';
          const parts = String(iso).split('-');
          if (parts.length !== 3) return String(iso);
          return parts[2] + '/' + parts[1] + '/' + parts[0];
        }

        async function loadDate(slotDate) {
          viewDateTbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Loading...</td></tr>';
          if (viewDateTitle) viewDateTitle.textContent = formatDate(slotDate);
          try {
            const res = await fetch('booking_details.php?ajax_date_details=1&slot_date=' + encodeURIComponent(slotDate), {
              headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (!data || data.ok !== true) {
              viewDateTbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Unable to load.</td></tr>';
              return;
            }
            viewDateTbody.innerHTML = data.rows_html || '';
          } catch (e) {
            viewDateTbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Unable to load.</td></tr>';
          }
        }

        async function loadDetail(ref) {
          if (!ref) return;
          detailBody.innerHTML = 'Loading...';
          try {
            const res = await fetch('booking_details.php?ajax_booking_detail=1&booking_reference=' + encodeURIComponent(ref), {
              headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (!data || data.ok !== true) {
              detailBody.innerHTML = '<div class="text-muted">Unable to load.</div>';
              return;
            }
            detailBody.innerHTML = data.detail_html || '<div class="text-muted">No details found.</div>';
          } catch (e) {
            detailBody.innerHTML = '<div class="text-muted">Unable to load.</div>';
          }
        }

        document.querySelectorAll('.btn-view-date').forEach(btn => {
          btn.addEventListener('click', () => {
            const slotDate = btn.getAttribute('data-slot-date') || '';
            if (!slotDate) return;
            loadDate(slotDate);
            viewModal.show();
          });
        });

        viewDateModalEl.addEventListener('click', (e) => {
          const target = e.target;
          const btn = target && target.closest ? target.closest('.js-view-detail') : null;
          if (!btn) return;
          const ref = btn.getAttribute('data-ref') || '';
          loadDetail(ref);
          detailModal.show();
        });
      })();

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
