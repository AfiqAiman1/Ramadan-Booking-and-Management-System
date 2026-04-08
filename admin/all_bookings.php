<?php
// all_bookings.php - view bookings (manual payment verification)

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin_auth.php';

require_admin_roles(['admin', 'staff', 'assistant', 'banquet', 'finance', 'ENT_ADMIN']);
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
$result = null;
$flashMessage = '';
$flashClass = 'alert-info';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

$summaryResult = null;

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $bookingRef = trim($_POST['booking_reference'] ?? '');
        $decision = strtoupper(trim((string) ($_POST['decision'] ?? '')));
        $rejectReason = trim((string) ($_POST['rejection_reason'] ?? ''));
        $adminUsername = trim((string) ($_SESSION['admin_username'] ?? ''));

        if ($action === 'payment_decision' && $bookingRef !== '') {
            if ($isViewOnly) {
                $flashMessage = 'You are not allowed to approve/reject payments.';
                $flashClass = 'alert-danger';
            } else {
            $csrfTokenPost = (string) ($_POST['csrf_token'] ?? '');
            if (!admin_verify_csrf($csrfTokenPost)) {
                $flashMessage = 'Invalid CSRF token.';
                $flashClass = 'alert-danger';
            } elseif (!in_array($decision, ['APPROVE', 'REJECT'], true)) {
                $flashMessage = 'Please choose Approve or Reject.';
                $flashClass = 'alert-danger';
            } elseif ($decision === 'REJECT' && $rejectReason === '') {
                $flashMessage = 'Please enter rejection reason.';
                $flashClass = 'alert-danger';
            } else {
                $paymentMethodForBooking = '';
                $checkStmt = $mysqli->prepare('SELECT payment_method FROM bookings WHERE booking_reference = ? LIMIT 1');
                if ($checkStmt) {
                    $checkStmt->bind_param('s', $bookingRef);
                    if ($checkStmt->execute()) {
                        $res = $checkStmt->get_result();
                        $row = $res ? $res->fetch_assoc() : null;
                        $paymentMethodForBooking = strtoupper(trim((string) (($row['payment_method'] ?? '') ?: '')));
                        if ($res) {
                            $res->free();
                        }
                    }
                    $checkStmt->close();
                }

                $isManualBooking = ($paymentMethodForBooking === 'MANUAL');

                if ($decision === 'APPROVE') {
                    $paymentProofToSet = null;
                    if ($isManualBooking) {
                        $existingProof = '';
                        $existingStmt = $mysqli->prepare('SELECT payment_proof FROM bookings WHERE booking_reference = ? LIMIT 1');
                        if ($existingStmt) {
                            $existingStmt->bind_param('s', $bookingRef);
                            if ($existingStmt->execute()) {
                                $existingRes = $existingStmt->get_result();
                                $existingRow = $existingRes ? ($existingRes->fetch_assoc() ?: null) : null;
                                $existingProof = trim((string) ($existingRow['payment_proof'] ?? ''));
                                if ($existingRes) {
                                    $existingRes->free();
                                }
                            }
                            $existingStmt->close();
                        }

                        $paymentProofToSet = $existingProof !== '' ? $existingProof : null;

                        if (isset($_FILES['payment_proof']) && is_array($_FILES['payment_proof']) && ($_FILES['payment_proof']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                            $fileInfo = $_FILES['payment_proof'];
                            $uploadError = (int) ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE);

                            if ($uploadError === UPLOAD_ERR_OK) {
                                $maxBytes = 5 * 1024 * 1024;
                                $size = (int) ($fileInfo['size'] ?? 0);
                                if ($size <= 0 || $size > $maxBytes) {
                                    $flashMessage = 'Upload failed: file size must be <= 5MB.';
                                    $flashClass = 'alert-danger';
                                } else {
                                    $allowedMime = [
                                        'image/jpeg' => 'jpg',
                                        'image/png' => 'png',
                                        'application/pdf' => 'pdf',
                                    ];
                                    $tmpName = (string) ($fileInfo['tmp_name'] ?? '');
                                    $detectedMime = '';
                                    if ($tmpName !== '' && is_file($tmpName)) {
                                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                                        $detectedMime = (string) ($finfo->file($tmpName) ?: '');
                                    }

                                    $ext = $allowedMime[$detectedMime] ?? null;
                                    if ($ext === null) {
                                        $flashMessage = 'Unsupported file type. Only JPG, PNG, and PDF are allowed.';
                                        $flashClass = 'alert-danger';
                                    } else {
                                        $uploadDir = __DIR__ . '/../uploads/payment_proof/';
                                        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
                                            $flashMessage = 'Unable to create upload directory.';
                                            $flashClass = 'alert-danger';
                                        } else {
                                            $newFileName = 'APPROVED_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
                                            $targetPath = $uploadDir . $newFileName;
                                            if (!move_uploaded_file($tmpName, $targetPath)) {
                                                $flashMessage = 'Failed to save uploaded file.';
                                                $flashClass = 'alert-danger';
                                            } else {
                                                $paymentProofToSet = 'uploads/payment_proof/' . $newFileName;
                                            }
                                        }
                                    }
                                }
                            } else {
                                $flashMessage = 'Upload failed.';
                                $flashClass = 'alert-danger';
                            }
                        }

                        if ($flashClass !== 'alert-danger' && ($paymentProofToSet === null || $paymentProofToSet === '')) {
                            $flashMessage = 'Payment proof is required for manual bookings.';
                            $flashClass = 'alert-danger';
                        }
                    }

                    if ($flashClass !== 'alert-danger') {
                        if ($isManualBooking) {
                            $stmt = $mysqli->prepare("UPDATE bookings SET payment_status='PAID', paid_at=NOW(), payment_proof=?, rejection_reason=NULL, payment_approved_by=? WHERE booking_reference=? AND payment_status <> 'PAID'");
                            if ($stmt) {
                                $stmt->bind_param('sss', $paymentProofToSet, $adminUsername, $bookingRef);
                            }
                        } else {
                            $stmt = $mysqli->prepare("UPDATE bookings SET payment_status='PAID', paid_at=NOW(), payment_method=NULL, rejection_reason=NULL, payment_approved_by=? WHERE booking_reference=? AND payment_status <> 'PAID'");
                            if ($stmt) {
                                $stmt->bind_param('ss', $adminUsername, $bookingRef);
                            }
                        }
                    } else {
                        $stmt = null;
                    }
                } else {
                    $stmt = $mysqli->prepare("UPDATE bookings SET payment_status='FAILED', paid_at=NULL, payment_method=NULL, rejection_reason=?, payment_approved_by=NULL WHERE booking_reference=? AND payment_status <> 'PAID'");
                    if ($stmt) {
                        $stmt->bind_param('ss', $rejectReason, $bookingRef);
                    }
                }

                if ($stmt) {
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        if ($decision === 'APPROVE') {
                            $flashMessage = 'Payment approved for ' . htmlspecialchars($bookingRef) . '.';
                            $flashClass = 'alert-success';

                            admin_create_notification(
                                $mysqli,
                                'payment_approved',
                                'Payment approved for ' . $bookingRef . '.',
                                $bookingRef
                            );
                        } else {
                            $flashMessage = 'Booking rejected for ' . htmlspecialchars($bookingRef) . '.';
                            $flashClass = 'alert-warning';

                            admin_create_notification(
                                $mysqli,
                                'payment_rejected',
                                'Booking rejected for ' . $bookingRef . '.',
                                $bookingRef,
                                ['reason' => $rejectReason]
                            );
                        }
                    } else {
                        $flashMessage = 'Unable to update payment status. It may already be PAID.';
                        $flashClass = 'alert-warning';
                    }
                    $stmt->close();
                } else {
                    $flashMessage = 'Failed to update payment status.';
                    $flashClass = 'alert-danger';
                }
            }
            }

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => ($flashClass !== 'alert-danger'),
                    'message' => $flashMessage,
                    'booking_reference' => $bookingRef,
                    'decision' => $decision,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } elseif ($action !== '') {
            $flashMessage = 'Invalid action.';
            $flashClass = 'alert-danger';
        }
    }

    if (isset($_GET['ajax_date_details'])) {
        $slotDate = trim((string) ($_GET['slot_date'] ?? ''));
        $detailsStmt = $mysqli->prepare(
            "SELECT booking_reference, full_name, phone, total_price, payment_status, payment_proof, payment_method, rejection_reason, remark, created_at, paid_at FROM bookings WHERE slot_date = ? ORDER BY created_at ASC"
        );
        $rowsHtml = '';
        if ($detailsStmt) {
            $detailsStmt->bind_param('s', $slotDate);
            if ($detailsStmt->execute()) {
                $detailsRes = $detailsStmt->get_result();
                if ($detailsRes && $detailsRes->num_rows > 0) {
                    while ($row = $detailsRes->fetch_assoc()) {
                        $paymentStatus = strtoupper(trim((string) ($row['payment_status'] ?? '')));
                        $paymentMethodLabel = strtoupper(trim((string) ($row['payment_method'] ?? '')));
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
                            $rowsHtml .= '<a class="status-pill bg-primary text-white text-decoration-none" href="../' . htmlspecialchars((string) $row['payment_proof']) . '" target="_blank" rel="noopener noreferrer">View</a>';
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

                        $pillClass = match ($paymentStatus) {
                            'PAID' => 'bg-success text-white',
                            'FAILED' => 'bg-danger text-white',
                            default => 'bg-warning text-dark'
                        };
                        $rowsHtml .= '<td>';
                        $rowsHtml .= '<span class="status-pill ' . $pillClass . '">' . htmlspecialchars((string) ($row['payment_status'] ?? '')) . '</span>';
                        if ($paymentStatus === 'FAILED' && trim((string) ($row['rejection_reason'] ?? '')) !== '') {
                            $rej = (string) ($row['rejection_reason'] ?? '');
                            $rowsHtml .= '<div class="small text-muted mt-1" style="max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="' . htmlspecialchars($rej) . '">' . htmlspecialchars($rej) . '</div>';
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

                        $rowsHtml .= '<td class="text-nowrap">';
                        $refForDownload = (string) ($row['booking_reference'] ?? '');
                        $downloadBtn = '';
                        if ($refForDownload !== '') {
                            $downloadBtn = '<a class="btn btn-sm btn-outline-dark" target="_blank" rel="noopener noreferrer" href="../public/booking_reference.php?ref=' . urlencode($refForDownload) . '&download=1" title="Download booking reference"><i class="bi bi-download"></i></a>';
                        }

                        if ($paymentStatus === 'PENDING') {
                            if ($isViewOnly) {
                                if ($downloadBtn !== '') {
                                    $rowsHtml .= '<div class="d-inline-flex flex-nowrap align-items-center">' . $downloadBtn . '</div>';
                                } else {
                                    $rowsHtml .= '<span class="text-muted">-</span>';
                                }
                            } else {
                                $rowsHtml .= '<div class="d-inline-flex flex-nowrap align-items-center gap-2">';
                                $rowsHtml .= '<button type="button" class="btn btn-sm btn-success btn-approve" data-ref="' . htmlspecialchars((string) ($row['booking_reference'] ?? '')) . '">Approve</button>';
                                $rowsHtml .= $downloadBtn;
                                $rowsHtml .= '</div>';
                            }
                        } else {
                            if ($downloadBtn !== '') {
                                $rowsHtml .= '<div class="d-inline-flex flex-nowrap align-items-center">' . $downloadBtn . '</div>';
                            } else {
                                $rowsHtml .= '<span class="text-muted">-</span>';
                            }
                        }
                        $rowsHtml .= '</td>';

                        $rowsHtml .= '</tr>';
                    }
                } else {
                    $rowsHtml = '<tr><td colspan="8" class="text-center text-muted py-4">No bookings found.</td></tr>';
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
            SUM(CASE WHEN payment_status = 'PENDING' THEN 1 ELSE 0 END) AS total_pending
        FROM bookings
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
    <title>All Bookings - Buffet Ramadan</title>
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
      .btn-approve {
        background: linear-gradient(140deg, #2aa36b, #1f7a4d);
        border: none;
      }
      .btn-approve:hover {
        filter: brightness(1.02);
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
            <a class="nav-link active" href="all_bookings.php"><i class="bi bi-list-ul"></i>All Bookings</a>
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
          <h1 class="h3 text-dark mb-2">All Bookings</h1>
          <p class="text-muted mb-0">Review uploaded payment proofs and approve or reject bookings.</p>
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
                    <th>Remark</th>
                    <th style="min-width: 160px;">Action</th>
                  </tr>
                </thead>
                <tbody id="viewDateTbody">
                  <tr>
                    <td colspan="8" class="text-center text-muted py-4">Loading...</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <form method="POST" id="approveForm" enctype="multipart/form-data" class="d-none">
      <input type="hidden" name="action" value="payment_decision">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="booking_reference" id="approveBookingRef" value="">
      <input type="hidden" name="decision" id="approveDecision" value="">
      <input type="hidden" name="rejection_reason" id="approveRejectionReason" value="">
    </form>

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

    <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1.25rem; border: 1px solid rgba(216, 180, 92, 0.35); overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd; border-bottom: 1px solid rgba(216, 180, 92, 0.25);">
            <h5 class="modal-title" style="font-weight: 700;">
              <i class="bi bi-moon-stars me-2" style="color: #d8b45c;"></i>
              Payment Action
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <div class="d-flex align-items-start gap-3">
              <div class="rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(216, 180, 92, 0.18); color: #08372b;">
                <i class="bi bi-shield-check"></i>
              </div>
              <div>
                <div class="fw-semibold text-dark mb-2">Choose action:</div>
                <div class="d-flex flex-wrap gap-3 mb-3">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="decision_ui" id="decisionApprove" value="APPROVE" checked>
                    <label class="form-check-label" for="decisionApprove">Approve</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="decision_ui" id="decisionReject" value="REJECT">
                    <label class="form-check-label" for="decisionReject">Reject</label>
                  </div>
                </div>

                <div class="mb-3" id="rejectReasonWrap" style="display:none;">
                  <label class="form-label fw-semibold">Reason (required for reject)</label>
                  <textarea class="form-control" id="rejectReasonInput" rows="3" placeholder="Enter reason..."></textarea>
                </div>

                <div class="fw-semibold text-dark">Confirm action for this booking?</div>
                <div class="text-muted small">Booking reference: <span class="fw-semibold" id="approveModalRef"></span></div>
              </div>
            </div>
          </div>
          <div class="modal-footer" style="background: #fff9ed; border-top: 1px solid rgba(8, 55, 43, 0.12);">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" id="confirmApproveBtn" style="background: linear-gradient(140deg, #2aa36b, #1f7a4d); border: none;">Confirm</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
      (function () {
        const approveModalEl = document.getElementById('approveModal');
        const approveModalRef = document.getElementById('approveModalRef');
        const confirmApproveBtn = document.getElementById('confirmApproveBtn');
        const approveForm = document.getElementById('approveForm');
        const approveBookingRef = document.getElementById('approveBookingRef');
        const approveDecision = document.getElementById('approveDecision');
        const approveRejectionReason = document.getElementById('approveRejectionReason');
        const rejectReasonWrap = document.getElementById('rejectReasonWrap');
        const rejectReasonInput = document.getElementById('rejectReasonInput');
        if (!approveModalEl || !confirmApproveBtn || !approveForm || !approveBookingRef) return;

        const modal = new bootstrap.Modal(approveModalEl);
        let currentRef = '';

        function bindApproveButtons(root) {
          if (!root) return;
          root.querySelectorAll('.btn-approve').forEach(btn => {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', () => {
              currentRef = btn.getAttribute('data-ref') || '';
              if (approveModalRef) approveModalRef.textContent = currentRef;
              modal.show();
            });
          });
        }

        bindApproveButtons(document);

        function syncRejectReasonUi() {
          const selected = document.querySelector('input[name="decision_ui"]:checked');
          const value = selected ? (selected.value || 'APPROVE') : 'APPROVE';
          if (rejectReasonWrap) {
            rejectReasonWrap.style.display = value === 'REJECT' ? '' : 'none';
          }
        }

        document.querySelectorAll('input[name="decision_ui"]').forEach(el => {
          el.addEventListener('change', syncRejectReasonUi);
        });

        approveModalEl.addEventListener('shown.bs.modal', syncRejectReasonUi);

        function escapeHtml(str) {
          return String(str)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
        }

        function showPaymentToast(message, ok) {
          const text = String(message || '').trim() || 'Done.';
          const toast = document.createElement('div');
          toast.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger') + ' shadow-sm';
          toast.style.position = 'fixed';
          toast.style.top = '18px';
          toast.style.right = '18px';
          toast.style.zIndex = '1080';
          toast.style.minWidth = '320px';
          toast.style.maxWidth = '520px';
          toast.innerHTML = '<div class="d-flex align-items-start justify-content-between gap-3">'
            + '<div>' + escapeHtml(text) + '</div>'
            + '<button type="button" class="btn-close" aria-label="Close"></button>'
            + '</div>';
          document.body.appendChild(toast);

          const btn = toast.querySelector('.btn-close');
          if (btn) {
            btn.addEventListener('click', () => {
              if (toast && toast.parentNode) toast.parentNode.removeChild(toast);
            });
          }

          setTimeout(() => {
            if (toast && toast.parentNode) toast.parentNode.removeChild(toast);
          }, 4000);
        }

        confirmApproveBtn.addEventListener('click', async () => {
          const refFromText = approveModalRef ? (approveModalRef.textContent || '').trim() : '';
          const refFromHidden = approveBookingRef ? (approveBookingRef.value || '').trim() : '';
          const finalRef = (currentRef || '').trim() || refFromHidden || refFromText;
          approveBookingRef.value = finalRef;
          const selected = document.querySelector('input[name="decision_ui"]:checked');
          const decisionValue = selected ? (selected.value || 'APPROVE') : 'APPROVE';
          approveDecision.value = decisionValue;
          const reasonValue = rejectReasonInput ? (rejectReasonInput.value || '').trim() : '';
          approveRejectionReason.value = reasonValue;
          if (decisionValue === 'REJECT' && reasonValue === '') {
            if (rejectReasonInput) rejectReasonInput.focus();
            return;
          }

          const oldText = confirmApproveBtn.textContent;
          confirmApproveBtn.disabled = true;
          confirmApproveBtn.textContent = 'Processing...';

          try {
            const fd = new FormData(approveForm);
            const res = await fetch('all_bookings.php', {
              method: 'POST',
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
              },
              body: fd,
              cache: 'no-store'
            });

            const data = await res.json();
            const ok = !!(data && data.ok);
            const msg = data && data.message ? data.message : (ok ? 'Done.' : 'Failed.');
            showPaymentToast(msg, ok);

            if (ok) {
              const ref = (data && data.booking_reference) ? String(data.booking_reference) : finalRef;
              document.querySelectorAll('.btn-approve[data-ref="' + CSS.escape(ref) + '"]').forEach(btn => {
                 btn.style.display = 'none';
              });

              const paymentModal = bootstrap.Modal.getInstance(approveModalEl);
              if (paymentModal) {
                paymentModal.hide();
              }
            }
          } catch (e) {
            showPaymentToast('Failed to process request.', false);
          } finally {
            confirmApproveBtn.disabled = false;
            confirmApproveBtn.textContent = oldText;
          }
        });
      })();

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
          viewDateTbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>';
          if (viewDateTitle) viewDateTitle.textContent = formatDate(slotDate);
          try {
            const res = await fetch('all_bookings.php?ajax_date_details=1&slot_date=' + encodeURIComponent(slotDate), {
              headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (!data || data.ok !== true) {
              viewDateTbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Unable to load.</td></tr>';
              return;
            }
            viewDateTbody.innerHTML = data.rows_html || '';

            if (typeof bootstrap !== 'undefined') {
              const tooltipTriggerList = [].slice.call(viewDateModalEl.querySelectorAll('[data-bs-toggle="tooltip"]'));
              tooltipTriggerList.forEach(function (el) {
                new bootstrap.Tooltip(el);
              });
            }

            const approveModalEl = document.getElementById('approveModal');
            const confirmApproveBtn = document.getElementById('confirmApproveBtn');
            if (approveModalEl && confirmApproveBtn) {
              viewDateModalEl.querySelectorAll('.btn-approve').forEach(btn => {
                if (btn.dataset.bound === '1') return;
                btn.dataset.bound = '1';
                btn.addEventListener('click', () => {
                  const ref = btn.getAttribute('data-ref') || '';
                  const approveModalRef = document.getElementById('approveModalRef');
                  if (approveModalRef) approveModalRef.textContent = ref;
                  const approveModal = bootstrap.Modal.getOrCreateInstance(approveModalEl);
                  approveModal.show();
                });
              });
            }
          } catch (e) {
            viewDateTbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Unable to load.</td></tr>';
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
