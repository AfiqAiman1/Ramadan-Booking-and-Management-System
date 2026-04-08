<?php
require_once __DIR__ . '/config/config.php';

$flashMessage = '';
$flashClass = 'alert-info';

$mysqli = null;

try {
    $mysqli = db_connect();
    ensure_admin_users_schema($mysqli);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string) ($_POST['action'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $role = strtolower(trim((string) ($_POST['role'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($action === 'signup') {
            if ($username === '' || $password === '' || $confirm === '') {
                $flashMessage = 'Please fill in all required fields.';
                $flashClass = 'alert-danger';
            } elseif (!preg_match('/^[A-Za-z0-9_\-\.]{3,64}$/', $username)) {
                $flashMessage = 'Invalid username format.';
                $flashClass = 'alert-danger';
            } elseif (!in_array($role, ['staff', 'assistant', 'banquet', 'entry_duty', 'finance', 'admin'], true)) {
                $flashMessage = 'Invalid role selected.';
                $flashClass = 'alert-danger';
            } elseif ($password !== $confirm) {
                $flashMessage = 'Passwords do not match.';
                $flashClass = 'alert-danger';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $defaultRole = $role;
                $defaultActive = 1;
                $stmt = $mysqli->prepare('INSERT INTO admin_users (username, password_hash, role, is_active) VALUES (?, ?, ?, ?)');
                if (!$stmt) {
                    $flashMessage = 'Unable to create account right now.';
                    $flashClass = 'alert-danger';
                } else {
                    $stmt->bind_param('sssi', $username, $hash, $defaultRole, $defaultActive);
                    if ($stmt->execute()) {
                        $flashMessage = 'Account created. You can now log in.';
                        $flashClass = 'alert-success';
                    } else {
                        $flashMessage = 'Unable to create account. Username may already exist.';
                        $flashClass = 'alert-danger';
                    }
                    $stmt->close();
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
    <title>Create Account - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
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
            <div class="display-6 title">Create Account</div>
            <div class="small text-uppercase" style="color: rgba(216,180,92,0.95);">Ramadan Mubarak</div>
          </div>

          <?php if ($flashMessage): ?>
            <div class="alert <?= htmlspecialchars($flashClass) ?> alert-dismissible fade show" role="alert">
              <?= htmlspecialchars($flashMessage) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <div class="login-card p-4">
            <h1 class="h5 mb-3">Sign up</h1>
            <form method="POST" class="row g-3">
              <input type="hidden" name="action" value="signup">
              <div class="col-12">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" name="username" autocomplete="username" required>
              </div>
              <div class="col-12">
                <label class="form-label">Role</label>
                <select class="form-select" name="role" required>
                  <option value="staff" selected>Sales</option>
                  <option value="assistant">Assistant</option>
                  <option value="banquet">Banquet</option>
                  <option value="entry_duty">Entry Duty</option>
                  <option value="finance">Finance</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" autocomplete="new-password" required>
              </div>
              <div class="col-12">
                <label class="form-label">Confirm Password</label>
                <input type="password" class="form-control" name="confirm_password" autocomplete="new-password" required>
              </div>
              <div class="col-12 d-grid">
                <button type="submit" class="btn btn-ramadan">
                  <i class="bi bi-person-plus me-2"></i>Create Account
                </button>
              </div>
            </form>

            <div class="mt-3">
              <a href="public/login.php" class="text-decoration-none"><i class="bi bi-box-arrow-in-right me-2"></i>Back to Login</a>
            </div>
          </div>

          <div class="hint mt-4 small">
            Accounts require approval before login.
          </div>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
  </body>
</html>
<?php
if ($mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
