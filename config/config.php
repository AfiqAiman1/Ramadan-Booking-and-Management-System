<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
// config.php
// Central configuration for database.
// IMPORTANT: Do not commit real secret keys to public repositories.

// Database
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'buka_puasa_booking';

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
}

function project_wants_json_response(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    if ($accept !== '' && str_contains($accept, 'application/json')) {
        return true;
    }

    $script = strtolower((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script !== '') {
        if (str_ends_with($script, '/admin/admin_live_data.php') || str_ends_with($script, '/admin_live_data.php')) {
            return true;
        }
        if (str_ends_with($script, '/autosave_booking.php') || str_ends_with($script, '/autosave_token.php')) {
            return true;
        }
    }

    $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if ($xhr === 'xmlhttprequest') {
        return true;
    }

    return false;
}

function render_service_unavailable(): void
{
    http_response_code(503);

    if (project_wants_json_response()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'SERVICE_UNAVAILABLE',
            'message' => 'Sorry, our system is currently busy. Please try again later.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>System Busy</title>';
    echo '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#fff9ed;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0f172a} .card{max-width:560px;width:92%;background:#ffffff;border:1px solid rgba(15,23,42,0.12);border-radius:16px;padding:28px;box-shadow:0 10px 30px rgba(2,6,23,0.08)} h1{margin:0 0 10px;font-size:22px} p{margin:0 0 8px;line-height:1.5;color:#334155} .hint{margin-top:14px;font-size:13px;color:#64748b}</style>';
    echo '</head>';
    echo '<body>';
    echo '<div class="card">';
    echo '<h1>System Currently Busy</h1>';
    echo '<p>Sorry, our system is experiencing high traffic or temporary downtime.</p>';
    echo '<p>Please try again in a few minutes.</p>';
    echo '<div class="hint">HTTP 503 Service Unavailable</div>';
    echo '</div>';
    echo '</body>';
    echo '</html>';
    exit;
}

function db_connect(): mysqli
{
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_OFF);
    }

    $mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($mysqli->connect_error) {
        render_service_unavailable();
    }
    try {
        $mysqli->set_charset('utf8mb4');
    } catch (Throwable $e) {
        // ignore
    }
    try {
        @$mysqli->query("SET time_zone = '+08:00'");
    } catch (Throwable $e) {
        // ignore
    }
    return $mysqli;
}

