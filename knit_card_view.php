<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    echo "<script>alert('You must be logged in'); window.location.href='login.php';</script>";
    exit();
}

$card_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';
$error = isset($_GET['error']) ? trim($_GET['error']) : '';

if ($card_id <= 0) {
    header("Location: knit_card_list.php?error=Invalid Card ID");
    exit();
}

// Build dynamic QR Code URL pointing to public unauthenticated view
$qr_url = APP_BASE_URL . "/knit_card_public_view.php?id=" . $card_id;

// Handle Update Header Form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_header'])) {
    $mc_no = trim($_POST['mc_no']);
    $mc_dia = trim($_POST['mc_dia']);
    $mc_gauge = trim($_POST['mc_gauge']);
    $finish_dia = trim($_POST['finish_dia']);
    $grey_gsm = trim($_POST['grey_gsm']);
    $finish_gsm = trim($_POST['finish_gsm']);
    $sl_vdq = floatval($_POST['sl_vdq']);
    $buyer = trim($_POST['buyer']);
    $order_no = trim($_POST['order_no']);
    $style_no = trim($_POST['style_no']);
    $fabrics_type = trim($_POST['fabrics_type']);
    $yarn_type = trim($_POST['yarn_type']);
    $lot_no = trim($_POST['lot_no']);
    $colour = trim($_POST['colour']);
    $quantity = floatval($_POST['quantity']);
    $prepared_by = trim($_POST['prepared_by']);
    $authorised_by = trim($_POST['authorised_by']);

    $upd_stmt = $db->prepare("UPDATE knit_card SET 
        mc_no=?, mc_dia=?, mc_gauge=?, finish_dia=?, grey_gsm=?, finish_gsm=?, sl_vdq=?, 
        buyer=?, order_no=?, style_no=?, fabrics_type=?, yarn_type=?, lot_no=?, colour=?, 
        quantity=?, prepared_by=?, authorised_by=? WHERE id=?");
    $upd_stmt->bind_param(
        "ssssssdsssssssdssi",
        $mc_no, $mc_dia, $mc_gauge, $finish_dia, $grey_gsm, $finish_gsm, $sl_vdq,
        $buyer, $order_no, $style_no, $fabrics_type, $yarn_type, $lot_no, $colour,
        $quantity, $prepared_by, $authorised_by, $card_id
    );
    if ($upd_stmt->execute()) {
        $msg = "Card header specifications updated successfully!";
    } else {
        $error = "Failed to update header specs: " . $db->error;
    }
}

// Handle Add Daily Production Log Form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_production_log'])) {
    $log_date = trim($_POST['log_date']);
    $a_shift_qty = floatval($_POST['a_shift_qty']);
    $b_shift_qty = floatval($_POST['b_shift_qty']);
    $c_shift_qty = floatval($_POST['c_shift_qty']);
    $operator_a = trim($_POST['operator_a']);
    $operator_b = trim($_POST['operator_b']);
    $operator_c = trim($_POST['operator_c']);

    if (empty($log_date)) {
        $error = "Log date is required.";
    } elseif ($a_shift_qty < 0 || $b_shift_qty < 0 || $c_shift_qty < 0) {
        $error = "Shift quantities cannot be negative values.";
    } else {
        // Server-side calculation
        $production_qty = $a_shift_qty + $b_shift_qty + $c_shift_qty;

        // Fetch card target quantity
        $card_stmt = $db->prepare("SELECT quantity FROM knit_card WHERE id = ?");
        $card_stmt->bind_param("i", $card_id);
        $card_stmt->execute();
        $card_res = $card_stmt->get_result()->fetch_assoc();
        $card_target_qty = $card_res ? floatval($card_res['quantity']) : 0.00;

        // Fetch latest cum_total for this card
        $prev_stmt = $db->prepare("SELECT cum_total FROM knit_card_production WHERE card_id = ? ORDER BY log_date DESC, id DESC LIMIT 1");
        $prev_stmt->bind_param("i", $card_id);
        $prev_stmt->execute();
        $prev_res = $prev_stmt->get_result();

        $previous_cum_total = 0.00;
        if ($prev_res && $prev_res->num_rows > 0) {
            $prev_row = $prev_res->fetch_assoc();
            $previous_cum_total = floatval($prev_row['cum_total']);
        }

        $cum_total = $previous_cum_total + $production_qty;
        $balance = $card_target_qty - $cum_total;
        if ($balance < 0) $balance = 0;

        // Insert new production log row
        $ins_prod = $db->prepare("INSERT INTO knit_card_production (
            card_id, log_date, a_shift_qty, b_shift_qty, c_shift_qty, production_qty, cum_total, balance, operator_a, operator_b, operator_c
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins_prod->bind_param(
            "isddddddsss",
            $card_id, $log_date, $a_shift_qty, $b_shift_qty, $c_shift_qty, $production_qty, $cum_total, $balance, $operator_a, $operator_b, $operator_c
        );

        if ($ins_prod->execute()) {
            $msg = "Daily production entry logged successfully!";
        } else {
            $error = "Error adding production log: " . $db->error;
        }
    }
}

