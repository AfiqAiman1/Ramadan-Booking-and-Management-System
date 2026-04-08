<?php
session_start();

$roles = [
    'AWAM' => 'Awam',
    'TDM'  => 'TDM',
    'TLDM' => 'TLDM',
    'TUDM' => 'TUDM',
];

$mainCategories = [
    'AWAM' => 'Awam',
    'ATM'  => 'ATM',
];

$atmBranches = [
    'TDM'  => 'TDM',
    'TLDM' => 'TLDM',
    'TUDM' => 'TUDM',
];

$pangkatByService = [
    'TDM' => [
        'Jeneral',
        'Leftenan Jeneral',
        'Mejar Jeneral',
        'Brigedier Jeneral',
        'Kolonel',
        'Leftenan Kolonel',
        'Mejar',
        'Kapten',
        'Leftenan',
        'Leftenan Muda',
        'LLP',
    ],
    'TLDM' => [
        'Laksamana Armada',
        'Laksamana',
        'Laksamana Madya',
        'Laksamana Muda',
        'Laksamana Pertama',
        'Kapten',
        'Komander',
        'Leftenan Komander',
        'Leftenan',
        'Leftenan Madya',
        'Leftenan Muda',
        'Pegawai Kadet  Kanan',
        'Kadet',
        'LLP',
    ],
    'TUDM' => [
        'Marsyal',
        'Jeneral',
        'Leftenan Jeneral',
        'Mejar Jeneral',
        'Brigedier Jeneral',
        'Kolonel',
        'Leftenan Kolonel',
        'Mejar',
        'Kapten',
        'Leftenan',
        'Leftenan Muda',
        'LLP',
    ],
];

