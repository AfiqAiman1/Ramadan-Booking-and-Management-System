<?php
// ent_index.php - ENT admin booking page (RM0 complimentary)

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/ent_auth.php';

require_ent_roles(['ent_admin']);

$csrfToken = ent_csrf_token();

$fullName = trim((string) ($_SESSION['ent_full_name'] ?? ''));
$phone = trim((string) ($_SESSION['ent_phone'] ?? ''));
$remarkPrefill = trim((string) ($_SESSION['ent_remark'] ?? ''));

if ($fullName === '' || $phone === '') {
    header('Location: ent_home.php');
    exit;
}

$ramadanStart = '2026-02-21';
$ramadanEnd   = '2026-03-19';
$slots        = [];
$errorMessage = '';
$settings     = [];

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
    ensure_booking_slots_schema($mysqli);
    ensure_global_settings_schema($mysqli);

    $settings = load_global_settings($mysqli);
    if (!empty($settings['event_start_date'])) {
        $ramadanStart = (string) $settings['event_start_date'];
    }
    if (!empty($settings['event_end_date'])) {
        $ramadanEnd = (string) $settings['event_end_date'];
    }

    $today = (new DateTime('today'))->format('Y-m-d');

    $slotQuery = "SELECT slot_date, max_capacity, booked_count FROM booking_slots WHERE slot_date BETWEEN ? AND ? AND slot_date >= ? ORDER BY slot_date ASC";
    if ($stmt = $mysqli->prepare($slotQuery)) {
        $stmt->bind_param('sss', $ramadanStart, $ramadanEnd, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $slots[] = $row;
        }
        $result->free();
        $stmt->close();
    } else {
        $errorMessage = 'Unable to prepare booking slot query.';
    }

    $mysqli->close();
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
    $errorMessage = 'Unable to load booking slots at the moment. Please try again later.';
}

