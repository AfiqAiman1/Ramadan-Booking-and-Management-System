<?php
$cacheSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
if ($cacheSecure) {
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin_auth.php';

admin_start_session();

$csrfToken = admin_csrf_token();

$basePrefix = '../';

$redirect = trim((string) ($_GET['redirect'] ?? ''));
$redirect = ltrim($redirect, '/');
$redirectAllowed = [
    'create_account.php',
    'create_account',
    'ent_home.php',
    'ent_home',
    'ent_index.php',
    'ent_index',
    'ent_booking.php',
    'ent_booking',
    'admin/admin_dashboard.php',
    'admin/admin_dashboard',
    'admin/all_bookings.php',
    'admin/all_bookings',
    'admin/check_in.php',
    'admin/check_in',
    'admin/list_guests.php',
    'admin/list_guests',
    'admin/reports.php',
    'admin/reports',
    'admin/finance_confirm.php',
    'admin/finance_confirm',
    'admin/settings.php',
    'admin/settings',
];
$redirectTarget = '';
if ($redirect !== '') {
    $redirectPath = strtok($redirect, '?');
    if (in_array($redirectPath, $redirectAllowed, true)) {
        $redirectTarget = $redirect;
    }
}

$alreadyLoggedIn = false;
$alreadyLoggedInRole = '';
$alreadyLoggedInRedirect = '';

if (admin_is_logged_in()) {
    $role = strtoupper(trim((string) ($_SESSION['admin_role'] ?? 'admin')));
    $alreadyLoggedIn = true;
    $alreadyLoggedInRole = $role;

    if ($redirectTarget !== '') {
        if ($redirectTarget === 'create_account.php' && $role !== 'ADMIN') {
            // ignore
        } elseif (in_array($redirectTarget, ['ent_home.php', 'ent_index.php', 'ent_booking.php'], true) && $role !== 'ENT_ADMIN') {
            // ignore
        } else {
            header('Location: ' . $basePrefix . $redirectTarget);
            exit;
        }
    }

    if ($role === 'BANQUET') {
        $alreadyLoggedInRedirect = $basePrefix . 'admin/list_guests.php';
    } elseif ($role === 'ENTRY_DUTY') {
        $alreadyLoggedInRedirect = $basePrefix . 'admin/check_in.php';
    } elseif ($role === 'ENT_ADMIN') {
        $alreadyLoggedInRedirect = $basePrefix . 'admin/admin_dashboard.php';
    } elseif ($role === 'STAFF') {
        $alreadyLoggedInRedirect = $basePrefix . 'admin/admin_dashboard.php';
    } elseif ($role === 'ASSISTANT') {
        $alreadyLoggedInRedirect = $basePrefix . 'admin/admin_dashboard.php';
    } else {
        $alreadyLoggedInRedirect = $basePrefix . 'admin/admin_dashboard.php';
    }
}

if ($alreadyLoggedIn && $_SERVER['REQUEST_METHOD'] !== 'POST' && $alreadyLoggedInRedirect !== '') {
    header('Location: ' . $alreadyLoggedInRedirect);
    exit;
}

$flashMessage = '';
$flashClass = 'alert-info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $flashMessage = 'Username and password are required.';
        $flashClass = 'alert-danger';
    } else {
        try {
            $mysqli = db_connect();
            ensure_admin_users_schema($mysqli);
            ensure_global_settings_schema($mysqli);
            $settings = load_global_settings($mysqli);

            $stmt = $mysqli->prepare('SELECT id, username, password_hash, role, is_active, password_valid_from, password_valid_until FROM admin_users WHERE username = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $res = $stmt->get_result();
                $user = $res ? ($res->fetch_assoc() ?: null) : null;
                if ($res) {
                    $res->free();
                }
                $stmt->close();

                if (!$user || empty($user['is_active'])) {
                    $flashMessage = 'Username is not exist.';
                    $flashClass = 'alert-danger';
                } elseif (!password_verify($password, (string) $user['password_hash'])) {
                    $flashMessage = 'Wrong password.';
                    $flashClass = 'alert-danger';
                } else {
                    $rawRole = strtoupper(trim((string) ($user['role'] ?? 'admin')));

                    if ($rawRole === 'ENTRY_DUTY') {
                        $validFrom = trim((string) ($user['password_valid_from'] ?? ''));
                        $validUntil = trim((string) ($user['password_valid_until'] ?? ''));

                        if ($validFrom === '' || $validUntil === '') {
                            $flashMessage = 'Entry duty password is not active. Please ask admin to generate a new password.';
                            $flashClass = 'alert-danger';
                            $mysqli->close();
                        } else {
                            $now = new DateTime('now');
                            $from = DateTime::createFromFormat('H:i:s', $validFrom) ?: DateTime::createFromFormat('H:i', $validFrom);
                            $until = DateTime::createFromFormat('H:i:s', $validUntil) ?: DateTime::createFromFormat('H:i', $validUntil);

                            if (!$from || !$until) {
                                $flashMessage = 'Entry duty password time window is invalid. Please ask admin to generate a new password.';
                                $flashClass = 'alert-danger';
                                $mysqli->close();
                            } else {
                                $from->setDate((int) $now->format('Y'), (int) $now->format('m'), (int) $now->format('d'));
                                $until->setDate((int) $now->format('Y'), (int) $now->format('m'), (int) $now->format('d'));

                                if ($now < $from || $now > $until) {
                                    $flashMessage = 'Entry duty login is only allowed from 17:00 to 23:59. Please ask admin for a new password.';
                                    $flashClass = 'alert-danger';
                                    $mysqli->close();
                                }
                            }
                        }
                    }

                    if ($flashClass === 'alert-danger') {
                        // Stop login flow if time window check failed.
                    } else {

                        $postLoginRedirect = $basePrefix . 'admin/admin_dashboard.php';
                        if ($rawRole === 'BANQUET') {
                            $postLoginRedirect = $basePrefix . 'admin/list_guests.php';
                        } elseif ($rawRole === 'ENTRY_DUTY') {
                            $postLoginRedirect = $basePrefix . 'admin/check_in.php';
                        } elseif ($rawRole === 'ENT_ADMIN') {
                            $postLoginRedirect = $basePrefix . 'admin/admin_dashboard.php';
                        } elseif ($rawRole === 'STAFF') {
                            $postLoginRedirect = $basePrefix . 'admin/admin_dashboard.php';
                        } elseif ($rawRole === 'ASSISTANT') {
                            $postLoginRedirect = $basePrefix . 'admin/admin_dashboard.php';
                        }

                        session_regenerate_id(true);
                        $_SESSION['admin_user_id'] = (int) $user['id'];
                        $_SESSION['admin_username'] = (string) $user['username'];
                        $_SESSION['admin_role'] = (string) ($user['role'] ?? 'admin');
                        $_SESSION['admin_event_name'] = (string) ($settings['event_name'] ?? '');

                        if ($mysqli) {
                            $mysqli->close();
                        }

                        $role = strtoupper(trim((string) ($_SESSION['admin_role'] ?? 'admin')));

                        if ($redirectTarget !== '') {
                            if ($redirectTarget === 'create_account.php' && $role !== 'ADMIN') {
                                // ignore
                            } elseif (in_array($redirectTarget, ['ent_home.php', 'ent_index.php', 'ent_booking.php'], true) && $role !== 'ENT_ADMIN') {
                                // ignore
                            } else {
                                header('Location: ' . $basePrefix . $redirectTarget);
                                exit;
                            }
                        }

                        header('Location: ' . $postLoginRedirect);
                        exit;
                    }
                }
            } else {
                $flashMessage = 'Unable to login right now.';
                $flashClass = 'alert-danger';
            }

            $mysqli->close();
        } catch (Throwable $e) {
            $flashMessage = 'Unable to login right now.';
            $flashClass = 'alert-danger';
        }
    }
}

