<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    echo "<script>alert('You must be logged in'); window.location.href='login.php';</script>";
    exit();
}

$errors = array();
$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit = ($edit_id > 0);

// Default values
$program_date = date('Y-m-d');
$mc_no = '';
$mc_dia = '';
$mc_gauge = '';
$finish_dia = '';
$open_tube = 'O';
$buyer = '';
$supplier = '';
$booking_no = '';
$style_no = '';
$so_no = '';
$so_item = '';
$shipment_date = '';
$tna_start = '';
$tna_end = '';
$yarn_type = '';
$yarn_count = '';
$lot_no = '';
$fabrics_type = '';
$grey_gsm = '';
$finish_gsm = '';
$sl_vdq = '0.00';
$colour = '';
$req_qty = '0.000';
$previous_knit = '0.000';
$a_shift = '0';
$b_shift = '0';
$c_shift = '0';
$total = '0';
$balance = '0.000';
$remarks = '';

// Load data if editing
if ($is_edit) {
    $stmt = $db->prepare("SELECT * FROM knitting_program WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows == 1) {
        $row = $res->fetch_assoc();
        $program_date = $row['program_date'];
        $mc_no = $row['mc_no'];
        $mc_dia = $row['mc_dia'];
        $mc_gauge = $row['mc_gauge'];
        $finish_dia = $row['finish_dia'];
        $open_tube = $row['open_tube'];
        $buyer = $row['buyer'];
        $supplier = $row['supplier'];
        $booking_no = $row['booking_no'];
        $style_no = $row['style_no'];
        $so_no = $row['so_no'];
        $so_item = $row['so_item'];
        $shipment_date = $row['shipment_date'];
        $tna_start = $row['tna_start'];
        $tna_end = $row['tna_end'];
        $yarn_type = $row['yarn_type'];
        $yarn_count = $row['yarn_count'];
        $lot_no = $row['lot_no'];
        $fabrics_type = $row['fabrics_type'];
        $grey_gsm = $row['grey_gsm'];
        $finish_gsm = $row['finish_gsm'];
        $sl_vdq = $row['sl_vdq'];
        $colour = $row['colour'];
        $req_qty = $row['req_qty'];
        $previous_knit = $row['previous_knit'];
        $a_shift = $row['a_shift'];
        $b_shift = $row['b_shift'];
        $c_shift = $row['c_shift'];
        $total = $row['total'];
        $balance = $row['balance'];
        $remarks = $row['remarks'];
    } else {
        header("Location: knitting_program_list.php?error=Record not found");
        exit();
    }
}