$awamTitles = [
    'Tuan',
    'Puan',
    'Encik',
    'Cik',
    'Tan Sri',
    'Puan Sri',
    'Dato',
    'Datin',
    'Dato Sri',
    'Datin Sri',
    'Datuk',
    'Datin Paduka',
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = strtoupper(trim((string) ($_POST['role'] ?? '')));
    $atmBranch = strtoupper(trim((string) ($_POST['atm_branch'] ?? '')));
    $pangkat = trim((string) ($_POST['pangkat'] ?? ''));
    $awamTitle = trim((string) ($_POST['awam_title'] ?? ''));
    $nameOnly = trim((string) ($_POST['name_only'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $militaryNo = trim((string) ($_POST['military_no'] ?? ''));
    $confirmInfo = (string) ($_POST['confirm_info'] ?? '');

    $effectiveRole = $role;
    if ($role === 'ATM') {
        $effectiveRole = $atmBranch;
    }

    if (!isset($mainCategories[$role])) {
        $error = 'Sila pilih kategori yang sah.';
    } elseif ($role === 'ATM' && !isset($atmBranches[$atmBranch])) {
        $error = 'Sila pilih perkhidmatan (TDM/TLDM/TUDM).';
    } elseif ($effectiveRole !== 'AWAM' && $pangkat === '') {
        $error = 'Sila pilih pangkat anda.';
    } elseif ($effectiveRole !== 'AWAM'
        && isset($pangkatByService[$effectiveRole])
        && !in_array($pangkat, $pangkatByService[$effectiveRole], true)
    ) {
        $error = 'Sila pilih pangkat anda.';
    } elseif ($effectiveRole === 'AWAM' && $awamTitle === '') {
        $error = 'Sila pilih gelaran anda.';
    } elseif ($nameOnly === '') {
        $error = 'Sila masukkan nama anda.';
    } elseif ($phone === '') {
        $error = 'Sila masukkan nombor telefon anda.';
    } elseif ($confirmInfo !== '1') {
        $error = 'Sila tandakan pengesahan maklumat sebelum meneruskan.';
    } elseif ($effectiveRole !== 'AWAM' && $militaryNo === '') {
        $error = 'Sila masukkan nombor tentera anda.';
    } elseif ($role === 'ATM') {
        $militaryNoNorm = strtoupper(str_replace(' ', '', $militaryNo));
        $isValid = true;
        $isLlp = strtoupper(trim($pangkat)) === 'LLP';
        if ($isLlp) {
            if ($atmBranch === 'TDM') {
                $isValid = (bool) preg_match('/^[1-9]\d{5,6}$/', $militaryNoNorm);
            } elseif ($atmBranch === 'TLDM') {
                $isValid = (bool) preg_match('/^8\d{5}$/', $militaryNoNorm);
            } elseif ($atmBranch === 'TUDM') {
                $isValid = (bool) preg_match('/^7\d{5}$/', $militaryNoNorm);
            }
        } else {
            if ($atmBranch === 'TDM') {
                $isValid = (bool) preg_match('/^(30\d{5}|75\d{5})$/', $militaryNoNorm);
            } elseif ($atmBranch === 'TUDM') {
                $isValid = (bool) preg_match('/^37\d{4}$/', $militaryNoNorm);
            } elseif ($atmBranch === 'TLDM') {
                $isValid = (bool) preg_match('/^(N40\d{4}|NV870\d{4})$/', $militaryNoNorm);
            }
        }
        if (!$isValid) {
            $error = 'Format no. tentera ATM tidak sah. Sila ikut prefix yang betul.';
        }
    }

    if ($error === '') {
        $prefix = '';
        if ($effectiveRole === 'AWAM') {
            $prefix = $awamTitle;
        } else {
            $prefix = $pangkat;
        }
        $fullName = trim($prefix . ' ' . $nameOnly);

        $_SESSION['visitor_role_main'] = $role;
        $_SESSION['visitor_role'] = $effectiveRole;
        $_SESSION['visitor_role_label'] = $roles[$effectiveRole] ?? $effectiveRole;
        $_SESSION['visitor_pangkat'] = $effectiveRole === 'AWAM' ? '' : $pangkat;
        $_SESSION['visitor_title'] = $effectiveRole === 'AWAM' ? $awamTitle : '';
        $_SESSION['visitor_full_name'] = $fullName;
        $_SESSION['visitor_phone'] = $phone;
        $_SESSION['visitor_military_no'] = $effectiveRole === 'AWAM' ? '' : $militaryNo;
        $_SESSION['visitor_email'] = '';

        header('Location: index.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Selamat Datang - Bufet Ramadan</title>
    <link rel="icon" type="image/png" href="../assets/img/Logo%20ATM.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <style>
      :root {
        --ramadan-green: #08372b;
        --ramadan-gold: #d8b45c;
        --ramadan-cream: #fff9ed;
        --ramadan-deep: #041f18;
      }
      body {
        font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
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
        background: linear-gradient(140deg, var(--ramadan-green), #0b4b3a);
        border: none;
        color: #fef6dd;
      }
      .btn-ramadan:hover { filter: brightness(1.03); color: #fef6dd; }
      .hint {
        color: rgba(254, 246, 221, 0.82);
        font-size: 0.9rem;
      }
      .hint-dark {
        color: rgba(8, 55, 43, 0.75);
      }
      .hint-gold {
        color: rgba(216, 180, 92, 0.95);
      }
      .terms-link {
        color: #0d6efd;
        text-decoration: underline;
        text-underline-offset: 3px;
      }
      .terms-link:hover {
        color: #0b5ed7;
      }
      .terms-full {
        width: 80vw;
        margin-left: calc(-40vw + 50%);
        margin-right: calc(-32vw + 40%);
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
      .promo-sticky {
        position: sticky;
        top: 0;
        z-index: 1020;
        margin-bottom: 1rem;
      }
      .promo-sticky .promo-inner {
        background: linear-gradient(180deg, rgba(216, 180, 92, 0.94), rgba(216, 180, 92, 0.84));
        border: 1px solid rgba(255, 246, 221, 0.55);
        border-radius: 1.25rem;
        box-shadow: 0 14px 30px rgba(4, 31, 24, 0.22);
        color: #041f18;
        overflow: hidden;
        position: relative;
      }
      .promo-sticky .promo-inner::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
          radial-gradient(700px 260px at 15% 0%, rgba(255, 246, 221, 0.38), transparent 60%),
          radial-gradient(650px 280px at 95% 35%, rgba(8, 55, 43, 0.18), transparent 62%);
        pointer-events: none;
      }
      .promo-sticky .promo-title {
        position: relative;
        font-weight: 800;
        letter-spacing: 0.2px;
      }
      .promo-sticky .promo-subtitle {
        position: relative;
        opacity: 0.92;
      }
      .promo-sticky .promo-btn {
        position: relative;
        background: linear-gradient(140deg, var(--ramadan-green), #0b4b3a);
        color: #fef6dd;
        border: 0;
        border-radius: 999px;
        padding: 0.5rem 0.9rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        white-space: nowrap;
      }
      .promo-sticky .promo-btn:hover {
        filter: brightness(1.03);
        color: #fef6dd;
      }
      .checkbox-box {
        border: 1px solid rgba(68, 68, 68, 0.4);
        background: rgba(8, 55, 43, 0.02);
        padding: 2rem;
        border-radius: 0.75rem;
      }
    </style>
  </head>
  <body>
    <main class="container pt-0">
      <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-7">
          <div class="mb-4 text-center">
          <div class="brand justify-content-center mb-0 flex-column align-items-center text-center">
            <div>
              <img src="../assets/img/logo%20png.png" alt="Logo" style="max-height: 100px; width: auto; max-width: 100%; object-fit: contain;">
            </div>
            <div class="text-center">
              <div class="fw-bold fs-3">🌙✨ BUFET RAMADAN 2026 ✨🌙</div>
              <div class="fw-bold fs-3">NOSTALGIA KAMPUNG</div>
            </div>
          </div>
            <div class="hint">Sila pilih kategori anda sebelum meneruskan tempahan.</div>
          </div>

          <div class="card card-ramadan">
            <div class="card-body p-4 p-md-5">
              <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
              <?php endif; ?>

              <form method="POST" class="row g-3" id="roleForm">
                <div class="col-12">
                  <label class="form-label fw-semibold">Kategori</label>
                  <select class="form-select" name="role" id="roleSelect" required>
                    <option value="" selected disabled>Pilih</option>
                    <option value="AWAM">Awam</option>
                    <option value="ATM">ATM</option>
                  </select>
                </div>

                <div class="col-12" id="atmBranchWrap" style="display:none;">
                  <label class="form-label fw-semibold">Perkhidmatan</label>
                  <select class="form-select" name="atm_branch" id="atmBranchSelect">
                    <option value="" selected disabled>Pilih perkhidmatan</option>
                    <option value="TDM">TDM</option>
                    <option value="TLDM">TLDM</option>
                    <option value="TUDM">TUDM</option>
                  </select>
                </div>

                <div class="col-12" id="awamTitleWrap" style="display:none;">
                  <label class="form-label fw-semibold">Gelaran</label>
                  <select class="form-select" name="awam_title" id="awamTitleSelect">
                    <option value="" selected disabled>Pilih gelaran</option>
                    <?php foreach ($awamTitles as $t): ?>
                      <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-12" id="pangkatWrap" style="display:none;">
                  <label class="form-label fw-semibold">Pangkat</label>
                  <select class="form-select" name="pangkat" id="pangkatSelect">
                    <option value="" selected disabled>Pilih pangkat</option>
                  </select>
                </div>

                <div class="col-12" id="detailsWrap" style="display:none;">
                  <div class="row g-3">
                    <div class="col-12">
                      <label class="form-label fw-semibold">Nama</label>
                      <input type="text" class="form-control" name="name_only" id="nameOnly" placeholder="Masukkan nama anda">
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label fw-semibold">No. Telefon</label>
                      <input type="tel" class="form-control" name="phone" id="phoneInput" placeholder="Contoh: 0123456789">
                    </div>
                    <div class="col-12 col-md-6" id="militaryNoWrap" style="display:none;">
                      <label class="form-label fw-semibold">No. Tentera</label>
                      <input type="text" class="form-control" name="military_no" id="militaryNo" placeholder="Masukkan no. tentera">
                      <div id="militaryNoError" class="invalid-feedback" style="display:none;">No tentera tidak sah.</div>
                    </div>
                  </div>

                  <div class="form-check mt-3 checkbox-box">
                    <input class="form-check-input" type="checkbox" value="1" id="confirmInfo" name="confirm_info" required>
                    <label class="form-check-label" for="confirmInfo">
                      Saya mengesahkan bahawa maklumat yang diberikan adalah benar. Sebarang maklumat palsu boleh menyebabkan tempahan dibatalkan dan saya bersetuju dengan <a href="#termsSection" class="terms-link" id="termsLink">Terma &amp; Syarat</a> yang dinyatakan
                    </label>
                  </div>
                </div>

                <div class="col-12 d-grid mt-2">
                  <button type="submit" class="btn btn-ramadan btn-lg" id="continueBtn" disabled>
                    Teruskan <i class="bi bi-arrow-right ms-2"></i>
                  </button>
                </div>

                <div class="col-12 d-grid mt-2">
                  <button type="button" class="btn btn-outline-secondary btn-lg" data-bs-toggle="modal" data-bs-target="#forgotBookingRefModal">
                    Terlupa booking reference?
                  </button>
                </div>
              </form>
            </div>
          </div>

          <div class="text-center mt-3 hint hint-gold">
            Pilihan anda digunakan untuk tujuan semakan/pengesahan semasa kemasukan.
          </div>

          <div class="ramadan-banner mt-4 mb-5 p-4 text-center">
            <div class="banner-title fs-5 mb-1">
              <i class="bi bi-moon-stars-fill me-2" style="color: var(--ramadan-gold);"></i>
              Selamat Menyambut Bulan Ramadan
              <i class="bi bi-moon-stars-fill ms-2" style="color: var(--ramadan-gold);"></i>
            </div>
            <div class="banner-subtitle small">NOSTALGIA KAMPUNG • Bufet Ramadan</div>
          </div>
          <div class="my-6 mt-6"></div>

          <div class="terms-full mt-4">
            <div id="termsSection" class="p-4 rounded-4" style="background: var(--ramadan-cream); border: 5px solid rgba(8, 55, 43, 0.12);">
              <div class="fw-bold mb-3" style="color: var(--ramadan-green);">TERMA &amp; SYARAT</div>

              <div class="fw-semibold mb-1">1. Pelanggaran Terma &amp; Syarat</div>
              <div class="mb-2">Sebarang pemberian maklumat palsu, ketidakpatuhan terhadap kategori tempahan (termasuk penyalahgunaan kategori ATM/Warga Emas), atau pelanggaran terhadap terma dan syarat yang ditetapkan adalah dianggap sebagai pelanggaran terma dan syarat.<br>Pihak penganjur berhak untuk:</div>
              <div class="mb-3">-Mengenakan caj tambahan di kaunter bagi melaraskan harga sebenar<br>-Menolak kemasukan tanpa sebarang bayaran balik<br>-Membatalkan tempahan yang didapati tidak mematuhi syarat</div>

              <div class="fw-semibold mb-1">2. Polisi Pembatalan</div>
              <div class="mb-3">Semua bayaran yang dibuat adalah muktamad dan tidak boleh dipulangkan (non-refundable).
                                Sebarang pembatalan atau bayaran balik tidak akan dipertimbangkan kecuali sekiranya acara dibatalkan sepenuhnya oleh pihak syarikat.</div>

              <div class="fw-semibold mb-1">3. Prosedur Kemasukan &amp; Pengesahan Tempahan</div>
              <div class="mb-2">Pembeli dikehendaki menunjukkan Nombor Rujukan Tempahan (Booking Reference) yang sah semasa pendaftaran di kaunter pada hari tempahan.<br>Kemasukan hanya dibenarkan setelah:</div>
              <div class="mb-3">-Nombor rujukan disahkan dalam sistem, dan<br>-Status bayaran disahkan sebagai BERJAYA / PAID</div>

              <div class="fw-semibold mb-1">4. Perubahan Tempahan</div>
              <div class="mb-2">Sebarang permohonan untuk pindaan tarikh, jumlah tiket atau maklumat tempahan hendaklah dimaklumkan kepada pihak pengurusan sekurang-kurangnya satu (1) hari sebelum tarikh tempahan, tertakluk kepada kelulusan dan ketersediaan.</div>

              <div class="fw-semibold mb-1">5. Hak Syarikat</div>
              <div class="mb-2">Pihak syarikat berhak untuk:</div>
              <div class="mb-3">-Mengubah, meminda atau mengemas kini terma dan syarat mengikut keperluan operasi dan polisi syarikat.<br>-Menolak kemasukan sekiranya berlaku pelanggaran terma dan syarat.<br>-Membatalkan tempahan yang didapati mencurigakan atau tidak mematuhi polisi yang ditetapkan.</div>

              <div class="fw-semibold mb-1">6. Perubahan Pengurusan</div>
              <div class="mb-2">Pihak syarikat berhak untuk membuat perubahan dari segi pengurusan acara, kaedah operasi atau prosedur kemasukan tanpa notis awal sekiranya perlu demi kelancaran acara.</div>
            
              <div class="fw-semibold mb-1">7. Polisi Harga Early Bird</div>
              <div class="mb-2">Harga Early Bird adalah harga promosi khas yang hanya ditawarkan pada hari pertama jualan sahaja, tertakluk kepada terma dan kuota yang ditetapkan.<br>Harga Early Bird adalah:</div>
              <div class="mb-3">-Harga tetap: RM65 (DEWASA SAHAJA)<br>-Sah untuk 21 Februari 2026 sahaja</div>
            
            </div>
          </div>
        </div>
      </div>
    </main>

    <div class="position-fixed start-0 top-0 p-3 d-none d-md-block" style="z-index: 1000; max-width: 220px;">
      <img src="../assets/img/flyer.png" alt="Flyer" class="img-fluid rounded shadow" style="max-height: 260px; object-fit: cover; cursor: pointer;" data-bs-toggle="modal" data-bs-target="#flyerModal">
    </div>

    <div class="modal fade" id="flyerModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 550px;">
        <div class="modal-content" style="border-radius: 1.25rem; border: 1px solid rgba(216, 180, 92, 0.35); overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd; border-bottom: 1px solid rgba(216, 180, 92, 0.25);">
            <h5 class="modal-title" style="font-weight: 700;">
              <i class="bi bi-image me-2" style="color: #d8b45c;"></i>
              Flyer
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-0" style="background: #fff9ed;">
            <img src="../assets/img/flyer.png" alt="Flyer" class="img-fluid">
          </div>
          <div class="modal-footer" style="background: #fff9ed; border-top: 1px solid rgba(8, 55, 43, 0.12);">
            <button type="button" class="btn btn-ramadan" data-bs-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="forgotBookingRefModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1.25rem; border: 1px solid rgba(216, 180, 92, 0.35); overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd; border-bottom: 1px solid rgba(216, 180, 92, 0.25);">
            <h5 class="modal-title" style="font-weight: 700;">
              <i class="bi bi-whatsapp me-2" style="color: #d8b45c;"></i>
              Hubungi Sales
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <div class="mb-2" style="color: rgba(8, 55, 43, 0.85);">Jika anda terlupa Booking Reference, sila hubungi:</div>

            <div class="list-group">
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="https://wa.me/60176983867" target="_blank" rel="noopener">
                <span><strong>Mas</strong> - 017 6983867</span>
                <i class="bi bi-whatsapp"></i>
              </a>
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="https://wa.me/60122967302" target="_blank" rel="noopener">
                <span><strong>Wan</strong> - 012 2967302</span>
                <i class="bi bi-whatsapp"></i>
              </a>
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="https://wa.me/60177819917" target="_blank" rel="noopener">
                <span><strong>Gee</strong> - 017 7819917</span>
                <i class="bi bi-whatsapp"></i>
              </a>
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="https://wa.me/60132995306" target="_blank" rel="noopener">
                <span><strong>Feqa</strong> - 013 2995306</span>
                <i class="bi bi-whatsapp"></i>
              </a>
            </div>
          </div>
          <div class="modal-footer" style="background: #fff9ed; border-top: 1px solid rgba(8, 55, 43, 0.12);">
            <button type="button" class="btn btn-ramadan" data-bs-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="categoryNoticeModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1.25rem; border: 1px solid rgba(216, 180, 92, 0.35); overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd; border-bottom: 1px solid rgba(216, 180, 92, 0.25);">
            <h5 class="modal-title" style="font-weight: 700;">
              <i class="bi bi-shield-exclamation me-2" style="color: #d8b45c;"></i>
              NOTIS AMARAN - MAKLUMAT TEMPAHAN
            </h5>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <p class="mb-3">Pihak syarikat ingin memaklumkan bahawa setiap pembeli bertanggungjawab untuk mengisi maklumat tempahan dengan betul dan jujur, termasuk pemilihan kategori tiket (Dewasa, Tentera, Kanak-kanak, Warga Emas dan lain-lain).</p>

            <p class="mb-3">Sebarang salah laku, pemalsuan maklumat atau pemilihan kategori yang tidak tepat adalah dianggap sebagai pelanggaran syarat tempahan.</p>

            <div class="mb-3">Sekiranya didapati berlaku penipuan, pihak syarikat berhak untuk:</div>
            <div>- Membatalkan tiket tanpa sebarang bayaran balik.</div>
            <div>- Mengenakan bayaran tambahan di kaunter kepada pembeli.</div>
            <div class="mb-3">- Mengambil tindakan lanjut sekiranya perlu..</div>

            <p class="mb-3">Dengan meneruskan tempahan, pembeli dianggap telah bersetuju dengan terma dan syarat ini.</p>

            <p class="mb-0">Terima kasih atas kerjasama anda.</p>
          </div>
          <div class="modal-footer" style="background: #fff9ed; border-top: 1px solid rgba(8, 55, 43, 0.12);">
            <button type="button" class="btn btn-ramadan" data-bs-dismiss="modal">Saya Faham, Teruskan</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>

    <script>
      const pangkatByService = <?= json_encode($pangkatByService, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const roleSelect = document.getElementById('roleSelect');
      const awamTitleWrap = document.getElementById('awamTitleWrap');
      const awamTitleSelect = document.getElementById('awamTitleSelect');
      const atmBranchWrap = document.getElementById('atmBranchWrap');
      const atmBranchSelect = document.getElementById('atmBranchSelect');
      const pangkatWrap = document.getElementById('pangkatWrap');
      const pangkatSelect = document.getElementById('pangkatSelect');
      const detailsWrap = document.getElementById('detailsWrap');
      const nameOnly = document.getElementById('nameOnly');
      const phoneInput = document.getElementById('phoneInput');
      const militaryNoWrap = document.getElementById('militaryNoWrap');
      const militaryNo = document.getElementById('militaryNo');
      const militaryNoError = document.getElementById('militaryNoError');
      const confirmInfo = document.getElementById('confirmInfo');
      const continueBtn = document.getElementById('continueBtn');

      function setPangkatOptions(service) {
        if (!pangkatSelect) return;
        const current = pangkatSelect.value;
        const opts = (pangkatByService && service && pangkatByService[service]) ? pangkatByService[service] : [];

        pangkatSelect.innerHTML = '<option value="" selected disabled>Pilih pangkat</option>';
        opts.forEach(function (label) {
          const opt = document.createElement('option');
          opt.value = label;
          opt.textContent = label;
          pangkatSelect.appendChild(opt);
        });

        if (current && opts.includes(current)) {
          pangkatSelect.value = current;
        }
      }

      function syncPangkatVisibility() {
        const role = (roleSelect.value || '').toUpperCase();
        const isAwam = role === 'AWAM';
        const isAtm = role === 'ATM';
        const branch = (atmBranchSelect ? (atmBranchSelect.value || '').toUpperCase() : '');
        const hasValidBranch = isAtm ? ['TDM', 'TLDM', 'TUDM'].includes(branch) : true;
        const effectiveRole = isAtm ? branch : role;

        if (isAtm && hasValidBranch) {
          setPangkatOptions(effectiveRole);
        } else {
          setPangkatOptions('');
        }

        atmBranchWrap.style.display = isAtm ? '' : 'none';
        if (atmBranchSelect) {
          atmBranchSelect.required = isAtm;
          if (!isAtm) atmBranchSelect.value = '';
        }

        const needsPangkat = !!effectiveRole && effectiveRole !== 'AWAM' && hasValidBranch;
        const needsTitle = effectiveRole === 'AWAM';
        const showDetails = !!role && (!isAtm || hasValidBranch);

        pangkatWrap.style.display = needsPangkat ? '' : 'none';
        pangkatSelect.required = needsPangkat;
        if (!needsPangkat) {
          pangkatSelect.value = '';
        }

        awamTitleWrap.style.display = needsTitle ? '' : 'none';
        awamTitleSelect.required = needsTitle;
        if (!needsTitle) {
          awamTitleSelect.value = '';
        }

        detailsWrap.style.display = showDetails ? '' : 'none';
        nameOnly.required = showDetails;
        phoneInput.required = showDetails;

        militaryNoWrap.style.display = needsPangkat ? '' : 'none';
        militaryNo.required = needsPangkat;
        if (!needsPangkat) {
          militaryNo.value = '';
        }

        if (!showDetails) {
          if (confirmInfo) confirmInfo.checked = false;
          if (continueBtn) continueBtn.disabled = true;
        }
      }

      roleSelect.addEventListener('change', syncPangkatVisibility);
      if (atmBranchSelect) {
        atmBranchSelect.addEventListener('change', syncPangkatVisibility);
      }
      syncPangkatVisibility();

      function syncContinueEnabled() {
        const role = (roleSelect.value || '').toUpperCase();
        const isAtm = role === 'ATM';
        const branch = (atmBranchSelect ? (atmBranchSelect.value || '').toUpperCase() : '');
        const hasValidBranch = isAtm ? ['TDM', 'TLDM', 'TUDM'].includes(branch) : true;
        const showDetails = !!role && (!isAtm || hasValidBranch);
        const ok = showDetails && confirmInfo && confirmInfo.checked;
        if (continueBtn) {
          continueBtn.disabled = !ok;
        }
      }

      function validateMilitaryNoPrefix() {
        const role = (roleSelect.value || '').toUpperCase();
        const isAtm = role === 'ATM';
        const branch = (atmBranchSelect ? (atmBranchSelect.value || '').toUpperCase() : '');
        const hasValidBranch = isAtm ? ['TDM', 'TLDM', 'TUDM'].includes(branch) : true;
        const effectiveRole = isAtm ? branch : role;
        const needsMilitaryNo = !!effectiveRole && effectiveRole !== 'AWAM' && hasValidBranch;
        const pangkat = (pangkatSelect ? (pangkatSelect.value || '') : '').toUpperCase();
        const isLlp = pangkat === 'LLP';

        if (!militaryNo) return true;
        if (!needsMilitaryNo) {
          militaryNo.classList.remove('is-invalid');
          if (militaryNoError) militaryNoError.style.display = 'none';
          return true;
        }

        const val = String(militaryNo.value || '').toUpperCase().replace(/\s+/g, '');
        if (val === '') {
          militaryNo.classList.remove('is-invalid');
          if (militaryNoError) militaryNoError.style.display = 'none';
          return true;
        }

        if (!isAtm) {
          militaryNo.classList.remove('is-invalid');
          if (militaryNoError) militaryNoError.style.display = 'none';
          return true;
        }

        let isValid = true;
        if (isLlp) {
          if (branch === 'TDM') {
            isValid = /^1\d{6}$/.test(val);
          } else if (branch === 'TLDM') {
            isValid = /^8\d{5}$/.test(val);
          } else if (branch === 'TUDM') {
            isValid = /^7\d{5}$/.test(val);
          }
        } else {
          if (branch === 'TDM') {
            isValid = /^(30\d{5}|75\d{5})$/.test(val);
          } else if (branch === 'TUDM') {
            isValid = /^37\d{4}$/.test(val);
          } else if (branch === 'TLDM') {
            isValid = /^(N40\d{4}|NV870\d{4})$/.test(val);
          }
        }

        if (!isValid) {
          militaryNo.classList.add('is-invalid');
          if (militaryNoError) militaryNoError.style.display = '';
          return false;
        }

        militaryNo.classList.remove('is-invalid');
        if (militaryNoError) militaryNoError.style.display = 'none';
        return true;
      }

      if (confirmInfo) {
        confirmInfo.addEventListener('change', syncContinueEnabled);
      }
      roleSelect.addEventListener('change', syncContinueEnabled);
      if (atmBranchSelect) {
        atmBranchSelect.addEventListener('change', syncContinueEnabled);
      }
      syncContinueEnabled();

      (function () {
        const form = document.getElementById('roleForm');
        if (!form) return;
        form.addEventListener('submit', function (e) {
          if (!validateMilitaryNoPrefix()) {
            e.preventDefault();
            if (militaryNo) militaryNo.focus();
          }
        });
      })();

      (function () {
        const link = document.getElementById('termsLink');
        const target = document.getElementById('termsSection');
        if (!link || !target) return;
        link.addEventListener('click', function (e) {
          e.preventDefault();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      })();

      (function () {
        const modalEl = document.getElementById('categoryNoticeModal');
        if (!modalEl || typeof bootstrap === 'undefined') return;
        const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
        modal.show();
      })();
    </script>
  </body>
</html>