$bookingOpen = (($settings['booking_status'] ?? 'OPEN') === 'OPEN');
$eventName = (string) ($settings['event_name'] ?? '');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ENT Booking - RM0</title>

    <link rel="icon" type="image/png" href="assets/img/Logo%20ATM.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">

    <style>
      :root {
        --ramadan-green: #0e3e32;
        --ramadan-deep: #092821;
        --ramadan-gold: #d9b45a;
        --ramadan-cream: #fff8ec;
        --text-dark: #0b1e1a;
      }

      body {
        font-family: 'Cairo', system-ui, -apple-system, 'Segoe UI', sans-serif;
        background:
          radial-gradient(circle at 20% 20%, rgba(217,180,90,0.18), transparent 55%),
          radial-gradient(circle at 80% 0%, rgba(217,180,90,0.15), transparent 45%),
          linear-gradient(180deg, var(--ramadan-deep) 0%, var(--ramadan-green) 60%, #051b15 100%);
        min-height: 100vh;
        color: var(--text-dark);
      }

      .booking-shell {
        padding: 3rem 0 4rem;
      }

      .hero-card {
        background: linear-gradient(135deg, rgba(5, 41, 33, 0.65), rgba(11, 62, 48, 0.9));
        border-radius: 1.5rem;
        border: 1px solid rgba(217,180,90,0.45);
        box-shadow: 0 18px 36px rgba(5, 22, 18, 0.45);
        overflow: hidden;
        position: relative;
      }

      .hero-card h1,
      .hero-card p {
        color: #fdf8ec;
        position: relative;
      }

      .icon-decor {
        color: var(--ramadan-gold);
        text-shadow: 0 6px 10px rgba(0,0,0,0.45);
      }

      .form-wrapper {
        background: var(--ramadan-cream);
        border-radius: 1.25rem;
        padding: 2.5rem;
        border: 1px solid rgba(217,180,90,0.35);
        margin-top: -60px;
        position: relative;
        z-index: 2;
        box-shadow: 0 22px 45px rgba(7, 20, 17, 0.25);
      }

      @media (max-width: 576px) {
        .form-wrapper {
          padding: 1.5rem;
        }
      }

      .form-label {
        font-weight: 600;
        color: #0f2c26;
      }

      .form-control,
      .form-select {
        border-radius: 0.8rem;
        padding: 0.85rem 1rem;
      }

      .form-control:focus,
      .form-select:focus {
        border-color: var(--ramadan-gold);
        box-shadow: 0 0 0 0.2rem rgba(217,180,90,0.25);
      }

      #eventDate {
        transition: box-shadow 0.12s ease, border-color 0.12s ease, transform 0.12s ease;
      }

      #eventDate:hover:not(:disabled) {
        border-color: rgba(217,180,90,0.85);
        box-shadow: 0 10px 24px rgba(5, 40, 32, 0.14);
        transform: translateY(-1px);
      }

      .ticket-pill {
        background: #ffffff;
        border: 1px solid rgba(15,46,38,0.08);
        border-radius: 1rem;
        padding: 1rem;
        height: 100%;
      }

      .ticket-pill h3 {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 0.35rem;
        color: #0e352c;
      }

      .ticket-pill small {
        color: #4f6b66;
      }

      .total-box {
        background: linear-gradient(135deg, var(--ramadan-gold), #f6d994);
        border-radius: 1rem;
        padding: 1.5rem;
        text-align: center;
        color: #2c1f03;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.3);
      }

      .total-box .amount {
        font-size: 2rem;
        font-weight: 800;
        letter-spacing: 0.5px;
      }

      .btn-ramadan {
        background: linear-gradient(140deg, #f8d687, #d4a842);
        border: none;
        border-radius: 999px;
        padding: 0.95rem 2rem;
        font-weight: 800;
        color: #2d1c01;
      }

      .btn-ramadan:hover {
        filter: brightness(1.02);
        color: #2d1c01;
      }

      .slot-highlight {
        background: rgba(15, 62, 48, 0.06);
        border-radius: 1rem;
        border: 1px solid rgba(31, 122, 77, 0.25);
        padding: 1rem 1.25rem;
      }

      .slot-chip {
        border: none;
        border-radius: 999px;
        padding: 0.55rem 1.1rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        cursor: pointer;
        transition: transform 0.12s ease, box-shadow 0.12s ease;
      }

      .slot-chip:not(:disabled):hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(5, 40, 32, 0.15);
      }

      .slot-chip.available {
        background: rgba(31, 122, 77, 0.15);
        color: #0b3c27;
      }

      .slot-chip.limited {
        background: rgba(214, 125, 31, 0.15);
        color: #7b3b05;
      }

      .slot-chip.full {
        background: rgba(200, 35, 51, 0.18);
        color: #7a101c;
      }

      .slot-chip:disabled {
        opacity: 0.55;
        cursor: not-allowed;
        box-shadow: none;
      }

      .slot-chip.selected {
        background: rgba(217, 180, 90, 0.55);
        color: #2c1f03;
        outline: 3px solid rgba(217, 180, 90, 0.95);
        box-shadow: 0 10px 28px rgba(5, 40, 32, 0.22);
        transform: translateY(-1px);
      }

      .slot-chip.selected small {
        color: rgba(44, 31, 3, 0.85);
      }
    </style>
  </head>
  <body>
    <main class="booking-shell">
      <div class="container">
        <div class="hero-card p-4 p-md-5 text-center mb-4">
          <div class="row align-items-center g-4">
            <div class="col-md-3 text-md-end">
              <i class="bi bi-stars display-5 icon-decor d-none d-md-inline"></i>
            </div>
            <div class="col-md-6">
              <p class="text-uppercase fw-semibold letter-spacing-1 text-light mb-1">ENT Complimentary</p>
              <h1 class="display-6 fw-bold mb-2"><?= htmlspecialchars($eventName !== '' ? $eventName : 'Ramadan Buffet') ?></h1>
              <p class="mb-0">All tickets will be locked to RM0.00 and marked as PAID.</p>
            </div>
            <div class="col-md-3 text-md-start">
              <i class="bi bi-moon-stars-fill display-5 icon-decor d-none d-md-inline"></i>
            </div>
          </div>
        </div>

        <section class="form-wrapper">
          <?php if ($errorMessage): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($errorMessage) ?></div>
          <?php endif; ?>

          <?php if (!$bookingOpen): ?>
            <div class="alert alert-warning" role="alert">Booking is currently closed.</div>
          <?php endif; ?>

          <form action="ent_create_booking.php" method="POST" class="row g-4" novalidate>
            <div class="col-12">
              <label for="eventDate" class="form-label">Pilih Tarikh Bufet</label>
              <input
                type="date"
                class="form-control"
                id="eventDate"
                name="slot_date"
                min="<?= htmlspecialchars((string)$ramadanStart) ?>"
                max="<?= htmlspecialchars((string)$ramadanEnd) ?>"
                required
                <?= !$bookingOpen ? 'disabled' : '' ?>
              >
              <div class="form-text">Tekan slot di bawah untuk isi automatik mengikut kekosongan semasa.</div>
            </div>

            <?php if ($slots): ?>
              <div class="col-12">
                <div class="slot-highlight">
                  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <i class="bi bi-moon-stars text-warning"></i>
                    <span class="fw-semibold text-muted text-uppercase small">Slot Ramadan</span>
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($slots as $slot): ?>
                      <?php
                        $remaining    = (int) $slot['max_capacity'] - (int) $slot['booked_count'];
                        $remaining    = max(0, $remaining);
                        $slotDate     = $slot['slot_date'];
                        $dateLabel    = (new DateTime($slotDate))->format('d M');
                        $isFull       = $remaining <= 0;
                        $isLimited    = !$isFull && $remaining < 50;
                        $chipClass    = $isFull ? 'full' : ($isLimited ? 'limited' : 'available');
                      ?>
                      <button
                        type="button"
                        class="slot-chip <?= $chipClass ?>"
                        data-slot-date="<?= htmlspecialchars($slotDate) ?>"
                        <?= $isFull ? 'disabled' : '' ?>
                      >
                        <span><?= htmlspecialchars($dateLabel) ?></span>
                      </button>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <div class="col-12">
              <label class="form-label">Kategori Tiket (RM0)</label>
              <div class="row g-3">
                <div class="col-md-4">
                  <div class="ticket-pill">
                    <h3>Dewasa</h3>
                    <small class="d-block mb-2 text-muted">RM0.00 setiap tiket</small>
                    <input type="number" class="form-control ticket-input" id="ticketDewasa" name="quantity_dewasa" min="0" value="" placeholder="0">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="ticket-pill">
                    <h3>Kanak-kanak</h3>
                    <small class="d-block mb-2 text-muted">RM0.00 setiap tiket</small>
                    <input type="number" class="form-control ticket-input" id="ticketKanak" name="quantity_kanak" min="0" value="" placeholder="0">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="ticket-pill">
                    <h3>Warga Emas</h3>
                    <small class="d-block mb-2 text-muted">RM0.00 setiap tiket</small>
                    <input type="number" class="form-control ticket-input" id="ticketWarga" name="quantity_warga_emas" min="0" value="" placeholder="0">
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="ticket-pill">
                    <h3>Kanak-kanak bawah 6 tahun</h3>
                    <small class="d-block mb-2 text-muted">RM0.00</small>
                    <input type="number" class="form-control ticket-input" id="ticketKanakFoc" name="quantity_kanak_foc" min="0" value="" placeholder="0">
                    <div class="form-text">Wajib buat pengesahan umur (bawah 6 tahun) sebelum teruskan.</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <label for="remark" class="form-label">Catatan</label>
              <textarea class="form-control" id="remark" name="remark" rows="3" placeholder="Contoh: Permintaan kerusi bayi / pilihan tempat duduk"><?= htmlspecialchars($remarkPrefill) ?></textarea>
              <div class="fw-semibold form-text"><em>*Sebarang permintaan khas atau pengaturan istimewa adalah tertakluk kepada ketersediaan, dan pihak syarikat berhak untuk menilai, meluluskan atau menolak permintaan tersebut berdasarkan kapasiti, polisi operasi, dan kelancaran sesi buffet.</em></div>
            </div>

            <div class="col-12">
              <div class="total-box">
                <p class="mb-2 text-uppercase fw-semibold">Jumlah</p>
                <div class="amount">RM <span id="totalAmount">0.00</span></div>
              </div>
            </div>

            <div class="col-12 text-center">
              <button type="submit" class="btn btn-ramadan btn-lg" <?= (!$bookingOpen) ? 'disabled' : '' ?>>
                <i class="bi bi-send-fill me-2"></i>Hantar Tempahan (RM0)
              </button>
            </div>

            <div class="col-12 text-center">
              <a href="ent_home.php" class="text-decoration-none">Back</a>
              <span class="text-muted mx-2">|</span>
              <button type="button" class="btn btn-link p-0 text-decoration-none align-baseline" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">Logout</button>
            </div>
          </form>
        </section>
      </div>
    </main>

    <div class="modal fade" id="validationModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2" style="color:#d8b45c;"></i>Makluman</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="validationModalBody" style="background: #fff9ed;"></div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-ramadan" data-bs-dismiss="modal">OK</button>
          </div>
        </div>
      </div>
    </div>

    <form method="POST" action="ent_logout.php" id="logoutForm" class="d-none">
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
    <script src="assets/js/main.js"></script>
    <script>
      const ticketInputs = document.querySelectorAll('.ticket-input');
      const slotButtons = document.querySelectorAll('.slot-chip');
      const slotDateInput = document.getElementById('eventDate');

      const bookingForm = document.querySelector('form[action="ent_create_booking.php"]');

      function showValidationModal(message) {
        const body = document.getElementById('validationModalBody');
        const el = document.getElementById('validationModal');
        if (!body || !el) return;
        body.textContent = message || '';
        const modal = bootstrap.Modal.getOrCreateInstance(el);
        modal.show();
      }

      function getTotalTicketQty() {
        if (!bookingForm) return 0;
        const qtyInputs = bookingForm.querySelectorAll('input[type="number"][name^="quantity_"]');
        let total = 0;
        qtyInputs.forEach(i => {
          total += parseInt(i.value, 10) || 0;
        });
        return total;
      }

      function syncSelectedSlotChip() {
        if (!slotDateInput) return;
        const selectedDate = slotDateInput.value;
        slotButtons.forEach(btn => {
          const btnDate = btn.dataset.slotDate;
          if (selectedDate && btnDate === selectedDate) {
            btn.classList.add('selected');
          } else {
            btn.classList.remove('selected');
          }
        });
      }

      slotButtons.forEach(button => {
        button.addEventListener('click', () => {
          const slotDate = button.dataset.slotDate;
          if (slotDateInput) {
            slotDateInput.value = slotDate;
            slotDateInput.dispatchEvent(new Event('change'));
          }
        });
      });

      if (slotDateInput) {
        slotDateInput.addEventListener('change', syncSelectedSlotChip);
        slotDateInput.addEventListener('input', syncSelectedSlotChip);
        slotDateInput.addEventListener('blur', syncSelectedSlotChip);
        syncSelectedSlotChip();
      }

      if (bookingForm) {
        bookingForm.addEventListener('submit', (e) => {
          const slot = slotDateInput ? String(slotDateInput.value || '') : '';
          if (!slot) {
            e.preventDefault();
            e.stopPropagation();
            showValidationModal('Sila pilih tarikh bufet terlebih dahulu.');
            return;
          }

          if (getTotalTicketQty() <= 0) {
            e.preventDefault();
            e.stopPropagation();
            showValidationModal('Sila pilih sekurang-kurangnya 1 tiket untuk meneruskan.');
          }
        });
      }

      ticketInputs.forEach(input => {
        input.addEventListener('focus', () => {
          if (input.value === '0') {
            input.value = '';
          }
        });
        input.addEventListener('input', () => {
          const raw = String(input.value || '');
          if (/^0+\d+$/.test(raw)) {
            input.value = String(parseInt(raw, 10));
          }
        });
      });

      (function () {
        const btn = document.getElementById('confirmLogoutBtn');
        const form = document.getElementById('logoutForm');
        if (!btn || !form) return;
        btn.addEventListener('click', () => form.submit());
      })();
    </script>
  </body>
</html>
