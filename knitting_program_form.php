<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$errors = [];
$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit = ($edit_id > 0);

// Pre-fetch Machine List from `mcno` table
$mcno_list = [];
$mcno_res = mysqli_query($db, "SELECT MCNOID, MCNO FROM mcno ORDER BY MCNO ASC");
if ($mcno_res) {
    while ($row = mysqli_fetch_assoc($mcno_res)) {
        $mcno_list[] = $row;
    }
}

// Pre-fetch Operator List from `knitting_operator` table
$operator_list = [];
$op_res = mysqli_query($db, "SELECT KOTID, OPERATOR_ID, OPERATOR_NAME FROM knitting_operator ORDER BY OPERATOR_NAME ASC");
if ($op_res) {
    while ($row = mysqli_fetch_assoc($op_res)) {
        $operator_list[] = $row;
    }
}

// Default field values using uppercase database column names
$main_tid = '';
$sub_tid = '';
$booking = '';
$sono = '';
$style = '';
$buyer = '';
$supplier = '';
$knit_m_description = '';
$mcno = '';
$qty = '0.00';
$shift = 'A-SHIFT';
$yarn_type = '';
$yarn_count = '';
$fabrics_type = '';
$finish_gsm = '';
$finish_dia = '';
$open_tube = 'O';
$lot_no = '';
$knit_material_code = '';
$operator_id = '';

// Load existing record for editing
if ($is_edit) {
    $stmt = $db->prepare("SELECT * FROM knitting_program WHERE KPTID = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows == 1) {
        $row = $res->fetch_assoc();
        $main_tid = $row['MAIN_TID'] ?? '';
        $sub_tid = $row['SUB_TID'] ?? '';
        $booking = $row['BOOKING'] ?? '';
        $sono = $row['SONO'] ?? '';
        $style = $row['STYLE'] ?? '';
        $buyer = $row['BUYER'] ?? '';
        $supplier = $row['SUPPLIER'] ?? '';
        $knit_m_description = $row['KNIT_M_DESCRIPTION'] ?? '';
        $mcno = $row['MCNO'] ?? '';
        $qty = $row['QTY'] ?? '0.00';
        $shift = $row['SHIFT'] ?? 'A-SHIFT';
        $yarn_type = $row['YARN_TYPE'] ?? '';
        $yarn_count = $row['YARN_COUNT'] ?? '';
        $fabrics_type = $row['FABRICS_TYPE'] ?? '';
        $finish_gsm = $row['FINISH_GSM'] ?? '';
        $finish_dia = $row['FINISH_DIA'] ?? '';
        $open_tube = $row['OPEN_TUBE'] ?? 'O';
        $lot_no = $row['LOT_NO'] ?? '';
        $knit_material_code = $row['KNIT_MATERIAL_CODE'] ?? '';
    } else {
        header("Location: knitting_program_list.php?error=Program+not+found");
        exit();
    }
}

