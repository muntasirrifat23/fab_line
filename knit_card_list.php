<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    echo "<script>alert('You must be logged in'); window.location.href='login.php';</script>";
    exit();
}

$buyer_filter = isset($_GET['buyer']) ? trim($_GET['buyer']) : '';
$mc_no_filter = isset($_GET['mc_no']) ? trim($_GET['mc_no']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Build prepared statement query
$query = "SELECT * FROM knit_card WHERE 1=1";
$params = array();
$types = "";

if ($buyer_filter !== '') {
    $query .= " AND buyer LIKE ?";
    $params[] = "%" . $buyer_filter . "%";
    $types .= "s";
}

if ($mc_no_filter !== '') {
    $query .= " AND mc_no LIKE ?";
    $params[] = "%" . $mc_no_filter . "%";
    $types .= "s";
}

if ($start_date !== '') {
    $query .= " AND card_date >= ?";
    $params[] = $start_date;
    $types .= "s";
}

if ($end_date !== '') {
    $query .= " AND card_date <= ?";
    $params[] = $end_date;
    $types .= "s";
}

$query .= " ORDER BY id DESC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Summary stats calculation
$total_cards = 0;
$total_qty = 0.00;
$buyers_set = array();
$mc_set = array();

if ($result && $result->num_rows > 0) {
    $rows_array = array();
    while ($r = $result->fetch_assoc()) {
        $rows_array[] = $r;
        $total_cards++;
        $total_qty += floatval($r['quantity']);
        if (!empty($r['buyer'])) $buyers_set[$r['buyer']] = true;
        if (!empty($r['mc_no'])) $mc_set[$r['mc_no']] = true;
    }
} else {
    $rows_array = array();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knit Cards Directory | Purbani Fabrics</title>

    <!-- FontAwesome & Bootstrap -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/mycss.css">

    <style>
        /* Global & Font Reset */
        :root {
            --primary-teal: #00796b;
            --dark-teal: #004d40;
            --accent-green: #059669;
            --surface-bg: #f8fafc;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        /* Fix mycss.css icon border bug */
        i, i.fa-solid, i.fas, i.far, i.fab {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            display: inline-block !important;
            transform: none !important;
        }

        body {
            padding: 20px;
            background-color: var(--surface-bg);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #334155;
        }

        /* Header Banner */
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

        /* Stats Cards */
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
        .stat-icon.blue { background: #dbeafe; color: #2563eb; }
        .stat-icon.green { background: #dcfce7; color: #16a34a; }
        .stat-icon.purple { background: #f3e8ff; color: #9333ea; }

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

        /* Filter Panel */
        .filter-panel {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
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

        /* Main Data Table */
        .content-panel {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
        }

        .custom-table {
            width: 100%;
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
            border: none;
        }

        .custom-table thead th:first-child { border-top-left-radius: 10px; }
        .custom-table thead th:last-child { border-top-right-radius: 10px; }

        .custom-table tbody td {
            padding: 14px 16px;
            font-size: 13.5px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .custom-table tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Custom Badges */
        .badge-kc {
            background: #e0f2f1;
            color: #00796b;
            font-weight: 700;
            font-size: 12.5px;
            padding: 6px 12px;
            border-radius: 20px;
            display: inline-block;
        }

        .badge-mc {
            background: #f1f5f9;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }

        .badge-buyer {
            color: #0f172a;
            font-weight: 700;
        }
    </style>
</head>

<body>

    <div class="container-fluid" style="max-width: 1400px;">
        
        <!-- Header Banner -->
        <div class="top-banner d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-id-card"></i> Knit Cards Directory
                </h1>
                <p class="mb-0 text-white-50 small mt-1">Manage and view digital production cards generated from knitting programs</p>
            </div>
            <div class="d-flex gap-2">
                <a href="initialPage.php" class="btn btn-light nav-btn text-dark">
                    <i class="fa-solid fa-house me-1"></i> Initial Page
                </a>
                <a href="knitting_program_list.php" class="btn btn-warning nav-btn text-dark">
                    <i class="fa-solid fa-list-check me-1"></i> Knitting Programs
                </a>
            </div>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Overview Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon teal">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <div>
                        <p class="stat-label">Total Cards</p>
                        <p class="stat-value"><?php echo $total_cards; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fa-solid fa-weight-hanging"></i>
                    </div>
                    <div>
                        <p class="stat-label">Total Req. Quantity</p>
                        <p class="stat-value"><?php echo number_format($total_qty, 2); ?> <small style="font-size: 13px;">KG</small></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <p class="stat-label">Active Buyers</p>
                        <p class="stat-value"><?php echo count($buyers_set); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fa-solid fa-gears"></i>
                    </div>
                    <div>
                        <p class="stat-label">Active Machines</p>
                        <p class="stat-value"><?php echo count($mc_set); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="filter-panel">
            <h6 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                <i class="fa-solid fa-filter text-teal"></i> Filter Knit Cards
            </h6>
            <form method="GET" action="knit_card_list.php" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Buyer Name</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="buyer" class="form-control" placeholder="Search Buyer..." value="<?php echo htmlspecialchars($buyer_filter); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">Machine (M/C No)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-hard-drive"></i></span>
                        <input type="text" name="mc_no" class="form-control" placeholder="e.g. 87" value="<?php echo htmlspecialchars($mc_no_filter); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">From Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">To Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1 py-2 fw-semibold" style="border-radius:10px; background-color: var(--primary-teal); border-color: var(--primary-teal);">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Apply Filter
                    </button>
                    <a href="knit_card_list.php" class="btn btn-sm btn-outline-secondary py-2 px-3 fw-semibold" style="border-radius:10px;">
                        <i class="fa-solid fa-rotate-left me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Main Data Table Panel -->
        <div class="content-panel">
            <div class="table-responsive">
                <table class="table custom-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Card ID</th>
                            <th>Card Date</th>
                            <th>M/C No</th>
                            <th>Buyer</th>
                            <th>Order / Booking No</th>
                            <th>Style No</th>
                            <th>Fabric Type</th>
                            <th>Yarn Type</th>
                            <th>Colour</th>
                            <th>Req Qty (KG)</th>
                            <th>Prepared By</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows_array) > 0): ?>
                            <?php foreach ($rows_array as $row): ?>
                                <tr>
                                    <td>
                                        <span class="badge-kc">#KC-<?php echo $row['id']; ?></span>
                                    </td>
                                    <td>
                                        <i class="fa-regular fa-calendar me-1 text-muted"></i>
                                        <?php echo htmlspecialchars($row['card_date']); ?>
                                    </td>
                                    <td>
                                        <span class="badge-mc">M/C <?php echo htmlspecialchars($row['mc_no']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-buyer"><?php echo htmlspecialchars($row['buyer']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['order_no']); ?></td>
                                    <td><?php echo htmlspecialchars($row['style_no']); ?></td>
                                    <td><?php echo htmlspecialchars($row['fabrics_type']); ?></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($row['yarn_type']); ?></small></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['colour']); ?></span>
                                    </td>
                                    <td>
                                        <strong class="text-success"><?php echo number_format((float)$row['quantity'], 2); ?> KG</strong>
                                    </td>
                                    <td>
                                        <small class="text-secondary"><i class="fa-solid fa-user-circle me-1"></i><?php echo htmlspecialchars($row['prepared_by']); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <a href="knit_card_view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary px-3 py-1" style="border-radius:8px; font-size:12.5px; background-color:#2563eb; border-color:#2563eb;" title="View & Manage Production Log">
                                                <i class="fa-solid fa-eye me-1"></i> View Log
                                            </a>
                                            <a href="knit_card_print.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-sm btn-success px-3 py-1" style="border-radius:8px; font-size:12.5px; background-color: var(--accent-green); border-color: var(--accent-green);" title="Print Production Card">
                                                <i class="fa-solid fa-print me-1"></i> Print
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open fa-3x mb-3 text-secondary d-block"></i>
                                    <h6 class="fw-bold">No Knit Cards Found</h6>
                                    <p class="small mb-0">Try clearing your search filters or generate a new card from the Knitting Programs page.</p>
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

