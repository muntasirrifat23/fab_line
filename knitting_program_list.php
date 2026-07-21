<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    echo "<script>alert('You must be logged in'); window.location.href='login.php';</script>";
    exit();
}

$uname = $_SESSION['username'];

// Search filters
$buyer_filter = isset($_GET['buyer']) ? trim($_GET['buyer']) : '';
$mc_no_filter = isset($_GET['mc_no']) ? trim($_GET['mc_no']) : '';
$booking_no_filter = isset($_GET['booking_no']) ? trim($_GET['booking_no']) : '';

// Build prepared query
$query = "SELECT kp.*, kc.id AS card_id FROM knitting_program kp LEFT JOIN knit_card kc ON kp.id = kc.program_id WHERE 1=1";
$params = array();
$types = "";

if ($buyer_filter !== '') {
    $query .= " AND kp.buyer LIKE ?";
    $params[] = "%" . $buyer_filter . "%";
    $types .= "s";
}

if ($mc_no_filter !== '') {
    $query .= " AND kp.mc_no LIKE ?";
    $params[] = "%" . $mc_no_filter . "%";
    $types .= "s";
}

if ($booking_no_filter !== '') {
    $query .= " AND kp.booking_no LIKE ?";
    $params[] = "%" . $booking_no_filter . "%";
    $types .= "s";
}

$query .= " ORDER BY kp.id DESC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Summary stats calculation
$total_programs = 0;
$total_req_qty = 0.00;
$generated_count = 0;
$pending_count = 0;