$eventName = (string) ($_SESSION['admin_event_name'] ?? 'Admin');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Buffet Ramadan</title>
    <link rel="icon" type="image/png" href="../assets/img/Logo%20ATM.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <style>
      :root {
        --ramadan-green: #08372b;
        --ramadan-gold: #d8b45c;
        --ramadan-cream: #fff9ed;
      }
      body {
        font-family: 'Cairo', system-ui, sans-serif;
        background:
          radial-gradient(circle at 20% 20%, rgba(216,180,92,0.16), transparent 55%),
          radial-gradient(circle at 80% 0%, rgba(216,180,92,0.12), transparent 45%),
          linear-gradient(180deg, #041f18 0%, var(--ramadan-green) 60%, #041f18 100%);
        min-height: 100vh;
      }
      .login-card {
        background: var(--ramadan-cream);
        border-radius: 1.25rem;
        border: 1px solid rgba(216,180,92,0.35);
        box-shadow: 0 22px 45px rgba(7, 20, 17, 0.25);
      }
      .brand {
        color: #fef6dd;
        text-align: center;
      }
      .brand .title {
        font-weight: 800;
        letter-spacing: 0.5px;
      }
      .btn-ramadan {
        background: linear-gradient(140deg, #f8d687, #d4a842);
        border: none;
        border-radius: 999px;
        padding: 0.85rem 1.4rem;
        font-weight: 800;
        color: #2d1c01;
      }
      .btn-ramadan:hover {
        color: #2d1c01;
      }
      .form-control {
        border-radius: 0.85rem;
        padding: 0.9rem 1rem;
      }
      .form-control:focus {
        border-color: var(--ramadan-gold);
        box-shadow: 0 0 0 0.2rem rgba(216,180,92,0.25);
      }
      .hint {
        color: rgba(255,255,255,0.75);
        text-align: center;
      }
    </style>
  </head>
  <body>
    <main class="container py-5">
      <div class="row justify-content-center">
        <div class="col-12 col-md-7 col-lg-5">
          <div class="brand mb-4">
            <div class="display-6 title">Login</div>
            <div class="small text-uppercase" style="color: rgba(216,180,92,0.95);">Ramadan Mubarak</div>
            <div class="mt-2" style="color: rgba(255,255,255,0.8);">
              <i class="bi bi-moon-stars-fill" style="color: rgba(216,180,92,0.95);"></i>
              <span class="ms-1"><?= htmlspecialchars($eventName) ?></span>
            </div>

          <?php if ($alreadyLoggedIn && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
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
          <?php endif; ?>
          </div>

          <?php if ($flashMessage): ?>
            <div class="alert <?= $flashClass ?> alert-dismissible fade show" role="alert">
              <?= htmlspecialchars($flashMessage) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <?php if ($alreadyLoggedIn && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
            <div class="alert alert-warning mb-3" role="alert">
              You are already logged in as <strong><?= htmlspecialchars(strtolower($alreadyLoggedInRole)) ?></strong>.
              <div class="mt-3 d-flex flex-wrap gap-2">
                <?php if ($alreadyLoggedInRedirect !== ''): ?>
                  <a class="btn btn-sm btn-outline-dark" href="<?= htmlspecialchars($alreadyLoggedInRedirect) ?>">Go to dashboard</a>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">Logout</button>
              </div>
            </div>
          <?php endif; ?>

          <div class="login-card p-4">
            <h1 class="h5 mb-3">Sign in</h1>
            <form method="POST" class="row g-3">
              <div class="col-12">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" name="username" autocomplete="username" required>
              </div>
              <div class="col-12">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" autocomplete="current-password" required>
              </div>
              <div class="col-12 d-grid">
                <button type="submit" class="btn btn-ramadan">
                  <i class="bi bi-box-arrow-in-right me-2"></i>Login
                </button>
              </div>
            </form>
          </div>

        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
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