// Form Submission Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking = trim($_POST['BOOKING'] ?? '');
    $sono = trim($_POST['SONO'] ?? '');
    $style = trim($_POST['STYLE'] ?? '');
    $buyer = trim($_POST['BUYER'] ?? '');
    $supplier = trim($_POST['SUPPLIER'] ?? '');
    $knit_m_description = trim($_POST['KNIT_M_DESCRIPTION'] ?? '');
    $mcno = trim($_POST['MCNO'] ?? '');
    $qty = floatval($_POST['QTY'] ?? 0);
    $shift = trim($_POST['SHIFT'] ?? 'A-SHIFT');
    $yarn_type = trim($_POST['YARN_TYPE'] ?? '');
    $yarn_count = trim($_POST['YARN_COUNT'] ?? '');
    $fabrics_type = trim($_POST['FABRICS_TYPE'] ?? '');
    $finish_gsm = trim($_POST['FINISH_GSM'] ?? '');
    $finish_dia = trim($_POST['FINISH_DIA'] ?? '');
    $open_tube = trim($_POST['OPEN_TUBE'] ?? 'O');
    $lot_no = trim($_POST['LOT_NO'] ?? '');
    $knit_material_code = trim($_POST['KNIT_MATERIAL_CODE'] ?? '');
    $operator_id = trim($_POST['OPERATOR_ID'] ?? '');
    $main_tid = trim($_POST['MAIN_TID'] ?? '');
    $sub_tid = trim($_POST['SUB_TID'] ?? '');

    // Auto-generate MAIN_TID & SUB_TID if empty
    if (empty($main_tid)) {
        $main_tid = time();
    }
    if (empty($sub_tid)) {
        $sub_tid = time() . rand(10, 99);
    }

    // Validation
    if (empty($booking)) {
        $errors[] = "BOOKING number is required.";
    }
    if (empty($mcno)) {
        $errors[] = "Machine No (MCNO) is required.";
    }
    if ($qty <= 0) {
        $errors[] = "QTY must be greater than 0.";
    }

    if (empty($errors)) {
        if ($is_edit) {
            $sql = "UPDATE knitting_program SET 
                MAIN_TID=?, SUB_TID=?, BOOKING=?, SONO=?, STYLE=?, BUYER=?, SUPPLIER=?, 
                KNIT_M_DESCRIPTION=?, MCNO=?, QTY=?, SHIFT=?, YARN_TYPE=?, YARN_COUNT=?, 
                FABRICS_TYPE=?, FINISH_GSM=?, FINISH_DIA=?, OPEN_TUBE=?, LOT_NO=?, KNIT_MATERIAL_CODE=? 
                WHERE KPTID=?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param(
                "sssssssssdsssssssssi",
                $main_tid, $sub_tid, $booking, $sono, $style, $buyer, $supplier,
                $knit_m_description, $mcno, $qty, $shift, $yarn_type, $yarn_count,
                $fabrics_type, $finish_gsm, $finish_dia, $open_tube, $lot_no, $knit_material_code, $edit_id
            );
            if ($stmt->execute()) {
                header("Location: knitting_program_list.php?msg=Program+updated+successfully");
                exit();
            } else {
                $errors[] = "Database update error: " . $db->error;
            }
        } else {
            $sql = "INSERT INTO knitting_program (
                MAIN_TID, SUB_TID, BOOKING, SONO, STYLE, BUYER, SUPPLIER, 
                KNIT_M_DESCRIPTION, MCNO, QTY, SHIFT, YARN_TYPE, YARN_COUNT, 
                FABRICS_TYPE, FINISH_GSM, FINISH_DIA, OPEN_TUBE, LOT_NO, KNIT_MATERIAL_CODE
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param(
                "sssssssssdsssssssss",
                $main_tid, $sub_tid, $booking, $sono, $style, $buyer, $supplier,
                $knit_m_description, $mcno, $qty, $shift, $yarn_type, $yarn_count,
                $fabrics_type, $finish_gsm, $finish_dia, $open_tube, $lot_no, $knit_material_code
            );
            if ($stmt->execute()) {
                header("Location: knitting_program_list.php?msg=New+program+added+successfully");
                exit();
            } else {
                $errors[] = "Database insertion error: " . $db->error;
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

        body {
            padding: 24px;
            background-color: var(--surface-bg);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #334155;
        }

        .top-banner {
            background: linear-gradient(135deg, #004d40 0%, #00796b 50%, #00897b 100%);
            color: white;
            padding: 24px 32px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 121, 107, 0.2);
            margin-bottom: 28px;
        }

        .top-banner h1 {
            font-weight: 700;
            font-size: 1.85rem;
            margin: 0;
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
            margin-top: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.5px;
        }

        .form-section-title:first-of-type {
            margin-top: 10px;
        }

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

        .content-panel .row > [class*="col-"] {
            margin-bottom: 20px !important;
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
                <p class="mb-0 text-white-50 small mt-1">Populate parameters based on Rifat's database structure</p>
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

                <!-- SECTION 1: Booking Lookup & Machine Selection -->
                <div class="form-section-title">
                    <i class="fa-solid fa-gears"></i> 1. Booking Lookup & Machine Selection
                </div>
                <div class="row gx-4 mb-2">
                    <div class="col-md-4">
                        <label class="form-label">BOOKING No <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="BOOKING" id="bookingInput" class="form-control" placeholder="Enter BOOKING (e.g. 230043287)" value="<?php echo htmlspecialchars($booking); ?>" required>
                            <button type="button" class="btn btn-primary" id="fetchBookingBtn">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Lookup
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Machine No (MCNO) <span class="text-danger">*</span></label>
                        <select name="MCNO" id="mcnoSelect" class="form-select" required>
                            <option value="">-- Select Machine (from `mcno` table) --</option>
                            <?php foreach ($mcno_list as $m): ?>
                                <option value="<?php echo htmlspecialchars($m['MCNO']); ?>" <?php echo ($mcno == $m['MCNO']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['MCNO']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Shift Selection</label>
                        <select name="SHIFT" class="form-select">
                            <option value="A-SHIFT" <?php echo ($shift == 'A-SHIFT') ? 'selected' : ''; ?>>A-SHIFT</option>
                            <option value="B-SHIFT" <?php echo ($shift == 'B-SHIFT') ? 'selected' : ''; ?>>B-SHIFT</option>
                            <option value="C-SHIFT" <?php echo ($shift == 'C-SHIFT') ? 'selected' : ''; ?>>C-SHIFT</option>
                        </select>
                    </div>
                </div>

                <!-- SECTION 2: Order & Description Details -->
                <div class="form-section-title">
                    <i class="fa-solid fa-file-invoice"></i> 2. Order & Description Details
                </div>
                <div class="row gx-4 mb-2">
                    <div class="col-md-4">
                        <label class="form-label">SONO</label>
                        <input type="text" name="SONO" id="sonoInput" class="form-control" placeholder="SONO" value="<?php echo htmlspecialchars($sono); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">STYLE</label>
                        <input type="text" name="STYLE" id="styleInput" class="form-control" placeholder="STYLE" value="<?php echo htmlspecialchars($style); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">BUYER</label>
                        <input type="text" name="BUYER" id="buyerInput" class="form-control" placeholder="BUYER" value="<?php echo htmlspecialchars($buyer); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SUPPLIER</label>
                        <input type="text" name="SUPPLIER" id="supplierInput" class="form-control" placeholder="SUPPLIER" value="<?php echo htmlspecialchars($supplier); ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">KNIT_M_DESCRIPTION</label>
                        <select name="KNIT_M_DESCRIPTION" id="descSelect" class="form-select">
                            <option value="<?php echo htmlspecialchars($knit_m_description); ?>">
                                <?php echo htmlspecialchars($knit_m_description ?: '-- Select Fabric Description --'); ?>
                            </option>
                        </select>
                    </div>
                </div>

                <!-- SECTION 3: Technical Specifications -->
                <div class="form-section-title">
                    <i class="fa-solid fa-scroll"></i> 3. Technical Specifications
                </div>
                <div class="row gx-4 mb-2">
                    <div class="col-md-3">
                        <label class="form-label">YARN_TYPE</label>
                        <input type="text" name="YARN_TYPE" id="yarnTypeInput" class="form-control" value="<?php echo htmlspecialchars($yarn_type); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">YARN_COUNT</label>
                        <input type="text" name="YARN_COUNT" id="yarnCountInput" class="form-control" value="<?php echo htmlspecialchars($yarn_count); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">FABRICS_TYPE</label>
                        <input type="text" name="FABRICS_TYPE" id="fabricsTypeInput" class="form-control" value="<?php echo htmlspecialchars($fabrics_type); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">FINISH_GSM</label>
                        <input type="text" name="FINISH_GSM" id="finishGsmInput" class="form-control" value="<?php echo htmlspecialchars($finish_gsm); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">FINISH_DIA</label>
                        <input type="text" name="FINISH_DIA" id="finishDiaInput" class="form-control" value="<?php echo htmlspecialchars($finish_dia); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">OPEN_TUBE</label>
                        <select name="OPEN_TUBE" id="openTubeSelect" class="form-select">
                            <option value="O" <?php echo ($open_tube == 'O') ? 'selected' : ''; ?>>Open (O)</option>
                            <option value="T" <?php echo ($open_tube == 'T') ? 'selected' : ''; ?>>Tube (T)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">LOT_NO</label>
                        <input type="text" name="LOT_NO" id="lotNoInput" class="form-control" value="<?php echo htmlspecialchars($lot_no); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">KNIT_MATERIAL_CODE</label>
                        <input type="text" name="KNIT_MATERIAL_CODE" id="knitMaterialCodeInput" class="form-control" value="<?php echo htmlspecialchars($knit_material_code); ?>">
                    </div>
                </div>

                <!-- SECTION 4: Production Operator & Program Quantity -->
                <div class="form-section-title">
                    <i class="fa-solid fa-weight-hanging"></i> 4. Production Operator & Program Quantity
                </div>
                <div class="row gx-4 mb-2">
                    <div class="col-md-4">
                        <label class="form-label">Operator (from `knitting_operator` table)</label>
                        <select name="OPERATOR_ID" class="form-select">
                            <option value="">-- Select Operator --</option>
                            <?php foreach ($operator_list as $op): ?>
                                <option value="<?php echo htmlspecialchars($op['OPERATOR_ID']); ?>" <?php echo ($operator_id == $op['OPERATOR_ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($op['OPERATOR_NAME'] . ' (' . $op['OPERATOR_ID'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Program QTY (KG) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="QTY" class="form-control fw-bold text-success" placeholder="0.00" value="<?php echo htmlspecialchars($qty); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">MAIN_TID / SUB_TID (Auto-Generated)</label>
                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars(($main_tid ? $main_tid . ' / ' . $sub_tid : 'Auto-generated on save')); ?>" readonly>
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

    <script src="jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#fetchBookingBtn').click(function() {
                var booking = $('#bookingInput').val().trim();
                if (!booking) {
                    alert('Please enter a BOOKING number first.');
                    return;
                }
                
                $.ajax({
                    url: 'ajaxKnittingProgram.php',
                    data: { booking: booking },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp && resp.success && resp.data) {
                            var d = resp.data;
                            $('#sonoInput').val(d.SONO || '');
                            $('#styleInput').val(d.STYLE || '');
                            $('#buyerInput').val(d.BUYER || '');
                            $('#supplierInput').val(d.SUPPLIER || '');
                            $('#yarnTypeInput').val(d.YARN_TYPE || '');
                            $('#yarnCountInput').val(d.YARN_COUNT || '');
                            $('#fabricsTypeInput').val(d.FABRICS_TYPE || '');
                            $('#finishGsmInput').val(d.FINISH_GSM || '');
                            $('#finishDiaInput').val(d.FINISH_DIA || '');
                            $('#openTubeSelect').val(d.OPEN_TUBE || 'O');
                            $('#lotNoInput').val(d.LOT_NO || '');
                            $('#knitMaterialCodeInput').val(d.KNIT_MATERIAL_CODE || '');

                            var descSelect = $('#descSelect');
                            descSelect.empty();
                            if (resp.descriptions && resp.descriptions.length > 0) {
                                resp.descriptions.forEach(function(desc) {
                                    descSelect.append(new Option(desc, desc));
                                });
                            } else if (d.KNIT_M_DESCRIPTION) {
                                descSelect.append(new Option(d.KNIT_M_DESCRIPTION, d.KNIT_M_DESCRIPTION));
                            }
                        } else {
                            alert(resp.error || 'No data found for this BOOKING.');
                        }
                    },
                    error: function() {
                        alert('Error communicating with the server.');
                    }
                });
            });
        });
    </script>
</body>

</html>
