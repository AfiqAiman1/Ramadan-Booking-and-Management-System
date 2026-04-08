<?php
// index.php - Ramadan booking form with slot availability

session_start();
if (empty($_SESSION['visitor_role'])) {
    header('Location: home.php');
    exit;
}

$visitorRole = (string) ($_SESSION['visitor_role'] ?? '');
$visitorRoleMain = (string) ($_SESSION['visitor_role_main'] ?? '');
$visitorFullName = trim((string) ($_SESSION['visitor_full_name'] ?? ''));
$visitorPhone = trim((string) ($_SESSION['visitor_phone'] ?? ''));
$visitorEmail = trim((string) ($_SESSION['visitor_email'] ?? ''));
$visitorMilitaryNo = trim((string) ($_SESSION['visitor_military_no'] ?? ''));

if ($visitorFullName === '' || $visitorPhone === '') {
    header('Location: home.php');
    exit;
}

require_once __DIR__ . '/../config/config.php';

$ramadanStart = '2026-02-21';
$ramadanEnd   = '2026-03-19';
$slots        = [];
$prices       = ['dewasa' => 95.0, 'kanak' => 50.0, 'warga' => 85.0];
$errorMessage = '';
$settings     = [];

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
    ensure_booking_slots_schema($mysqli);

    $settings = load_global_settings($mysqli);
    if (!empty($settings['event_start_date'])) {
        $ramadanStart = (string) $settings['event_start_date'];
    }
    if (!empty($settings['event_end_date'])) {
        $ramadanEnd = (string) $settings['event_end_date'];
    }

    $prices = load_event_settings_prices($mysqli);

    $today = (new DateTime('today'))->format('Y-m-d');

    $cleanupSql = "
        DELETE bs
        FROM booking_slots bs
        LEFT JOIN bookings b ON b.slot_date = bs.slot_date
        WHERE bs.slot_date < ? AND b.id IS NULL
    ";
    if ($cleanupStmt = $mysqli->prepare($cleanupSql)) {
        $cleanupStmt->bind_param('s', $today);
        $cleanupStmt->execute();
        $cleanupStmt->close();
    }

    $slotQuery = "SELECT slot_date, max_capacity, booked_count FROM booking_slots WHERE slot_date BETWEEN ? AND ? AND slot_date >= ? ORDER BY slot_date ASC";
    if ($stmt = $mysqli->prepare($slotQuery)) {
        $stmt->bind_param('sss', $ramadanStart, $ramadanEnd, $today);
        $stmt->execute();
        $slotDate = '';
        $maxCapacity = 0;
        $bookedCount = 0;
        $stmt->bind_result($slotDate, $maxCapacity, $bookedCount);
        while ($stmt->fetch()) {
            $slots[] = [
                'slot_date' => $slotDate,
                'max_capacity' => $maxCapacity,
                'booked_count' => $bookedCount,
            ];
        }
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

$promoStart = '';
$promoEnd = '';
try {
    $endDt = DateTime::createFromFormat('Y-m-d', (string) $ramadanEnd);
    if ($endDt) {
        $promoEnd = $endDt->format('Y-m-d');
        $promoStart = (clone $endDt)->modify('-3 days')->format('Y-m-d');
    }
} catch (Throwable $e) {
    $promoStart = '';
    $promoEnd = '';
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ramadan Iftar Buffet</title>

    <link rel="icon" type="image/png" href="../assets/img/Logo%20ATM.png">

    <!-- Bootstrap & assets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">

    <style>
      :root {
        --ramadan-green: #0e3e32;
        --ramadan-deep: #092821;
        --ramadan-mint: #1e5c4a;
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

      .hero-card::after,
      .hero-card::before {
        content: '';
        position: absolute;
        background: radial-gradient(circle, rgba(217,180,90,0.35) 0%, transparent 65%);
        opacity: 0.6;
      }

      .hero-card::before {
        width: 180px;
        height: 180px;
        top: -60px;
        left: -40px;
      }

      .hero-card::after {
        width: 260px;
        height: 260px;
        bottom: -80px;
        right: -60px;
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
        font-weight: 700;
        letter-spacing: 0.5px;
      }

      .btn-ramadan {
        background: linear-gradient(140deg, #f8d687, #d4a842);
        border: none;
        border-radius: 999px;
        padding: 0.95rem 2rem;
        font-weight: 700;
        color: #2d1c01;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
      }

      .btn-ramadan:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(8, 16, 13, 0.2);
        color: #2d1c01;
      }

      .lantern {
        color: rgba(248,214,135,0.9);
        font-size: 1.25rem;
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
              <p class="text-uppercase fw-semibold letter-spacing-1 text-light mb-1">Ramadan Kareem</p>
              <h1 class="display-6 fw-bold mb-2"><?= htmlspecialchars((string)($settings['event_name'] ?? 'Sajian Serantau Negeri')) ?></h1>
              <p class="mb-0">Tempah meja untuk juadah berbuka penuh tradisi & kehangatan keluarga.</p>
            </div>
            <div class="col-md-3 text-md-start">
              <i class="bi bi-moon-stars-fill display-5 icon-decor d-none d-md-inline"></i>
            </div>
          </div>
        </div>

        <section class="form-wrapper">
          <div class="text-center mb-4">
            <i class="bi bi-lamp-fill lantern me-2"></i>
            <span class="text-uppercase text-muted fw-semibold small">Tiket Bufet</span>
            <i class="bi bi-lamp-fill lantern ms-2"></i>
          </div>

          <?php if ($errorMessage): ?>
            <div class="alert alert-danger" role="alert"><?= $errorMessage ?></div>
          <?php endif; ?>

          <?php if (!$bookingOpen): ?>
            <div class="alert alert-warning" role="alert">Tempahan buat masa ini ditutup. Sila cuba lagi kemudian.</div>
          <?php endif; ?>

          <form action="upload_proof.php" method="POST" class="row g-4" novalidate>
            <input type="hidden" name="visitor_role" value="<?= htmlspecialchars($visitorRole) ?>">
            <input type="hidden" name="visitor_role_main" value="<?= htmlspecialchars($visitorRoleMain) ?>">
            <input type="hidden" name="full_name" value="<?= htmlspecialchars($visitorFullName) ?>">
            <input type="hidden" name="phone" value="<?= htmlspecialchars($visitorPhone) ?>">
            <input type="hidden" name="email" value="<?= htmlspecialchars($visitorEmail) ?>">
            <input type="hidden" name="military_no" value="<?= htmlspecialchars($visitorMilitaryNo) ?>">

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
              <div class="form-text">
                Tekan slot di bawah untuk isi automatik mengikut kekosongan semasa.
              </div>
            </div>

            <?php if ($slots): ?>
              <div class="col-12">
                <div class="slot-highlight">
                  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <i class="bi bi-moon-stars text-warning"></i>
                    <span class="fw-semibold text-muted text-uppercase small">Slot Ramadan (<?= htmlspecialchars((new DateTime($ramadanStart))->format('d M')) ?> – <?= htmlspecialchars((new DateTime($ramadanEnd))->format('d M')) ?>)</span>
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
                        $statusText   = $isFull ? 'Penuh' : ($isLimited ? 'Terhad' : 'Tersedia');
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
                  <div class="pt-3 border-top mt-3">
                    <small>
                      <span class="slot-chip available me-2" style="pointer-events:none;">Tersedia</span>
                      <span class="slot-chip limited me-2" style="pointer-events:none;">&lt; 50</span>
                      <span class="slot-chip full" style="pointer-events:none;">Penuh</span>
                    </small>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <div class="col-12">
                <div class="alert alert-warning mb-0">Data slot Ramadan (masa nyata) tidak tersedia buat sementara waktu. Anda masih boleh pilih mana-mana tarikh pada Februari atau Mac.</div>
              </div>
            <?php endif; ?>

            <!-- Ticket Categories -->
            <div class="col-12">
              <label class="form-label">Kategori Tiket</label>
              <?php if ($visitorRole === 'AWAM'): ?>
                <div class="row g-3">
                  <div class="col-md-4">
                    <div class="ticket-pill">
                      <h3>Dewasa</h3>
                      <small class="d-block mb-2 text-muted">RM<span id="priceDewasaLabel"><?= number_format((float)$prices['dewasa'], 2) ?></span> setiap tiket</small>
                      <input type="number" class="form-control ticket-input" id="ticketDewasa" name="quantity_dewasa" min="0" value="" placeholder="0" data-price="<?= htmlspecialchars((string)$prices['dewasa']) ?>" data-base-price="<?= htmlspecialchars((string)$prices['dewasa']) ?>">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="ticket-pill">
                      <h3>Kanak-kanak</h3>
                      <small class="d-block mb-2 text-muted">RM<?= number_format((float)$prices['kanak'], 2) ?> setiap tiket</small>
                      <input type="number" class="form-control ticket-input" id="ticketKanak" name="quantity_kanak" min="0" value="" placeholder="0" data-price="<?= htmlspecialchars((string)$prices['kanak']) ?>">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="ticket-pill">
                      <h3>Warga Emas</h3>
                      <small class="d-block mb-2 text-muted">RM<span id="priceWargaLabel"><?= number_format((float)$prices['warga'], 2) ?></span> setiap tiket</small>
                      <input type="number" class="form-control ticket-input" id="ticketWarga" name="quantity_warga_emas" min="0" value="" placeholder="0" data-price="<?= htmlspecialchars((string)$prices['warga']) ?>" data-base-price="<?= htmlspecialchars((string)$prices['warga']) ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="ticket-pill">
                      <h3>Kanak-kanak bawah 6 tahun</h3>
                      <small class="d-block mb-2 text-muted">RM0.00</small>
                      <input type="number" class="form-control" id="ticketKanakFoc" name="quantity_kanak_foc" min="0" value="" placeholder="0">
                      <div class="form-text">Wajib buat pengesahan bawah 6 tahun sebelum teruskan.</div>
                    </div>
                  </div>
                </div>
              <?php elseif ($visitorRoleMain === 'ATM'): ?>
                <input type="hidden" name="quantity_dewasa" value="0">
                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="ticket-pill">
                      <h3>ATM</h3>
                      <small class="d-block mb-2 text-muted">RM<span id="priceAtmLabel"><?= number_format((float)($prices['atm'] ?? $prices['warga'] ?? 0), 2) ?></span> setiap tiket</small>
                      <input type="number" class="form-control ticket-input" id="ticketAtm" name="quantity_warga_emas" min="0" value="" placeholder="0" data-price="<?= htmlspecialchars((string)($prices['atm'] ?? $prices['warga'] ?? 0)) ?>" data-base-price="<?= htmlspecialchars((string)($prices['atm'] ?? $prices['warga'] ?? 0)) ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="ticket-pill">
                      <h3>Kanak-kanak</h3>
                      <small class="d-block mb-2 text-muted">RM<?= number_format((float)$prices['kanak'], 2) ?> setiap tiket</small>
                      <input type="number" class="form-control ticket-input" id="ticketKanak" name="quantity_kanak" min="0" value="" placeholder="0" data-price="<?= htmlspecialchars((string)$prices['kanak']) ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="ticket-pill">
                      <h3>Kanak-kanak bawah 6 tahun</h3>
                      <small class="d-block mb-2 text-muted">RM0.00</small>
                      <input type="number" class="form-control" id="ticketKanakFoc" name="quantity_kanak_foc" min="0" value="" placeholder="0">
                      <div class="form-text">Wajib buat pengesahan umur (bawah 6 tahun) sebelum teruskan.</div>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <input type="hidden" name="quantity_dewasa" value="0">
                <input type="hidden" name="quantity_kanak" value="0">
                <div class="row g-3">
                  <div class="col-md-4">
                    <div class="ticket-pill">
                      <h3>ATM</h3>
                      <small class="d-block mb-2 text-muted">RM<?= number_format((float)$prices['warga'], 2) ?> setiap tiket</small>
                      <input type="number" class="form-control ticket-input" id="ticketWarga" name="quantity_warga_emas" min="0" value="" placeholder="0" data-price="<?= htmlspecialchars((string)$prices['warga']) ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="ticket-pill">
                      <h3>Kanak-kanak bawah 6 tahun</h3>
                      <small class="d-block mb-2 text-muted">RM0.00</small>
                      <input type="number" class="form-control" id="ticketKanakFoc" name="quantity_kanak_foc" min="0" value="" placeholder="0">
                      <div class="form-text">Wajib buat pengesahan umur (bawah 6 tahun) sebelum teruskan.</div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <div class="col-12" id="focDeclarationWrap" style="display:none;">
              <div class="form-check text-start">
                <input class="form-check-input" type="checkbox" value="1" id="focDeclaration" name="foc_declaration">
                <label class="form-check-label" for="focDeclaration">
                  Saya mengesahkan semua kanak-kanak yang dipilih dalam kategori <strong>Kanak-kanak bawah 6 tahun</strong> adalah benar-benar berumur bawah 6 tahun.
                </label>
              </div>
              <div class="form-text">Pihak hotel berhak menolak kemasukan jika maklumat tidak benar.</div>
            </div>

            <div class="col-12">
              <label for="remark" class="form-label">Catatan</label>
              <textarea class="form-control" id="remark" name="remark" rows="3" placeholder="Contoh: Permintaan kerusi bayi / pilihan tempat duduk"></textarea>
              <div class="form-text">
                Sila nyatakan sebarang permintaan khas (contoh: kerusi bayi atau pilihan tempat duduk) di ruangan catatan. Kami akan cuba penuhi sebaik mungkin, tertakluk kepada ketersediaan.
                <div class="fw-semibold mt-1"><em>*Sebarang permintaan khas atau pengaturan istimewa adalah tertakluk kepada ketersediaan, dan pihak syarikat berhak untuk menilai, meluluskan atau menolak permintaan tersebut berdasarkan kapasiti, polisi operasi, dan kelancaran sesi buffet.</em></div>
              </div>
            </div>

            <!-- Total -->
            <div class="col-12">
              <div class="total-box">
                <p class="mb-2 text-uppercase fw-semibold">Jumlah</p>
                <div class="amount">RM <span id="totalAmount">0.00</span></div>
              </div>
            </div>

            <!-- Submit -->
            <div class="col-12 text-center">
              <input type="hidden" name="total_price" id="totalPriceInput" value="0">
              <button type="submit" class="btn btn-ramadan btn-lg" <?= (!$bookingOpen) ? 'disabled' : '' ?>>
                <i class="bi bi-send-fill me-2"></i>Seterusnya
              </button>
              <div class="small mt-3" style="color: rgba(15,46,38,0.65);" id="autosaveStatus" aria-live="polite"></div>
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
      const promoStartDate = <?= $promoStart !== '' ? ('\'' . htmlspecialchars($promoStart, ENT_QUOTES) . '\'') : 'null' ?>;
      const promoEndDate = <?= $promoEnd !== '' ? ('\'' . htmlspecialchars($promoEnd, ENT_QUOTES) . '\'') : 'null' ?>;
      const promoPrice = 75;
      const ticketInputs = document.querySelectorAll('.ticket-input');
      const totalAmountEl = document.getElementById('totalAmount');
      const totalPriceInput = document.getElementById('totalPriceInput');
      const slotButtons = document.querySelectorAll('.slot-chip');
      const slotDateInput = document.getElementById('eventDate');
      const dewasaInput = document.getElementById('ticketDewasa');
      const dewasaPriceLabel = document.getElementById('priceDewasaLabel');
      const wargaInput = document.getElementById('ticketWarga');
      const wargaPriceLabel = document.getElementById('priceWargaLabel');
      const atmInput = document.getElementById('ticketAtm');
      const atmPriceLabel = document.getElementById('priceAtmLabel');
      const focInput = document.getElementById('ticketKanakFoc');
      const focWrap = document.getElementById('focDeclarationWrap');
      const focDeclaration = document.getElementById('focDeclaration');
      const bookingForm = document.querySelector('form[action="upload_proof.php"]');

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
            return;
          }

          const focQty = focInput ? (parseInt(focInput.value, 10) || 0) : 0;
          if (focQty > 0 && focDeclaration && !focDeclaration.checked) {
            e.preventDefault();
            e.stopPropagation();
            showValidationModal('Sila tandakan pengesahan umur untuk Kanak-kanak bawah 6 tahun.');
          }
        });
      }

      function applyEarlyBirdPrice() {
        const selectedDate = slotDateInput ? String(slotDateInput.value || '') : '';
        const isPromo = !!(promoStartDate && promoEndDate && selectedDate && selectedDate >= promoStartDate && selectedDate <= promoEndDate);

        if (dewasaInput) {
          const basePrice = parseFloat(dewasaInput.dataset.basePrice || dewasaInput.dataset.price || '0');
          const effectivePrice = (selectedDate === '2026-02-21') ? 65 : (isPromo ? promoPrice : basePrice);
          dewasaInput.dataset.price = String(effectivePrice);
          if (dewasaPriceLabel) {
            dewasaPriceLabel.innerHTML = isPromo
              ? ('<span class="text-decoration-line-through me-1">' + basePrice.toFixed(2) + '</span>' + Number(promoPrice).toFixed(2))
              : effectivePrice.toFixed(2);
          }
        }

        if (wargaInput) {
          const wargaBasePrice = parseFloat(wargaInput.dataset.basePrice || wargaInput.dataset.price || '0');
          const wargaEffectivePrice = (selectedDate === '2026-02-21') ? 65 : (isPromo ? promoPrice : wargaBasePrice);
          wargaInput.dataset.price = String(wargaEffectivePrice);
          if (wargaPriceLabel) {
            wargaPriceLabel.innerHTML = isPromo
              ? ('<span class="text-decoration-line-through me-1">' + wargaBasePrice.toFixed(2) + '</span>' + Number(promoPrice).toFixed(2))
              : wargaEffectivePrice.toFixed(2);
          }
        }

        if (atmInput) {
          const atmBasePrice = parseFloat(atmInput.dataset.basePrice || atmInput.dataset.price || '0');
          const atmEffectivePrice = (selectedDate === '2026-02-21') ? 65 : (isPromo ? promoPrice : atmBasePrice);
          atmInput.dataset.price = String(atmEffectivePrice);
          if (atmPriceLabel) {
            atmPriceLabel.innerHTML = isPromo
              ? ('<span class="text-decoration-line-through me-1">' + atmBasePrice.toFixed(2) + '</span>' + Number(promoPrice).toFixed(2))
              : atmEffectivePrice.toFixed(2);
          }
        }
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

      function updateTotal() {
        let total = 0;
        ticketInputs.forEach(input => {
          const quantity = parseInt(input.value, 10) || 0;
          const price = parseFloat(input.dataset.price);
          total += quantity * price;
        });
        totalAmountEl.textContent = total.toFixed(2);
        if (totalPriceInput) {
          totalPriceInput.value = total.toFixed(2);
        }
      }

      function syncFocDeclaration() {
        if (!focInput || !focWrap || !focDeclaration) return;
        const focQty = parseInt(focInput.value, 10) || 0;
        focWrap.style.display = focQty > 0 ? '' : 'none';
        if (focQty <= 0) {
          focDeclaration.checked = false;
          focDeclaration.required = false;
        } else {
          focDeclaration.required = true;
        }
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
        input.addEventListener('input', updateTotal);
        input.addEventListener('change', updateTotal);
      });

      if (focInput) {
        focInput.addEventListener('input', syncFocDeclaration);
        focInput.addEventListener('change', syncFocDeclaration);
        syncFocDeclaration();
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
        slotDateInput.addEventListener('change', () => {
          syncSelectedSlotChip();
          applyEarlyBirdPrice();
          updateTotal();
        });
        slotDateInput.addEventListener('input', () => {
          syncSelectedSlotChip();
          applyEarlyBirdPrice();
          updateTotal();
        });
        slotDateInput.addEventListener('blur', () => {
          syncSelectedSlotChip();
          applyEarlyBirdPrice();
          updateTotal();
        });
        syncSelectedSlotChip();
        applyEarlyBirdPrice();
        updateTotal();
      }

      (function () {
        const statusEl = document.getElementById('autosaveStatus');
        const form = document.querySelector('form[action="upload_proof.php"]');
        if (!form || !statusEl) return;

        const fields = {
          slot_date: document.getElementById('eventDate'),
          quantity_dewasa: document.getElementById('ticketDewasa'),
          quantity_kanak: document.getElementById('ticketKanak'),
          quantity_kanak_foc: document.getElementById('ticketKanakFoc'),
          quantity_warga_emas: document.getElementById('ticketWarga') || document.getElementById('ticketAtm'),
          total_price: document.getElementById('totalPriceInput'),
          remark: document.getElementById('remark'),
          foc_declaration: document.getElementById('focDeclaration'),
        };

        function setStatus(text) {
          statusEl.textContent = text || '';
        }

        function collectDraft() {
          return {
            slot_date: fields.slot_date ? fields.slot_date.value : '',
            quantity_dewasa: fields.quantity_dewasa ? fields.quantity_dewasa.value : '0',
            quantity_kanak: fields.quantity_kanak ? fields.quantity_kanak.value : '0',
            quantity_kanak_foc: fields.quantity_kanak_foc ? fields.quantity_kanak_foc.value : '0',
            quantity_warga_emas: fields.quantity_warga_emas ? fields.quantity_warga_emas.value : '0',
            total_price: fields.total_price ? fields.total_price.value : '0',
            remark: fields.remark ? fields.remark.value : '',
            foc_declaration: fields.foc_declaration ? (fields.foc_declaration.checked ? '1' : '0') : '0',
          };
        }

        function applyDraft(draft) {
          if (!draft || typeof draft !== 'object') return;
          if (fields.slot_date && typeof draft.slot_date === 'string' && draft.slot_date) {
            fields.slot_date.value = draft.slot_date;
            fields.slot_date.dispatchEvent(new Event('change'));
          }
          if (fields.quantity_dewasa && draft.quantity_dewasa !== undefined) fields.quantity_dewasa.value = String(draft.quantity_dewasa ?? '0') === '0' ? '' : String(draft.quantity_dewasa ?? '0');
          if (fields.quantity_kanak && draft.quantity_kanak !== undefined) fields.quantity_kanak.value = String(draft.quantity_kanak ?? '0') === '0' ? '' : String(draft.quantity_kanak ?? '0');
          if (fields.quantity_kanak_foc && draft.quantity_kanak_foc !== undefined) fields.quantity_kanak_foc.value = String(draft.quantity_kanak_foc ?? '0') === '0' ? '' : String(draft.quantity_kanak_foc ?? '0');
          if (fields.quantity_warga_emas && draft.quantity_warga_emas !== undefined) fields.quantity_warga_emas.value = String(draft.quantity_warga_emas ?? '0') === '0' ? '' : String(draft.quantity_warga_emas ?? '0');
          if (fields.remark && typeof draft.remark === 'string') fields.remark.value = draft.remark;
          if (fields.foc_declaration) fields.foc_declaration.checked = String(draft.foc_declaration || '0') === '1';
          applyEarlyBirdPrice();
          updateTotal();
        }

        let autosaveToken = '';
        async function ensureToken() {
          if (autosaveToken) return autosaveToken;
          const res = await fetch('../autosave_token.php', { cache: 'no-store', credentials: 'same-origin' });
          if (!res.ok) throw new Error('token');
          const data = await res.json();
          if (!data || !data.ok || !data.autosave_token) throw new Error('token');
          autosaveToken = String(data.autosave_token);
          return autosaveToken;
        }

        async function restore() {
          try {
            setStatus('Restoring saved draft...');
            const t = await ensureToken();
            const res = await fetch('../autosave_booking.php', {
              method: 'GET',
              cache: 'no-store',
              credentials: 'same-origin',
              headers: { 'X_AUTOSAVE_TOKEN': t }
            });
            if (!res.ok) {
              setStatus('');
              return;
            }
            const data = await res.json();
            if (data && data.ok && data.draft) {
              applyDraft(data.draft);
              if (data.draft._saved_at) {
                setStatus('Draft restored.');
              } else {
                setStatus('');
              }
            } else {
              setStatus('');
            }
          } catch (e) {
            setStatus('');
          }
        }

        let saveTimer = null;
        async function saveNow() {
          try {
            setStatus('Saving...');
            const t = await ensureToken();
            const payload = collectDraft();
            const res = await fetch('../autosave_booking.php', {
              method: 'POST',
              cache: 'no-store',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json', 'X_AUTOSAVE_TOKEN': t },
              body: JSON.stringify(payload)
            });
            if (!res.ok) {
              setStatus('');
              return;
            }
            const data = await res.json();
            if (data && data.ok && data.saved_at) {
              setStatus('Saved.');
            } else {
              setStatus('');
            }
          } catch (e) {
            setStatus('');
          }
        }

        function scheduleSave() {
          if (saveTimer) window.clearTimeout(saveTimer);
          saveTimer = window.setTimeout(saveNow, 700);
        }

        Object.values(fields).forEach(el => {
          if (!el) return;
          el.addEventListener('input', scheduleSave);
          el.addEventListener('change', scheduleSave);
        });

        restore();
      })();
    </script>
  </body>
</html>
