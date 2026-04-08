<?php
require_once __DIR__ . '/../config/config.php';

$mysqli = null;
try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

if (isset($_GET['ajax']) && (string) ($_GET['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $slotDate = trim((string) ($_GET['slot_date'] ?? ''));
    $dt = DateTime::createFromFormat('Y-m-d', $slotDate);
    $slotDate = $dt ? $dt->format('Y-m-d') : '';
    if ($slotDate === '') {
        $slotDate = date('Y-m-d');
    }
    
    $now = new DateTime('now');
    $readyAt = DateTime::createFromFormat('Y-m-d H:i:s', $slotDate . ' 18:30:00');
    $isReadyTime = $readyAt ? ($now >= $readyAt) : true;
    $rowStatus = $isReadyTime ? 'READY' : 'NOT READY';
    $rowStatusClass = $isReadyTime ? 'badge-ready' : 'badge-not-ready';

    $rows = [];
    $sql = "
        SELECT
            booking_reference,
            full_name,
            remark,
            slot_date,
            quantity_dewasa,
            free_quantity_dewasa,
            quantity_atm,
            free_quantity_atm,
            quantity_kanak,
            free_quantity_kanak,
            quantity_kanak_foc,
            free_quantity_kanak_foc,
            quantity_warga_emas,
            free_quantity_warga_emas,
            staff_blanket_qty,
            living_in_qty,
            ajk_qty,
            free_voucher_qty,
            comp_qty,
            table_no
        FROM bookings
        WHERE slot_date = ?
          AND table_no IS NOT NULL
          AND table_no <> ''
        ORDER BY
          CASE WHEN table_no IS NULL OR table_no = '' THEN 1 ELSE 0 END ASC,
          table_no ASC,
          booking_reference ASC
    ";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $slotDate);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $awam = (int) ($row['quantity_dewasa'] ?? 0) + (int) ($row['free_quantity_dewasa'] ?? 0);
                $atm = (int) ($row['quantity_atm'] ?? 0) + (int) ($row['free_quantity_atm'] ?? 0);
                $kanak = (int) ($row['quantity_kanak'] ?? 0) + (int) ($row['free_quantity_kanak'] ?? 0);
                $infant = (int) ($row['quantity_kanak_foc'] ?? 0) + (int) ($row['free_quantity_kanak_foc'] ?? 0);
                $warga = (int) ($row['quantity_warga_emas'] ?? 0) + (int) ($row['free_quantity_warga_emas'] ?? 0);
                $staff = (int) ($row['staff_blanket_qty'] ?? 0);
                $living = (int) ($row['living_in_qty'] ?? 0);
                $ajk = (int) ($row['ajk_qty'] ?? 0);
                $freeVoucher = (int) ($row['free_voucher_qty'] ?? 0);
                $comp = (int) ($row['comp_qty'] ?? 0);

                $row['total_pax'] = $awam + $atm + $kanak + $infant + $warga + $staff + $living + $ajk + $freeVoucher + $comp;
                $row['table_no'] = trim((string) ($row['table_no'] ?? ''));

                $rows[] = [
                    'booking_reference' => (string) ($row['booking_reference'] ?? ''),
                    'full_name' => (string) ($row['full_name'] ?? ''),
                    'table_no' => (string) ($row['table_no'] ?? ''),
                    'total_pax' => (int) ($row['total_pax'] ?? 0),
                    'status' => $rowStatus,
                    'status_class' => $rowStatusClass,
                ];
            }
            $res->free();
        }
        $stmt->close();
    }

    echo json_encode([
        'ok' => true,
        'slot_date' => $slotDate,
        'count' => count($rows),
        'rows' => $rows,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$slotDate = trim((string) ($_GET['slot_date'] ?? ''));
$dt = DateTime::createFromFormat('Y-m-d', $slotDate);
$slotDate = $dt ? $dt->format('Y-m-d') : '';
if ($slotDate === '') {
    $slotDate = date('Y-m-d');
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live Display</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
      :root {
        --bg: #071b15;
        --bg2: #0a241d;
        --fg: #f6f2e8;
        --muted: rgba(246,242,232,0.70);
        --line: rgba(246,242,232,0.18);
        --accent: #d8b45c;
      }
      html, body { height: 100%; }
      body {
        margin: 0;
        font-family: 'Cairo', system-ui, sans-serif;
        background: radial-gradient(1200px 600px at 20% 0%, rgba(216,180,92,0.20), transparent 60%),
                    linear-gradient(180deg, var(--bg), var(--bg2));
        color: var(--fg);
      }
      .wrap { padding: 28px 34px; height: 100%; display: flex; flex-direction: column; gap: 18px; }
      .topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
      .title { font-size: 34px; font-weight: 700; letter-spacing: 0.3px; }
      .subtitle { color: var(--muted); font-size: 16px; }
      .clock { text-align: right; }
      .clock .time { font-size: 34px; font-weight: 700; color: var(--accent); }
      .clock .date { font-size: 16px; color: var(--muted); }

      .panel { flex: 1; border: 1px solid var(--line); border-radius: 18px; overflow: hidden; background: rgba(0,0,0,0.18); }
      table { width: 100%; border-collapse: collapse; }
      thead th {
        padding: 14px 16px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 14px;
        background: rgba(0,0,0,0.35);
        border-bottom: 1px solid var(--line);
        color: rgba(246,242,232,0.85);
      }
      tbody td { padding: 14px 16px; font-size: 22px; border-bottom: 1px solid rgba(246,242,232,0.08); }
      tbody tr:last-child td { border-bottom: 0; }

      .col-ref { width: 18%; }
      .col-name { width: 44%; }
      .col-table { width: 18%; }
      .col-pax { width: 10%; text-align: right; }
      .col-status { width: 10%; }

      .badge-ready {
        display: inline-block;
        padding: 6px 12px;
        border: 1px solid rgba(62, 247, 124, 0.55);
        border-radius: 999px;
        color: var(--accent);
        background: rgba(216,180,92,0.10);
        font-weight: 700;
        letter-spacing: 0.08em;
        font-size: 14px;
      }

      .badge-not-ready {
        display: inline-block;
        padding: 6px 12px;
        border: 1px solid rgba(246,242,232,0.35);
        border-radius: 999px;
        color: rgba(246,242,232,0.65);
        background: rgba(0,0,0,0.18);
        font-weight: 700;
        letter-spacing: 0.08em;
        font-size: 14px;
      }
      
      .footer { display: flex; justify-content: space-between; color: var(--muted); font-size: 14px; }
      .fade { opacity: 0; transition: opacity 260ms ease; }
      .show { opacity: 1; transition: opacity 260ms ease; }
    </style>
  </head>
  <body>
    <div class="wrap">
      <div class="topbar">
        <div>
          <div class="title">Live Display</div>
          <div class="subtitle">Date: <span id="slotDateLabel"></span></div>
        </div>
        <div class="clock">
          <div class="time" id="clockTime"></div>
          <div class="date" id="clockDate"></div>
        </div>
      </div>

      <div class="panel">
        <table>
          <thead>
            <tr>
              <th class="col-ref">Booking Ref</th>
              <th class="col-name">Name</th>
              <th class="col-table">Table No</th>
              <th class="col-pax">Pax</th>
              <th class="col-status">Status</th>
            </tr>
          </thead>
          <tbody id="rowsBody"></tbody>
        </table>
      </div>

      <div class="footer">
        <div id="footerLeft"></div>
        <div id="footerRight"></div>
      </div>
    </div>

    <script>
      const slotDate = '<?= htmlspecialchars($slotDate) ?>';
      const pageSize = 7;
      const rotateMs = 8000;
      const refreshMs = 60000;

      let allRows = [];
      let pageIndex = 0;

      function setClock() {
        const now = new Date();
        const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const date = now.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: '2-digit' });
        document.getElementById('clockTime').textContent = time;
        document.getElementById('clockDate').textContent = date;
      }

      function renderPage() {
        const body = document.getElementById('rowsBody');
        const total = allRows.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));

        if (pageIndex >= totalPages) pageIndex = 0;

        const start = pageIndex * pageSize;
        const end = Math.min(start + pageSize, total);
        const view = allRows.slice(start, end);

        body.classList.remove('show');
        body.classList.add('fade');

        setTimeout(() => {
          body.innerHTML = '';

          if (view.length === 0) {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="5" style="padding: 24px 16px; color: rgba(246,242,232,0.70); font-size: 20px;">No bookings for this date.</td>';
            body.appendChild(tr);
          } else {
            for (const r of view) {
              const tr = document.createElement('tr');
              const tableNo = (r.table_no || '').trim();
              const statusText = (r.status || 'READY');
              const statusClass = (r.status_class || 'badge-ready');
              tr.innerHTML =
                '<td class="col-ref">' + escapeHtml(r.booking_reference || '') + '</td>' +
                '<td class="col-name">' + escapeHtml(r.full_name || '') + '</td>' +
                '<td class="col-table">' + (tableNo ? escapeHtml(tableNo) : '-') + '</td>' +
                '<td class="col-pax" style="text-align:right;">' + String(r.total_pax || 0) + '</td>' +
                '<td class="col-status"><span class="' + escapeHtml(statusClass) + '">' + escapeHtml(statusText) + '</span></td>';
              body.appendChild(tr);
            }
          }

          body.classList.remove('fade');
          body.classList.add('show');

          document.getElementById('footerLeft').textContent = 'Showing ' + (view.length ? (start + 1) + '-' + end : '0') + ' of ' + total;
          document.getElementById('footerRight').textContent = 'Page ' + (pageIndex + 1) + '/' + totalPages;
        }, 260);
      }

      function escapeHtml(str) {
        return String(str)
          .replaceAll('&', '&amp;')
          .replaceAll('<', '&lt;')
          .replaceAll('>', '&gt;')
          .replaceAll('"', '&quot;')
          .replaceAll("'", '&#039;');
      }

      async function loadData() {
        try {
          const url = new URL(window.location.href);
          url.searchParams.set('ajax', '1');
          url.searchParams.set('slot_date', slotDate);
          const res = await fetch(url.toString(), { cache: 'no-store' });
          const data = await res.json();
          if (data && data.ok && Array.isArray(data.rows)) {
            allRows = data.rows;
            const totalPages = Math.max(1, Math.ceil(allRows.length / pageSize));
            if (pageIndex >= totalPages) {
            pageIndex = 0;
            }
            document.getElementById('slotDateLabel').textContent = data.slot_date || slotDate;
            renderPage();
          }
        } catch (e) {
          // ignore
        }
      }

      function startRotation() {
        function rotatePage() {
            const totalPages = Math.max(1, Math.ceil(allRows.length / pageSize));
            if (totalPages > 1) {
                pageIndex = pageIndex + 1;
      
      // Reset to 0 only after reaching the last page
                if (pageIndex >= totalPages) {
                  pageIndex = 0;
                }
      
                renderPage();
            }
        }
  
        setInterval(rotatePage, rotateMs);
    }

      function startRefresh() {
        setInterval(() => {
          loadData();
        }, refreshMs);
      }

      setClock();
      setInterval(setClock, 1000);

      loadData();
      startRotation();
      startRefresh();
    </script>
  </body>
</html>
