<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin_auth.php';

require_admin_roles(['admin', 'finance', 'assistant', 'ENT_ADMIN']);
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

$adminRoleUpper = strtoupper(trim((string) admin_get_role()));
$isViewOnly = in_array($adminRoleUpper, ['BANQUET', 'ENT_ADMIN'], true);

$mysqli = null;
$flashMessage = '';
$flashClass = 'alert-info';

$summaryResult = null;

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        $bookingRef = trim((string) ($_POST['booking_reference'] ?? ''));
        $decision = strtoupper(trim((string) ($_POST['decision'] ?? '')));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $adminUsername = trim((string) ($_SESSION['admin_username'] ?? ''));
        $isAjax = ((string) ($_POST['ajax'] ?? '')) === '1';

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        if ($action === 'bank_received_update' && $bookingRef !== '') {
            if ($isViewOnly) {
                $flashMessage = 'You are not allowed to update bank confirmations.';
                $flashClass = 'alert-danger';
                if ($isAjax) {
                    echo json_encode(['ok' => false, 'message' => $flashMessage]);
                    exit;
                }
            } else {
                $csrfTokenPost = (string) ($_POST['csrf_token'] ?? '');
                if (!admin_verify_csrf($csrfTokenPost)) {
                    $flashMessage = 'Invalid CSRF token.';
                    $flashClass = 'alert-danger';
                    if ($isAjax) {
                        echo json_encode(['ok' => false, 'message' => $flashMessage]);
                        exit;
                    }
                } elseif (!in_array($decision, ['CONFIRM', 'NOT_RECEIVED'], true)) {
                    $flashMessage = 'Invalid action.';
                    $flashClass = 'alert-danger';
                    if ($isAjax) {
                        echo json_encode(['ok' => false, 'message' => $flashMessage]);
                        exit;
                    }
                } elseif ($decision === 'NOT_RECEIVED' && $reason === '') {
                    $flashMessage = 'Reason is required for Not received.';
                    $flashClass = 'alert-danger';
                    if ($isAjax) {
                        echo json_encode(['ok' => false, 'message' => $flashMessage]);
                        exit;
                    }
                } else {
                    $newStatus = $decision === 'CONFIRM' ? 'CONFIRMED' : 'NOT_RECEIVED';
                    $newReason = $decision === 'NOT_RECEIVED' ? $reason : null;

                    $stmt = $mysqli->prepare("UPDATE bookings SET bank_received_status=?, bank_not_received_reason=?, bank_confirmed_at=NOW(), bank_received_by=? WHERE booking_reference=?");
                    if ($stmt) {
                        $stmt->bind_param('ssss', $newStatus, $newReason, $adminUsername, $bookingRef);
                        if ($stmt->execute() && $stmt->affected_rows >= 0) {
                            if ($decision === 'CONFIRM') {
                                $flashMessage = 'Bank received confirmed for ' . htmlspecialchars($bookingRef) . '.';
                                $flashClass = 'alert-success';
                            } else {
                                $flashMessage = 'Marked Not received for ' . htmlspecialchars($bookingRef) . '.';
                                $flashClass = 'alert-warning';
                            }

                            if ($isAjax) {
                                echo json_encode(['ok' => true, 'message' => strip_tags($flashMessage)]);
                                $stmt->close();
                                exit;
                            }
                        } else {
                            $flashMessage = 'Unable to update bank received status.';
                            $flashClass = 'alert-danger';

                            if ($isAjax) {
                                echo json_encode(['ok' => false, 'message' => $flashMessage]);
                                $stmt->close();
                                exit;
                            }
                        }
                        $stmt->close();
                    } else {
                        $flashMessage = 'Unable to update bank received status.';
                        $flashClass = 'alert-danger';

                        if ($isAjax) {
                            echo json_encode(['ok' => false, 'message' => $flashMessage]);
                            exit;
                        }
                    }
                }
            }
        }
    }

    if (isset($_GET['ajax_date_details'])) {
        $slotDate = trim((string) ($_GET['slot_date'] ?? ''));
        $detailsStmt = $mysqli->prepare(
            "SELECT booking_reference, full_name, phone, total_price, payment_status, payment_proof, payment_method, remark, bank_received_status, bank_not_received_reason, bank_confirmed_at, created_at FROM bookings WHERE slot_date = ? AND payment_status IN ('PAID','PENDING') ORDER BY created_at ASC"
        );
        $rowsHtml = '';
        if ($detailsStmt) {
            $detailsStmt->bind_param('s', $slotDate);
            if ($detailsStmt->execute()) {
                $detailsRes = $detailsStmt->get_result();
                if ($detailsRes && $detailsRes->num_rows > 0) {
                    while ($row = $detailsRes->fetch_assoc()) {
                        $paymentStatus = strtoupper(trim((string) ($row['payment_status'] ?? '')));
                        $bankStatus = strtoupper(trim((string) ($row['bank_received_status'] ?? 'PENDING')));
                        $bankReason = trim((string) ($row['bank_not_received_reason'] ?? ''));
                        $remark = trim((string) ($row['remark'] ?? ''));

                        $rowsHtml .= '<tr>';
                        $rowsHtml .= '<td>' . htmlspecialchars((string) ($row['booking_reference'] ?? '')) . '</td>';
                        $rowsHtml .= '<td>' . htmlspecialchars((string) ($row['full_name'] ?? '')) . '</td>';
                        $rowsHtml .= '<td>' . htmlspecialchars((string) ($row['phone'] ?? '')) . '</td>';
                        $rowsHtml .= '<td>' . number_format((float) ($row['total_price'] ?? 0), 2) . '</td>';

                        $methodLabel = strtoupper(trim((string) ($row['payment_method'] ?? '')));
                        $hasProof = !empty($row['payment_proof']);
                        $rowsHtml .= '<td>';
                        if ($hasProof) {
                            $proofRaw = trim((string) ($row['payment_proof'] ?? ''));
                            $proofParts = array_values(array_filter(preg_split('/\r\n|\r|\n/', $proofRaw) ?: []));
                            if (!$proofParts) {
                                $rowsHtml .= '<a class="status-pill bg-primary text-white text-decoration-none" href="../' . htmlspecialchars($proofRaw) . '" target="_blank" rel="noopener noreferrer">Proof</a>';
                            } else {
                                foreach ($proofParts as $idx => $proofPath) {
                                    $label = $idx === 0 ? 'View' : 'Add Proof';
                                    if ($idx > 0) {
                                        $rowsHtml .= '<div class="mt-1">';
                                    }
                                    $rowsHtml .= '<a class="status-pill bg-primary text-white text-decoration-none" href="../' . htmlspecialchars((string) $proofPath) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($label) . '</a>';
                                    if ($idx > 0) {
                                        $rowsHtml .= '</div>';
                                    }
                                }
                            }
                            if ($methodLabel !== '' && $methodLabel !== 'MANUAL') {
                                $rowsHtml .= ' <span class="badge text-bg-light ms-2">' . htmlspecialchars($methodLabel) . '</span>';
                            }
                        } else {
                            if ($methodLabel !== '' && $methodLabel !== 'MANUAL') {
                                $rowsHtml .= '<span class="badge text-bg-light">' . htmlspecialchars($methodLabel) . '</span>';
                            } else {
                                $rowsHtml .= '<span class="text-muted">-</span>';
                            }
                        }
                        $rowsHtml .= '</td>';

                        $payPill = match ($paymentStatus) {
                            'PAID' => 'bg-success text-white',
                            'FAILED' => 'bg-danger text-white',
                            default => 'bg-warning text-dark'
                        };
                        $rowsHtml .= '<td><span class="status-pill ' . $payPill . '">' . htmlspecialchars((string) ($row['payment_status'] ?? '')) . '</span></td>';

                        $bankPill = match ($bankStatus) {
                            'CONFIRMED' => 'bg-success text-white',
                            'NOT_RECEIVED' => 'bg-danger text-white',
                            default => 'bg-warning text-dark'
                        };
                        $rowsHtml .= '<td>';
                        $rowsHtml .= '<span class="status-pill ' . $bankPill . '">' . htmlspecialchars($bankStatus) . '</span>';
                        if ($bankStatus === 'NOT_RECEIVED' && $bankReason !== '') {
                            $rowsHtml .= '<div class="small text-muted mt-1" style="max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="' . htmlspecialchars($bankReason) . '">' . htmlspecialchars($bankReason) . '</div>';
                        }
                        $rowsHtml .= '</td>';

                        $rowsHtml .= '<td>';
                        if ($remark !== '') {
                            $rowsHtml .= '<span class="d-print-none" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars($remark) . '" style="display:inline-block; max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: help;">' . htmlspecialchars($remark) . '</span>';
                            $rowsHtml .= '<span class="d-none d-print-inline-block" style="white-space: normal; word-break: break-word;">' . htmlspecialchars($remark) . '</span>';
                        } else {
                            $rowsHtml .= '<span class="text-muted">-</span>';
                        }
                        $rowsHtml .= '</td>';

                        $rowsHtml .= '<td>';
                        if ($paymentStatus === 'PAID') {
                            if ($isViewOnly) {
                                $rowsHtml .= '<span class="text-muted">-</span>';
                            } else {
                                $rowsHtml .= '<button type="button" class="btn btn-sm btn-outline-dark btn-bank-action" data-ref="' . htmlspecialchars((string) ($row['booking_reference'] ?? '')) . '">Action</button>';
                            }
                        } else {
                            $rowsHtml .= '<span class="text-muted">-</span>';
                        }
                        $rowsHtml .= '</td>';

                        $rowsHtml .= '</tr>';
                    }
                } else {
                    $rowsHtml = '<tr><td colspan="9" class="text-center text-muted py-4">No bookings found.</td></tr>';
                }
                if ($detailsRes) {
                    $detailsRes->free();
                }
            }
            $detailsStmt->close();
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'slot_date' => $slotDate,
            'rows_html' => $rowsHtml,
        ]);
        exit;
    }

    $summarySql = "
        SELECT
            slot_date,
            COUNT(*) AS total_bookings,
            SUM(CASE WHEN payment_status = 'PAID' THEN 1 ELSE 0 END) AS total_paid,
            SUM(CASE WHEN payment_status = 'PENDING' THEN 1 ELSE 0 END) AS total_pending,
            SUM(CASE WHEN bank_received_status = 'PENDING' THEN 1 ELSE 0 END) AS total_bank_pending
        FROM bookings
        WHERE payment_status IN ('PAID','PENDING')
        GROUP BY slot_date
        ORDER BY slot_date ASC
    ";
    $summaryResult = $mysqli->query($summarySql);
} catch (Throwable $e) {
    die('<h2>Database connection failed.</h2>');
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Finance Confirm - Buffet Ramadan</title>
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
        padding: 2rem;
      }
      .status-pill {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 999px;
        font-size: 0.85rem;
      }
      .ramadan-table {
        border-collapse: separate;
        border-spacing: 0;
      }
      .ramadan-table thead th {
        background: linear-gradient(180deg, var(--ramadan-green), #041f18);
        color: #fef6dd;
        border: none;
        padding-top: 0.9rem;
        padding-bottom: 0.9rem;
      }
      .ramadan-table thead th:first-child {
        border-top-left-radius: 0.85rem;
      }
      .ramadan-table thead th:last-child {
        border-top-right-radius: 0.85rem;
      }
      .ramadan-table tbody tr {
        background: #ffffff;
      }
      .ramadan-table tbody tr:nth-child(even) {
        background: rgba(216, 180, 92, 0.08);
      }
      .ramadan-table tbody td {
        border-top: 1px solid rgba(8, 55, 43, 0.08);
        vertical-align: middle;
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
          <?php if (in_array($adminRole, ['ADMIN', 'STAFF', 'FINANCE', 'BANQUET', 'ASSISTANT', 'ENTRY_DUTY', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="list_guests.php"><i class="bi bi-people"></i>Name List</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'BANQUET'], true)): ?>
            <a class="nav-link" href="table_no.php"><i class="bi bi-table"></i>Table No</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'STAFF', 'FINANCE', 'ASSISTANT', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-line"></i>Reports</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'FINANCE', 'ASSISTANT', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link active" href="finance_confirm.php"><i class="bi bi-bank2"></i>Finance Confirm</a>
          <?php endif; ?>
          <?php if ($adminRole === 'ENT_ADMIN'): ?>
            <a class="nav-link" href="../ent_home.php"><i class="bi bi-box-arrow-in-right"></i>ENT</a>
          <?php endif; ?>
          <?php if ($adminRole === 'ADMIN'): ?>
            <a class="nav-link" href="booking_slots.php"><i class="bi bi-calendar-event"></i>Booking Slots</a>
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
          <h1 class="h3 text-dark mb-2">Finance Confirm</h1>
          <p class="text-muted mb-0">Double confirm whether the payment is received in the bank account.</p>
        </header>

        <?php if ($flashMessage): ?>
          <div class="alert <?= $flashClass ?> alert-dismissible fade show" role="alert">
            <?= $flashMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-body">
            <div class="table-responsive">
              <table class="table align-middle ramadan-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Total Booking</th>
                    <th>Payment Paid</th>
                    <th>Payment Pending</th>
                    <th>Bank Received Pending</th>
                    <th>View</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($summaryResult && $summaryResult->num_rows > 0): ?>
                    <?php while ($srow = $summaryResult->fetch_assoc()): ?>
                      <tr>
                        <td>
                          <?php
                            $dateLabel = '';
                            $rawDate = (string) ($srow['slot_date'] ?? '');
                            if ($rawDate !== '') {
                                $dt = DateTime::createFromFormat('Y-m-d', $rawDate);
                                $dateLabel = $dt ? $dt->format('d/m/Y') : $rawDate;
                            }
                            echo htmlspecialchars($dateLabel !== '' ? $dateLabel : '-');
                          ?>
                        </td>
                        <td><?= (int) ($srow['total_bookings'] ?? 0) ?></td>
                        <td><?= (int) ($srow['total_paid'] ?? 0) ?></td>
                        <td><?= (int) ($srow['total_pending'] ?? 0) ?></td>
                        <td><?= (int) ($srow['total_bank_pending'] ?? 0) ?></td>
                        <td>
                          <?php if (!empty($srow['slot_date'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-dark btn-view-date" data-slot-date="<?= htmlspecialchars((string) $srow['slot_date']) ?>">View</button>
                          <?php else: ?>
                            <span class="text-muted">-</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" class="text-center text-muted py-4">No bookings found.</td>
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
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
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
                    <th>Phone No</th>
                    <th>Total (RM)</th>
                    <th>Payment Proof</th>
                    <th>Payment Status</th>
                    <th>Bank Received</th>
                    <th>Remark</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody id="viewDateTbody">
                  <tr>
                    <td colspan="9" class="text-center text-muted py-4">Loading...</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <form method="POST" id="bankActionForm" class="d-none">
      <input type="hidden" name="action" value="bank_received_update">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="booking_reference" id="bankBookingRef" value="">
      <input type="hidden" name="decision" id="bankDecision" value="">
      <input type="hidden" name="reason" id="bankReason" value="">
    </form>

    <div class="modal fade" id="bankActionModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title"><i class="bi bi-shield-check me-2" style="color:#d8b45c;"></i>Bank Action</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <div class="mb-2 text-muted small">Booking reference: <span class="fw-semibold" id="bankActionRef"></span></div>

            <div class="d-flex flex-wrap gap-3 mb-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="bank_action_decision" id="bankDecisionConfirm" value="CONFIRM" checked>
                <label class="form-check-label" for="bankDecisionConfirm">Confirm</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="bank_action_decision" id="bankDecisionNotReceived" value="NOT_RECEIVED">
                <label class="form-check-label" for="bankDecisionNotReceived">Not received</label>
              </div>
            </div>

            <div class="mb-2" id="bankActionReasonWrap" style="display:none;">
              <label class="form-label fw-semibold">Reason (required)</label>
              <textarea class="form-control" id="bankActionReasonInput" rows="3" placeholder="Enter reason..."></textarea>
            </div>
          </div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" id="confirmBankActionBtn">Submit</button>
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
        if (!viewDateModalEl || !viewDateTbody) return;

        const viewModal = new bootstrap.Modal(viewDateModalEl);

        function formatDate(iso) {
          if (!iso) return '';
          const parts = String(iso).split('-');
          if (parts.length !== 3) return String(iso);
          return parts[2] + '/' + parts[1] + '/' + parts[0];
        }

        async function loadDate(slotDate) {
          viewDateTbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Loading...</td></tr>';
          if (viewDateTitle) viewDateTitle.textContent = formatDate(slotDate);
          try {
            const res = await fetch('finance_confirm.php?ajax_date_details=1&slot_date=' + encodeURIComponent(slotDate), {
              headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (!data || data.ok !== true) {
              viewDateTbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Unable to load.</td></tr>';
              return;
            }
            viewDateTbody.innerHTML = data.rows_html || '';

            if (typeof bootstrap !== 'undefined') {
              const tooltipTriggerList = [].slice.call(viewDateModalEl.querySelectorAll('[data-bs-toggle="tooltip"]'));
              tooltipTriggerList.forEach(function (el) {
                new bootstrap.Tooltip(el);
              });
            }

            bindRowActions();
          } catch (e) {
            viewDateTbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Unable to load.</td></tr>';
          }
        }

        let currentSlotDate = '';

        document.querySelectorAll('.btn-view-date').forEach(btn => {
          btn.addEventListener('click', () => {
            const slotDate = btn.getAttribute('data-slot-date') || '';
            if (!slotDate) return;
            currentSlotDate = slotDate;
            loadDate(slotDate);
            viewModal.show();
          });
        });

        const bankForm = document.getElementById('bankActionForm');
        const bankBookingRef = document.getElementById('bankBookingRef');
        const bankDecision = document.getElementById('bankDecision');
        const bankReason = document.getElementById('bankReason');

        const bankActionModalEl = document.getElementById('bankActionModal');
        const bankActionRef = document.getElementById('bankActionRef');
        const bankActionReasonWrap = document.getElementById('bankActionReasonWrap');
        const bankActionReasonInput = document.getElementById('bankActionReasonInput');
        const confirmBankActionBtn = document.getElementById('confirmBankActionBtn');

        let currentRef = '';
        const bankActionModal = bankActionModalEl ? new bootstrap.Modal(bankActionModalEl) : null;

        function getSelectedDecision() {
          const selected = document.querySelector('input[name="bank_action_decision"]:checked');
          return selected ? (selected.value || 'CONFIRM') : 'CONFIRM';
        }

        function syncReasonVisibility() {
          const decisionValue = getSelectedDecision();
          if (bankActionReasonWrap) {
            bankActionReasonWrap.style.display = decisionValue === 'NOT_RECEIVED' ? '' : 'none';
          }
        }

        function bindRowActions() {
          if (!bankForm || !bankBookingRef || !bankDecision || !bankReason) return;

          viewDateModalEl.querySelectorAll('.btn-bank-action').forEach(btn => {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', () => {
              currentRef = btn.getAttribute('data-ref') || '';
              if (bankActionRef) bankActionRef.textContent = currentRef;
              if (bankActionReasonInput) bankActionReasonInput.value = '';
              const confirmRadio = document.getElementById('bankDecisionConfirm');
              if (confirmRadio) {
                confirmRadio.checked = true;
              }
              syncReasonVisibility();
              if (bankActionModal) bankActionModal.show();
            });
          });
        }

        document.querySelectorAll('input[name="bank_action_decision"]').forEach(el => {
          el.addEventListener('change', syncReasonVisibility);
        });

        if (confirmBankActionBtn) {
          confirmBankActionBtn.addEventListener('click', async () => {
            if (!bankForm || !bankBookingRef || !bankDecision || !bankReason) return;

            const decisionValue = getSelectedDecision();
            const reasonValue = bankActionReasonInput ? (bankActionReasonInput.value || '').trim() : '';

            if (decisionValue === 'NOT_RECEIVED' && reasonValue === '') {
              if (bankActionReasonInput) bankActionReasonInput.focus();
              return;
            }

            bankBookingRef.value = currentRef;
            bankDecision.value = decisionValue;
            bankReason.value = decisionValue === 'NOT_RECEIVED' ? reasonValue : '';

            confirmBankActionBtn.disabled = true;
            try {
              const fd = new FormData(bankForm);
              fd.append('ajax', '1');
              const res = await fetch('finance_confirm.php', {
                method: 'POST',
                body: fd,
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
              });
              const data = await res.json();
              if (!res.ok || !data || data.ok !== true) {
                return;
              }

              if (bankActionModal) {
                bankActionModal.hide();
              }

              const actionBtnList = viewDateModalEl.querySelectorAll('.btn-bank-action');
              let actionBtn = null;
              actionBtnList.forEach((b) => {
                if (actionBtn) return;
                if ((b.getAttribute('data-ref') || '') === currentRef) actionBtn = b;
              });

              const rowEl = actionBtn && actionBtn.closest ? actionBtn.closest('tr') : null;
              if (rowEl) {
                const tds = rowEl.querySelectorAll('td');
                const bankCell = tds.length >= 7 ? tds[6] : null;
                const actionCell = tds.length >= 9 ? tds[8] : null;

                if (bankCell) {
                  const statusUpper = String(decisionValue || '').toUpperCase() === 'NOT_RECEIVED' ? 'NOT_RECEIVED' : 'CONFIRMED';
                  const pillClass = statusUpper === 'CONFIRMED' ? 'bg-success text-white' : 'bg-danger text-white';
                  bankCell.innerHTML = '';

                  const pill = document.createElement('span');
                  pill.className = 'status-pill ' + pillClass;
                  pill.textContent = statusUpper;
                  bankCell.appendChild(pill);

                  if (statusUpper === 'NOT_RECEIVED' && reasonValue) {
                    const reasonDiv = document.createElement('div');
                    reasonDiv.className = 'small text-muted mt-1';
                    reasonDiv.style.maxWidth = '260px';
                    reasonDiv.style.whiteSpace = 'nowrap';
                    reasonDiv.style.overflow = 'hidden';
                    reasonDiv.style.textOverflow = 'ellipsis';
                    reasonDiv.title = reasonValue;
                    reasonDiv.textContent = reasonValue;
                    bankCell.appendChild(reasonDiv);
                  }
                }

                if (actionCell) {
                  actionCell.innerHTML = '<span class="text-muted">-</span>';
                } else if (actionBtn) {
                  actionBtn.remove();
                }
              }
            } catch (e) {
              // ignore
            } finally {
              confirmBankActionBtn.disabled = false;
            }
          });
        }
      })();

      (function () {
        const btn = document.getElementById('confirmLogoutBtn');
        const form = document.getElementById('logoutForm');
        if (!btn || !form) return;
        btn.addEventListener('click', () => form.submit());
      })();

      (function () {
        if (typeof bootstrap === 'undefined') return;
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (el) {
          new bootstrap.Tooltip(el);
        });
      })();
    </script>
  </body>
</html>
<?php
if ($summaryResult) {
    $summaryResult->free();
}
if ($mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
