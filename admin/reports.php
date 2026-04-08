<?php
// reports.php - analytics overview

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin_auth.php';

require_admin_roles(['admin', 'staff', 'assistant', 'finance', 'ENT_ADMIN']);
$csrfToken = admin_csrf_token();

$mysqli = null;

$sidebarRoleLabel = match (strtolower(admin_get_role())) {
    'banquet' => 'Banquet',
    'finance' => 'Finance',
    'assistant' => 'Assistant',
    'staff' => 'Sales',
    'ent_admin' => 'ENT',
    'entry_duty' => 'Entry Staff',
    default => 'Admin',
};

try {
    $mysqli = db_connect();
    ensure_bookings_schema($mysqli);
} catch (Throwable $e) {
    die('<h2>Database connection failed.</h2>');
}

$financeDate = trim((string) ($_GET['finance_date'] ?? ''));
$financeSort = trim((string) ($_GET['finance_sort'] ?? 'event_date'));
$financeDir = strtolower(trim((string) ($_GET['finance_dir'] ?? 'asc')));

if ($financeDate !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $financeDate);
    $financeDate = $dt ? $dt->format('Y-m-d') : '';
}

$allowedSort = [
    'event_date' => 'slot_date',
    'total_revenue' => 'total_revenue',
    'total_bookings' => 'total_bookings',
];
$sortSql = $allowedSort[$financeSort] ?? 'slot_date';
$dirSql = $financeDir === 'desc' ? 'DESC' : 'ASC';

$financeWhere = [];
$financeParams = [];
$financeTypes = '';

$financeWhere[] = "payment_status = 'PAID'";

if ($financeDate !== '') {
    $financeWhere[] = 'slot_date = ?';
    $financeParams[] = $financeDate;
    $financeTypes .= 's';
}

$financeSql = "
    SELECT
        slot_date,
        COUNT(*) AS total_bookings,
        SUM(
            quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
            + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
            + staff_blanket_qty + living_in_qty + ajk_qty + free_voucher_qty + comp_qty
        ) AS total_tickets,
        SUM(CASE WHEN booking_reference NOT LIKE 'ENT%' THEN total_price ELSE 0 END) AS total_revenue
    FROM bookings
";

if ($financeWhere) {
    $financeSql .= " WHERE " . implode(' AND ', $financeWhere);
}

$financeSql .= "\n    GROUP BY slot_date\n    ORDER BY {$sortSql} {$dirSql}\n";

$financeRows = [];
$stmt = $mysqli->prepare($financeSql);
if ($stmt) {
    if ($financeTypes !== '') {
        $stmt->bind_param($financeTypes, ...$financeParams);
    }
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $financeRows[] = $row;
        }
        $res->free();
    }
    $stmt->close();
}


$summaryDate = trim((string) ($_GET['summary_date'] ?? ''));
$showDetails = ((string) ($_GET['show_details'] ?? '')) === '1';
if ($summaryDate !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $summaryDate);
    $summaryDate = $dt ? $dt->format('Y-m-d') : '';
}

$dailyQuery = "
    SELECT
        slot_date,
        COUNT(*) AS total_bookings,
        SUM(
            quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
            + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
            + staff_blanket_qty + living_in_qty + ajk_qty + free_voucher_qty + comp_qty
        ) AS total_people,
        SUM(CASE WHEN payment_status = 'PAID' THEN total_price ELSE 0 END) AS total_amount,
        SUM(CASE WHEN payment_status = 'PAID' THEN (
            quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
            + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
            + staff_blanket_qty + living_in_qty + ajk_qty + comp_qty
        ) ELSE 0 END) AS paid_people,
        SUM(CASE WHEN payment_status = 'PAID' AND checkin_status = 'Checked' THEN (
            quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
            + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
            + staff_blanket_qty + living_in_qty + ajk_qty + comp_qty
        ) ELSE 0 END) AS checked_in_people
    FROM bookings
";
if ($summaryDate !== '') {
    $dailyQuery .= " WHERE slot_date = ?";
}
$dailyQuery .= "\n    GROUP BY slot_date\n    ORDER BY slot_date ASC\n";
$dailyStats = [];
if ($summaryDate !== '') {
    $stmt = $mysqli->prepare($dailyQuery);
    if ($stmt) {
        $stmt->bind_param('s', $summaryDate);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $dailyStats[] = $row;
            }
            $res->free();
        }
        $stmt->close();
    }
} else {
    if ($result = $mysqli->query($dailyQuery)) {
        while ($row = $result->fetch_assoc()) {
            $dailyStats[] = $row;
        }
        $result->free();
    }
}

$categoryOptions = [
    'staff_blanket' => ['label' => 'Staff Blanket', 'col' => 'staff_blanket_qty'],
    'living_in' => ['label' => 'Living In', 'col' => 'living_in_qty'],
    'ajk' => ['label' => 'AJK', 'col' => 'ajk_qty'],
    'free_voucher' => ['label' => 'FREE VOUCHER', 'col' => 'free_voucher_qty'],
    'comp' => ['label' => 'COMP', 'col' => 'comp_qty'],
];

$bookingDetails = [];
if ($showDetails && $summaryDate !== '') {
    $detailSql = "
        SELECT
            booking_reference,
            full_name,
            phone,
            military_no,
            remark,
            atm_branch_type,
            quantity_dewasa,
            quantity_kanak,
            quantity_kanak_foc,
            quantity_warga_emas,
            quantity_atm,
            free_quantity_dewasa,
            free_quantity_kanak,
            free_quantity_kanak_foc,
            free_quantity_warga_emas,
            free_quantity_atm,
            staff_blanket_qty,
            living_in_qty,
            ajk_qty,
            free_voucher_qty,
            comp_qty
        FROM bookings
        WHERE slot_date = ? AND payment_status IN ('PAID','PENDING')
        ORDER BY booking_reference ASC
    ";
    $stmt = $mysqli->prepare($detailSql);
    if ($stmt) {
        $stmt->bind_param('s', $summaryDate);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $bookingDetails[] = $row;
            }
            $res->free();
        }
        $stmt->close();
    }
}