$rows_array = array();
if ($result && $result->num_rows > 0) {
    while ($r = $result->fetch_assoc()) {
        $rows_array[] = $r;
        $total_programs++;
        $total_req_qty += floatval($r['req_qty']);
        if ($r['card_generated'] == 1) {
            $generated_count++;
        } else {
            $pending_count++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knitting Program Directory | Purbani Fabrics</title>

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
            padding: 20px;
            background-color: var(--surface-bg);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #334155;
        }

        /* Top Header Banner */
        .top-banner {
            background: linear-gradient(135deg, #004d40 0%, #00796b 50%, #00897b 100%);
            color: white;
            padding: 22px 28px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 121, 107, 0.2);
            margin-bottom: 24px;
        }

        .top-banner h1 {
            font-weight: 700;
            font-size: 1.75rem;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .nav-btn {
            border-radius: 10px;
            font-weight: 600;
            padding: 8px 16px;
            transition: all 0.2s ease;
        }

        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Summary Stat Cards */
        .stat-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 16px 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.teal { background: #e0f2f1; color: #00796b; }
        .stat-icon.green { background: #dcfce7; color: #16a34a; }
        .stat-icon.amber { background: #fef3c7; color: #d97706; }
        .stat-icon.blue { background: #dbeafe; color: #2563eb; }

        .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        /* Panels */
        .content-panel {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }

        .panel-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #e0f2f1;
            padding-bottom: 8px;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 9px 14px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: var(--primary-teal);
            box-shadow: 0 0 0 3px rgba(0, 121, 107, 0.15);
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
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            vertical-align: middle;
            border: none;
        }

        .custom-table tbody td {
            padding: 14px 16px;
            font-size: 13.5px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .custom-table tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Status Badges ONLY */
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-generated {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .badge-pending {
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .btn-teal {
            background-color: var(--primary-teal);
            border-color: var(--primary-teal);
            color: white;
            font-weight: 600;
            border-radius: 8px;
        }

        .btn-teal:hover {
            background-color: var(--dark-teal);
            color: white;
        }
    </style>
</head>

<body>

    <div class="container-fluid" style="max-width: 1400px;">

        <!-- Top Navigation Header Banner -->
        <div class="top-banner d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-list-check"></i> Knitting Program Directory
                </h1>
                <p class="mb-0 text-white-50 small mt-1">View, search, and manage production booking programs & generate floor knit cards</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="initialPage.php" class="btn btn-light nav-btn text-dark">
                    <i class="fa-solid fa-house me-1"></i> Initial Page
                </a>
                <a href="knit_card_list.php" class="btn btn-info nav-btn text-white" style="background-color:#2563eb; border-color:#2563eb;">
                    <i class="fa-solid fa-id-card me-1"></i> View Knit Cards List
                </a>
                <a href="knitting_program_form.php" class="btn btn-warning nav-btn text-dark">
                    <i class="fa-solid fa-plus me-1"></i> New Program
                </a>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4">
                <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Summary Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon teal">
                        <i class="fa-solid fa-rectangle-list"></i>
                    </div>
                    <div>
                        <p class="stat-label">Total Programs</p>
                        <p class="stat-value"><?php echo $total_programs; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <p class="stat-label">Cards Generated</p>
                        <p class="stat-value"><?php echo $generated_count; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon amber">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div>
                        <p class="stat-label">Cards Pending</p>
                        <p class="stat-value"><?php echo $pending_count; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fa-solid fa-weight-hanging"></i>
                    </div>
                    <div>
                        <p class="stat-label">Total Req. Quantity</p>
                        <p class="stat-value"><?php echo number_format($total_req_qty, 2); ?> <small style="font-size:13px;">KG</small></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="content-panel">
            <div class="panel-title">
                <i class="fa-solid fa-filter text-teal"></i> Filter Knitting Programs
            </div>
            <form method="GET" action="knitting_program_list.php" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Buyer Name</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="buyer" class="form-control" placeholder="Search Buyer..." value="<?php echo htmlspecialchars($buyer_filter); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Machine (M/C No)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-hard-drive"></i></span>
                        <input type="text" name="mc_no" class="form-control" placeholder="Search M/C No..." value="<?php echo htmlspecialchars($mc_no_filter); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Booking No</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-bookmark"></i></span>
                        <input type="text" name="booking_no" class="form-control" placeholder="Search Booking No..." value="<?php echo htmlspecialchars($booking_no_filter); ?>">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-teal flex-grow-1 py-2 fw-semibold">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Filter
                    </button>
                    <a href="knitting_program_list.php" class="btn btn-sm btn-outline-secondary py-2 px-3 fw-semibold" style="border-radius:8px;">
                        <i class="fa-solid fa-rotate-left me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Main Data Table Panel -->
        <div class="content-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="panel-title mb-0 border-0 p-0">
                    <i class="fa-solid fa-table-list text-teal"></i> Program Listings
                </div>
                <span class="text-muted small">Showing <strong><?php echo count($rows_array); ?></strong> records</span>
            </div>

            <div class="table-responsive-wrapper">
                <table class="table custom-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>M/C No</th>
                            <th>M/C Dia/Gauge</th>
                            <th>Buyer</th>
                            <th>Booking No</th>
                            <th>Style No</th>
                            <th>Fabric Type</th>
                            <th>Yarn Type</th>
                            <th>Colour</th>
                            <th>Req Qty (KG)</th>
                            <th>Card Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows_array) > 0): ?>
                            <?php foreach ($rows_array as $row): ?>
                                <tr>
                                    <td><strong>#<?php echo $row['id']; ?></strong></td>
                                    <td>
                                        <i class="fa-regular fa-calendar me-1 text-muted"></i>
                                        <?php echo htmlspecialchars($row['program_date']); ?>
                                    </td>
                                    <td><strong>M/C <?php echo htmlspecialchars($row['mc_no']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['mc_dia'] . ' / ' . $row['mc_gauge']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['buyer']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['booking_no']); ?></td>
                                    <td><?php echo htmlspecialchars($row['style_no']); ?></td>
                                    <td><?php echo htmlspecialchars($row['fabrics_type']); ?></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($row['yarn_type']); ?></small></td>
                                    <td><?php echo htmlspecialchars($row['colour']); ?></td>
                                    <td><strong class="text-success"><?php echo number_format((float)$row['req_qty'], 2); ?> KG</strong></td>
                                    <td>
                                        <?php if ($row['card_generated'] == 1): ?>
                                            <span class="badge-status badge-generated"><i class="fa-solid fa-circle-check"></i> Generated</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-pending"><i class="fa-solid fa-clock"></i> Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <?php if ($row['card_generated'] == 1): ?>
                                                <!-- Duplicate disabled grey button removed. Keep View & Edit only -->
                                                <?php if (!empty($row['card_id'])): ?>
                                                    <a href="knit_card_view.php?id=<?php echo $row['card_id']; ?>" class="btn btn-sm btn-primary px-3 py-1" style="border-radius:6px; font-size:12.5px; background-color:#2563eb; border-color:#2563eb;" title="View Generated Card Log">
                                                        <i class="fa-solid fa-eye me-1"></i> View
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="knit_card_generate.php?program_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-teal px-3 py-1" style="border-radius:6px; font-size:12.5px;" onclick="return confirm('Generate a new Knit Card for this program?');" title="Generate Knit Card">
                                                    <i class="fa-solid fa-file-circle-plus me-1"></i> Generate Card
                                                </a>
                                            <?php endif; ?>

                                            <a href="knitting_program_form.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning text-dark px-3 py-1 fw-semibold" style="border-radius:6px; font-size:12.5px; background-color:#d97706; border-color:#d97706; color:white !important;" title="Edit Program">
                                                <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open fa-3x mb-3 text-secondary d-block"></i>
                                    <h6 class="fw-bold">No Knitting Programs Found</h6>
                                    <p class="small mb-0">Try adjusting your filters or click "New Program" to add an entry.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>

</html>
