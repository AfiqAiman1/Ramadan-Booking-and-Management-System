<?php
// backup_payment_proofs.php - Enhanced Backup System with Date-based Organization
require_once __DIR__ . '/../config/config.php';

// Handle AJAX requests first
if (isset($_GET['date'])) {
    header('Content-Type: text/html; charset=utf-8');
    $mysqli = db_connect();
    $date = $mysqli->real_escape_string($_GET['date']);
    
    $result = $mysqli->query("
        SELECT 
            booking_reference,
            full_name,
            phone,
            total_price,
            payment_status,
            payment_proof,
            created_at
        FROM bookings 
        WHERE DATE(slot_date) = '$date'
        AND payment_proof IS NOT NULL 
        AND payment_proof != ''
        ORDER BY created_at DESC
    ");
    
    echo "<div class='table-responsive'>
        <table class='table table-sm table-striped'>
            <thead class='table-dark'>
                <tr>
                    <th>Name</th>
                    <th>Booking Reference</th>
                    <th class='text-end'>Total (RM)</th>
                    <th>Payment Status</th>
                    <th>Proof File</th>
                </tr>
            </thead>
            <tbody>";
    
    while ($row = $result->fetch_assoc()) {
        $proof_file = basename($row['payment_proof']);
        $status_color = $row['payment_status'] == 'PAID' ? 'success' : 'warning';
        $total = number_format($row['total_price'], 2);
        
        echo "<tr>
            <td><strong>" . htmlspecialchars($row['full_name']) . "</strong></td>
            <td><code class='text-primary'>" . htmlspecialchars($row['booking_reference']) . "</code></td>
            <td class='text-end fw-bold'>" . $total . "</td>
            <td><span class='badge bg-$status_color'>" . $row['payment_status'] . "</span></td>
            <td><small class='text-muted'>" . htmlspecialchars($proof_file) . "</small></td>
        </tr>";
    }
    
    if ($result->num_rows === 0) {
        echo "<tr><td colspan='5' class='text-center text-muted py-3'>No bookings found for this date.</td></tr>";
    }
    
    echo "</tbody></table></div>";
    $mysqli->close();
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'create_zip') {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Don't display errors, log them instead
    
    // Start output buffering to catch any unwanted output
    ob_start();
    
    header('Content-Type: application/json');
    
    try {
        // Check if ZipArchive class exists
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive class not available. Please enable php-zip extension.');
        }
        
        $backup_dir = '../backups';
        $timestamp = date('Y-m-d_H-i-s');
        $backup_main_dir = $backup_dir . '/payment_proofs_' . $timestamp;
        
        // Create backup directory
        if (!file_exists($backup_dir)) {
            if (!mkdir($backup_dir, 0777, true)) {
                throw new Exception('Failed to create backup directory: ' . $backup_dir);
            }
        }
        
        if (!mkdir($backup_main_dir, 0777, true)) {
            throw new Exception('Failed to create temp directory: ' . $backup_main_dir);
        }
        
        $zip_file = $backup_main_dir . '/payment_proofs_backup.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
            $mysqli = db_connect();
            
            if (!$mysqli) {
                throw new Exception('Failed to connect to database');
            }
            
            // Get all bookings with proofs
            $result = $mysqli->query("
                SELECT 
                    booking_reference,
                    full_name,
                    phone,
                    total_price,
                    payment_status,
                    payment_proof,
                    slot_date,
                    quantity_dewasa,
                    quantity_kanak,
                    quantity_kanak_foc,
                    quantity_warga_emas,
                    quantity_atm
                FROM bookings 
                WHERE payment_proof IS NOT NULL 
                AND payment_proof != ''
                ORDER BY slot_date, booking_reference
            ");
            
            if (!$result) {
                throw new Exception('Database query failed: ' . $mysqli->error);
            }
            
            $copied_files = 0;
            $missing_files = 0;
            
            while ($row = $result->fetch_assoc()) {
                $date_folder = date('Y-m-d', strtotime($row['slot_date']));
                $booking_folder = $row['booking_reference'];
                $folder_path = $date_folder . '/' . $booking_folder . '/';
                
                // Create folder structure in ZIP - handle errors gracefully
                if (!$zip->addEmptyDir($date_folder)) {
                    // Directory might already exist, continue
                    error_log('Warning: Could not add date directory (may already exist): ' . $date_folder);
                }
                
                if (!$zip->addEmptyDir($folder_path)) {
                    // Directory might already exist, continue
                    error_log('Warning: Could not add booking directory (may already exist): ' . $folder_path);
                }
                
                // Add payment proof file if it exists
                if (!empty($row['payment_proof'])) {
                    $correct_path = '../uploads/payment_proof/' . basename($row['payment_proof']);
                    if (file_exists($correct_path)) {
                        if (!$zip->addFile(
                            $correct_path, 
                            $folder_path . basename($row['payment_proof'])
                        )) {
                            error_log('Warning: Failed to add file: ' . $correct_path);
                        } else {
                            $copied_files++;
                        }
                    } else {
                        $missing_files++;
                    }
                } else {
                    $missing_files++;
                }
                
                // Add booking info file
                $total_pax = $row['quantity_dewasa'] + $row['quantity_kanak'] + $row['quantity_kanak_foc'] + $row['quantity_warga_emas'] + $row['quantity_atm'];
                
                $info = "=================================================================\n";
                $info .= "BOOKING INFORMATION\n";
                $info .= "=================================================================\n\n";
                $info .= "Booking Reference: " . $row['booking_reference'] . "\n";
                $info .= "Name: " . $row['full_name'] . "\n";
                $info .= "Phone: " . $row['phone'] . "\n";
                $info .= "Slot Date: " . $row['slot_date'] . "\n\n";
                $info .= "TICKET DETAILS\n";
                $info .= "-----------------------------------------------------------------\n";
                $info .= "Dewasa: " . $row['quantity_dewasa'] . "\n";
                $info .= "Kanak-kanak: " . $row['quantity_kanak'] . "\n";
                $info .= "Kanak-kanak (< 6): " . $row['quantity_kanak_foc'] . "\n";
                $info .= "Warga Emas: " . $row['quantity_warga_emas'] . "\n";
                $info .= "ATM: " . $row['quantity_atm'] . "\n";
                $info .= "Total Pax: " . $total_pax . "\n\n";
                $info .= "PAYMENT DETAILS\n";
                $info .= "-----------------------------------------------------------------\n";
                $info .= "Total Amount: RM " . number_format($row['total_price'], 2) . "\n";
                $info .= "Status: " . $row['payment_status'] . "\n\n";
                $info .= "=================================================================\n";
                $info .= "Generated: " . date('Y-m-d H:i:s') . "\n";
                $info .= "=================================================================\n";
                
                if (!$zip->addFromString($folder_path . 'booking_info.txt', $info)) {
                    error_log('Warning: Failed to add booking info for: ' . $row['booking_reference']);
                }
            }
            
            // Add summary file at root
            $summary = "PAYMENT PROOF BACKUP SUMMARY\n";
            $summary .= "=================================================================\n\n";
            $summary .= "Backup Date: " . date('Y-m-d H:i:s') . "\n";
            $summary .= "Total Bookings: " . $result->num_rows . "\n";
            $summary .= "Files Copied: " . $copied_files . "\n";
            $summary .= "Missing Files: " . $missing_files . "\n\n";
            $summary .= "FOLDER STRUCTURE\n";
            $summary .= "=================================================================\n";
            $summary .= "Each date folder contains booking reference folders\n";
            $summary .= "Each booking folder contains:\n";
            $summary .= "  - Payment proof file (original format)\n";
            $summary .= "  - booking_info.txt (booking details)\n";
            
            if (!$zip->addFromString('BACKUP_SUMMARY.txt', $summary)) {
                throw new Exception('Failed to add summary file');
            }
            
            if (!$zip->close()) {
                throw new Exception('Failed to close ZIP file');
            }
            
            $mysqli->close();
            
            // Move ZIP to backups folder
            $final_zip_path = $backup_dir . '/payment_proofs_' . $timestamp . '.zip';
            if (!rename($zip_file, $final_zip_path)) {
                throw new Exception('Failed to move ZIP file to final location');
            }
            
            // Set proper permissions for download
            chmod($final_zip_path, 0644);
            
            // Remove temp directory
            if (!rmdir($backup_main_dir)) {
                // Don't throw error for temp dir cleanup, just log it
                error_log('Warning: Could not remove temp directory: ' . $backup_main_dir);
            }
            
            // Clean any output buffer before sending JSON
            ob_clean();
            
            echo json_encode([
                'success' => true,
                'zip_url' => 'backups/' . basename($final_zip_path),
                'zip_name' => basename($final_zip_path),
                'bookings_count' => $result->num_rows,
                'files_copied' => $copied_files,
                'missing_files' => $missing_files
            ]);
        } else {
            throw new Exception('Failed to create ZIP file. Error code: ' . $zip->status);
        }
    } catch (Exception $e) {
        // Log the full error for debugging
        error_log('Backup Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        
        // Clean any output buffer before sending JSON
        ob_clean();
        
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    // End output buffering
    ob_end_flush();
    exit;
}

// Main page HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Proof Backup System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ramadan-green: #08372b;
            --ramadan-deep: #041f18;
            --ramadan-gold: #d8b45c;
            --ramadan-cream: #fff8ec;
        }
        body {
            font-family: 'Cairo', system-ui, sans-serif;
            background: linear-gradient(180deg, var(--ramadan-deep) 0%, var(--ramadan-green) 100%);
            min-height: 100vh;
            color: #fff;
        }
        .card {
            background: var(--ramadan-cream);
            border: none;
            border-radius: 1.5rem;
            color: #0b1e1a;
        }
        .card-header {
            background: linear-gradient(135deg, var(--ramadan-green), #1a4d3a);
            color: #fff;
            border-radius: 1.5rem 1.5rem 0 0 !important;
        }
        .btn-gold {
            background: linear-gradient(140deg, #f8d687, #d4a842);
            border: none;
            color: #2d1c01;
            font-weight: 700;
        }
        .btn-gold:hover {
            background: linear-gradient(140deg, #fae3a0, #e0b855);
            color: #2d1c01;
        }
        .modal-content {
            border-radius: 1.5rem;
            background: var(--ramadan-cream);
        }
        .modal-header {
            background: linear-gradient(135deg, var(--ramadan-green), #1a4d3a);
            color: #fff;
            border-radius: 1.5rem 1.5rem 0 0;
        }
        .table-dark {
            background: var(--ramadan-green);
        }
        .badge {
            font-weight: 600;
        }
        .stats-card {
            background: rgba(255,255,255,0.1);
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--ramadan-gold);
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-download me-2"></i>Payment Proof Backup</h2>
        <a href="admin_dashboard.php" class="btn btn-outline-light">
            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>

    <?php
    try {
        $mysqli = db_connect();
        
        // Get summary stats
        $stats_result = $mysqli->query("
            SELECT 
                COUNT(DISTINCT DATE(slot_date)) as total_dates,
                COUNT(*) as total_bookings,
                SUM(quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm + staff_blanket_qty + living_in_qty + ajk_qty + free_voucher_qty + comp_qty) as total_pax
            FROM bookings 
            WHERE payment_proof IS NOT NULL 
            AND payment_proof != ''
        ");
        $stats = $stats_result->fetch_assoc();
        
        // Get bookings grouped by date
        $result = $mysqli->query("
            SELECT 
                DATE(slot_date) as booking_date,
                COUNT(*) as total_bookings,
                SUM(quantity_dewasa + quantity_kanak + quantity_kanak_foc + quantity_warga_emas + quantity_atm + staff_blanket_qty + living_in_qty + ajk_qty + free_voucher_qty + comp_qty) as total_pax
            FROM bookings 
            WHERE payment_proof IS NOT NULL 
            AND payment_proof != ''
            GROUP BY DATE(slot_date)
            ORDER BY booking_date DESC
        ");
        
        echo "<div class='row mb-4'>
            <div class='col-md-4 mb-3'>
                <div class='stats-card'>
                    <div class='stats-number'>" . $stats['total_dates'] . "</div>
                    <div>Total Dates</div>
                </div>
            </div>
            <div class='col-md-4 mb-3'>
                <div class='stats-card'>
                    <div class='stats-number'>" . $stats['total_bookings'] . "</div>
                    <div>Total Bookings</div>
                </div>
            </div>
            <div class='col-md-4 mb-3'>
                <div class='stats-card'>
                    <div class='stats-number'>" . $stats['total_pax'] . "</div>
                    <div>Total Pax</div>
                </div>
            </div>
        </div>";
        
        echo "<div class='card'>
            <div class='card-header d-flex justify-content-between align-items-center'>
                <h5 class='mb-0'><i class='bi bi-calendar3 me-2'></i>Bookings by Date</h5>
                <button class='btn btn-gold btn-sm' onclick='createZipBackup()' id='zipBtn'>
                    <i class='bi bi-file-earmark-zip me-2'></i>Create ZIP Backup
                </button>
            </div>
            <div class='card-body p-0'>
                <div class='table-responsive'>
                    <table class='table table-striped mb-0'>
                        <thead class='table-dark'>
                            <tr>
                                <th>Date</th>
                                <th class='text-center'>Total Bookings</th>
                                <th class='text-center'>Total Pax</th>
                                <th class='text-center'>Action</th>
                            </tr>
                        </thead>
                        <tbody>";
        
        $date_groups = [];
        
        while ($row = $result->fetch_assoc()) {
            $booking_date = $row['booking_date'];
            $date_groups[$booking_date] = $row;
            $formatted_date = date('d F Y (l)', strtotime($booking_date));
            
            echo "<tr>
                <td><strong>$formatted_date</strong></td>
                <td class='text-center'><span class='badge bg-primary fs-6'>{$row['total_bookings']}</span></td>
                <td class='text-center'><span class='badge bg-success fs-6'>{$row['total_pax']}</span></td>
                <td class='text-center'>
                    <button class='btn btn-outline-primary btn-sm' 
                            onclick='showDateModal(\"$booking_date\", \"$formatted_date\")'>
                        <i class='bi bi-eye me-1'></i>View Details
                    </button>
                </td>
            </tr>";
        }
        
        echo "</tbody></table></div></div></div>";
        
        if ($result->num_rows === 0) {
            echo "<div class='card mt-4'>
                <div class='card-body text-center py-5'>
                    <i class='bi bi-info-circle display-4 text-muted'></i>
                    <p class='text-muted mt-3'>No payment proofs found in the database.</p>
                </div>
            </div>";
        }
        
        $mysqli->close();
        
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle me-2'></i><strong>Error:</strong> " . $e->getMessage() . "</div>";
    }
    ?>

    <!-- Progress Modal -->
    <div class="modal fade" id="progressModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status" id="spinner">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5 id="progressTitle">Creating ZIP Backup...</h5>
                    <p class="text-muted" id="progressMessage">Please wait while we organize files</p>
                    <div class="progress mt-3" id="progressBar" style="display: none;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Details Modal -->
    <div class="modal fade" id="dateModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dateModalTitle">
                        <i class="bi bi-calendar-date me-2"></i>
                        <span id="modalDateDisplay"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="dateModalContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading bookings...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentDate = '';
        let dateModal = new bootstrap.Modal(document.getElementById('dateModal'));
        let progressModal = new bootstrap.Modal(document.getElementById('progressModal'));
        
        function showDateModal(date, formattedDate) {
            currentDate = date;
            document.getElementById('modalDateDisplay').textContent = formattedDate;
            document.getElementById('dateModalContent').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading bookings...</p>
                </div>
            `;
            
            dateModal.show();
            
            fetch(window.location.href + '?date=' + encodeURIComponent(date))
                .then(response => {
                    if (!response.ok) throw new Error('Failed to load data');
                    return response.text();
                })
                .then(html => {
                    document.getElementById('dateModalContent').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('dateModalContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Error loading data: ${error.message}
                        </div>
                    `;
                });
        }
        
        function createZipBackup() {
            const btn = document.getElementById('zipBtn');
            const title = document.getElementById('progressTitle');
            const message = document.getElementById('progressMessage');
            const spinner = document.getElementById('spinner');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Creating ZIP...';
            
            title.textContent = 'Creating ZIP Backup...';
            message.textContent = 'Organizing payment proofs by date and booking reference';
            spinner.style.display = 'block';
            document.getElementById('progressBar').style.display = 'none';
            
            progressModal.show();
            
            fetch(window.location.href + '?action=create_zip')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    progressModal.hide();
                    
                    if (data.success) {
                        // Show success and download
                        title.textContent = 'Backup Created!';
                        message.innerHTML = `
                            <strong>${data.bookings_count}</strong> bookings backed up<br>
                            <strong>${data.files_copied}</strong> files copied<br>
                            <strong>${data.missing_files}</strong> files missing
                        `;
                        spinner.style.display = 'none';
                        
                        // Download ZIP
                        const link = document.createElement('a');
                        link.href = data.zip_url;
                        link.download = data.zip_name;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        // Show success alert
                        setTimeout(() => {
                            alert('ZIP backup created successfully!\n\n' + 
                                  'Bookings: ' + data.bookings_count + '\n' +
                                  'Files: ' + data.files_copied + '\n' +
                                  'Missing: ' + data.missing_files + '\n\n' +
                                  'Download starting...');
                        }, 500);
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    progressModal.hide();
                    alert('Error creating backup: ' + error.message);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-file-earmark-zip me-2"></i>Create ZIP Backup';
                });
        }
    </script>
</div>
</body>
</html>