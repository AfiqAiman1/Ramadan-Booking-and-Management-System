<?php
require_once __DIR__ . '/ent_auth.php';

require_ent_roles(['ent_admin']);

$csrfToken = ent_csrf_token();

$flashMessage = '';
$flashClass = 'alert-info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $remark = trim((string) ($_POST['remark'] ?? ''));

    if ($fullName === '' || $phone === '') {
        $flashMessage = 'Please fill in name and phone.';
        $flashClass = 'alert-danger';
    } else {
        $_SESSION['ent_full_name'] = $fullName;
        $_SESSION['ent_phone'] = $phone;
        $_SESSION['ent_remark'] = $remark;

        header('Location: ent_index.php');
        exit;
    }
}

$eventName = (string) ($_SESSION['admin_event_name'] ?? '');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ENT - Booking</title>
    <link rel="icon" type="image/png" href="assets/img/Logo%20ATM.png">
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
        --ramadan-deep: #041f18;
      }
      body {
        font-family: 'Cairo', system-ui, sans-serif;
        background:
          radial-gradient(circle at 20% 20%, rgba(216,180,92,0.18), transparent 55%),
          radial-gradient(circle at 80% 0%, rgba(216,180,92,0.12), transparent 45%),
          radial-gradient(circle at 70% 95%, rgba(8,55,43,0.22), transparent 60%),
          linear-gradient(180deg, var(--ramadan-green), var(--ramadan-deep));
        min-height: 100vh;
      }
      .brand {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #fef6dd;
      }
      .brand .icon {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border-radius: 14px;
        background: linear-gradient(180deg, var(--ramadan-green), #041f18);
        color: #fef6dd;
      }
      .card-ramadan {
        border: 0;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(8, 55, 43, 0.10);
        background: var(--ramadan-cream);
      }
      .btn-ramadan {
        background: linear-gradient(140deg, var(--ramadan-gold), #d4a842);
        border: none;
        color: #2d1c01;
        font-weight: 800;
        border-radius: 999px;
      }
      .btn-ramadan:hover { filter: brightness(1.03); color: #2d1c01; }
      .hint {
        color: rgba(254, 246, 221, 0.82);
        font-size: 0.9rem;
      }

      .ramadan-banner {
        background: linear-gradient(180deg, rgba(8, 55, 43, 0.92), rgba(4, 31, 24, 0.96));
        border: 1px solid rgba(216, 180, 92, 0.30);
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(8, 55, 43, 0.12);
        color: #fef6dd;
        overflow: hidden;
        position: relative;
      }
      .ramadan-banner::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
          radial-gradient(900px 300px at 15% 10%, rgba(216, 180, 92, 0.18), transparent 60%),
          radial-gradient(700px 280px at 90% 15%, rgba(255, 246, 221, 0.10), transparent 55%);
        pointer-events: none;
      }
      .ramadan-banner .banner-title {
        position: relative;
        font-weight: 800;
        color: #fef6dd;
      }
      .ramadan-banner .banner-subtitle {
        position: relative;
        color: rgba(254, 246, 221, 0.82);
      }
    </style>
  </head>
  <body>
    <main class="container py-5">
      <div class="row justify-content-center">
        <div class="col-12 col-md-9 col-lg-6">
          <div class="mb-4 text-center">
            <div class="brand justify-content-center mb-2">
              <div class="icon"><i class="bi bi-moon-stars-fill"></i></div>
              <div class="fw-bold fs-3">ENT Booking</div>
            </div>
            <?php if ($eventName !== ''): ?>
              <div class="hint"><i class="bi bi-stars" style="color: rgba(216,180,92,0.95);"></i> <?= htmlspecialchars($eventName) ?></div>
            <?php endif; ?>
            
          </div>

          <?php if ($flashMessage): ?>
            <div class="alert <?= htmlspecialchars($flashClass) ?> alert-dismissible fade show" role="alert">
              <?= htmlspecialchars($flashMessage) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <div class="card card-ramadan">
            <div class="card-body p-4 p-md-5">
              <form method="POST" class="row g-3">
                <div class="col-12">
                  <label class="form-label fw-semibold">Full Name</label>
                  <input type="text" class="form-control" name="full_name" required>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Phone</label>
                  <input type="text" class="form-control" name="phone" required>
                </div>
                <div class="col-12 d-grid">
                  <button type="submit" class="btn btn-ramadan btn-lg">
                    Continue <i class="bi bi-arrow-right ms-2"></i>
                  </button>
                </div>
              </form>
            </div>
          </div>

          <div class="ramadan-banner mt-4 mb-2 p-4 text-center">
            <div class="banner-title fs-5 mb-1">
              <i class="bi bi-moon-stars-fill me-2" style="color: var(--ramadan-gold);"></i>
              Selamat Menyambut Bulan Ramadan
              <i class="bi bi-moon-stars-fill ms-2" style="color: var(--ramadan-gold);"></i>
            </div>
            <div class="banner-subtitle small">Nostalgia Kampung • Bufet Iftar</div>
          </div>

          <div class="text-center mt-3">
            <a href="admin/admin_dashboard.php" class="btn btn-link text-decoration-none" style="color: rgba(254, 246, 221, 0.85);">
              <i class="bi bi-arrow-left-circle me-2"></i>Back
            </a>
          </div>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
  </body>
</html>
