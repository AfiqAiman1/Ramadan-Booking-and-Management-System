<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin_auth.php';

require_admin_roles(['admin']);

$mysqli = null;
$csrfToken = admin_csrf_token();

$sidebarRoleLabel = match (strtolower(admin_get_role())) {
    'banquet' => 'Banquet',
    'assistant' => 'Assistant',
    'staff' => 'Sales',
    'entry_duty' => 'Entry Staff',
    default => 'Admin',
};

$flashMessage = '';
$flashClass   = 'alert-info';

$mysqli = null;
$settings = [];
$adminUsers = [];
$generatedPassword = '';

function store_uploaded_qr(array $fileInfo, string &$errorMessage, string &$relativePath): void
{
    $errorMessage = '';
    $relativePath = '';

    if (($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errorMessage = 'Please choose a QR image file.';
        return;
    }
    if (($fileInfo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errorMessage = 'Error uploading QR image.';
        return;
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
    ];
    $allowedExtensions = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ];

    $maxFileSize = 3 * 1024 * 1024;
    if (($fileInfo['size'] ?? 0) > $maxFileSize) {
        $errorMessage = 'QR image must be less than 3MB.';
        return;
    }

    $detectedExtension = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file((string) ($fileInfo['tmp_name'] ?? ''));
        if (isset($allowedMime[$mimeType])) {
            $detectedExtension = $allowedMime[$mimeType];
        }
    } else {
        $originalExt = strtolower(pathinfo((string) ($fileInfo['name'] ?? ''), PATHINFO_EXTENSION));
        if (isset($allowedExtensions[$originalExt])) {
            $detectedExtension = $originalExt === 'jpeg' ? 'jpg' : $originalExt;
        }
    }

    if ($detectedExtension === null) {
        $errorMessage = 'Unsupported file type. Only JPG and PNG are allowed.';
        return;
    }

    $uploadDir = __DIR__ . '/../uploads/payment_qr/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        $errorMessage = 'Unable to create upload folder.';
        return;
    }

    $newFileName = 'payment_qr_' . date('Ymd_His') . '.' . $detectedExtension;
    $targetPath = $uploadDir . $newFileName;
    if (!move_uploaded_file((string) ($fileInfo['tmp_name'] ?? ''), $targetPath)) {
        $errorMessage = 'Failed to save uploaded QR image.';
        return;
    }

    $relativePath = 'uploads/payment_qr/' . $newFileName;
}

function sanitize_price($value, float $fallback): float
{
    $float = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($float === false || $float < 0) {
        return $fallback;
    }
    return round((float) $float, 2);
}

function normalize_date(?string $date): ?string
{
    if (!$date) {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('Y-m-d') : null;
}

function normalize_time(?string $time): ?string
{
    if (!$time) {
        return null;
    }
    $dt = DateTime::createFromFormat('H:i', $time) ?: DateTime::createFromFormat('H:i:s', $time);
    return $dt ? $dt->format('H:i:s') : null;
}

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
    ensure_booking_slots_schema($mysqli);
    $settings = load_global_settings($mysqli);
    ensure_admin_users_schema($mysqli);
} catch (Throwable $e) {
    die('<h2>Database connection failed.</h2>');
}

$slotRows = [];
if ($res = $mysqli->query('SELECT slot_date, max_capacity, booked_count, is_locked, locked_prev_capacity FROM booking_slots ORDER BY slot_date ASC')) {
    while ($row = $res->fetch_assoc()) {
        $slotRows[] = $row;
    }
    $res->free();
}