$entryViewDate = trim((string) ($_GET['entry_view_date'] ?? ''));
if ($entryViewDate !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $entryViewDate);
    $entryViewDate = $dt ? $dt->format('Y-m-d') : '';
}

$entrySummary = [];
$entrySummarySql = "
    SELECT
        slot_date,
        SUM(
            quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
            + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
            + staff_blanket_qty + living_in_qty + ajk_qty + free_voucher_qty + comp_qty
        ) AS total_people,
        SUM(CASE WHEN checkin_status = 'Checked' THEN (
            quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
            + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
            + staff_blanket_qty + living_in_qty + ajk_qty + free_voucher_qty + comp_qty
        ) ELSE 0 END) AS entered_people
    FROM bookings
    WHERE payment_status = 'PAID'
    GROUP BY slot_date
    ORDER BY slot_date ASC
";
if ($res = $mysqli->query($entrySummarySql)) {
    while ($row = $res->fetch_assoc()) {
        $entrySummary[] = $row;
    }
    $res->free();
}

$entryDetails = [];
if ($entryViewDate !== '') {
    $entryDetailSql = "
        SELECT
            booking_reference,
            full_name,
            slot_date,
            (
                quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
                + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
                + staff_blanket_qty + living_in_qty + ajk_qty + free_voucher_qty + comp_qty
            ) AS total_people,
            checkin_status
        FROM bookings
        WHERE payment_status = 'PAID' AND slot_date = ?
        ORDER BY booking_reference ASC
    ";
    $stmt = $mysqli->prepare($entryDetailSql);
    if ($stmt) {
        $stmt->bind_param('s', $entryViewDate);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $entryDetails[] = $row;
            }
            $res->free();
        }
        $stmt->close();
    }
}

$ticketSummary = [];
$ticketSummarySql = "
    SELECT
        slot_date,
        SUM(quantity_dewasa + free_quantity_dewasa) AS total_dewasa,
        SUM(quantity_kanak + free_quantity_kanak) AS total_kanak,
        SUM(quantity_kanak_foc + free_quantity_kanak_foc) AS total_kanak_foc,
        SUM(quantity_warga_emas + free_quantity_warga_emas) AS total_warga,
        SUM(quantity_atm + free_quantity_atm) AS total_atm,
        SUM(staff_blanket_qty) AS total_staff_blanket,
        SUM(living_in_qty) AS total_living_in,
        SUM(ajk_qty) AS total_ajk,
        SUM(free_voucher_qty) AS total_free_voucher,
        SUM(comp_qty) AS total_comp,
        SUM(
            quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
            + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
            + staff_blanket_qty + living_in_qty + ajk_qty + free_voucher_qty + comp_qty
        ) AS total_tickets
    FROM bookings
    WHERE payment_status IN ('PAID','PENDING')
";
if ($summaryDate !== '') {
    $ticketSummarySql .= " AND slot_date = ?";
}
$ticketSummarySql .= "\n    GROUP BY slot_date\n    ORDER BY slot_date ASC\n";

if ($summaryDate !== '') {
    $stmt = $mysqli->prepare($ticketSummarySql);
    if ($stmt) {
        $stmt->bind_param('s', $summaryDate);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $ticketSummary[] = $row;
            }
            $res->free();
        }
        $stmt->close();
    }
} else {
    if ($result = $mysqli->query($ticketSummarySql)) {
        while ($row = $result->fetch_assoc()) {
            $ticketSummary[] = $row;
        }
        $result->free();
    }
}

$atmBranchSummary = [];
$atmBranchSql = "
    SELECT
        slot_date,
        SUM(CASE
            WHEN (military_no REGEXP '^(30|75)[0-9]{5}$' OR military_no REGEXP '^1[0-9]{6}$')
              OR (atm_branch_type = 'ATM-TDM')
              OR (remark LIKE '%ATM: ATM-TDM%')
              THEN (quantity_atm + free_quantity_atm)
            ELSE 0
        END) AS total_tdm,
        SUM(CASE
            WHEN (military_no REGEXP '^(N40[0-9]{4}|NV870[0-9]{4})$' OR military_no REGEXP '^8[0-9]{5}$')
              OR (atm_branch_type = 'ATM_TLDM')
              OR (remark LIKE '%ATM: ATM_TLDM%')
              THEN (quantity_atm + free_quantity_atm)
            ELSE 0
        END) AS total_tldm,
        SUM(CASE
            WHEN (military_no REGEXP '^37[0-9]{4}$' OR military_no REGEXP '^7[0-9]{5}$')
              OR (atm_branch_type = 'ATM-TUDM')
              OR (remark LIKE '%ATM: ATM-TUDM%')
              THEN (quantity_atm + free_quantity_atm)
            ELSE 0
        END) AS total_tudm,
        SUM(quantity_atm + free_quantity_atm) AS total_atm
    FROM bookings
    WHERE (quantity_atm > 0 OR free_quantity_atm > 0)
      AND payment_status IN ('PAID','PENDING')
";

if ($summaryDate !== '') {
    $atmBranchSql .= " AND slot_date = ?";
}

$atmBranchSql .= "\n    GROUP BY slot_date\n    ORDER BY slot_date ASC\n";

if ($summaryDate !== '') {
    $stmt = $mysqli->prepare($atmBranchSql);
    if ($stmt) {
        $stmt->bind_param('s', $summaryDate);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $atmBranchSummary[] = $row;
            }
            $res->free();
        }
        $stmt->close();
    }
} else {
    if ($result = $mysqli->query($atmBranchSql)) {
        while ($row = $result->fetch_assoc()) {
            $atmBranchSummary[] = $row;
        }
        $result->free();
    }
}