// Fetch Knit Card Header Data
$stmt = $db->prepare("SELECT kc.*, kp.booking_no FROM knit_card kc LEFT JOIN knitting_program kp ON kc.program_id = kp.id WHERE kc.id = ?");
$stmt->bind_param("i", $card_id);
$stmt->execute();
$card_res = $stmt->get_result();

if (!$card_res || $card_res->num_rows == 0) {
    header("Location: knit_card_list.php?error=Card not found");
    exit();
}

$card = $card_res->fetch_assoc();

// Fetch Daily Production Logs
$prod_stmt = $db->prepare("SELECT * FROM knit_card_production WHERE card_id = ? ORDER BY log_date ASC, id ASC");
$prod_stmt->bind_param("i", $card_id);
$prod_stmt->execute();
$prod_result = $prod_stmt->get_result();

// Progress Calculation
$total_cum_produced = 0.00;
$latest_balance = floatval($card['quantity']);

$logs_array = array();
if ($prod_result && $prod_result->num_rows > 0) {
    while ($pr = $prod_result->fetch_assoc()) {
        $logs_array[] = $pr;
        $total_cum_produced = floatval($pr['cum_total']);
        $latest_balance = floatval($pr['balance']);
    }
}

$target_qty = floatval($card['quantity']);
$completion_pct = ($target_qty > 0) ? min(100, round(($total_cum_produced / $target_qty) * 100, 1)) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knit Card #<?php echo $card['id']; ?> View & Production Log | Purbani Fabrics</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/mycss.css">
    <script src="js/qrcode.min.js"></script>

    <style>
        :root {
            --primary-teal: #00796b;
            --dark-teal: #004d40;
            --surface-bg: #f8fafc;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        /* Fix mycss.css FontAwesome icon border bug */
        i, i.fa-solid, i.fas, i.far, i.fab, i.fa-regular {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
            display: inline-block !important;
            transform: none !important;
        }

        body {
            padding: 24px;
            background-color: var(--surface-bg);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #334155;
        }

        /* Top Header Banner */
        .top-banner {
            background: linear-gradient(135deg, #004d40 0%, #00796b 50%, #00897b 100%);
            color: white;
            padding: 24px 30px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 121, 107, 0.2);
            margin-bottom: 28px;
        }

        .top-banner h1 {
            font-weight: 700;
            font-size: 1.75rem;
            margin: 0;
        }

        .nav-btn {
            border-radius: 10px;
            font-weight: 600;
            padding: 9px 18px;
            transition: all 0.2s ease;
        }

        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .content-panel {
            background: #ffffff;
            border-radius: 16px;
            padding: 32px 36px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
            margin-bottom: 28px;
        }

        .form-section-title {
            font-size: 14.5px;
            font-weight: 700;
            color: var(--primary-teal);
            text-transform: uppercase;
            border-bottom: 2px solid #e0f2f1;
            padding-bottom: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.5px;
        }

        .form-label {
            display: block !important;
            width: 100% !important;
            margin-bottom: 8px !important;
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
        }

        .form-control, .form-select {
            display: block !important;
            width: 100% !important;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 10px 14px;
            font-size: 14px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-teal);
            box-shadow: 0 0 0 3px rgba(0, 121, 107, 0.15);
        }

        /* 20px Row Margin */
        .content-panel .row > [class*="col-"] {
            margin-bottom: 20px !important;
        }

        /* Table Styling */
        .table-responsive-wrapper {
            width: 100%;
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .custom-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .custom-table thead th {
            background: #1e293b;
            color: #f8fafc;
            font-size: 12.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 13px 15px;
            vertical-align: middle;
            border: none;
        }

        .custom-table tbody td {
            padding: 13px 15px;
            font-size: 13.5px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .custom-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .btn-teal {
            background-color: var(--primary-teal);
            border-color: var(--primary-teal);
            color: white;
            font-weight: 600;
            border-radius: 10px;
            padding: 10px 22px;
        }

        .btn-teal:hover {
            background-color: var(--dark-teal);
            color: white;
        }

        .stat-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 18px;
        }

        /* Custom Standalone Modal Styles */
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeInModal 0.2s ease-out;
        }

        @keyframes fadeInModal {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .custom-modal-container {
            background: #ffffff;
            border-radius: 16px;
            width: 90%;
            max-width: 440px;
            padding: 24px 28px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            text-align: center;
            position: relative;
        }

        .custom-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .custom-modal-title {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .custom-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            line-height: 1;
            padding: 0 4px;
        }
        .custom-modal-close:hover {
            color: #0f172a;
        }

        .custom-modal-body {
            padding: 10px 0;
        }

        .qr-img-wrapper {
            background: #f8fafc;
            padding: 16px;
            border-radius: 14px;
            display: inline-block;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 14px;
        }

        .qr-caption {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 4px;
        }

        .qr-url-text {
            font-size: 11.5px;
            font-family: monospace;
            color: #64748b;
            margin-bottom: 0;
            word-break: break-all;
        }

        .custom-modal-footer {
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            margin-top: 20px;
            display: flex;
            justify-content: center;
        }

        .custom-modal-btn {
            border-radius: 10px;
            padding: 8px 24px;
            font-weight: 600;
        }
    </style>
</head>

<body>

    <div class="container-fluid" style="max-width: 1350px;">

        <!-- Top Header Banner -->
        <div class="top-banner d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-id-card"></i> Knit Card #<?php echo $card['id']; ?> Details
                </h1>
                <p class="mb-0 text-white-50 small mt-1">
                    Card Date: <strong><?php echo htmlspecialchars($card['card_date']); ?></strong> &nbsp;|&nbsp;
                    Machine: <strong>M/C <?php echo htmlspecialchars($card['mc_no']); ?></strong> &nbsp;|&nbsp;
                    Buyer: <strong><?php echo htmlspecialchars($card['buyer']); ?></strong>
                </p>
            </div>
            
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="knit_card_list.php" class="btn btn-light nav-btn text-dark">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Cards
                </a>
                <a href="knitting_program_list.php" class="btn btn-outline-light nav-btn text-white">
                    <i class="fa-solid fa-list-check me-1"></i> Programs List
                </a>
                <button type="button" class="btn btn-light nav-btn text-dark" id="btnOpenQrModal">
                    <i class="fa-solid fa-qrcode me-1"></i> QR Code
                </button>
                <a href="knit_card_print.php?id=<?php echo $card['id']; ?>" target="_blank" class="btn btn-warning nav-btn text-dark fw-bold">
                    <i class="fa-solid fa-print me-1"></i> Print Floor Card
                </a>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4 p-3">
                <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4 p-3">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Production Progress Summary Card -->
        <div class="content-panel p-4 mb-4">
            <div class="row align-items-center g-3">
                <div class="col-md-3">
                    <div class="stat-box">
                        <small class="text-muted text-uppercase fw-bold d-block mb-1">Target Quantity</small>
                        <h4 class="mb-0 fw-bold text-dark"><?php echo number_format($target_qty, 2); ?> <small style="font-size:14px;">KG</small></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <small class="text-muted text-uppercase fw-bold d-block mb-1">Cumulative Produced</small>
                        <h4 class="mb-0 fw-bold text-success"><?php echo number_format($total_cum_produced, 2); ?> <small style="font-size:14px;">KG</small></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <small class="text-muted text-uppercase fw-bold d-block mb-1">Remaining Balance</small>
                        <h4 class="mb-0 fw-bold text-danger"><?php echo number_format($latest_balance, 2); ?> <small style="font-size:14px;">KG</small></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <small class="text-muted text-uppercase fw-bold d-block mb-1">Production Completion</small>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height: 12px; border-radius: 6px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $completion_pct; ?>%;"></div>
                            </div>
                            <span class="fw-bold small text-dark"><?php echo $completion_pct; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 1: Card Header Specification Form (Editable) -->
        <div class="content-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-section-title mb-0 border-0 p-0">
                    <i class="fa-solid fa-sliders text-teal me-1"></i> Card Specifications Header
                </div>
                <small class="text-muted"><i class="fa-solid fa-pen-to-square me-1"></i> Edit parameters below and click "Update Header Specs"</small>
            </div>

            <form method="POST" action="knit_card_view.php?id=<?php echo $card_id; ?>">
                <input type="hidden" name="update_header" value="1">
                
                <!-- Row 1: Machine & Fabric Parameters (6 Columns) -->
                <div class="row gx-3">
                    <div class="col-md-2">
                        <label class="form-label">M/C No</label>
                        <input type="text" name="mc_no" class="form-control" value="<?php echo htmlspecialchars($card['mc_no']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">M/C Dia</label>
                        <input type="text" name="mc_dia" class="form-control" value="<?php echo htmlspecialchars($card['mc_dia']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Gauge</label>
                        <input type="text" name="mc_gauge" class="form-control" value="<?php echo htmlspecialchars($card['mc_gauge']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Finish Dia</label>
                        <input type="text" name="finish_dia" class="form-control" value="<?php echo htmlspecialchars($card['finish_dia']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Grey GSM</label>
                        <input type="text" name="grey_gsm" class="form-control" value="<?php echo htmlspecialchars($card['grey_gsm']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Finish GSM</label>
                        <input type="text" name="finish_gsm" class="form-control" value="<?php echo htmlspecialchars($card['finish_gsm']); ?>">
                    </div>
                </div>

                <!-- Row 2: Order & Yarn Specifications (6 Columns) -->
                <div class="row gx-3">
                    <div class="col-md-2">
                        <label class="form-label">SL / VDQ Ratio</label>
                        <input type="number" step="0.01" name="sl_vdq" class="form-control" value="<?php echo htmlspecialchars($card['sl_vdq']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Buyer Name</label>
                        <input type="text" name="buyer" class="form-control" value="<?php echo htmlspecialchars($card['buyer']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Booking / Order No</label>
                        <input type="text" name="order_no" class="form-control" value="<?php echo htmlspecialchars($card['order_no']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Style No</label>
                        <input type="text" name="style_no" class="form-control" value="<?php echo htmlspecialchars($card['style_no']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fabric Type</label>
                        <input type="text" name="fabrics_type" class="form-control" value="<?php echo htmlspecialchars($card['fabrics_type']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Yarn Type</label>
                        <input type="text" name="yarn_type" class="form-control" value="<?php echo htmlspecialchars($card['yarn_type']); ?>">
                    </div>
                </div>

                <!-- Row 3: Color, Lot & Quantity -->
                <div class="row gx-3">
                    <div class="col-md-3">
                        <label class="form-label">Yarn Lot No</label>
                        <input type="text" name="lot_no" class="form-control" value="<?php echo htmlspecialchars($card['lot_no']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Colour</label>
                        <input type="text" name="colour" class="form-control" value="<?php echo htmlspecialchars($card['colour']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Req Quantity (KG)</label>
                        <input type="number" step="0.001" name="quantity" class="form-control fw-bold text-success" value="<?php echo htmlspecialchars($card['quantity']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Prepared By</label>
                        <input type="text" name="prepared_by" class="form-control" value="<?php echo htmlspecialchars($card['prepared_by']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Authorised By</label>
                        <input type="text" name="authorised_by" class="form-control" value="<?php echo htmlspecialchars($card['authorised_by']); ?>">
                    </div>
                </div>

                <div class="d-flex justify-content-end pt-3 border-top">
                    <button type="submit" class="btn btn-teal">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Update Header Specs
                    </button>
                </div>
            </form>
        </div>

        <!-- SECTION 2: Daily Production Log Table -->
        <div class="content-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-section-title mb-0 border-0 p-0">
                    <i class="fa-solid fa-table-list text-teal me-1"></i> Daily Production Log Records
                </div>
                <span class="text-muted small">Total <strong><?php echo count($logs_array); ?></strong> log entries recorded</span>
            </div>

            <div class="table-responsive-wrapper mb-4">
                <table class="table custom-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;" class="text-center">SL#</th>
                            <th style="width: 120px;">Log Date</th>
                            <th>Shift A (KG)</th>
                            <th>Shift B (KG)</th>
                            <th>Shift C (KG)</th>
                            <th style="background-color: #2563eb; color:white;">Daily Prod (KG)</th>
                            <th style="background-color: #059669; color:white;">Cum. Total (KG)</th>
                            <th style="background-color: #d97706; color:white;">Balance (KG)</th>
                            <th>Operator A</th>
                            <th>Operator B</th>
                            <th>Operator C</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs_array) > 0): ?>
                            <?php $sl = 1; ?>
                            <?php foreach ($logs_array as $prow): ?>
                                <tr>
                                    <td class="text-center fw-bold">#<?php echo $sl++; ?></td>
                                    <td>
                                        <i class="fa-regular fa-calendar me-1 text-muted"></i>
                                        <strong><?php echo htmlspecialchars($prow['log_date']); ?></strong>
                                    </td>
                                    <td><?php echo number_format((float)$prow['a_shift_qty'], 2); ?></td>
                                    <td><?php echo number_format((float)$prow['b_shift_qty'], 2); ?></td>
                                    <td><?php echo number_format((float)$prow['c_shift_qty'], 2); ?></td>
                                    <td class="fw-bold text-primary" style="background-color:#eff6ff;"><?php echo number_format((float)$prow['production_qty'], 2); ?> KG</td>
                                    <td class="fw-bold text-success" style="background-color:#f0fdf4;"><?php echo number_format((float)$prow['cum_total'], 2); ?> KG</td>
                                    <td class="fw-bold text-danger" style="background-color:#fffbeb;"><?php echo number_format((float)$prow['balance'], 2); ?> KG</td>
                                    <td><small class="text-secondary"><?php echo htmlspecialchars($prow['operator_a']); ?></small></td>
                                    <td><small class="text-secondary"><?php echo htmlspecialchars($prow['operator_b']); ?></small></td>
                                    <td><small class="text-secondary"><?php echo htmlspecialchars($prow['operator_c']); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open fa-3x mb-3 text-secondary d-block"></i>
                                    <h6 class="fw-bold">No Daily Production Log Entries</h6>
                                    <p class="small mb-0">Use the form below to enter the first shift production entry for this card.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Form to Add New Daily Production Log Entry -->
            <div class="bg-light p-4 border rounded-3" style="border-color:#e2e8f0 !important;">
                <h6 class="fw-bold text-dark mb-3">
                    <i class="fa-solid fa-plus-circle text-teal me-1"></i> Add Daily Production Log Entry
                </h6>
                <form method="POST" action="knit_card_view.php?id=<?php echo $card_id; ?>">
                    <input type="hidden" name="add_production_log" value="1">
                    
                    <!-- Row 1: Log Date & Shift Quantities (4 Columns) -->
                    <div class="row gx-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Log Date <span class="text-danger">*</span></label>
                            <input type="date" name="log_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Shift A Qty (KG)</label>
                            <input type="number" step="0.01" min="0" name="a_shift_qty" class="form-control" placeholder="0.00" value="0.00" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Shift B Qty (KG)</label>
                            <input type="number" step="0.01" min="0" name="b_shift_qty" class="form-control" placeholder="0.00" value="0.00" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Shift C Qty (KG)</label>
                            <input type="number" step="0.01" min="0" name="c_shift_qty" class="form-control" placeholder="0.00" value="0.00" required>
                        </div>
                    </div>

                    <!-- Row 2: Operators & Submit Button (4 Columns) -->
                    <div class="row gx-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Operator A Name</label>
                            <input type="text" name="operator_a" class="form-control" placeholder="Operator A">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Operator B Name</label>
                            <input type="text" name="operator_b" class="form-control" placeholder="Operator B">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Operator C Name</label>
                            <input type="text" name="operator_c" class="form-control" placeholder="Operator C">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-teal w-100 py-2">
                                <i class="fa-solid fa-plus me-1"></i> Add Log Entry
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- Custom QR Code Centered Modal Popup -->
    <div id="customQrModal" class="custom-modal-overlay" style="display: none;">
      <div class="custom-modal-container">
        <div class="custom-modal-header">
          <h5 class="custom-modal-title">
              <i class="fa-solid fa-qrcode" style="color:#00796b;"></i> Live Knit Card QR Code
          </h5>
          <button type="button" class="custom-modal-close" id="btnCloseQrModalX">&times;</button>
        </div>
        <div class="custom-modal-body">
          <div class="qr-img-wrapper">
              <div id="modal_qrcode"></div>
          </div>
          <p class="qr-caption">Scan to view live card</p>
          <p class="qr-url-text"><?php echo htmlspecialchars($qr_url); ?></p>
        </div>
        <div class="custom-modal-footer">
          <button type="button" class="btn btn-secondary custom-modal-btn" id="btnCloseQrModal">Close</button>
        </div>
      </div>
    </div>

    <!-- Client-side QR Code & Modal Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('customQrModal');
            var btnOpen = document.getElementById('btnOpenQrModal');
            var btnCloseX = document.getElementById('btnCloseQrModalX');
            var btnClose = document.getElementById('btnCloseQrModal');
            var qrBox = document.getElementById('modal_qrcode');

            // Generate QR Code once into the modal container
            if (qrBox && typeof QRCode !== 'undefined') {
                new QRCode(qrBox, {
                    text: "<?php echo $qr_url; ?>",
                    width: 220,
                    height: 220,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            }

            function openModal() {
                if (modal) modal.style.display = 'flex';
            }

            function closeModal() {
                if (modal) modal.style.display = 'none';
            }

            if (btnOpen) btnOpen.addEventListener('click', openModal);
            if (btnCloseX) btnCloseX.addEventListener('click', closeModal);
            if (btnClose) btnClose.addEventListener('click', closeModal);

            // Close when clicking outside modal box (on dark overlay backdrop)
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModal();
                    }
                });
            }
        });
    </script>
</body>

</html>