if ($res = $mysqli->query('SELECT id, username, role, is_active, created_at FROM admin_users ORDER BY id ASC')) {
    while ($row = $res->fetch_assoc()) {
        $adminUsers[] = $row;
    }
    $res->free();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfPost = (string) ($_POST['csrf_token'] ?? '');
    if (!admin_verify_csrf($csrfPost)) {
        $flashMessage = 'Invalid CSRF token.';
        $flashClass = 'alert-danger';
    } else {
    $formType = $_POST['form_type'] ?? '';
    $payload = [];

    $isAjax = ((string) ($_POST['ajax'] ?? '')) === '1';
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    if ($formType === 'event_info') {
        $eventName = trim($_POST['event_name'] ?? ($settings['event_name'] ?? ''));
        $eventVenue = trim($_POST['event_venue'] ?? ($settings['event_venue'] ?? ''));
        $eventYear = (int) ($_POST['event_year'] ?? ($settings['event_year'] ?? 0));
        $startDate = normalize_date($_POST['event_start_date'] ?? ($settings['event_start_date'] ?? ''));
        $endDate = normalize_date($_POST['event_end_date'] ?? ($settings['event_end_date'] ?? ''));

        if ($eventName === '' || $eventVenue === '') {
            $flashMessage = 'Event name and venue are required.';
            $flashClass = 'alert-danger';
        } elseif ($eventYear <= 0) {
            $flashMessage = 'Event year must be valid.';
            $flashClass = 'alert-danger';
        } elseif (!$startDate || !$endDate) {
            $flashMessage = 'Please provide valid start and end dates.';
            $flashClass = 'alert-danger';
        } elseif ($startDate > $endDate) {
            $flashMessage = 'Event start date must be before end date.';
            $flashClass = 'alert-danger';
        } else {
            $payload = [
                'event_name' => $eventName,
                'event_venue' => $eventVenue,
                'event_year' => $eventYear,
                'event_start_date' => $startDate,
                'event_end_date' => $endDate,
            ];
        }
    } elseif ($formType === 'pricing') {
        $payload = [
            'price_dewasa' => sanitize_price($_POST['price_dewasa'] ?? ($settings['price_dewasa'] ?? 95.0), (float) ($settings['price_dewasa'] ?? 95.0)),
            'price_kanak'  => sanitize_price($_POST['price_kanak'] ?? ($settings['price_kanak'] ?? 70.0), (float) ($settings['price_kanak'] ?? 70.0)),
            'price_warga'  => sanitize_price($_POST['price_warga'] ?? ($settings['price_warga'] ?? 85.0), (float) ($settings['price_warga'] ?? 85.0)),
        ];
    } elseif ($formType === 'payment_info') {
        $payload = [
            'payment_method_name' => trim($_POST['payment_method_name'] ?? ($settings['payment_method_name'] ?? '')),
            'payment_bank_name' => trim($_POST['payment_bank_name'] ?? ($settings['payment_bank_name'] ?? '')),
            'payment_account_holder' => trim($_POST['payment_account_holder'] ?? ($settings['payment_account_holder'] ?? '')),
            'payment_instructions' => trim($_POST['payment_instructions'] ?? ($settings['payment_instructions'] ?? '')),
        ];
    } elseif ($formType === 'generate_password') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $flashMessage = 'Invalid user.';
            $flashClass = 'alert-danger';

            if ($isAjax) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => $flashMessage], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            $stmt = $mysqli->prepare('SELECT id, role, is_active FROM admin_users WHERE id = ? LIMIT 1');
            if (!$stmt) {
                $flashMessage = 'Unable to update password right now.';
                $flashClass = 'alert-danger';

                if ($isAjax) {
                    http_response_code(500);
                    echo json_encode(['ok' => false, 'message' => $flashMessage], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            } else {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $id = 0;
                $role = '';
                $isActive = 0;
                $stmt->bind_result($id, $role, $isActive);
                $user = $stmt->fetch() ? ['id' => $id, 'role' => $role, 'is_active' => $isActive] : null;
                $stmt->close();

                if (!$user || empty($user['is_active'])) {
                    $flashMessage = 'User not found or inactive.';
                    $flashClass = 'alert-danger';

                    if ($isAjax) {
                        http_response_code(404);
                        echo json_encode(['ok' => false, 'message' => $flashMessage], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                } elseif (strtolower(trim((string) ($user['role'] ?? ''))) !== 'entry_duty') {
                    $flashMessage = 'Password generation is only allowed for entry_duty users.';
                    $flashClass = 'alert-danger';

                    if ($isAjax) {
                        http_response_code(403);
                        echo json_encode(['ok' => false, 'message' => $flashMessage], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                } else {
                    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
                    $plain = '';
                    for ($i = 0; $i < 10; $i++) {
                        $plain .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                    }
                    $hash = password_hash($plain, PASSWORD_DEFAULT);

                    $validFrom = '17:00:00';
                    $validUntil = '23:59:59';
                    $up = $mysqli->prepare('UPDATE admin_users SET password_hash = ?, password_valid_from = ?, password_valid_until = ? WHERE id = ?');
                    if ($up) {
                        $up->bind_param('sssi', $hash, $validFrom, $validUntil, $userId);
                        if ($up->execute()) {
                            $generatedPassword = $plain;
                            $flashMessage = 'New password generated successfully.';
                            $flashClass = 'alert-success';

                            if ($isAjax) {
                                echo json_encode([
                                    'ok' => true,
                                    'password' => $plain,
                                    'message' => $flashMessage,
                                ], JSON_UNESCAPED_UNICODE);
                                $up->close();
                                exit;
                            }
                        } else {
                            $flashMessage = 'Failed to update password.';
                            $flashClass = 'alert-danger';

                            if ($isAjax) {
                                http_response_code(500);
                                echo json_encode(['ok' => false, 'message' => $flashMessage], JSON_UNESCAPED_UNICODE);
                                $up->close();
                                exit;
                            }
                        }
                        $up->close();
                    } else {
                        $flashMessage = 'Failed to update password.';
                        $flashClass = 'alert-danger';

                        if ($isAjax) {
                            http_response_code(500);
                            echo json_encode(['ok' => false, 'message' => $flashMessage], JSON_UNESCAPED_UNICODE);
                            exit;
                        }
                    }
                }
            }
        }
    } elseif ($formType === 'reset_password') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newPassword = trim($newPassword);

        if ($userId <= 0) {
            $flashMessage = 'Invalid user.';
            $flashClass = 'alert-danger';
        } elseif ($newPassword === '') {
            $flashMessage = 'Password is required.';
            $flashClass = 'alert-danger';
        } else {
            $stmt = $mysqli->prepare('SELECT id, role, is_active, password_valid_from, password_valid_until FROM admin_users WHERE id = ? LIMIT 1');
            if (!$stmt) {
                $flashMessage = 'Unable to update password right now.';
                $flashClass = 'alert-danger';
            } else {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $res = $stmt->get_result();
                $user = $res ? ($res->fetch_assoc() ?: null) : null;
                if ($res) {
                    $res->free();
                }
                $stmt->close();

                if (!$user || empty($user['is_active'])) {
                    $flashMessage = 'User not found or inactive.';
                    $flashClass = 'alert-danger';
                } else {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $roleLower = strtolower(trim((string) ($user['role'] ?? '')));
                    $validFrom = trim((string) ($user['password_valid_from'] ?? ''));
                    $validUntil = trim((string) ($user['password_valid_until'] ?? ''));

                    if ($roleLower === 'entry_duty' && ($validFrom === '' || $validUntil === '')) {
                        $validFrom = '17:00:00';
                        $validUntil = '23:59:59';
                    }

                    $up = $mysqli->prepare('UPDATE admin_users SET password_hash = ?, password_valid_from = ?, password_valid_until = ? WHERE id = ?');
                    if ($up) {
                        $up->bind_param('sssi', $hash, $validFrom, $validUntil, $userId);
                        if ($up->execute()) {
                            $flashMessage = 'Password reset successfully.';
                            $flashClass = 'alert-success';
                        } else {
                            $flashMessage = 'Failed to reset password.';
                            $flashClass = 'alert-danger';
                        }
                        $up->close();
                    } else {
                        $flashMessage = 'Failed to reset password.';
                        $flashClass = 'alert-danger';
                    }
                }
            }
        }
    } elseif ($formType === 'payment_qr') {
        $uploadError = '';
        $relativePath = '';
        store_uploaded_qr($_FILES['payment_qr'] ?? [], $uploadError, $relativePath);

        if ($uploadError !== '') {
            $flashMessage = $uploadError;
            $flashClass = 'alert-danger';
        } else {
            $payload = [
                'payment_qr_path' => $relativePath,
            ];
        }
    } elseif ($formType === 'slot_lock_toggle') {
        $slotDateRaw = (string) ($_POST['slot_date'] ?? '');
        $slotDate = normalize_date($slotDateRaw);
        $action = strtoupper(trim((string) ($_POST['action'] ?? '')));

        if ($slotDate === '' || $slotDate === null) {
            $flashMessage = 'Invalid slot date.';
            $flashClass = 'alert-danger';
            if ($isAjax) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => $flashMessage], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } elseif (!in_array($action, ['LOCK', 'UNLOCK'], true)) {
            $flashMessage = 'Invalid action.';
            $flashClass = 'alert-danger';
            if ($isAjax) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => $flashMessage], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            $stmt = $mysqli->prepare('SELECT slot_date, max_capacity, booked_count, is_locked, locked_prev_capacity FROM booking_slots WHERE slot_date = ? LIMIT 1');
            if (!$stmt) {
                $flashMessage = 'Unable to update slot right now.';
                $flashClass = 'alert-danger';
                if ($isAjax) {
                    http_response_code(500);
                    echo json_encode(['ok' => false, 'message' => $flashMessage], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            } else {
                $stmt->bind_param('s', $slotDate);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? ($res->fetch_assoc() ?: null) : null;
                if ($res) {
                    $res->free();
                }
                $stmt->close();

                if (!$row) {
                    $flashMessage = 'Slot not found.';
                    $flashClass = 'alert-danger';
                    if ($isAjax) {
                        http_response_code(404);
                        echo json_encode(['ok' => false, 'message' => $flashMessage], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                } else {
                    $maxCap = (int) ($row['max_capacity'] ?? 0);
                    $booked = (int) ($row['booked_count'] ?? 0);
                    $isLocked = !empty($row['is_locked']);
                    $prevCap = (int) ($row['locked_prev_capacity'] ?? 0);

                    if ($action === 'LOCK') {
                        if ($isLocked) {
                            $flashMessage = 'Slot is already locked.';
                            $flashClass = 'alert-warning';
                        } else {
                            $up = $mysqli->prepare('UPDATE booking_slots SET is_locked = 1, locked_prev_capacity = ?, max_capacity = ? WHERE slot_date = ?');
                            if ($up) {
                                $up->bind_param('iis', $maxCap, $booked, $slotDate);
                                if ($up->execute()) {
                                    $flashMessage = 'Slot locked successfully.';
                                    $flashClass = 'alert-success';
                                    $isLocked = true;
                                    $prevCap = $maxCap;
                                    $maxCap = $booked;
                                } else {
                                    $flashMessage = 'Failed to lock slot.';
                                    $flashClass = 'alert-danger';
                                }
                                $up->close();
                            } else {
                                $flashMessage = 'Failed to lock slot.';
                                $flashClass = 'alert-danger';
                            }
                        }
                    } else {
                        if (!$isLocked) {
                            $flashMessage = 'Slot is already unlocked.';
                            $flashClass = 'alert-warning';
                        } else {
                            $restoreCap = $prevCap > 0 ? $prevCap : $maxCap;
                            $up = $mysqli->prepare('UPDATE booking_slots SET is_locked = 0, locked_prev_capacity = 0, max_capacity = ? WHERE slot_date = ?');
                            if ($up) {
                                $up->bind_param('is', $restoreCap, $slotDate);
                                if ($up->execute()) {
                                    $flashMessage = 'Slot unlocked successfully.';
                                    $flashClass = 'alert-success';
                                    $isLocked = false;
                                    $prevCap = 0;
                                    $maxCap = $restoreCap;
                                } else {
                                    $flashMessage = 'Failed to unlock slot.';
                                    $flashClass = 'alert-danger';
                                }
                                $up->close();
                            } else {
                                $flashMessage = 'Failed to unlock slot.';
                                $flashClass = 'alert-danger';
                            }
                        }
                    }

                    if ($isAjax) {
                        $payload = [
                            'ok' => $flashClass === 'alert-success' || $flashClass === 'alert-warning',
                            'message' => $flashMessage,
                            'slot_date' => $slotDate,
                            'is_locked' => $isLocked ? 1 : 0,
                            'max_capacity' => $maxCap,
                            'booked_count' => $booked,
                            'locked_prev_capacity' => $prevCap,
                            'level' => $flashClass,
                        ];
                        if ($flashClass === 'alert-danger') {
                            http_response_code(500);
                        }
                        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                }
            }
        }
    } else {
        $flashMessage = 'Invalid form submission.';
        $flashClass = 'alert-danger';
    }

    if ($payload && $flashClass !== 'alert-danger') {
        if (update_global_settings($mysqli, $payload)) {
            $flashMessage = 'Settings saved successfully.';
            $flashClass = 'alert-success';
        } else {
            $flashMessage = 'Failed to save settings.';
            $flashClass = 'alert-danger';
        }
    }

    $settings = load_global_settings($mysqli);

    $adminUsers = [];
    if ($res = $mysqli->query('SELECT id, username, role, is_active, created_at FROM admin_users ORDER BY id ASC')) {
        while ($row = $res->fetch_assoc()) {
            $adminUsers[] = $row;
        }
        $res->free();
    }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings - Buffet Ramadan</title>
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
        .sidebar.offcanvas {
          visibility: visible !important;
          transform: none !important;
          position: sticky;
        }
      }
      .sidebar .nav-link { color: #f5e9c8; border-radius: 0.75rem; padding: 0.65rem 1rem; display: flex; gap: 0.5rem; }
      .sidebar .nav-link.active,
      .sidebar .nav-link:hover { background: rgba(216,180,92,0.18); color: var(--ramadan-gold); }
      .logout-btn { background: rgba(220,53,69,0.12); border: 1px solid rgba(220,53,69,0.4); color: #ffb6b6; }
      .main-content { padding: 2rem; }
      .chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        padding: 0.35rem 0.85rem;
        font-size: 0.85rem;
      }
      .chip.success { background: rgba(31,122,77,0.15); color: #0f402a; }
      .chip.warning { background: rgba(214,125,31,0.18); color: #7b3b05; }
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
          <p class="text-muted small mb-0">Settings</p>
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
          <a class="nav-link" href="booking_slots.php"><i class="bi bi-calendar-event"></i>Booking Slots</a>
          <a class="nav-link" href="booking_details.php"><i class="bi bi-card-text"></i>Booking Details</a>
          <?php if (strtolower(admin_get_role()) === 'admin'): ?>
            <a class="nav-link active" href="settings.php"><i class="bi bi-gear"></i>Settings</a>
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
          <p class="text-uppercase text-muted small mb-1">Configuration</p>
          <h1 class="h3 text-dark mb-2">Settings</h1>
          <p class="text-muted mb-0">Global configuration for event and pricing.</p>
        </header>

        <?php if ($flashMessage): ?>
          <div class="alert <?= $flashClass ?> alert-dismissible fade show" role="alert">
            <?= $flashMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-body">
            <ul class="nav nav-pills mb-4" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-event" type="button" role="tab">Event</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-payment" type="button" role="tab">Payment</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-pricing" type="button" role="tab">Pricing</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-slots" type="button" role="tab">Slots</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-accounts" type="button" role="tab">Manage Accounts</button>
              </li>
            </ul>

            <div class="tab-content">
              <div class="tab-pane fade show active" id="tab-event" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <h2 class="h5 mb-1">Event / Buffet Information</h2>
                    <p class="text-muted mb-0">Global event details displayed on booking pages.</p>
                  </div>
                  <span class="chip warning"><i class="bi bi-moon-stars"></i> Global</span>
                </div>
                <form method="POST" class="row g-3">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                  <input type="hidden" name="form_type" value="event_info">
                  <div class="col-md-6">
                    <label class="form-label">Event Name</label>
                    <input type="text" class="form-control" name="event_name" value="<?= htmlspecialchars((string)($settings['event_name'] ?? '')) ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Venue</label>
                    <input type="text" class="form-control" name="event_venue" value="<?= htmlspecialchars((string)($settings['event_venue'] ?? '')) ?>" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Event Year</label>
                    <input type="number" class="form-control" name="event_year" min="2000" step="1" value="<?= (int)($settings['event_year'] ?? 0) ?>" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Event Start Date</label>
                    <input type="date" class="form-control" name="event_start_date" value="<?= htmlspecialchars((string)($settings['event_start_date'] ?? '')) ?>" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Event End Date</label>
                    <input type="date" class="form-control" name="event_end_date" value="<?= htmlspecialchars((string)($settings['event_end_date'] ?? '')) ?>" required>
                  </div>
                  <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-success">Save</button>
                  </div>
                </form>
              </div>

              <div class="tab-pane fade" id="tab-payment" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <h2 class="h5 mb-1">Payment Configuration</h2>
                    <p class="text-muted mb-0">Displayed on the Upload Proof page (Cara Pembayaran).</p>
                  </div>
                  <span class="chip success"><i class="bi bi-bank2"></i> Payment</span>
                </div>

                <form method="POST" class="row g-3" action="settings.php#tab-payment">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                  <input type="hidden" name="form_type" value="payment_info">
                  <div class="col-md-4">
                    <label class="form-label">Payment Method Name</label>
                    <input type="text" class="form-control" name="payment_method_name" value="<?= htmlspecialchars((string)($settings['payment_method_name'] ?? '')) ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Bank Name</label>
                    <input type="text" class="form-control" name="payment_bank_name" value="<?= htmlspecialchars((string)($settings['payment_bank_name'] ?? '')) ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Account Holder</label>
                    <input type="text" class="form-control" name="payment_account_holder" value="<?= htmlspecialchars((string)($settings['payment_account_holder'] ?? '')) ?>">
                  </div>
                  <div class="col-12">
                    <label class="form-label">Payment Instructions (Cara Pembayaran)</label>
                    <textarea class="form-control" name="payment_instructions" rows="6"><?= htmlspecialchars((string)($settings['payment_instructions'] ?? '')) ?></textarea>
                  </div>
                  <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-success">Save</button>
                  </div>
                </form>

                <hr class="my-4">

                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <h3 class="h6 mb-1">Payment QR</h3>
                    <p class="text-muted mb-0">Upload the QR image shown on the Upload Proof page.</p>
                  </div>
                  <span class="chip success"><i class="bi bi-qr-code"></i> QR</span>
                </div>

                <?php if (!empty($settings['payment_qr_path'])): ?>
                  <div class="mb-3">
                    <img src="<?= htmlspecialchars((string) $settings['payment_qr_path']) ?>" alt="Payment QR" style="max-width: 240px; width: 100%; height: auto; border-radius: 12px; border: 1px solid rgba(216, 180, 92, 0.35);">
                  </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="row g-3" action="settings.php#tab-payment">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                  <input type="hidden" name="form_type" value="payment_qr">
                  <div class="col-md-6">
                    <label class="form-label">Upload QR Image (PNG/JPG)</label>
                    <input type="file" class="form-control" name="payment_qr" accept="image/png,image/jpeg" required>
                  </div>
                  <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-success">Upload QR</button>
                  </div>
                </form>
              </div>

              <div class="tab-pane fade" id="tab-pricing" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <h2 class="h5 mb-1">Ticket Pricing Configuration</h2>
                    <p class="text-muted mb-0">These prices are used to calculate totals.</p>
                  </div>
                  <span class="chip success"><i class="bi bi-lightning-charge-fill"></i> Live</span>
                </div>
                <form method="POST" class="row g-3">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                  <input type="hidden" name="form_type" value="pricing">
                  <div class="col-md-4">
                    <label class="form-label">Dewasa Price (RM)</label>
                    <input type="number" class="form-control" name="price_dewasa" min="0" step="0.5" value="<?= htmlspecialchars(number_format((float)($settings['price_dewasa'] ?? 95), 2, '.', '')) ?>" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Kanak-kanak Price (RM)</label>
                    <input type="number" class="form-control" name="price_kanak" min="0" step="0.5" value="<?= htmlspecialchars(number_format((float)($settings['price_kanak'] ?? 70), 2, '.', '')) ?>" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Warga Emas / ATM Price (RM)</label>
                    <input type="number" class="form-control" name="price_warga" min="0" step="0.5" value="<?= htmlspecialchars(number_format((float)($settings['price_warga'] ?? 85), 2, '.', '')) ?>" required>
                  </div>
                  <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-success">Save</button>
                  </div>
                </form>
              </div>

              <div class="tab-pane fade" id="tab-slots" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <h2 class="h5 mb-1">Slot Lock / Unlock</h2>
                    <p class="text-muted mb-0">Lock a date to prevent new bookings without changing remarks.</p>
                  </div>
                  <span class="chip warning"><i class="bi bi-lock"></i> Control</span>
                </div>

                <div id="slotLockAlert"></div>

                <div class="table-responsive">
                  <table class="table table-striped align-middle">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th class="text-center">Total Pax</th>
                        <th class="text-center">Max Capacity</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if ($slotRows): ?>
                        <?php foreach ($slotRows as $s): ?>
                          <?php
                            $rawDate = (string) ($s['slot_date'] ?? '');
                            $dt = DateTime::createFromFormat('Y-m-d', $rawDate);
                            $dateLabel = $dt ? $dt->format('d/m/Y') : $rawDate;
                            $booked = (int) ($s['booked_count'] ?? 0);
                            $maxCap = (int) ($s['max_capacity'] ?? 0);
                            $isLocked = !empty($s['is_locked']);
                          ?>
                          <tr data-slot-row="1" data-slot-date="<?= htmlspecialchars($rawDate) ?>" data-locked="<?= $isLocked ? '1' : '0' ?>">
                            <td class="fw-semibold"><?= htmlspecialchars($dateLabel) ?></td>
                            <td class="text-center" data-slot-booked="1"><?= number_format($booked) ?></td>
                            <td class="text-center" data-slot-capacity="1"><?= number_format($maxCap) ?></td>
                            <td class="text-center">
                              <?php if ($isLocked): ?>
                                <span class="badge text-bg-danger" data-slot-status="1"><i class="bi bi-lock-fill me-1"></i>LOCKED</span>
                              <?php else: ?>
                                <span class="badge text-bg-success" data-slot-status="1"><i class="bi bi-unlock-fill me-1"></i>OPEN</span>
                              <?php endif; ?>
                            </td>
                            <td class="text-end">
                              <?php if ($isLocked): ?>
                                <button type="button" class="btn btn-sm btn-outline-success js-slot-toggle" data-action="UNLOCK" data-slot-date="<?= htmlspecialchars($rawDate) ?>"><i class="bi bi-unlock me-1"></i>Unlock</button>
                              <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-danger js-slot-toggle" data-action="LOCK" data-slot-date="<?= htmlspecialchars($rawDate) ?>"><i class="bi bi-lock me-1"></i>Lock</button>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="5" class="text-center text-muted py-4">No slots found.</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="tab-pane fade" id="tab-accounts" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <h2 class="h5 mb-1">Manage Accounts</h2>
                    <p class="text-muted mb-0">Generate daily passwords for entry duty staff when needed.</p>
                  </div>
                  <span class="chip warning"><i class="bi bi-people"></i> Users</span>
                </div>

                <?php if ($generatedPassword !== ''): ?>
                  <div class="alert alert-warning">
                    <div class="fw-semibold mb-1">Generated Password (show once)</div>
                    <div class="fs-5"><code><?= htmlspecialchars($generatedPassword) ?></code></div>
                  </div>
                <?php endif; ?>

                <div class="table-responsive">
                  <table class="table table-striped align-middle">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($adminUsers as $u): ?>
                        <?php
                          $role = strtolower(trim((string) ($u['role'] ?? '')));
                          $isActive = !empty($u['is_active']);
                        ?>
                        <tr>
                          <td><?= (int) ($u['id'] ?? 0) ?></td>
                          <td><?= htmlspecialchars((string) ($u['username'] ?? '')) ?></td>
                          <td><?= htmlspecialchars((string) ($u['role'] ?? '')) ?></td>
                          <td>
                            <?php if ($isActive): ?>
                              <span class="badge text-bg-success">Active</span>
                            <?php else: ?>
                              <span class="badge text-bg-secondary">Inactive</span>
                            <?php endif; ?>
                          </td>
                          <td><?= htmlspecialchars((string) ($u['created_at'] ?? '')) ?></td>
                          <td class="text-end">
                            <?php if ($isActive): ?>
                              <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                <?php if ($role === 'entry_duty'): ?>
                                  <form method="POST" class="d-inline" action="settings.php#tab-accounts">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="form_type" value="generate_password">
                                    <input type="hidden" name="user_id" value="<?= (int) ($u['id'] ?? 0) ?>">
                                    <button type="button" class="btn btn-sm btn-outline-danger js-open-generate-password" data-user-id="<?= (int) ($u['id'] ?? 0) ?>" data-username="<?= htmlspecialchars((string) ($u['username'] ?? '')) ?>">Generate Password</button>
                                  </form>
                                <?php endif; ?>

                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#resetPasswordModal" data-user-id="<?= (int) ($u['id'] ?? 0) ?>" data-username="<?= htmlspecialchars((string) ($u['username'] ?? '')) ?>">
                                  Reset Password
                                </button>
                              </div>
                            <?php else: ?>
                              <span class="text-muted">-</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title"><i class="bi bi-key me-2" style="color:#d8b45c;"></i>Reset Password</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="settings.php#tab-accounts" id="resetPasswordForm">
            <div class="modal-body" style="background: #fff9ed;">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
              <input type="hidden" name="form_type" value="reset_password">
              <input type="hidden" name="user_id" id="resetPasswordUserId" value="">

              <div class="mb-2 text-muted small">Reset password for: <span class="fw-semibold" id="resetPasswordUsername"></span></div>
              <div class="mb-3">
                <label class="form-label fw-semibold">New Password</label>
                <div class="input-group">
                  <input type="password" class="form-control" name="new_password" id="resetPasswordInput" required>
                  <button class="btn btn-outline-secondary" type="button" id="toggleResetPassword" aria-label="Toggle password visibility">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>
            </div>
            <div class="modal-footer" style="background: #fff9ed;">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary" id="openResetConfirmBtn">Reset</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="resetPasswordConfirmModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title"><i class="bi bi-shield-check me-2" style="color:#d8b45c;"></i>Confirm Reset</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            Are you sure you want to reset the password for <span class="fw-semibold" id="resetPasswordConfirmUsername"></span>?
          </div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmResetSubmitBtn">Yes, Reset</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="generatePasswordModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title"><i class="bi bi-shield-check me-2" style="color:#d8b45c;"></i>Generate Password</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <div class="mb-2">Generate a new password for this entry_duty user?</div>
            <div class="text-muted small mb-3">Username: <span class="fw-semibold" id="generatePasswordUsername"></span></div>
            <div id="generatePasswordResult" style="display:none;">
              <div class="alert alert-warning mb-0">
                <div class="fw-semibold mb-1">Generated Password (show once)</div>
                <div class="fs-5"><code id="generatePasswordValue"></code></div>
              </div>
            </div>
            <div id="generatePasswordLoading" class="py-2 text-center text-muted" style="display:none;">Generating...</div>
            <div id="generatePasswordError" class="alert alert-danger mb-0" style="display:none;"></div>
          </div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">No</button>
            <button type="button" class="btn btn-danger" id="confirmGeneratePasswordBtn">Yes</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
      (function () {
        const hash = window.location.hash;
        let targetHash = hash;
        if (!targetHash) {
          try {
            targetHash = localStorage.getItem('settings_active_tab') || '';
          } catch (_) {
            targetHash = '';
          }
        }

        if (!targetHash) return;
        const trigger = document.querySelector('[data-bs-toggle="pill"][data-bs-target="' + targetHash + '"]');
        if (!trigger || typeof bootstrap === 'undefined' || !bootstrap.Tab) return;
        const tab = new bootstrap.Tab(trigger);
        tab.show();
      })();

      window.addEventListener('DOMContentLoaded', function () {
        const csrfToken = '<?= htmlspecialchars($csrfToken) ?>';

        document.querySelectorAll('[data-bs-toggle="pill"]').forEach((btn) => {
          btn.addEventListener('shown.bs.tab', function () {
            const target = btn.getAttribute('data-bs-target') || '';
            if (!target) return;
            try {
              localStorage.setItem('settings_active_tab', target);
            } catch (_) {}
            if (history && history.replaceState) {
              history.replaceState(null, '', target);
            } else {
              window.location.hash = target;
            }
          });
        });

        const btn = document.getElementById('confirmLogoutBtn');
        const form = document.getElementById('logoutForm');
        if (btn && form) {
          btn.addEventListener('click', () => form.submit());
        }

        const slotAlert = document.getElementById('slotLockAlert');
        const slotModalEl = document.getElementById('slotLockConfirmModal');
        const slotConfirmBtn = document.getElementById('confirmSlotLockBtn');
        const slotConfirmTitle = document.getElementById('slotLockConfirmTitle');
        const slotConfirmBody = document.getElementById('slotLockConfirmBody');
        const slotConfirmDate = document.getElementById('slotLockConfirmDate');
        let pendingSlotDate = '';
        let pendingAction = '';

        function showSlotNotice(message, level) {
          if (!slotAlert) return;
          const cls = level || 'alert-success';
          slotAlert.innerHTML = '<div class="alert ' + cls + ' alert-dismissible fade show" role="alert">' + String(message || '') + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }

        function escapeHtml(str) {
          return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        }

        function updateSlotRow(slotDate, isLocked, maxCapacity) {
          const row = document.querySelector('tr[data-slot-row="1"][data-slot-date="' + CSS.escape(slotDate) + '"]');
          if (!row) return;
          row.setAttribute('data-locked', isLocked ? '1' : '0');

          const capEl = row.querySelector('[data-slot-capacity="1"]');
          if (capEl) capEl.textContent = String(maxCapacity);

          const statusEl = row.querySelector('[data-slot-status="1"]');
          const actionWrap = row.lastElementChild;
          if (statusEl) {
            if (isLocked) {
              statusEl.className = 'badge text-bg-danger';
              statusEl.innerHTML = '<i class="bi bi-lock-fill me-1"></i>LOCKED';
            } else {
              statusEl.className = 'badge text-bg-success';
              statusEl.innerHTML = '<i class="bi bi-unlock-fill me-1"></i>OPEN';
            }
          }
          if (actionWrap) {
            actionWrap.innerHTML = isLocked
              ? '<button type="button" class="btn btn-sm btn-outline-success js-slot-toggle" data-action="UNLOCK" data-slot-date="' + escapeHtml(slotDate) + '"><i class="bi bi-unlock me-1"></i>Unlock</button>'
              : '<button type="button" class="btn btn-sm btn-outline-danger js-slot-toggle" data-action="LOCK" data-slot-date="' + escapeHtml(slotDate) + '"><i class="bi bi-lock me-1"></i>Lock</button>';
          }
        }

        document.addEventListener('click', function (e) {
          const btnEl = e.target && e.target.closest ? e.target.closest('.js-slot-toggle') : null;
          if (!btnEl) return;
          pendingSlotDate = btnEl.getAttribute('data-slot-date') || '';
          pendingAction = btnEl.getAttribute('data-action') || '';
          if (!pendingSlotDate || !pendingAction || !slotModalEl || !bootstrap?.Modal) return;

          if (slotConfirmDate) slotConfirmDate.textContent = pendingSlotDate;
          if (slotConfirmTitle) slotConfirmTitle.textContent = pendingAction === 'LOCK' ? 'Confirm Lock' : 'Confirm Unlock';
          if (slotConfirmBody) {
            slotConfirmBody.textContent = pendingAction === 'LOCK'
              ? 'This will lock the slot and prevent new bookings for this date. Continue?'
              : 'This will unlock the slot and allow new bookings for this date. Continue?';
          }
          bootstrap.Modal.getOrCreateInstance(slotModalEl).show();
        });

        if (slotConfirmBtn) {
          slotConfirmBtn.addEventListener('click', async function () {
            if (!pendingSlotDate || !pendingAction) return;
            slotConfirmBtn.disabled = true;

            try {
              const body = new URLSearchParams();
              body.set('csrf_token', csrfToken);
              body.set('form_type', 'slot_lock_toggle');
              body.set('ajax', '1');
              body.set('slot_date', pendingSlotDate);
              body.set('action', pendingAction);

              const res = await fetch('settings.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                cache: 'no-store',
                credentials: 'same-origin'
              });

              const data = await res.json();
              if (!res.ok || !data || data.ok !== true) {
                const msg = (data && data.message) ? data.message : 'Failed to update slot.';
                showSlotNotice(msg, 'alert-danger');
                return;
              }

              const msg = (data && data.message) ? data.message : 'Updated.';
              const level = (data && data.level) ? String(data.level) : 'alert-success';
              showSlotNotice(msg, level);
              updateSlotRow(String(data.slot_date || pendingSlotDate), String(data.is_locked || '0') === '1', String(data.max_capacity ?? '0'));

              if (slotModalEl && bootstrap?.Modal) {
                const modal = bootstrap.Modal.getInstance(slotModalEl);
                if (modal) modal.hide();
              }
            } catch (err) {
              showSlotNotice('Failed to update slot.', 'alert-danger');
            } finally {
              slotConfirmBtn.disabled = false;
              pendingSlotDate = '';
              pendingAction = '';
            }
          });
        }

        const resetModal = document.getElementById('resetPasswordModal');
        const resetConfirmModal = document.getElementById('resetPasswordConfirmModal');
        const resetForm = document.getElementById('resetPasswordForm');
        const openResetConfirmBtn = document.getElementById('openResetConfirmBtn');
        const confirmResetSubmitBtn = document.getElementById('confirmResetSubmitBtn');
        const confirmUsernameEl = document.getElementById('resetPasswordConfirmUsername');
        const toggleBtn = document.getElementById('toggleResetPassword');

        if (toggleBtn) {
          toggleBtn.addEventListener('click', function () {
            const input = document.getElementById('resetPasswordInput');
            const icon = toggleBtn.querySelector('i');
            if (!input) return;
            const isPassword = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPassword ? 'text' : 'password');
            if (icon) {
              icon.classList.toggle('bi-eye', !isPassword);
              icon.classList.toggle('bi-eye-slash', isPassword);
            }
          });
        }

        if (openResetConfirmBtn && resetConfirmModal) {
          openResetConfirmBtn.addEventListener('click', function () {
            const username = (document.getElementById('resetPasswordUsername') || {}).textContent || '';
            if (confirmUsernameEl) confirmUsernameEl.textContent = username;
            const modal = bootstrap.Modal.getOrCreateInstance(resetConfirmModal);
            modal.show();
          });
        }

        if (confirmResetSubmitBtn && resetForm) {
          confirmResetSubmitBtn.addEventListener('click', function () {
            resetForm.submit();
          });
        }

        if (resetModal) {
          resetModal.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger) return;
            const userId = trigger.getAttribute('data-user-id') || '';
            const username = trigger.getAttribute('data-username') || '';
            const idEl = document.getElementById('resetPasswordUserId');
            const userEl = document.getElementById('resetPasswordUsername');
            const passEl = document.getElementById('resetPasswordInput');
            if (idEl) idEl.value = userId;
            if (userEl) userEl.textContent = username;
            if (passEl) passEl.value = '';
          });
        }

        const genModalEl = document.getElementById('generatePasswordModal');
        const genUserEl = document.getElementById('generatePasswordUsername');
        const genConfirmBtn = document.getElementById('confirmGeneratePasswordBtn');
        const genResultWrap = document.getElementById('generatePasswordResult');
        const genPasswordValue = document.getElementById('generatePasswordValue');
        const genLoading = document.getElementById('generatePasswordLoading');
        const genError = document.getElementById('generatePasswordError');

        let genUserId = '';

        function resetGenerateModal() {
          if (genResultWrap) genResultWrap.style.display = 'none';
          if (genPasswordValue) genPasswordValue.textContent = '';
          if (genLoading) genLoading.style.display = 'none';
          if (genError) {
            genError.style.display = 'none';
            genError.textContent = '';
          }
          if (genConfirmBtn) genConfirmBtn.disabled = false;
        }

        document.querySelectorAll('.js-open-generate-password').forEach((btnEl) => {
          btnEl.addEventListener('click', function () {
            if (!genModalEl || typeof bootstrap === 'undefined') return;
            genUserId = btnEl.getAttribute('data-user-id') || '';
            const username = btnEl.getAttribute('data-username') || '';
            if (genUserEl) genUserEl.textContent = username;
            resetGenerateModal();
            bootstrap.Modal.getOrCreateInstance(genModalEl).show();
          });
        });

        if (genConfirmBtn) {
          genConfirmBtn.addEventListener('click', async function () {
            if (!genUserId) return;
            resetGenerateModal();
            genConfirmBtn.disabled = true;
            if (genLoading) genLoading.style.display = '';

            try {
              const body = new URLSearchParams();
              body.set('csrf_token', csrfToken);
              body.set('form_type', 'generate_password');
              body.set('user_id', genUserId);
              body.set('ajax', '1');

              const res = await fetch('settings.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                cache: 'no-store',
                credentials: 'same-origin'
              });
              const data = await res.json();
              if (!res.ok || !data || data.ok !== true) {
                const msg = (data && data.message) ? data.message : 'Unable to generate password.';
                if (genError) {
                  genError.textContent = msg;
                  genError.style.display = '';
                }
                return;
              }

              if (genPasswordValue) genPasswordValue.textContent = data.password || '';
              if (genResultWrap) genResultWrap.style.display = '';
            } catch (e) {
              if (genError) {
                genError.textContent = 'Unable to generate password.';
                genError.style.display = '';
              }
            } finally {
              if (genLoading) genLoading.style.display = 'none';
              genConfirmBtn.disabled = false;
            }
          });
        }
      });
    </script>

    <div class="modal fade" id="slotLockConfirmModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title"><i class="bi bi-lock me-2" style="color:#d8b45c;"></i><span id="slotLockConfirmTitle">Confirm</span></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <div class="mb-2">Date: <span class="fw-semibold" id="slotLockConfirmDate"></span></div>
            <div id="slotLockConfirmBody"></div>
          </div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmSlotLockBtn">Yes, Continue</button>
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
  </body>
</html>
<?php
$mysqli->close();
?>
