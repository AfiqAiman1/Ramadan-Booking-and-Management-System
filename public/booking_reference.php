<?php
require_once __DIR__ . '/../config/config.php';

$ref = trim($_GET['ref'] ?? '');
if ($ref === '') {
    header('Location: index.php');
    exit;
}

$isNewBooking = ((string) ($_GET['new'] ?? '') === '1');

$mysqli = null;
$booking = null;
$settings = [];

$backHref = 'home.php';

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
    ensure_global_settings_schema($mysqli);
    $settings = load_global_settings($mysqli);

    $stmt = $mysqli->prepare('SELECT booking_reference, full_name, phone, slot_date, quantity_dewasa, quantity_kanak, quantity_kanak_foc, quantity_warga_emas, quantity_atm, staff_blanket_qty, living_in_qty, ajk_qty, free_voucher_qty, comp_qty, total_price, payment_method FROM bookings WHERE booking_reference = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $ref);
        $stmt->execute();
        $res = $stmt->get_result();
        $booking = $res ? ($res->fetch_assoc() ?: null) : null;
        if ($res) {
            $res->free();
        }
        $stmt->close();
    }

    $mysqli->close();
} catch (Throwable $e) {
    if ($mysqli instanceof mysqli) {
        $mysqli->close();
    }
}

if (!$booking) {
    http_response_code(404);
    $backHref = 'index.php';
} else {
    $paymentMethod = strtoupper(trim((string) ($booking['payment_method'] ?? '')));
    $isEntBooking = $paymentMethod === 'ENT' || str_starts_with((string) ($booking['booking_reference'] ?? ''), 'ENT');
    if ($isEntBooking) {
        $backHref = '../admin/admin_dashboard.php';
    }
}