// entry list is now handled by $entrySummary + $entryDetails

 if (((string) ($_GET['ajax_ticket_details'] ?? '')) === '1') {
    $ajaxDate = trim((string) ($_GET['slot_date'] ?? ''));
    if ($ajaxDate !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $ajaxDate);
        $ajaxDate = $dt ? $dt->format('Y-m-d') : '';
    }

    header('Content-Type: text/html; charset=utf-8');

    if ($ajaxDate === '') {
        echo '<div class="alert alert-warning mb-0">Invalid date.</div>';
        $mysqli->close();
        exit;
    }

    $rows = [];
    $detailSql = "
        SELECT
            booking_reference,
            full_name,
            phone,
            military_no,
            remark,
            atm_branch_type,
            quantity_dewasa,
            quantity_kanak,
            quantity_kanak_foc,
            quantity_warga_emas,
            quantity_atm,
            free_quantity_dewasa,
            free_quantity_kanak,
            free_quantity_kanak_foc,
            free_quantity_warga_emas,
            free_quantity_atm,
            staff_blanket_qty,
            living_in_qty,
            ajk_qty,
            free_voucher_qty,
            comp_qty
        FROM bookings
        WHERE slot_date = ? AND payment_status IN ('PAID','PENDING')
        ORDER BY booking_reference ASC
    ";
    $stmt = $mysqli->prepare($detailSql);
    if ($stmt) {
        $stmt->bind_param('s', $ajaxDate);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }
        $stmt->close();
    }


    if (!$rows) {
        echo '<div class="alert alert-light mb-0">No bookings found for this date.</div>';
        $mysqli->close();
        exit;
    }

    echo '<div class="table-responsive">';
    echo '<table class="table table-striped align-middle mb-0">';
    echo '<thead><tr>';
    echo '<th>Booking Ref</th>';
    echo '<th>Name</th>';
    echo '<th>Phone</th>';
    echo '<th class="text-end">Dewasa</th>';
    echo '<th class="text-end">Kanak</th>';
    echo '<th class="text-end">Infant</th>';
    echo '<th class="text-end">Warga</th>';
    echo '<th class="text-end">ATM (TDM)</th>';
    echo '<th class="text-end">ATM (TUDM)</th>';
    echo '<th class="text-end">ATM (TLDM)</th>';
    echo '<th class="text-end">Staff Blanket</th>';
    echo '<th class="text-end">Living In</th>';
    echo '<th class="text-end">AJK</th>';
    echo '<th class="text-end">FREE</th>';
    echo '<th class="text-end">COMP</th>';
    echo '<th class="text-end">Total Tickets</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($rows as $b) {
        $mil = (string) ($b['military_no'] ?? '');
        $remark = (string) ($b['remark'] ?? '');
        $atmBranchType = (string) ($b['atm_branch_type'] ?? '');
        $atmQty = (int) ($b['quantity_atm'] ?? 0) + (int) ($b['free_quantity_atm'] ?? 0);
        $atmTdm = 0;
        $atmTudm = 0;
        $atmTldm = 0;

        if ($atmQty > 0 && $mil !== '') {
            if (preg_match('/^(30|75)[0-9]{5}$/', $mil) || preg_match('/^1[0-9]{6}$/', $mil)) {
                $atmTdm = $atmQty;
            } elseif (preg_match('/^(N40[0-9]{4}|NV870[0-9]{4})$/', $mil) || preg_match('/^8[0-9]{5}$/', $mil)) {
                $atmTldm = $atmQty;
            } elseif (preg_match('/^37[0-9]{4}$/', $mil) || preg_match('/^7[0-9]{5}$/', $mil)) {
                $atmTudm = $atmQty;
            }
        } elseif ($atmQty > 0) {
            if ($atmBranchType === 'ATM-TDM' || stripos($remark, 'ATM: ATM-TDM') !== false) {
                $atmTdm = $atmQty;
            } elseif ($atmBranchType === 'ATM_TLDM' || stripos($remark, 'ATM: ATM_TLDM') !== false) {
                $atmTldm = $atmQty;
            } elseif ($atmBranchType === 'ATM-TUDM' || stripos($remark, 'ATM: ATM-TUDM') !== false) {
                $atmTudm = $atmQty;
            }
        }

        $dewasaQty = (int) ($b['quantity_dewasa'] ?? 0) + (int) ($b['free_quantity_dewasa'] ?? 0);
        $kanakQty = (int) ($b['quantity_kanak'] ?? 0) + (int) ($b['free_quantity_kanak'] ?? 0);
        $infantQty = (int) ($b['quantity_kanak_foc'] ?? 0) + (int) ($b['free_quantity_kanak_foc'] ?? 0);
        $wargaQty = (int) ($b['quantity_warga_emas'] ?? 0) + (int) ($b['free_quantity_warga_emas'] ?? 0);
        $staffBlanketQty = (int) ($b['staff_blanket_qty'] ?? 0);
        $livingInQty = (int) ($b['living_in_qty'] ?? 0);
        $ajkQty = (int) ($b['ajk_qty'] ?? 0);
        $freeVoucherQty = (int) ($b['free_voucher_qty'] ?? 0);
        $compQty = (int) ($b['comp_qty'] ?? 0);

        $totalTickets = $dewasaQty
            + $kanakQty
            + $infantQty
            + $wargaQty
            + $atmQty
            + $staffBlanketQty
            + $livingInQty
            + $ajkQty
            + $freeVoucherQty
            + $compQty;

        echo '<tr>';
        echo '<td>' . htmlspecialchars((string) ($b['booking_reference'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($b['full_name'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($b['phone'] ?? '')) . '</td>';
        echo '<td class="text-end">' . number_format($dewasaQty) . '</td>';
        echo '<td class="text-end">' . number_format($kanakQty) . '</td>';
        echo '<td class="text-end">' . number_format($infantQty) . '</td>';
        echo '<td class="text-end">' . number_format($wargaQty) . '</td>';
        echo '<td class="text-end">' . number_format($atmTdm) . '</td>';
        echo '<td class="text-end">' . number_format($atmTudm) . '</td>';
        echo '<td class="text-end">' . number_format($atmTldm) . '</td>';
        echo '<td class="text-end">' . number_format($staffBlanketQty) . '</td>';
        echo '<td class="text-end">' . number_format($livingInQty) . '</td>';
        echo '<td class="text-end">' . number_format($ajkQty) . '</td>';
        echo '<td class="text-end">' . number_format($freeVoucherQty) . '</td>';
        echo '<td class="text-end">' . number_format($compQty) . '</td>';
        echo '<td class="text-end fw-semibold">' . number_format($totalTickets) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';

    $mysqli->close();
    exit;
}

 if (((string) ($_GET['ajax_category_details'] ?? '')) === '1') {
    $cat = trim((string) ($_GET['category'] ?? ''));
    header('Content-Type: text/html; charset=utf-8');

    if (!isset($categoryOptions[$cat])) {
        echo '<div class="alert alert-light mb-0">Choose a category to view the filtered ticket details.</div>';
        $mysqli->close();
        exit;
    }

    $categoryLabel = (string) ($categoryOptions[$cat]['label'] ?? '');
    $categoryCol = (string) ($categoryOptions[$cat]['col'] ?? '');

    $rows = [];
    $sql = "
        SELECT
            booking_reference,
            full_name,
            phone,
            remark,
            {$categoryCol} AS selected_qty,
            (
                quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
                + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
                + staff_blanket_qty + living_in_qty + ajk_qty + comp_qty
            ) AS total_tickets
        FROM bookings
        WHERE payment_status IN ('PAID','PENDING')
          AND {$categoryCol} > 0
        ORDER BY slot_date ASC, booking_reference ASC
    ";
    if ($res = $mysqli->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }

    if (!$rows) {
        echo '<div class="alert alert-light mb-0">No bookings found for ' . htmlspecialchars($categoryLabel) . '.</div>';
        $mysqli->close();
        exit;
    }

    echo '<div class="table-responsive">';
    echo '<table class="table table-striped align-middle mb-0">';
    echo '<thead><tr>';
    echo '<th>Booking Ref</th>';
    echo '<th>Name</th>';
    echo '<th>Phone</th>';
    echo '<th>Remark</th>';
    echo '<th class="text-end">' . htmlspecialchars($categoryLabel) . '</th>';
    echo '<th class="text-end">Total Tickets</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($rows as $row) {
        $selQty = (int) ($row['selected_qty'] ?? 0);
        $totalTickets = (int) ($row['total_tickets'] ?? 0);
        $remark = trim((string) ($row['remark'] ?? ''));

        echo '<tr>';
        echo '<td>' . htmlspecialchars((string) ($row['booking_reference'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['full_name'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['phone'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars($remark !== '' ? $remark : '-') . '</td>';
        echo '<td class="text-end fw-semibold">' . number_format($selQty) . '</td>';
        echo '<td class="text-end">' . number_format($totalTickets) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';

    $mysqli->close();
    exit;
 }

 if (((string) ($_GET['ajax_entry_details'] ?? '')) === '1') {
     $ajaxDate = trim((string) ($_GET['slot_date'] ?? ''));
     if ($ajaxDate !== '') {
         $dt = DateTime::createFromFormat('Y-m-d', $ajaxDate);
         $ajaxDate = $dt ? $dt->format('Y-m-d') : '';
     }

     header('Content-Type: text/html; charset=utf-8');

     if ($ajaxDate === '') {
         echo '<div class="alert alert-warning mb-0">Invalid date.</div>';
         $mysqli->close();
         exit;
     }

     $rows = [];
     $detailSql = "
         SELECT
             booking_reference,
             full_name,
             (
                 quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm
                 + free_quantity_dewasa + free_quantity_kanak + free_quantity_kanak_foc + free_quantity_warga_emas + free_quantity_atm
                 + staff_blanket_qty + living_in_qty + ajk_qty + comp_qty
             ) AS total_people,
             checkin_status
         FROM bookings
         WHERE payment_status = 'PAID' AND slot_date = ?
         ORDER BY booking_reference ASC
     ";

     $stmt = $mysqli->prepare($detailSql);
     if ($stmt) {
         $stmt->bind_param('s', $ajaxDate);
         if ($stmt->execute()) {
             $res = $stmt->get_result();
             while ($row = $res->fetch_assoc()) {
                 $rows[] = $row;
             }
             $res->free();
         }
         $stmt->close();
     }

     if (!$rows) {
         echo '<div class="alert alert-light mb-0">No bookings found for this date.</div>';
         $mysqli->close();
         exit;
     }

     echo '<div class="table-responsive">';
     echo '<table class="table table-striped align-middle mb-0">';
     echo '<thead><tr>';
     echo '<th>Booking Reference</th>';
     echo '<th>Name</th>';
     echo '<th class="text-end">People</th>';
     echo '<th>Entry</th>';
     echo '</tr></thead>';
     echo '<tbody>';

     foreach ($rows as $r) {
         $isEntered = ((string) ($r['checkin_status'] ?? '') === 'Checked');
         echo '<tr>';
         echo '<td>' . htmlspecialchars((string) ($r['booking_reference'] ?? '')) . '</td>';
         echo '<td>' . htmlspecialchars((string) ($r['full_name'] ?? '')) . '</td>';
         echo '<td class="text-end">' . number_format((int) ($r['total_people'] ?? 0)) . '</td>';
         echo '<td><span class="badge ' . ($isEntered ? 'text-bg-success' : 'text-bg-secondary') . '">' . htmlspecialchars($isEntered ? 'Entered' : 'Not Entered') . '</span></td>';
         echo '</tr>';
     }

     echo '</tbody></table></div>';
     $mysqli->close();
     exit;
 }
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports - Buffet Ramadan</title>
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
      .badge-pay { font-weight: 700; }
      .badge-qr { background: rgba(25,135,84,0.12); color: #13795b; border: 1px solid rgba(25,135,84,0.25); }
      .badge-cash { background: rgba(13,110,253,0.10); color: #0b5ed7; border: 1px solid rgba(13,110,253,0.25); }
      @media print {
        @page { size: landscape; margin: 10mm; }
        body { background: #ffffff; }
        body * { visibility: hidden !important; }
        .print-target, .print-target * { visibility: visible !important; }
        .print-target { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
        .table-responsive { overflow: visible !important; }
        .table { font-size: 11px; }
        .print-target table { width: 100% !important; border-collapse: collapse !important; table-layout: fixed !important; }
        .print-target th, .print-target td { border: 1px solid #000 !important; padding: 4px 6px !important; vertical-align: top !important; }
        .print-target th { font-weight: 700 !important; }
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
          <p class="text-muted small mb-0">Reports Panel</p>
        </div>
        <nav class="flex-grow-1">
          <?php $adminRole = strtoupper(trim((string) admin_get_role())); ?>
          <?php if (in_array($adminRole, ['ADMIN', 'STAFF', 'FINANCE', 'ASSISTANT', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2"></i>Dashboard</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'STAFF', 'ASSISTANT', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="all_bookings.php"><i class="bi bi-list-ul"></i>All Bookings</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'ASSISTANT', 'ENTRY_DUTY', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="check_in.php"><i class="bi bi-qr-code-scan"></i>Entry</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'STAFF', 'FINANCE', 'ASSISTANT', 'ENT_ADMIN'], true)): ?>
            <a class="nav-link" href="list_guests.php"><i class="bi bi-people"></i>Name List</a>
            <a class="nav-link active" href="reports.php"><i class="bi bi-bar-chart-line"></i>Reports</a>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['ADMIN', 'BANQUET'], true)): ?>
            <a class="nav-link" href="table_no.php"><i class="bi bi-table"></i>Table No</a>
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

      <main class="main-content flex-grow-1" id="print-area">
        <header class="mb-4">
          <button class="btn btn-outline-secondary d-lg-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
            <i class="bi bi-list"></i>
          </button>
          <h1 class="h3 text-dark mb-2">Reports &amp; Analytics</h1>
        </header>

        <section class="card border-0 shadow-sm rounded-4 mb-4" id="ticket-summary">
          <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
              <div>
                <h2 class="h5 mb-1">Ticket Summary</h2>
                <p class="text-muted mb-0">Ticket totals by date.</p>
              </div>
            </div>

            <?php if ($ticketSummary): ?>
              <?php
                $atmByDate = [];
                foreach ($atmBranchSummary as $row) {
                    $d = (string) ($row['slot_date'] ?? '');
                    if ($d === '') continue;
                    $atmByDate[$d] = [
                        'tdm' => (int) ($row['total_tdm'] ?? 0),
                        'tudm' => (int) ($row['total_tudm'] ?? 0),
                        'tldm' => (int) ($row['total_tldm'] ?? 0),
                    ];
                }
              ?>
              <div class="table-responsive">
                <table class="table table-striped align-middle">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Dewasa</th>
                      <th>Kanak</th>
                      <th>Infant</th>
                      <th>Warga Emas</th>
                      <th>ATM TDM</th>
                      <th>ATM TUDM</th>
                      <th>ATM TLDM</th>
                      <th>Staff Blanket</th>
                      <th>Living In</th>
                      <th>AJK</th>
                      <th>FREE</th>
                      <th>COMP</th>
                      <th>Total Tiket</th>
                      <th class="no-print">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($ticketSummary as $row): ?>
                      <?php
                        $dateLabel = '';
                        $slotDate = (string) ($row['slot_date'] ?? '');
                        if ($slotDate !== '') {
                            $dt = DateTime::createFromFormat('Y-m-d', $slotDate);
                            $dateLabel = $dt ? $dt->format('d/m/Y') : $slotDate;
                        }
                        $kanakOnly = (int) ($row['total_kanak'] ?? 0);
                        $infantOnly = (int) ($row['total_kanak_foc'] ?? 0);
                        $ajkOnly = (int) ($row['total_ajk'] ?? 0);
                        $freeOnly = (int) ($row['total_free_voucher'] ?? 0);
                        $compOnly = (int) ($row['total_comp'] ?? 0);
                        $atmBreakdown = $atmByDate[$slotDate] ?? ['tdm' => 0, 'tudm' => 0, 'tldm' => 0];
                      ?>
                      <tr>
                        <td><?= htmlspecialchars($dateLabel) ?></td>
                        <td><?= number_format((int) ($row['total_dewasa'] ?? 0)) ?></td>
                        <td><?= number_format($kanakOnly) ?></td>
                        <td><?= number_format($infantOnly) ?></td>
                        <td><?= number_format((int) ($row['total_warga'] ?? 0)) ?></td>
                        <td><?= number_format((int) $atmBreakdown['tdm']) ?></td>
                        <td><?= number_format((int) $atmBreakdown['tudm']) ?></td>
                        <td><?= number_format((int) $atmBreakdown['tldm']) ?></td>
                        <td><?= number_format((int) ($row['total_staff_blanket'] ?? 0)) ?></td>
                        <td><?= number_format((int) ($row['total_living_in'] ?? 0)) ?></td>
                        <td><?= number_format($ajkOnly) ?></td>
                        <td><?= number_format($freeOnly) ?></td>
                        <td><?= number_format($compOnly) ?></td>
                        <td><?= number_format((int) ($row['total_tickets'] ?? 0)) ?></td>
                        <td class="no-print">
                          <?php if ($slotDate !== ''): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary js-ticket-details" data-slot-date="<?= htmlspecialchars($slotDate) ?>" data-bs-toggle="modal" data-bs-target="#ticketDetailsModal">View</button>
                          <?php else: ?>
                            <span class="text-muted">-</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-light mb-0">No ticket data available yet.</div>
            <?php endif; ?>
          </div>
        </section>

        <section class="card border-0 shadow-sm rounded-4 mb-4" id="category-ticket-details">
          <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
              <div>
                <h2 class="h5 mb-1">Category Ticket Details</h2>
              </div>
            </div>

            <form method="GET" class="row g-2 align-items-end mb-3" id="categoryFilterForm">
              <div class="col-sm-6 col-md-4">
                <label class="form-label">Category</label>
                <select name="category_filter" class="form-select" id="categoryFilterSelect">
                  <option value="">Select category</option>
                  <option value="staff_blanket">Staff Blanket</option>
                  <option value="living_in">Living In</option>
                  <option value="ajk">AJK</option>
                  <option value="free_voucher">FREE VOUCHER</option>
                  <option value="comp">COMP</option>
                </select>
              </div>
              <div class="col-sm-6 col-md-3">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
              </div>
            </form>

            <div id="categoryTicketDetailsBody">
              <div class="alert alert-light mb-0">Choose a category to view the filtered ticket details.</div>
            </div>
          </div>
        </section>

        <?php if ($showDetails && $summaryDate !== ''): ?>
          <section class="card border-0 shadow-sm rounded-4 mb-4" id="ticket-details">
            <div class="card-body">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div>
                  <h2 class="h5 mb-1">Ticket Details</h2>
                  <p class="text-muted mb-0">Booking list for <?= htmlspecialchars($summaryDate) ?>.</p>
                </div>
                <div class="no-print d-flex gap-2">
                  <button type="button" class="btn btn-outline-secondary" onclick="printBox('ticket-details')"><i class="bi bi-printer me-2"></i>Print</button>
                </div>
              </div>

              <?php if ($bookingDetails): ?>
                <div class="table-responsive">
                  <table class="table table-striped align-middle">
                    <thead>
                      <tr>
                        <th>Booking Ref</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th class="text-end">Dewasa</th>
                        <th class="text-end">Kanak</th>
                        <th class="text-end">Infant</th>
                        <th class="text-end">Warga</th>
                        <th class="text-end">ATM (TDM)</th>
                        <th class="text-end">ATM (TUDM)</th>
                        <th class="text-end">ATM (TLDM)</th>
                        <th class="text-end">Staff Blanket</th>
                        <th class="text-end">Living In</th>
                        <th class="text-end">AJK</th>
                        <th class="text-end">FREE</th>
                        <th class="text-end">COMP</th>
                        <th class="text-end">Total Tickets</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($bookingDetails as $b): ?>
                        <?php
                          $mil = (string) ($b['military_no'] ?? '');
                          $atmQty = (int) ($b['quantity_atm'] ?? 0) + (int) ($b['free_quantity_atm'] ?? 0);
                          $atmTdm = 0;
                          $atmTudm = 0;
                          $atmTldm = 0;

                          if ($atmQty > 0 && $mil !== '') {
                              if (preg_match('/^(30|75)[0-9]{5}$/', $mil) || preg_match('/^1[0-9]{6}$/', $mil)) {
                                  $atmTdm = $atmQty;
                              } elseif (preg_match('/^(N40[0-9]{4}|NV870[0-9]{4})$/', $mil) || preg_match('/^8[0-9]{5}$/', $mil)) {
                                  $atmTldm = $atmQty;
                              } elseif (preg_match('/^37[0-9]{4}$/', $mil) || preg_match('/^7[0-9]{5}$/', $mil)) {
                                  $atmTudm = $atmQty;
                              }
                          }

                          $dewasaQty = (int) ($b['quantity_dewasa'] ?? 0) + (int) ($b['free_quantity_dewasa'] ?? 0);
                          $kanakQty = (int) ($b['quantity_kanak'] ?? 0) + (int) ($b['free_quantity_kanak'] ?? 0);
                          $infantQty = (int) ($b['quantity_kanak_foc'] ?? 0) + (int) ($b['free_quantity_kanak_foc'] ?? 0);
                          $wargaQty = (int) ($b['quantity_warga_emas'] ?? 0) + (int) ($b['free_quantity_warga_emas'] ?? 0);
                          $staffBlanketQty = (int) ($b['staff_blanket_qty'] ?? 0);
                          $livingInQty = (int) ($b['living_in_qty'] ?? 0);
                          $ajkQty = (int) ($b['ajk_qty'] ?? 0);
                          $freeVoucherQty = (int) ($b['free_voucher_qty'] ?? 0);
                          $compQty = (int) ($b['comp_qty'] ?? 0);
                          $totalTickets = $dewasaQty + $kanakQty + $infantQty + $wargaQty + $atmQty + $staffBlanketQty + $livingInQty + $ajkQty + $freeVoucherQty + $compQty;
                        ?>
                        <tr>
                          <td><?= htmlspecialchars((string) ($b['booking_reference'] ?? '')) ?></td>
                          <td><?= htmlspecialchars((string) ($b['full_name'] ?? '')) ?></td>
                          <td><?= htmlspecialchars((string) ($b['phone'] ?? '')) ?></td>
                          <td class="text-end"><?= number_format($dewasaQty) ?></td>
                          <td class="text-end"><?= number_format($kanakQty) ?></td>
                          <td class="text-end"><?= number_format($infantQty) ?></td>
                          <td class="text-end"><?= number_format($wargaQty) ?></td>
                          <td class="text-end"><?= number_format($atmTdm) ?></td>
                          <td class="text-end"><?= number_format($atmTudm) ?></td>
                          <td class="text-end"><?= number_format($atmTldm) ?></td>
                          <td class="text-end"><?= number_format($staffBlanketQty) ?></td>
                          <td class="text-end"><?= number_format($livingInQty) ?></td>
                          <td class="text-end"><?= number_format($ajkQty) ?></td>
                          <td class="text-end"><?= number_format($freeVoucherQty) ?></td>
                          <td class="text-end"><?= number_format($compQty) ?></td>
                          <td class="text-end"><?= number_format($totalTickets) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="alert alert-light mb-0">No bookings found for this date.</div>
              <?php endif; ?>
            </div>
          </section>
        <?php endif; ?>

        <section class="card border-0 shadow-sm rounded-4 mb-4" id="finance-summary">
          <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
              <div>
                <h2 class="h5 mb-1">Finance Summary Report (Ramadan Buffet)</h2>
                <p class="text-muted mb-0">Only <span class="fw-semibold">PAID</span> bookings are included. Revenue excludes ENT.</p>
              </div>
              <div class="no-print d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary" onclick="printBox('finance-summary')"><i class="bi bi-printer me-2"></i>Print / PDF</button>
              </div>
            </div>

            <?php
              $financeTotals = [
                'total_bookings' => 0,
                'total_tickets' => 0,
                'total_revenue' => 0.0,
              ];
              foreach ($financeRows as $r) {
                $financeTotals['total_bookings'] += (int) ($r['total_bookings'] ?? 0);
                $financeTotals['total_tickets'] += (int) ($r['total_tickets'] ?? 0);
                $financeTotals['total_revenue'] += (float) ($r['total_revenue'] ?? 0);
              }
            ?>

            <?php if ($financeRows): ?>
              <div class="table-responsive">
                <table class="table table-striped align-middle">
                  <thead>
                    <tr>
                      <th class="text-dark">Buffet Date</th>
                      <th class="text-end text-dark">Total Bookings</th>
                      <th class="text-end">Total Tickets</th>
                      <th class="text-end text-dark">Total Revenue (RM)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($financeRows as $row): ?>
                      <?php
                        $dateLabel = '';
                        if (!empty($row['slot_date'])) {
                            $dt = DateTime::createFromFormat('Y-m-d', (string) $row['slot_date']);
                            $dateLabel = $dt ? $dt->format('d/m/Y') : (string) $row['slot_date'];
                        }
                      ?>
                      <tr>
                        <td><?= htmlspecialchars($dateLabel) ?></td>
                        <td class="text-end"><?= number_format((int) ($row['total_bookings'] ?? 0)) ?></td>
                        <td class="text-end"><?= number_format((int) ($row['total_tickets'] ?? 0)) ?></td>
                        <td class="text-end fw-semibold"><?= number_format((float) ($row['total_revenue'] ?? 0), 2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                  <tfoot>
                    <tr class="table-light">
                      <th>Total</th>
                      <th class="text-end"><?= number_format((int) $financeTotals['total_bookings']) ?></th>
                      <th class="text-end"><?= number_format((int) $financeTotals['total_tickets']) ?></th>
                      <th class="text-end"><?= number_format((float) $financeTotals['total_revenue'], 2) ?></th>
                    </tr>
                  </tfoot>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-light mb-0">No PAID finance data available for the selected filter.</div>
            <?php endif; ?>
          </div>
        </section>

        <section class="card border-0 shadow-sm rounded-4" id="entry-status">
          <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
              <div>
                <h2 class="h5 mb-1">Guest Entry Status</h2>
                <p class="text-muted mb-0">Entered / Not Entered based on admin entry marking.</p>
              </div>
            </div>

            <?php if ($entryViewDate !== ''): ?>
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 no-print">
                <div class="fw-semibold">Viewing date: <?= htmlspecialchars($entryViewDate) ?></div>
                <a href="reports.php#entry-status" class="btn btn-outline-secondary">Back</a>
              </div>
              <?php if ($entryDetails): ?>
                <div class="table-responsive">
                  <table class="table table-striped align-middle">
                    <thead>
                      <tr>
                        <th>Booking Reference</th>
                        <th>Name</th>
                        <th class="text-end">People</th>
                        <th>Entry</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($entryDetails as $row): ?>
                        <?php $isEntered = ((string) ($row['checkin_status'] ?? '') === 'Checked'); ?>
                        <tr>
                          <td><?= htmlspecialchars((string) ($row['booking_reference'] ?? '')) ?></td>
                          <td><?= htmlspecialchars((string) ($row['full_name'] ?? '')) ?></td>
                          <td class="text-end"><?= number_format((int) ($row['total_people'] ?? 0)) ?></td>
                          <td>
                            <span class="badge <?= $isEntered ? 'text-bg-success' : 'text-bg-secondary' ?>">
                              <?= htmlspecialchars($isEntered ? 'Entered' : 'Not Entered') ?>
                            </span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="alert alert-light mb-0">No bookings found for this date.</div>
              <?php endif; ?>

            <?php elseif ($entrySummary): ?>
              <div class="table-responsive">
                <table class="table table-striped align-middle">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th class="text-end">Total People</th>
                      <th class="text-end">People Enter</th>
                      <th class="text-end">People Not Enter</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($entrySummary as $row): ?>
                      <?php
                        $dateLabel = '';
                        if (!empty($row['slot_date'])) {
                            $dt = DateTime::createFromFormat('Y-m-d', (string) $row['slot_date']);
                            $dateLabel = $dt ? $dt->format('d/m/Y') : (string) $row['slot_date'];
                        }
                        $totalPeople = (int) ($row['total_people'] ?? 0);
                        $enteredPeople = (int) ($row['entered_people'] ?? 0);
                        $notEnteredPeople = max(0, $totalPeople - $enteredPeople);
                      ?>
                      <tr>
                        <td><?= htmlspecialchars($dateLabel) ?></td>
                        <td class="text-end"><?= number_format($totalPeople) ?></td>
                        <td class="text-end"><?= number_format($enteredPeople) ?></td>
                        <td class="text-end"><?= number_format($notEnteredPeople) ?></td>
                        <td class="text-end">
                          <?php if (!empty($row['slot_date'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary js-entry-details" data-slot-date="<?= htmlspecialchars((string) ($row['slot_date'] ?? '')) ?>" data-bs-toggle="modal" data-bs-target="#entryDetailsModal">View</button>
                          <?php else: ?>
                            <span class="text-muted">-</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-light mb-0">No bookings available yet.</div>
            <?php endif; ?>
          </div>
        </section>
      </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
      function printBox(sectionId) {
        const el = document.getElementById(sectionId);
        if (!el) {
          window.print();
          return;
        }
        el.classList.add('print-target');
        window.print();
        setTimeout(() => el.classList.remove('print-target'), 300);
      }

      function printTicketDetailsModal() {
        const body = document.getElementById('ticketDetailsBody');
        const titleEl = document.getElementById('ticketDetailsTitle');
        const html = body ? String(body.innerHTML || '') : '';
        const title = titleEl ? String(titleEl.textContent || 'Ticket Details') : 'Ticket Details';

        if (!html.trim()) return;

        const iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        document.body.appendChild(iframe);

        const doc = iframe.contentWindow ? iframe.contentWindow.document : null;
        if (!doc) {
          document.body.removeChild(iframe);
          return;
        }

        doc.open();
        doc.write('<!doctype html><html><head><meta charset="utf-8">');
        doc.write('<meta name="viewport" content="width=device-width, initial-scale=1">');
        doc.write('<title>' + title.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</title>');
        doc.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">');
        doc.write('<style>@page{size:landscape;margin:10mm}body{padding:0;font-family:Arial,sans-serif}h1{font-size:16px;margin:0 0 10px}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #000;padding:4px 6px;font-size:11px;vertical-align:top}th{font-weight:700}td{text-align:left}.text-end{text-align:right} .table-responsive{overflow:visible}</style>');
        doc.write('</head><body>');
        doc.write('<h1>' + title.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</h1>');
        doc.write(html);
        doc.write('</body></html>');
        doc.close();

        setTimeout(() => {
          try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
          } finally {
            setTimeout(() => {
              try { document.body.removeChild(iframe); } catch (_) {}
            }, 500);
          }
        }, 50);
      }

      async function loadTicketDetails(slotDate) {
        const title = document.getElementById('ticketDetailsTitle');
        const body = document.getElementById('ticketDetailsBody');
        const printBtn = document.getElementById('ticketDetailsPrintBtn');

        if (title) {
          let formattedDate = slotDate;
          if (slotDate) {
            const parts = slotDate.split('-');
            if (parts.length === 3) {
              formattedDate = parts[2] + '/' + parts[1] + '/' + parts[0]; // DD/MM/YYYY
            }
          }
          title.textContent = 'Ticket Details - ' + formattedDate;
        }
        if (body) {
          body.innerHTML = '<div class="py-4 text-center text-muted">Loading...</div>';
        }

        if (printBtn) {
          printBtn.onclick = function () {
            printTicketDetailsModal();
          };
        }

        try {
          const url = 'reports.php?ajax_ticket_details=1&slot_date=' + encodeURIComponent(slotDate);
          const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
          if (!res.ok) throw new Error('Request failed');
          const html = await res.text();
          if (body) body.innerHTML = html;
        } catch (e) {
          if (body) {
            body.innerHTML = '<div class="alert alert-danger mb-0">Failed to load details. Please try again.</div>';
          }
        }
      }

      document.addEventListener('click', function (e) {
        const btn = e.target && e.target.closest ? e.target.closest('.js-ticket-details') : null;
        if (!btn) return;
        const slotDate = btn.getAttribute('data-slot-date') || '';
        loadTicketDetails(slotDate);
      });

      async function loadCategoryTicketDetails(category) {
        const body = document.getElementById('categoryTicketDetailsBody');
        if (!body) return;

        if (!category) {
          body.innerHTML = '<div class="alert alert-light mb-0">Choose a category to view the filtered ticket details.</div>';
          return;
        }

        body.innerHTML = '<div class="py-4 text-center text-muted">Loading...</div>';
        try {
          const url = 'reports.php?ajax_category_details=1&category=' + encodeURIComponent(category);
          const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
          if (!res.ok) throw new Error('Request failed');
          const html = await res.text();
          body.innerHTML = html;
        } catch (e) {
          body.innerHTML = '<div class="alert alert-danger mb-0">Failed to load details. Please try again.</div>';
        }
      }

      (function () {
        const form = document.getElementById('categoryFilterForm');
        const select = document.getElementById('categoryFilterSelect');
        if (!form || !select) return;

        form.addEventListener('submit', function (e) {
          e.preventDefault();
          loadCategoryTicketDetails(select.value || '');
        });
      })();

      async function loadEntryDetails(slotDate) {
        const title = document.getElementById('entryDetailsTitle');
        const body = document.getElementById('entryDetailsBody');

        if (title) {
          let formattedDate = slotDate;
          if (slotDate) {
            const parts = slotDate.split('-');
            if (parts.length === 3) {
              formattedDate = parts[2] + '/' + parts[1] + '/' + parts[0];
            }
          }
          title.textContent = 'Guest Entry Details - ' + formattedDate;
        }
        if (body) {
          body.innerHTML = '<div class="py-4 text-center text-muted">Loading...</div>';
        }

        try {
          const url = 'reports.php?ajax_entry_details=1&slot_date=' + encodeURIComponent(slotDate);
          const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
          if (!res.ok) throw new Error('Request failed');
          const html = await res.text();
          if (body) body.innerHTML = html;
        } catch (e) {
          if (body) {
            body.innerHTML = '<div class="alert alert-danger mb-0">Failed to load details. Please try again.</div>';
          }
        }
      }

      document.addEventListener('click', function (e) {
        const btn = e.target && e.target.closest ? e.target.closest('.js-entry-details') : null;
        if (!btn) return;
        const slotDate = btn.getAttribute('data-slot-date') || '';
        loadEntryDetails(slotDate);
      });

      window.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('confirmLogoutBtn');
        const form = document.getElementById('logoutForm');
        if (!btn || !form) return;
        btn.addEventListener('click', () => form.submit());
      });

    </script>

    <div class="modal fade" id="ticketDetailsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title" id="ticketDetailsTitle"><i class="bi bi-table me-2" style="color:#d8b45c;"></i>Ticket Details</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="ticketDetailsPrint" style="background: #fff9ed;">
            <div id="ticketDetailsBody"></div>
          </div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-outline-secondary" id="ticketDetailsPrintBtn"><i class="bi bi-printer me-2"></i>Print</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="entryDetailsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 1.25rem; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(180deg, #08372b, #041f18); color: #fef6dd;">
            <h5 class="modal-title" id="entryDetailsTitle"><i class="bi bi-check2-circle me-2" style="color:#d8b45c;"></i>Guest Entry Details</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: #fff9ed;">
            <div id="entryDetailsBody"></div>
          </div>
          <div class="modal-footer" style="background: #fff9ed;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
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