// Process POST submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $program_date = trim($_POST['program_date']);
    $mc_no = trim($_POST['mc_no']);
    $mc_dia = trim($_POST['mc_dia']);
    $mc_gauge = trim($_POST['mc_gauge']);
    $finish_dia = trim($_POST['finish_dia']);
    $open_tube = trim($_POST['open_tube']);
    $buyer = trim($_POST['buyer']);
    $supplier = trim($_POST['supplier']);
    $booking_no = trim($_POST['booking_no']);
    $style_no = trim($_POST['style_no']);
    $so_no = trim($_POST['so_no']);
    $so_item = trim($_POST['so_item']);
    $shipment_date = !empty($_POST['shipment_date']) ? $_POST['shipment_date'] : NULL;
    $tna_start = !empty($_POST['tna_start']) ? $_POST['tna_start'] : NULL;
    $tna_end = !empty($_POST['tna_end']) ? $_POST['tna_end'] : NULL;
    $yarn_type = trim($_POST['yarn_type']);
    $yarn_count = trim($_POST['yarn_count']);
    $lot_no = trim($_POST['lot_no']);
    $fabrics_type = trim($_POST['fabrics_type']);
    $grey_gsm = trim($_POST['grey_gsm']);
    $finish_gsm = trim($_POST['finish_gsm']);
    $sl_vdq = floatval($_POST['sl_vdq']);
    $colour = trim($_POST['colour']);
    $req_qty = floatval($_POST['req_qty']);
    $previous_knit = floatval($_POST['previous_knit']);
    $a_shift = intval($_POST['a_shift']);
    $b_shift = intval($_POST['b_shift']);
    $c_shift = intval($_POST['c_shift']);

    // Server-side calculation of Total Target and Balance
    $total = $a_shift + $b_shift + $c_shift;
    $balance = $req_qty - $previous_knit - $total;
    if ($balance < 0) $balance = 0;

    $remarks = trim($_POST['remarks']);

    // Validation
    if (empty($program_date)) {
        $errors[] = "Program Date is required.";
    }
    if (empty($mc_no)) {
        $errors[] = "Machine No (M/C No) is required.";
    }
    if ($req_qty < 0 || $previous_knit < 0 || $sl_vdq < 0 || $a_shift < 0 || $b_shift < 0 || $c_shift < 0) {
        $errors[] = "Numeric fields cannot be negative values.";
    }

    if (empty($errors)) {
        if ($is_edit) {
            $sql = "UPDATE knitting_program SET 
                program_date=?, mc_no=?, mc_dia=?, mc_gauge=?, finish_dia=?, open_tube=?, buyer=?, supplier=?, 
                booking_no=?, style_no=?, so_no=?, so_item=?, shipment_date=?, tna_start=?, tna_end=?, yarn_type=?, 
                yarn_count=?, lot_no=?, fabrics_type=?, grey_gsm=?, finish_gsm=?, sl_vdq=?, colour=?, req_qty=?, 
                previous_knit=?, a_shift=?, b_shift=?, c_shift=?, total=?, balance=?, remarks=? 
                WHERE id=?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param(
                "sssssssssssssssssssssdsddiiiidsi",
                $program_date, $mc_no, $mc_dia, $mc_gauge, $finish_dia, $open_tube, $buyer, $supplier,
                $booking_no, $style_no, $so_no, $so_item, $shipment_date, $tna_start, $tna_end, $yarn_type,
                $yarn_count, $lot_no, $fabrics_type, $grey_gsm, $finish_gsm, $sl_vdq, $colour, $req_qty,
                $previous_knit, $a_shift, $b_shift, $c_shift, $total, $balance, $remarks, $edit_id
            );
            if ($stmt->execute()) {
                header("Location: knitting_program_list.php?msg=Program updated successfully!");
                exit();
            } else {
                $errors[] = "Database update error: " . $db->error;
            }
        } else {
            $sql = "INSERT INTO knitting_program (
                program_date, mc_no, mc_dia, mc_gauge, finish_dia, open_tube, buyer, supplier, 
                booking_no, style_no, so_no, so_item, shipment_date, tna_start, tna_end, yarn_type, 
                yarn_count, lot_no, fabrics_type, grey_gsm, finish_gsm, sl_vdq, colour, req_qty, 
                previous_knit, a_shift, b_shift, c_shift, total, balance, remarks
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param(
                "sssssssssssssssssssssdsddiiiids",
                $program_date, $mc_no, $mc_dia, $mc_gauge, $finish_dia, $open_tube, $buyer, $supplier,
                $booking_no, $style_no, $so_no, $so_item, $shipment_date, $tna_start, $tna_end, $yarn_type,
                $yarn_count, $lot_no, $fabrics_type, $grey_gsm, $finish_gsm, $sl_vdq, $colour, $req_qty,
                $previous_knit, $a_shift, $b_shift, $c_shift, $total, $balance, $remarks
            );
            if ($stmt->execute()) {
                header("Location: knitting_program_list.php?msg=New Knitting Program added successfully!");
                exit();
            } else {
                $errors[] = "Database insert error: " . $db->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Knitting Program #' . $edit_id : 'New Knitting Program Entry'; ?> | Purbani Fabrics</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/mycss.css">

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

        /* Top Banner Header */
        .top-banner {
            background: linear-gradient(135deg, #004d40 0%, #00796b 50%, #00897b 100%);
            color: white;
            padding: 26px 32px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 121, 107, 0.2);
            margin-bottom: 28px;
        }

        .top-banner h1 {
            font-weight: 700;
            font-size: 1.85rem;
            margin: 0;
        }

        .nav-btn {
            border-radius: 10px;
            font-weight: 600;
            padding: 10px 20px;
            transition: all 0.2s ease;
        }

        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .content-panel {
            background: #ffffff;
            border-radius: 16px;
            padding: 36px 40px;
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
            margin-top: 32px;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.5px;
        }

        .form-section-title:first-of-type {
            margin-top: 10px;
        }

        /* Strict Label & Form Field Layout - Label Always On Top */
        .content-panel .form-label {
            display: block !important;
            width: 100% !important;
            margin-bottom: 8px !important;
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
        }

        .content-panel .form-control, 
        .content-panel .form-select {
            display: block !important;
            width: 100% !important;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 11px 14px;
            font-size: 14px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-teal);
            box-shadow: 0 0 0 3px rgba(0, 121, 107, 0.15);
        }

        .form-control[readonly] {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            cursor: not-allowed;
        }

        .read-only-highlight {
            background-color: #e0f2f1 !important;
            color: #004d40 !important;
            border-color: #b2dfdb !important;
        }

        /* Strict 24px Row-to-Row Vertical Gap for Every Grid Column Across All Sections */
        .content-panel .row > [class*="col-"] {
            margin-bottom: 24px !important;
        }

        /* Clean up HTML5 number input steppers */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }

        .btn-teal {
            background-color: var(--primary-teal);
            border-color: var(--primary-teal);
            color: white;
            font-weight: 600;
            border-radius: 10px;
            padding: 11px 24px;
        }

        .btn-teal:hover {
            background-color: var(--dark-teal);
            color: white;
        }
    </style>
</head>

<body>

    <div class="container-fluid" style="max-width: 1350px;">

        <!-- Header Banner -->
        <div class="top-banner d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="d-flex align-items-center gap-2">
                    <i class="fa-solid <?php echo $is_edit ? 'fa-pen-to-square' : 'fa-plus-circle'; ?>"></i>
                    <?php echo $is_edit ? 'Edit Knitting Program #' . $edit_id : 'New Knitting Program Entry'; ?>
                </h1>
                <p class="mb-0 text-white-50 small mt-1">Fill in the production parameters below to save or update the program entry</p>
            </div>
            <div>
                <a href="knitting_program_list.php" class="btn btn-light nav-btn text-dark">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Program List
                </a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4 p-3">
                <h6 class="alert-heading fw-bold mb-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> Validation Errors:</h6>
                <ul class="mb-0 small ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="content-panel">
            <form method="POST" id="programForm" action="knitting_program_form.php<?php echo $is_edit ? '?id=' . $edit_id : ''; ?>">

                <!-- SECTION 1: General & Machine Specifications -->
                <div class="form-section-title">
                    <i class="fa-solid fa-gears"></i> 1. General & Machine Specifications
                </div>
                <div class="row gx-4 mb-2">
                    <div class="col-md-3">
                        <label class="form-label">Program Date <span class="text-danger">*</span></label>
                        <input type="date" name="program_date" class="form-control" value="<?php echo htmlspecialchars($program_date); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Machine No (M/C No) <span class="text-danger">*</span></label>
                        <input type="text" name="mc_no" class="form-control" placeholder="e.g. 87" value="<?php echo htmlspecialchars($mc_no); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">M/C Dia</label>
                        <input type="text" name="mc_dia" class="form-control" placeholder="e.g. 34X24" value="<?php echo htmlspecialchars($mc_dia); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">M/C Gauge</label>
                        <input type="text" name="mc_gauge" class="form-control" placeholder="e.g. 28" value="<?php echo htmlspecialchars($mc_gauge); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Finish Dia</label>
                        <input type="text" name="finish_dia" class="form-control" placeholder="e.g. 68" value="<?php echo htmlspecialchars($finish_dia); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Open / Tube</label>
                        <select name="open_tube" class="form-select">
                            <option value="O" <?php echo ($open_tube == 'O') ? 'selected' : ''; ?>>Open (O)</option>
                            <option value="T" <?php echo ($open_tube == 'T') ? 'selected' : ''; ?>>Tube (T)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Grey GSM</label>
                        <input type="text" name="grey_gsm" class="form-control" placeholder="e.g. 160" value="<?php echo htmlspecialchars($grey_gsm); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Finish GSM</label>
                        <input type="text" name="finish_gsm" class="form-control" placeholder="e.g. 150" value="<?php echo htmlspecialchars($finish_gsm); ?>">
                    </div>
                </div>

                <!-- SECTION 2: Buyer & Order Details -->
                <div class="form-section-title">
                    <i class="fa-solid fa-file-invoice"></i> 2. Buyer & Order Details
                </div>
                <div class="row gx-4 mb-2">
                    <div class="col-md-4">
                        <label class="form-label">Buyer Name</label>
                        <input type="text" name="buyer" class="form-control" placeholder="e.g. HEMA" value="<?php echo htmlspecialchars($buyer); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Supplier Name</label>
                        <input type="text" name="supplier" class="form-control" placeholder="e.g. KARIM" value="<?php echo htmlspecialchars($supplier); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Booking No</label>
                        <input type="text" name="booking_no" class="form-control" placeholder="e.g. 230043287" value="<?php echo htmlspecialchars($booking_no); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Style No</label>
                        <input type="text" name="style_no" class="form-control" placeholder="e.g. 236860" value="<?php echo htmlspecialchars($style_no); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SO No</label>
                        <input type="text" name="so_no" class="form-control" placeholder="SO Number" value="<?php echo htmlspecialchars($so_no); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SO Item</label>
                        <input type="text" name="so_item" class="form-control" placeholder="Item No" value="<?php echo htmlspecialchars($so_item); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">TNA Start Date</label>
                        <input type="date" name="tna_start" class="form-control" value="<?php echo htmlspecialchars($tna_start); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">TNA End Date</label>
                        <input type="date" name="tna_end" class="form-control" value="<?php echo htmlspecialchars($tna_end); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Shipment Date</label>
                        <input type="date" name="shipment_date" class="form-control" value="<?php echo htmlspecialchars($shipment_date); ?>">
                    </div>
                </div>

                <!-- SECTION 3: Fabric & Yarn Specifications -->
                <div class="form-section-title">
                    <i class="fa-solid fa-scroll"></i> 3. Fabric & Yarn Specifications
                </div>
                <div class="row gx-4 mb-2">
                    <div class="col-md-4">
                        <label class="form-label">Yarn Type</label>
                        <input type="text" name="yarn_type" class="form-control" placeholder="e.g. COMBED COTTON" value="<?php echo htmlspecialchars($yarn_type); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Yarn Count</label>
                        <input type="text" name="yarn_count" class="form-control" placeholder="e.g. 30/1" value="<?php echo htmlspecialchars($yarn_count); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Lot No</label>
                        <input type="text" name="lot_no" class="form-control" placeholder="Lot Number" value="<?php echo htmlspecialchars($lot_no); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Fabric Type</label>
                        <input type="text" name="fabrics_type" class="form-control" placeholder="e.g. SINGLE JERSEY" value="<?php echo htmlspecialchars($fabrics_type); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SL / VDQ Ratio</label>
                        <input type="number" step="0.01" min="0" name="sl_vdq" class="form-control" placeholder="e.g. 2.75" value="<?php echo htmlspecialchars($sl_vdq); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Colour</label>
                        <input type="text" name="colour" class="form-control" placeholder="Colour Name" value="<?php echo htmlspecialchars($colour); ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <input type="text" name="remarks" class="form-control" placeholder="Additional notes or comments..." value="<?php echo htmlspecialchars($remarks); ?>">
                    </div>
                </div>

                <!-- SECTION 4: Quantities & Shift Targets -->
                <div class="form-section-title">
                    <i class="fa-solid fa-weight-hanging"></i> 4. Quantities & Shift Targets
                </div>
                <div class="row gx-4 mb-2">
                    <div class="col-md-3">
                        <label class="form-label">Required Qty (KG) <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" min="0" name="req_qty" id="input_req_qty" class="form-control fw-bold text-success" placeholder="0.000" value="<?php echo htmlspecialchars($req_qty); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Previous Knit (KG)</label>
                        <input type="number" step="0.001" min="0" name="previous_knit" id="input_prev_knit" class="form-control" placeholder="0.000" value="<?php echo htmlspecialchars($previous_knit); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Shift A Target (Pcs)</label>
                        <input type="number" min="0" name="a_shift" id="input_a_shift" class="form-control" placeholder="0" value="<?php echo htmlspecialchars($a_shift); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Shift B Target (Pcs)</label>
                        <input type="number" min="0" name="b_shift" id="input_b_shift" class="form-control" placeholder="0" value="<?php echo htmlspecialchars($b_shift); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Shift C Target (Pcs)</label>
                        <input type="number" min="0" name="c_shift" id="input_c_shift" class="form-control" placeholder="0" value="<?php echo htmlspecialchars($c_shift); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Total Target (Auto-Calculated)</label>
                        <input type="number" name="total" id="input_total" class="form-control" readonly value="<?php echo htmlspecialchars($total); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Balance (Auto-Calculated)</label>
                        <input type="number" step="0.001" name="balance" id="input_balance" class="form-control read-only-highlight" readonly value="<?php echo htmlspecialchars($balance); ?>">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-end gap-3 pt-4 border-top">
                    <a href="knitting_program_list.php" class="btn btn-outline-secondary px-4 py-2 fw-semibold" style="border-radius:10px;">
                        <i class="fa-solid fa-xmark me-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-teal px-4 py-2">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Program Entry
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Live JS Calculation for Total & Balance -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var reqQtyInput = document.getElementById('input_req_qty');
            var prevKnitInput = document.getElementById('input_prev_knit');
            var aShiftInput = document.getElementById('input_a_shift');
            var bShiftInput = document.getElementById('input_b_shift');
            var cShiftInput = document.getElementById('input_c_shift');
            var totalInput = document.getElementById('input_total');
            var balanceInput = document.getElementById('input_balance');

            function recalculate() {
                var reqQty = parseFloat(reqQtyInput.value) || 0;
                var prevKnit = parseFloat(prevKnitInput.value) || 0;
                var aShift = parseInt(aShiftInput.value) || 0;
                var bShift = parseInt(bShiftInput.value) || 0;
                var cShift = parseInt(cShiftInput.value) || 0;

                var total = aShift + bShift + cShift;
                var balance = reqQty - prevKnit - total;
                if (balance < 0) balance = 0;

                totalInput.value = total;
                balanceInput.value = balance.toFixed(3);
            }

            [reqQtyInput, prevKnitInput, aShiftInput, bShiftInput, cShiftInput].forEach(function(el) {
                if (el) {
                    el.addEventListener('input', recalculate);
                }
            });

            recalculate();
        });
    </script>
</body>

</html>