$eventName = (string) ($settings['event_name'] ?? '');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rujukan Tempahan - Bufet Ramadan</title>
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
        --ramadan-deep: #041f18;
        --ramadan-gold: #d8b45c;
        --ramadan-cream: #fff8ec;
      }
      body {
        font-family: 'Cairo', system-ui, sans-serif;
        background:
          radial-gradient(circle at 20% 20%, rgba(216,180,92,0.18), transparent 55%),
          radial-gradient(circle at 80% 0%, rgba(216,180,92,0.12), transparent 45%),
          linear-gradient(180deg, var(--ramadan-deep) 0%, var(--ramadan-green) 60%, #051b15 100%);
        min-height: 100vh;
        color: #0b1e1a;
      }
      .shell {
        padding: 3rem 0 4rem;
      }
      .card-ramadan {
        background: var(--ramadan-cream);
        border-radius: 1.5rem;
        border: 1px solid rgba(216,180,92,0.35);
        box-shadow: 0 22px 45px rgba(7, 20, 17, 0.35);
      }
      .ref-box {
        background: linear-gradient(135deg, rgba(216,180,92,0.26), rgba(216,180,92,0.12));
        border: 1px solid rgba(216,180,92,0.45);
        border-radius: 1.25rem;
        padding: 1.5rem;
        text-align: center;
      }
      .ref-label {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 1rem;
        border-radius: 999px;
        background: rgba(8,55,43,0.08);
        color: #0f3f2e;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 0.8rem;
      }
      .ref-value {
        font-size: 2.25rem;
        font-weight: 800;
        letter-spacing: 0.6px;
        margin: 0.75rem 0 0;
        color: #0f3f2e;
        word-break: break-word;
      }
      .ref-box-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
      }
      .ref-side {
        flex: 0 0  100px;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .ref-side img {
        max-height: 64px;
        width: auto;
        max-width: 100%;
        object-fit: contain;
      }
      .ref-center {
        flex: 1 1 auto;
        text-align: center;
      }
      @media (max-width: 576px) {
        .ref-box {
          padding: 1.1rem;
        }
        .ref-box-inner {
          flex-direction: row;
          align-items: center;
          flex-wrap: wrap;
          gap: 0.5rem 1rem;
        }
        .ref-side {
          flex: 0 0 auto;
        }
        .ref-side-left {
          order: 1;
        }
        .ref-side-right {
          order: 2;
          margin-left: auto;
        }
        .ref-center {
          order: 3;
          flex: 0 0 100%;
          margin-top: 0.35rem;
        }
        .ref-side img {
          max-height: 48px;
        }
        .ref-label {
          font-size: 0.72rem;
        }
        .ref-value {
          font-size: 1.7rem;
          letter-spacing: 0.4px;
        }
      }
      .btn-ramadan {
        background: linear-gradient(140deg, #f8d687, #d4a842);
        border: none;
        border-radius: 999px;
        padding: 0.9rem 1.4rem;
        font-weight: 800;
        color: #2d1c01;
      }
      .btn-ramadan:hover {
        color: #2d1c01;
      }
      .muted-top {
        color: rgba(255,255,255,0.82);
      }
      .line-item {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.55rem 0;
        border-bottom: 1px dashed rgba(8,55,43,0.18);
      }
      .line-item:last-child {
        border-bottom: none;
      }
      .line-title {
        font-weight: 700;
        color: #163b33;
      }
      .line-value {
        font-weight: 700;
        color: #0b1e1a;
      }
    </style>
  </head>
  <body>
    <main class="shell">
      <div class="container">
        <div class="text-center mb-4">
          <div class="muted-top text-uppercase fw-semibold small">Tempahan Ramadan</div>
          <h1 class="text-white fw-bold mb-1">Rujukan Tempahan</h1>
          <?php if ($eventName !== ''): ?>
            <div class="muted-top"><i class="bi bi-moon-stars-fill" style="color: rgba(216,180,92,0.95);"></i> <?= htmlspecialchars($eventName) ?></div>
          <?php endif; ?>
        </div>

        <div class="col-lg-8 mx-auto">
          <div class="card-ramadan p-4 p-md-5">
            <?php if (!$booking): ?>
              <div class="alert alert-warning mb-0">Tempahan tidak ditemui untuk rujukan <strong><?= htmlspecialchars($ref) ?></strong>.</div>
              <div class="mt-3">
                <a href="<?= htmlspecialchars($backHref) ?>" class="btn btn-ramadan">Kembali</a>
              </div>
            <?php else: ?>
              <div class="modal fade" id="bookingSuccessModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content" style="border-radius: 1.25rem; border: 1px solid rgba(216,180,92,0.35);">
                    <div class="modal-header" style="background: rgba(31,122,77,0.14);">
                      <h2 class="modal-title h6 mb-0"><i class="bi bi-check-circle-fill" style="color: rgba(31,122,77,0.95);"></i> Tempahan Berjaya</h2>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="fw-semibold">Tempahan anda telah berjaya direkodkan.</div>
                      <div class="text-muted mt-1">Sila simpan rujukan tempahan ini dan tunjukkan kepada petugas semasa hari buffet untuk tujuan pengesahan.</div>
                      <div class="text-muted mt-1">Sebarang permintaan khas adalah tertakluk kepada ketersediaan.</div>
                      <div class="text-muted mt-1">Sila dapatkan nombor meja di kaunter semakan pada hari kehadiran.</div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-ramadan" data-bs-dismiss="modal">Baik</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="ref-box mb-4">
                <div class="ref-box-inner">
                  <div class="ref-side ref-side-left">
                    <img src="../assets/img/Logo%20ATM.png" alt="Logo ATM">
                  </div>
                  <div class="ref-center">
                    <div class="ref-label"><i class="bi bi-ticket-perforated"></i> Rujukan Tempahan</div>
                    <div class="ref-value"><?= htmlspecialchars((string)$booking['booking_reference']) ?></div>
                    <div class="text-muted mt-2" style="font-size: 0.95rem; line-height: 1.35;">Sila ke kaunter semakan untuk tujuan pengesahan.</div>
                  </div>
                  <div class="ref-side ref-side-right">
                    <img src="../assets/img/bg%20green.png" alt="Bg">
                  </div>
                </div>
              </div>

              <div class="mb-4">
                <div class="line-item">
                  <div class="line-title">Nama </div>
                  <div class="line-value"><?= htmlspecialchars((string)$booking['full_name']) ?></div>
                </div>
                <div class="line-item">
                  <div class="line-title">No. telefon</div>
                  <div class="line-value"><?= htmlspecialchars((string)$booking['phone']) ?></div>
                </div>
                <div class="line-item">
                  <div class="line-title">Tarikh tempahan</div>
                  <div class="line-value"><?= htmlspecialchars((string)$booking['slot_date']) ?></div>
                </div>
              </div>

              <h2 class="h5 mb-3">Kuantiti tiket</h2>
              <div class="mb-4">
                <?php if ($isEntBooking): ?>
                  <?php if ((int) ($booking['comp_qty'] ?? 0) > 0): ?>
                    <div class="line-item">
                      <div class="line-title">COMP</div>
                      <div class="line-value"><?= (int) ($booking['comp_qty'] ?? 0) ?></div>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <?php if ((int) $booking['quantity_dewasa'] > 0): ?>
                    <div class="line-item">
                      <div class="line-title">Dewasa</div>
                      <div class="line-value"><?= (int) $booking['quantity_dewasa'] ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if ((int) $booking['quantity_kanak'] > 0): ?>
                    <div class="line-item">
                      <div class="line-title">Kanak-kanak</div>
                      <div class="line-value"><?= (int) $booking['quantity_kanak'] ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if ((int) ($booking['quantity_kanak_foc'] ?? 0) > 0): ?>
                    <div class="line-item">
                      <div class="line-title">Kanak-kanak bawah 6 tahun</div>
                      <div class="line-value"><?= (int) $booking['quantity_kanak_foc'] ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if ((int) $booking['quantity_warga_emas'] > 0): ?>
                    <div class="line-item">
                      <div class="line-title">Warga Emas</div>
                      <div class="line-value"><?= (int) $booking['quantity_warga_emas'] ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if ((int) ($booking['quantity_atm'] ?? 0) > 0): ?>
                    <div class="line-item">
                      <div class="line-title">ATM</div>
                      <div class="line-value"><?= (int) ($booking['quantity_atm'] ?? 0) ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if ((int) ($booking['staff_blanket_qty'] ?? 0) > 0): ?>
                    <div class="line-item">
                      <div class="line-title">STAFF BLANKET</div>
                      <div class="line-value"><?= (int) ($booking['staff_blanket_qty'] ?? 0) ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if ((int) ($booking['living_in_qty'] ?? 0) > 0): ?>
                    <div class="line-item">
                      <div class="line-title">LIVING IN</div>
                      <div class="line-value"><?= (int) ($booking['living_in_qty'] ?? 0) ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if ((int) ($booking['ajk_qty'] ?? 0) > 0): ?>
                    <div class="line-item">
                      <div class="line-title">AJK</div>
                      <div class="line-value"><?= (int) ($booking['ajk_qty'] ?? 0) ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if ((int) ($booking['free_voucher_qty'] ?? 0) > 0): ?>
                    <div class="line-item">
                      <div class="line-title">FREE VOUCHER</div>
                      <div class="line-value"><?= (int) ($booking['free_voucher_qty'] ?? 0) ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if ((int) ($booking['comp_qty'] ?? 0) > 0): ?>
                    <div class="line-item">
                      <div class="line-title">COMP</div>
                      <div class="line-value"><?= (int) ($booking['comp_qty'] ?? 0) ?></div>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>

              <div class="alert" style="background: rgba(31,122,77,0.12); border: 1px solid rgba(31,122,77,0.25);">
                <div class="d-flex justify-content-between align-items-center">
                  <div class="fw-bold">Jumlah (RM)</div>
                  <div class="fw-bold">RM <?= number_format((float) $booking['total_price'], 2) ?></div>
                </div>
              </div>

              <div class="mt-4 d-flex flex-column flex-sm-row gap-2 justify-content-center">
                <button type="button" class="btn btn-outline-dark" id="downloadBooking" data-ref="<?= htmlspecialchars((string)$booking['booking_reference']) ?>" data-name="<?= htmlspecialchars((string)$booking['full_name']) ?>" data-phone="<?= htmlspecialchars((string)$booking['phone']) ?>" data-date="<?= htmlspecialchars((string)$booking['slot_date']) ?>" data-dewasa="<?= (int) ($booking['quantity_dewasa'] ?? 0) ?>" data-kanak="<?= (int) ($booking['quantity_kanak'] ?? 0) ?>" data-kanak-foc="<?= (int) ($booking['quantity_kanak_foc'] ?? 0) ?>" data-warga="<?= (int) ($booking['quantity_warga_emas'] ?? 0) ?>" data-atm="<?= (int) ($booking['quantity_atm'] ?? 0) ?>" data-staff-blanket="<?= (int) ($booking['staff_blanket_qty'] ?? 0) ?>" data-living-in="<?= (int) ($booking['living_in_qty'] ?? 0) ?>" data-ajk="<?= (int) ($booking['ajk_qty'] ?? 0) ?>" data-free-voucher="<?= (int) ($booking['free_voucher_qty'] ?? 0) ?>" data-comp="<?= (int) ($booking['comp_qty'] ?? 0) ?>" data-is-ent="<?= $isEntBooking ? '1' : '0' ?>" data-total="<?= htmlspecialchars(number_format((float)$booking['total_price'], 2, '.', '')) ?>" data-event="<?= htmlspecialchars($eventName) ?>">
                  <i class="bi bi-download me-2"></i>Muat Turun
                </button>
                <a href="<?= htmlspecialchars($backHref) ?>" class="btn btn-ramadan">Kembali</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <?php if ($booking): ?>
      <script>
        window.addEventListener('load', function () {
          const isNew = <?= $isNewBooking ? 'true' : 'false' ?>;
          const successEl = document.getElementById('bookingSuccessModal');
          const reminderEl = document.getElementById('bringRefModal');

          function showReminder() {
            if (!reminderEl) return;
            const reminderModal = new bootstrap.Modal(reminderEl, { backdrop: true });
            reminderModal.show();
          }

          if (isNew && successEl) {
            const successModal = new bootstrap.Modal(successEl, { backdrop: true });
            successEl.addEventListener('hidden.bs.modal', function () {
              showReminder();
            }, { once: true });
            successModal.show();
          } else {
            showReminder();
          }
        });
      </script>

      <script>
        (function () {
          const btn = document.getElementById('downloadBooking');
          if (!btn) return;

          function drawRoundedRect(ctx, x, y, w, h, r) {
            const radius = Math.min(r, w / 2, h / 2);
            ctx.beginPath();
            ctx.moveTo(x + radius, y);
            ctx.arcTo(x + w, y, x + w, y + h, radius);
            ctx.arcTo(x + w, y + h, x, y + h, radius);
            ctx.arcTo(x, y + h, x, y, radius);
            ctx.arcTo(x, y, x + w, y, radius);
            ctx.closePath();
          }

          function downloadCanvasAsPng(canvas, filename) {
            const link = document.createElement('a');
            link.download = filename;
            link.href = canvas.toDataURL('image/png');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
          }

          async function handleDownload() {
            if (document.fonts && document.fonts.ready) {
              try { await document.fonts.ready; } catch (_) {}
            }

            const ref = btn.dataset.ref || '';
            const name = btn.dataset.name || '';
            const phone = btn.dataset.phone || '';
            const date = btn.dataset.date || '';
            const dewasa = btn.dataset.dewasa || '0';
            const kanak = btn.dataset.kanak || '0';
            const kanakFoc = btn.dataset.kanakFoc || '0';
            const warga = btn.dataset.warga || '0';
            const atm = btn.dataset.atm || '0';
            const staffBlanket = btn.dataset.staffBlanket || '0';
            const livingIn = btn.dataset.livingIn || '0';
            const ajk = btn.dataset.ajk || '0';
            const freeVoucher = btn.dataset.freeVoucher || '0';
            const comp = btn.dataset.comp || '0';
            const isEnt = btn.dataset.isEnt === '1';
            const total = btn.dataset.total || '0.00';
            const eventName = btn.dataset.event || '';

            const logoPath = '../assets/img/Logo%20ATM.png';
            function loadImage(src) {
              return new Promise((resolve) => {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = () => resolve(null);
                img.src = src;
              });
            }

            const logoImg = await loadImage(logoPath);

            const canvas = document.createElement('canvas');
            canvas.width = 1200;
            canvas.height = 1600;
            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            ctx.fillStyle = '#041f18';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            const grad = ctx.createRadialGradient(240, 220, 40, 240, 220, 700);
            grad.addColorStop(0, 'rgba(216,180,92,0.22)');
            grad.addColorStop(1, 'rgba(216,180,92,0)');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.fillStyle = '#fff8ec';
            drawRoundedRect(ctx, 80, 140, 1040, 1320, 36);
            ctx.fill();

            ctx.strokeStyle = 'rgba(216,180,92,0.6)';
            ctx.lineWidth = 3;
            drawRoundedRect(ctx, 80, 140, 1040, 1320, 36);
            ctx.stroke();

            ctx.fillStyle = '#0f3f2e';
            ctx.textAlign = 'center';
            ctx.font = "700 34px Cairo, system-ui, sans-serif";
            ctx.fillText('BOOKING REFERENCE', canvas.width / 2, 230);

            if (logoImg) {
              const logoW = 90;
              const logoH = 90;
              const topY = 175;
              ctx.drawImage(logoImg, 140, topY, logoW, logoH);
              ctx.drawImage(logoImg, canvas.width - 140 - logoW, topY, logoW, logoH);
            }

            ctx.font = "800 70px Cairo, system-ui, sans-serif";
            ctx.fillText(ref, canvas.width / 2, 320);

            ctx.fillStyle = 'rgba(15,63,46,0.12)';
            drawRoundedRect(ctx, 140, 360, 920, 90, 22);
            ctx.fill();

            ctx.fillStyle = '#163b33';
            ctx.font = "700 30px Cairo, system-ui, sans-serif";
            ctx.fillText(eventName !== '' ? eventName : 'Ramadan Booking', canvas.width / 2, 418);

            ctx.textAlign = 'left';
            ctx.fillStyle = '#163b33';
            ctx.font = "700 30px Cairo, system-ui, sans-serif";

            let y = 520;
            const leftX = 160;
            const rightX = 1020;
            const lineH = 56;

            function row(label, value) {
              ctx.fillStyle = '#163b33';
              ctx.textAlign = 'left';
              ctx.fillText(label, leftX, y);
              ctx.fillStyle = '#0b1e1a';
              ctx.textAlign = 'right';
              ctx.fillText(value, rightX, y);
              ctx.strokeStyle = 'rgba(8,55,43,0.18)';
              ctx.lineWidth = 2;
              ctx.beginPath();
              ctx.moveTo(150, y + 22);
              ctx.lineTo(1050, y + 22);
              ctx.stroke();
              y += lineH;
            }

            row('Customer name', name);
            row('Phone number', phone);
            row('Booking date', date);

            y += 18;
            ctx.textAlign = 'left';
            ctx.fillStyle = '#0f3f2e';
            ctx.font = "800 34px Cairo, system-ui, sans-serif";
            ctx.fillText('Ticket quantities', leftX, y);
            y += 56;

            ctx.font = "700 30px Cairo, system-ui, sans-serif";
            if (isEnt) {
              if (Number(comp) > 0) {
                row('COMP', comp);
              } else {
                row('-', '-');
              }
            } else {
              if (Number(dewasa) > 0) {
                row('Dewasa', dewasa);
              }
              if (Number(kanak) > 0) {
                row('Kanak-kanak', kanak);
              }
              if (Number(kanakFoc) > 0) {
                row('Kanak-kanak bawah 6 tahun', kanakFoc);
              }
              if (Number(warga) > 0) {
                row('Warga Emas', warga);
              }
              if (Number(atm) > 0) {
                row('ATM', atm);
              }
              if (Number(staffBlanket) > 0) {
                row('STAFF BLANKET', staffBlanket);
              }
              if (Number(livingIn) > 0) {
                row('LIVING IN', livingIn);
              }
              if (Number(ajk) > 0) {
                row('AJK', ajk);
              }
              if (Number(freeVoucher) > 0) {
                row('FREE VOUCHER', freeVoucher);
              }
              if (Number(comp) > 0) {
                row('COMP', comp);
              }
            }

            y += 22;
            ctx.fillStyle = 'rgba(31,122,77,0.12)';
            drawRoundedRect(ctx, 140, y, 920, 120, 22);
            ctx.fill();
            ctx.strokeStyle = 'rgba(31,122,77,0.25)';
            ctx.lineWidth = 2;
            drawRoundedRect(ctx, 140, y, 920, 120, 22);
            ctx.stroke();

            ctx.fillStyle = '#0b1e1a';
            ctx.font = "800 34px Cairo, system-ui, sans-serif";
            ctx.textAlign = 'left';
            ctx.fillText('Total amount (RM)', 170, y + 76);
            ctx.textAlign = 'right';
            ctx.fillText('RM ' + Number(total).toFixed(2), 1030, y + 76);

            ctx.fillStyle = 'rgba(11, 30, 26, 0.7)';
            ctx.font = "600 22px Cairo, system-ui, sans-serif";
            ctx.textAlign = 'center';
            ctx.fillText('Sila simpan rujukan tempahan ini dan tunjukkan kepada petugas semasa hari buffet untuk tujuan pengesahan.', canvas.width / 2, 1425);

            downloadCanvasAsPng(canvas, ref + '.png');
          }

          btn.addEventListener('click', handleDownload);

          const params = new URLSearchParams(window.location.search);
          if (params.get('download') === '1') {
            handleDownload();
          }
        })();
      </script>
    <?php endif; ?>
  </body>
</html>