function ensure_admin_notifications_schema(mysqli $mysqli): void
{
    $createSql = "CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(64) NOT NULL,
        message TEXT NOT NULL,
        booking_reference VARCHAR(64) DEFAULT NULL,
        meta_json TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_created_at (created_at),
        KEY idx_booking_reference (booking_reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try {
        @$mysqli->query($createSql);
    } catch (Throwable $e) {
        // ignore
    }
}
function admin_create_notification(mysqli $mysqli, string $type, string $message, ?string $bookingReference = null, array $meta = []): void
{
    try {
        ensure_admin_notifications_schema($mysqli);
        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $stmt = $mysqli->prepare('INSERT INTO admin_notifications (type, message, booking_reference, meta_json) VALUES (?, ?, ?, ?)');
        if (!$stmt) {
            return;
        }
        $ref = $bookingReference;
        $stmt->bind_param('ssss', $type, $message, $ref, $metaJson);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // ignore
    }
}

function ensure_add_pax_logs_schema(mysqli $mysqli): void
{
    $createSql = "CREATE TABLE IF NOT EXISTS add_pax_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_reference VARCHAR(64) NOT NULL,
        slot_date DATE DEFAULT NULL,
        added_quantity_dewasa INT NOT NULL DEFAULT 0,
        added_quantity_kanak INT NOT NULL DEFAULT 0,
        added_quantity_kanak_foc INT NOT NULL DEFAULT 0,
        added_quantity_warga_emas INT NOT NULL DEFAULT 0,
        added_quantity_atm INT NOT NULL DEFAULT 0,
        added_staff_blanket_qty INT NOT NULL DEFAULT 0,
        added_living_in_qty INT NOT NULL DEFAULT 0,
        added_ajk_qty INT NOT NULL DEFAULT 0,
        added_free_voucher_qty INT NOT NULL DEFAULT 0,
        added_remark TEXT DEFAULT NULL,
        added_by VARCHAR(255) DEFAULT NULL,
        added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_add_pax_booking_reference (booking_reference),
        KEY idx_add_pax_slot_date (slot_date),
        KEY idx_add_pax_added_at (added_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try {
        @$mysqli->query($createSql);
    } catch (Throwable $e) {
        // ignore
    }

    $requiredColumns = [
        'added_quantity_dewasa' => "ALTER TABLE add_pax_logs ADD COLUMN added_quantity_dewasa INT NOT NULL DEFAULT 0",
        'added_quantity_kanak' => "ALTER TABLE add_pax_logs ADD COLUMN added_quantity_kanak INT NOT NULL DEFAULT 0",
        'added_quantity_kanak_foc' => "ALTER TABLE add_pax_logs ADD COLUMN added_quantity_kanak_foc INT NOT NULL DEFAULT 0",
        'added_quantity_warga_emas' => "ALTER TABLE add_pax_logs ADD COLUMN added_quantity_warga_emas INT NOT NULL DEFAULT 0",
        'added_quantity_atm' => "ALTER TABLE add_pax_logs ADD COLUMN added_quantity_atm INT NOT NULL DEFAULT 0",
        'added_staff_blanket_qty' => "ALTER TABLE add_pax_logs ADD COLUMN added_staff_blanket_qty INT NOT NULL DEFAULT 0",
        'added_living_in_qty' => "ALTER TABLE add_pax_logs ADD COLUMN added_living_in_qty INT NOT NULL DEFAULT 0",
        'added_ajk_qty' => "ALTER TABLE add_pax_logs ADD COLUMN added_ajk_qty INT NOT NULL DEFAULT 0",
        'added_free_voucher_qty' => "ALTER TABLE add_pax_logs ADD COLUMN added_free_voucher_qty INT NOT NULL DEFAULT 0",
    ];

    $existing = [];
    try {
        if ($res = @$mysqli->query("SHOW COLUMNS FROM add_pax_logs")) {
            while ($row = $res->fetch_assoc()) {
                $existing[strtolower((string) ($row['Field'] ?? ''))] = true;
            }
            $res->free();
        }
    } catch (Throwable $e) {
        $existing = [];
    }

    foreach ($requiredColumns as $col => $alterSql) {
        if (!isset($existing[strtolower($col)])) {
            try {
                @$mysqli->query($alterSql);
            } catch (Throwable $e) {
                // ignore
            }
            $existing[strtolower($col)] = true;
        }
    }
}

function ensure_entry_logs_schema(mysqli $mysqli): void
{
    $createSql = "CREATE TABLE IF NOT EXISTS entry_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_reference VARCHAR(64) NOT NULL,
        slot_date DATE DEFAULT NULL,
        entered_quantity_dewasa INT NOT NULL DEFAULT 0,
        entered_quantity_kanak INT NOT NULL DEFAULT 0,
        entered_quantity_kanak_foc INT NOT NULL DEFAULT 0,
        entered_quantity_warga_emas INT NOT NULL DEFAULT 0,
        entered_quantity_atm INT NOT NULL DEFAULT 0,
        entered_staff_blanket_qty INT NOT NULL DEFAULT 0,
        entered_living_in_qty INT NOT NULL DEFAULT 0,
        entered_ajk_qty INT NOT NULL DEFAULT 0,
        entered_free_voucher_qty INT NOT NULL DEFAULT 0,
        entered_comp_qty INT NOT NULL DEFAULT 0,
        entered_by VARCHAR(255) DEFAULT NULL,
        entered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_entry_logs_booking_reference (booking_reference),
        KEY idx_entry_logs_slot_date (slot_date),
        KEY idx_entry_logs_entered_at (entered_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try {
        @$mysqli->query($createSql);
    } catch (Throwable $e) {
        // ignore
    }

    $requiredColumns = [
        'entered_quantity_dewasa' => "ALTER TABLE entry_logs ADD COLUMN entered_quantity_dewasa INT NOT NULL DEFAULT 0",
        'entered_quantity_kanak' => "ALTER TABLE entry_logs ADD COLUMN entered_quantity_kanak INT NOT NULL DEFAULT 0",
        'entered_quantity_kanak_foc' => "ALTER TABLE entry_logs ADD COLUMN entered_quantity_kanak_foc INT NOT NULL DEFAULT 0",
        'entered_quantity_warga_emas' => "ALTER TABLE entry_logs ADD COLUMN entered_quantity_warga_emas INT NOT NULL DEFAULT 0",
        'entered_quantity_atm' => "ALTER TABLE entry_logs ADD COLUMN entered_quantity_atm INT NOT NULL DEFAULT 0",
        'entered_staff_blanket_qty' => "ALTER TABLE entry_logs ADD COLUMN entered_staff_blanket_qty INT NOT NULL DEFAULT 0",
        'entered_living_in_qty' => "ALTER TABLE entry_logs ADD COLUMN entered_living_in_qty INT NOT NULL DEFAULT 0",
        'entered_ajk_qty' => "ALTER TABLE entry_logs ADD COLUMN entered_ajk_qty INT NOT NULL DEFAULT 0",
        'entered_free_voucher_qty' => "ALTER TABLE entry_logs ADD COLUMN entered_free_voucher_qty INT NOT NULL DEFAULT 0",
        'entered_comp_qty' => "ALTER TABLE entry_logs ADD COLUMN entered_comp_qty INT NOT NULL DEFAULT 0",
        'entered_by' => "ALTER TABLE entry_logs ADD COLUMN entered_by VARCHAR(255) DEFAULT NULL",
        'entered_at' => "ALTER TABLE entry_logs ADD COLUMN entered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    ];

    $existing = [];
    try {
        if ($res = @$mysqli->query("SHOW COLUMNS FROM entry_logs")) {
            while ($row = $res->fetch_assoc()) {
                $existing[strtolower((string) ($row['Field'] ?? ''))] = true;
            }
            $res->free();
        }
    } catch (Throwable $e) {
        $existing = [];
    }

    foreach ($requiredColumns as $col => $alterSql) {
        if (!isset($existing[strtolower($col)])) {
            try {
                @$mysqli->query($alterSql);
            } catch (Throwable $e) {
                // ignore
            }
            $existing[strtolower($col)] = true;
        }
    }
}

function ensure_bookings_schema(mysqli $mysqli): void
{
    // Ensure table exists (safe no-op if it already exists)
    $createSql = "CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_reference VARCHAR(64) NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        phone VARCHAR(40) NOT NULL,
        military_no VARCHAR(40) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        remark TEXT DEFAULT NULL,
        atm_branch_type VARCHAR(16) DEFAULT NULL,
        slot_date DATE NOT NULL,
        quantity_dewasa INT NOT NULL DEFAULT 0,
        quantity_kanak INT NOT NULL DEFAULT 0,
        quantity_kanak_foc INT NOT NULL DEFAULT 0,
        quantity_warga_emas INT NOT NULL DEFAULT 0,
        quantity_atm INT NOT NULL DEFAULT 0,
        free_quantity_dewasa INT NOT NULL DEFAULT 0,
        free_quantity_kanak INT NOT NULL DEFAULT 0,
        free_quantity_kanak_foc INT NOT NULL DEFAULT 0,
        free_quantity_warga_emas INT NOT NULL DEFAULT 0,
        free_quantity_atm INT NOT NULL DEFAULT 0,
        comp_qty INT NOT NULL DEFAULT 0,
        staff_blanket_qty INT NOT NULL DEFAULT 0,
        miss_office_qty INT NOT NULL DEFAULT 0,
        living_in_qty INT NOT NULL DEFAULT 0,
        ajk_qty INT NOT NULL DEFAULT 0,
        free_voucher_qty INT NOT NULL DEFAULT 0,
        total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        payment_status ENUM('PENDING','PAID','FAILED') NOT NULL DEFAULT 'PENDING',
        payment_method VARCHAR(16) DEFAULT NULL,
        rejection_reason TEXT DEFAULT NULL,
        payment_approved_by VARCHAR(255) DEFAULT NULL,
        checkin_status VARCHAR(32) NOT NULL DEFAULT 'Not Checked',
        payment_proof TEXT DEFAULT NULL,
        payment_proof_hash VARCHAR(64) DEFAULT NULL,
        billcode VARCHAR(64) DEFAULT NULL,
        bank_received_status ENUM('PENDING','CONFIRMED','NOT_RECEIVED') NOT NULL DEFAULT 'PENDING',
        bank_not_received_reason TEXT DEFAULT NULL,
        bank_confirmed_at DATETIME DEFAULT NULL,
        bank_received_by VARCHAR(255) DEFAULT NULL,
        table_no VARCHAR(255) DEFAULT NULL,
        pax_added_at DATETIME DEFAULT NULL,
        pax_added_by VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        paid_at DATETIME DEFAULT NULL,
        UNIQUE KEY uq_booking_reference (booking_reference),
        KEY idx_billcode (billcode),
        KEY idx_slot_date (slot_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try {
        @$mysqli->query($createSql);
    } catch (Throwable $e) {
        // ignore
    }

    $requiredColumns = [
        'booking_reference' => "ALTER TABLE bookings ADD COLUMN booking_reference VARCHAR(64) NOT NULL DEFAULT ''",
        'full_name' => "ALTER TABLE bookings ADD COLUMN full_name VARCHAR(255) NOT NULL DEFAULT ''",
        'phone' => "ALTER TABLE bookings ADD COLUMN phone VARCHAR(40) NOT NULL DEFAULT ''",
        'military_no' => "ALTER TABLE bookings ADD COLUMN military_no VARCHAR(40) DEFAULT NULL",
        'email' => "ALTER TABLE bookings ADD COLUMN email VARCHAR(255) DEFAULT NULL",
        'remark' => "ALTER TABLE bookings ADD COLUMN remark TEXT DEFAULT NULL",
        'atm_branch_type' => "ALTER TABLE bookings ADD COLUMN atm_branch_type VARCHAR(16) DEFAULT NULL",
        'slot_date' => "ALTER TABLE bookings ADD COLUMN slot_date DATE NOT NULL DEFAULT '1970-01-01'",
        'quantity_dewasa' => "ALTER TABLE bookings ADD COLUMN quantity_dewasa INT NOT NULL DEFAULT 0",
        'quantity_kanak' => "ALTER TABLE bookings ADD COLUMN quantity_kanak INT NOT NULL DEFAULT 0",
        'quantity_kanak_foc' => "ALTER TABLE bookings ADD COLUMN quantity_kanak_foc INT NOT NULL DEFAULT 0",
        'quantity_warga_emas' => "ALTER TABLE bookings ADD COLUMN quantity_warga_emas INT NOT NULL DEFAULT 0",
        'quantity_atm' => "ALTER TABLE bookings ADD COLUMN quantity_atm INT NOT NULL DEFAULT 0",
        'free_quantity_dewasa' => "ALTER TABLE bookings ADD COLUMN free_quantity_dewasa INT NOT NULL DEFAULT 0",
        'free_quantity_kanak' => "ALTER TABLE bookings ADD COLUMN free_quantity_kanak INT NOT NULL DEFAULT 0",
        'free_quantity_kanak_foc' => "ALTER TABLE bookings ADD COLUMN free_quantity_kanak_foc INT NOT NULL DEFAULT 0",
        'free_quantity_warga_emas' => "ALTER TABLE bookings ADD COLUMN free_quantity_warga_emas INT NOT NULL DEFAULT 0",
        'free_quantity_atm' => "ALTER TABLE bookings ADD COLUMN free_quantity_atm INT NOT NULL DEFAULT 0",
        'comp_qty' => "ALTER TABLE bookings ADD COLUMN comp_qty INT NOT NULL DEFAULT 0",
        'staff_blanket_qty' => "ALTER TABLE bookings ADD COLUMN staff_blanket_qty INT NOT NULL DEFAULT 0",
        'miss_office_qty' => "ALTER TABLE bookings ADD COLUMN miss_office_qty INT NOT NULL DEFAULT 0",
        'living_in_qty' => "ALTER TABLE bookings ADD COLUMN living_in_qty INT NOT NULL DEFAULT 0",
        'ajk_qty' => "ALTER TABLE bookings ADD COLUMN ajk_qty INT NOT NULL DEFAULT 0",
        'free_voucher_qty' => "ALTER TABLE bookings ADD COLUMN free_voucher_qty INT NOT NULL DEFAULT 0",
        'total_price' => "ALTER TABLE bookings ADD COLUMN total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'payment_status' => "ALTER TABLE bookings ADD COLUMN payment_status ENUM('PENDING','PAID','FAILED') NOT NULL DEFAULT 'PENDING'",
        'payment_method' => "ALTER TABLE bookings ADD COLUMN payment_method VARCHAR(16) DEFAULT NULL",
        'rejection_reason' => "ALTER TABLE bookings ADD COLUMN rejection_reason TEXT DEFAULT NULL",
        'payment_approved_by' => "ALTER TABLE bookings ADD COLUMN payment_approved_by VARCHAR(255) DEFAULT NULL",
        'checkin_status' => "ALTER TABLE bookings ADD COLUMN checkin_status VARCHAR(32) NOT NULL DEFAULT 'Not Checked'",
        'payment_proof' => "ALTER TABLE bookings ADD COLUMN payment_proof TEXT DEFAULT NULL",
        'payment_proof_hash' => "ALTER TABLE bookings ADD COLUMN payment_proof_hash VARCHAR(64) DEFAULT NULL",
        'billcode' => "ALTER TABLE bookings ADD COLUMN billcode VARCHAR(64) DEFAULT NULL",
        'bank_received_status' => "ALTER TABLE bookings ADD COLUMN bank_received_status ENUM('PENDING','CONFIRMED','NOT_RECEIVED') NOT NULL DEFAULT 'PENDING'",
        'bank_not_received_reason' => "ALTER TABLE bookings ADD COLUMN bank_not_received_reason TEXT DEFAULT NULL",
        'bank_confirmed_at' => "ALTER TABLE bookings ADD COLUMN bank_confirmed_at DATETIME DEFAULT NULL",
        'bank_received_by' => "ALTER TABLE bookings ADD COLUMN bank_received_by VARCHAR(255) DEFAULT NULL",
        'table_no' => "ALTER TABLE bookings ADD COLUMN table_no VARCHAR(255) DEFAULT NULL",
        'pax_added_at' => "ALTER TABLE bookings ADD COLUMN pax_added_at DATETIME DEFAULT NULL",
        'pax_added_by' => "ALTER TABLE bookings ADD COLUMN pax_added_by VARCHAR(255) DEFAULT NULL",
        'created_at' => "ALTER TABLE bookings ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'paid_at' => "ALTER TABLE bookings ADD COLUMN paid_at DATETIME DEFAULT NULL",
    ];

    $existing = [];
    try {
        if ($res = @$mysqli->query("SHOW COLUMNS FROM bookings")) {
            while ($row = $res->fetch_assoc()) {
                $existing[strtolower($row['Field'])] = true;
            }
            $res->free();
        }
    } catch (Throwable $e) {
        $existing = [];
    }

    foreach ($requiredColumns as $col => $alterSql) {
        if (!isset($existing[strtolower($col)])) {
            try {
                @$mysqli->query($alterSql);
            } catch (Throwable $e) {
                // ignore
            }
            $existing[strtolower($col)] = true;
        }
    }

    try {
        $mysqli->query("ALTER TABLE bookings MODIFY COLUMN payment_proof TEXT DEFAULT NULL");
    } catch (Throwable $e) {
        // ignore
    }

    // Backfill from legacy columns if your old manual-payment schema exists.
    // We do this only when those columns exist.
    if (isset($existing['event_date'])) {
        $mysqli->query("UPDATE bookings SET slot_date = event_date WHERE (slot_date IS NULL OR slot_date = '1970-01-01') AND event_date IS NOT NULL AND event_date <> ''");
    }
    if (isset($existing['dewasa_qty'])) {
        $mysqli->query("UPDATE bookings SET quantity_dewasa = dewasa_qty WHERE quantity_dewasa = 0 AND dewasa_qty > 0");
    }
    if (isset($existing['kanak_qty'])) {
        $mysqli->query("UPDATE bookings SET quantity_kanak = kanak_qty WHERE quantity_kanak = 0 AND kanak_qty > 0");
    }
    if (isset($existing['warga_emas_qty'])) {
        $mysqli->query("UPDATE bookings SET quantity_warga_emas = warga_emas_qty WHERE quantity_warga_emas = 0 AND warga_emas_qty > 0");
    }

    if (isset($existing['quantity_atm'])) {
        $mysqli->query("UPDATE bookings SET quantity_atm = quantity_warga_emas, quantity_warga_emas = 0 WHERE (military_no IS NOT NULL AND military_no <> '') AND quantity_atm = 0 AND quantity_warga_emas > 0");
    }
    if (isset($existing['total_amount'])) {
        $mysqli->query("UPDATE bookings SET total_price = total_amount WHERE total_price = 0 AND total_amount > 0");
    }

    // Normalize legacy text statuses if present
    $mysqli->query("UPDATE bookings SET payment_status='PENDING' WHERE payment_status IN ('Pending','pending')");
    $mysqli->query("UPDATE bookings SET payment_status='PAID' WHERE payment_status IN ('Paid','paid')");
    $mysqli->query("UPDATE bookings SET payment_status='FAILED' WHERE payment_status IN ('Rejected','Failed','rejected','failed')");
}

function ensure_booking_slots_schema(mysqli $mysqli): void
{
    $createSql = "CREATE TABLE IF NOT EXISTS booking_slots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slot_date DATE NOT NULL,
        max_capacity INT NOT NULL DEFAULT 0,
        booked_count INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_slot_date (slot_date),
        KEY idx_slot_date (slot_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $mysqli->query($createSql);

    $existing = [];
    if ($res = $mysqli->query("SHOW COLUMNS FROM booking_slots")) {
        while ($row = $res->fetch_assoc()) {
            $existing[(string) ($row['Field'] ?? '')] = true;
        }
        $res->free();
    }

    if (!isset($existing['is_locked'])) {
        $mysqli->query("ALTER TABLE booking_slots ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!isset($existing['locked_prev_capacity'])) {
        $mysqli->query("ALTER TABLE booking_slots ADD COLUMN locked_prev_capacity INT NOT NULL DEFAULT 0");
    }
}

function ensure_global_settings_schema(mysqli $mysqli): void
{
    $createSql = "CREATE TABLE IF NOT EXISTS global_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_name VARCHAR(255) NOT NULL DEFAULT 'Ramadan Iftar Buffet',
        event_venue VARCHAR(255) NOT NULL DEFAULT 'Dewan Wisma Perwira',
        event_year INT NOT NULL DEFAULT 2026,
        event_start_date DATE NOT NULL DEFAULT '2026-02-01',
        event_end_date DATE NOT NULL DEFAULT '2026-03-31',
        price_dewasa DECIMAL(10,2) NOT NULL DEFAULT 98.00,
        price_kanak DECIMAL(10,2) NOT NULL DEFAULT 50.00,
        price_warga DECIMAL(10,2) NOT NULL DEFAULT 85.00,
        payment_method_name VARCHAR(255) NOT NULL DEFAULT 'DuitNow QR',
        payment_bank_name VARCHAR(255) NOT NULL DEFAULT 'Maybank',
        payment_account_holder VARCHAR(255) NOT NULL DEFAULT 'Hotel Buka Puasa',
        payment_qr_path VARCHAR(255) DEFAULT NULL,
        payment_instructions TEXT NOT NULL,
        max_tickets_per_booking INT NOT NULL DEFAULT 10000,
        booking_status ENUM('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
        allow_same_day_booking TINYINT(1) NOT NULL DEFAULT 1,
        checkin_start_time TIME NOT NULL DEFAULT '17:00:00',
        allow_ticket_reprint TINYINT(1) NOT NULL DEFAULT 1,
        ticket_reference_prefix VARCHAR(32) NOT NULL DEFAULT 'WP26',
        admin_name VARCHAR(255) NOT NULL DEFAULT 'Admin',
        admin_email VARCHAR(255) NOT NULL DEFAULT 'admin@hotel.com',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $mysqli->query($createSql);

    $res = $mysqli->query('SELECT id FROM global_settings ORDER BY id ASC LIMIT 1');
    if ($res && $res->num_rows > 0) {
        $res->free();
        return;
    }
    if ($res) {
        $res->free();
    }

    $defaultInstructions = "Sila buat pembayaran menggunakan DuitNow QR.\n\n1) Scan QR\n2) Masukkan jumlah tepat\n3) Upload resit / bukti pembayaran\n\nTempahan akan disahkan selepas semakan oleh pihak admin.";
    $stmt = $mysqli->prepare('INSERT INTO global_settings (payment_instructions) VALUES (?)');
    if ($stmt) {
        $stmt->bind_param('s', $defaultInstructions);
        $stmt->execute();
        $stmt->close();
    }
}

function load_global_settings(mysqli $mysqli): array
{
    ensure_global_settings_schema($mysqli);
    $row = [];
    if ($res = $mysqli->query('SELECT * FROM global_settings ORDER BY id ASC LIMIT 1')) {
        $row = $res->fetch_assoc() ?: [];
        $res->free();
    }
    return $row;
}

function update_global_settings(mysqli $mysqli, array $fields): bool
{
    ensure_global_settings_schema($mysqli);
    $settings = load_global_settings($mysqli);
    if (!$settings || empty($settings['id'])) {
        return false;
    }

    $allowed = [
        'event_name','event_venue','event_year','event_start_date','event_end_date',
        'price_dewasa','price_kanak','price_warga',
        'payment_method_name','payment_bank_name','payment_account_holder','payment_qr_path','payment_instructions',
        'max_tickets_per_booking','booking_status','allow_same_day_booking',
        'checkin_start_time','allow_ticket_reprint','ticket_reference_prefix',
        'admin_name','admin_email'
    ];

    $setParts = [];
    $types = '';
    $values = [];
    foreach ($fields as $k => $v) {
        if (!in_array($k, $allowed, true)) {
            continue;
        }
        $setParts[] = $k . ' = ?';
        if (is_int($v)) {
            $types .= 'i';
        } elseif (is_float($v)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
        $values[] = $v;
    }

    if (!$setParts) {
        return true;
    }

    $sql = 'UPDATE global_settings SET ' . implode(', ', $setParts) . ' WHERE id = ?';
    $types .= 'i';
    $values[] = (int) $settings['id'];

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param($types, ...$values);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function load_event_settings_prices(mysqli $mysqli): array
{
    $prices = [
        'dewasa' => 98.0,
        'kanak' => 50.0,
        'warga' => 85.0,
    ];

    $global = load_global_settings($mysqli);
    if ($global) {
        $prices['dewasa'] = (float) ($global['price_dewasa'] ?? $prices['dewasa']);
        $prices['kanak']  = (float) ($global['price_kanak'] ?? $prices['kanak']);
        $prices['warga']  = (float) ($global['price_warga'] ?? $prices['warga']);
    }

    // event_settings table may not exist on fresh installs; keep fallback defaults.
    try {
        if ($stmt = $mysqli->prepare('SELECT price_dewasa, price_kanak, price_warga FROM event_settings ORDER BY id ASC LIMIT 1')) {
            if ($stmt->execute()) {
                $dewasa = null;
                $kanak = null;
                $warga = null;
                $stmt->bind_result($dewasa, $kanak, $warga);
                if ($stmt->fetch()) {
                    $prices['dewasa'] = (float) ($dewasa ?? $prices['dewasa']);
                    $prices['kanak']  = (float) ($kanak ?? $prices['kanak']);
                    $prices['warga']  = (float) ($warga ?? $prices['warga']);
                }
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        // ignore missing table / SQL errors; fall back to global_settings
    }

    return $prices;
}

function apply_special_prices(mysqli $mysqli, array $prices, string $slotDate): array
{
    $slotDate = trim($slotDate);
    if ($slotDate === '2026-02-21') {
        $prices['dewasa'] = 65.0;
        $prices['warga'] = 65.0;
        $prices['atm'] = 65.0;
    }

    try {
        $settings = load_global_settings($mysqli);
        $eventEnd = trim((string) ($settings['event_end_date'] ?? ''));
        if ($eventEnd !== '') {
            $end = DateTime::createFromFormat('Y-m-d', $eventEnd);
            $slot = DateTime::createFromFormat('Y-m-d', $slotDate);
            if ($end && $slot) {
                $promoStart = (clone $end)->modify('-3 days');
                if ($slot >= $promoStart && $slot <= $end) {
                    $prices['dewasa'] = 75.0;
                    $prices['warga'] = 75.0;
                    $prices['atm'] = 75.0;
                }
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    return $prices;
}

function ensure_admin_users_schema(mysqli $mysqli): void
{
    $createSql = "CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(64) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(32) NOT NULL DEFAULT 'admin',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        password_valid_from TIME NULL,
        password_valid_until TIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_admin_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $mysqli->query($createSql);

    $requiredColumns = [
        'role' => "ALTER TABLE admin_users ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'admin'",
        'is_active' => "ALTER TABLE admin_users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        'password_valid_from' => "ALTER TABLE admin_users ADD COLUMN password_valid_from TIME NULL",
        'password_valid_until' => "ALTER TABLE admin_users ADD COLUMN password_valid_until TIME NULL",
        'created_at' => "ALTER TABLE admin_users ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    ];

    $existing = [];
    if ($res = $mysqli->query("SHOW COLUMNS FROM admin_users")) {
        while ($row = $res->fetch_assoc()) {
            $existing[strtolower($row['Field'])] = true;
        }
        $res->free();
    }

    foreach ($requiredColumns as $col => $alterSql) {
        if (!isset($existing[strtolower($col)])) {
            try {
                @$mysqli->query($alterSql);
            } catch (Throwable $e) {
                // ignore
            }
        }
    }

    $res = $mysqli->query("SELECT id FROM admin_users ORDER BY id ASC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $res->free();
        return;
    }
    if ($res) {
        $res->free();
    }

    $defaultUsername = 'admin';
    $defaultPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $defaultRole = 'admin';
    $stmt = $mysqli->prepare('INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('sss', $defaultUsername, $defaultPasswordHash, $defaultRole);
        $stmt->execute();
        $stmt->close();
    }
}
