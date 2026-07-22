<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    echo "<script>alert('You must be logged in'); window.location.href='login.php';</script>";
    exit();
}

$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;

if ($program_id <= 0) {
    header("Location: knitting_program_list.php?error=Invalid program ID");
    exit();
}

// Check if card already generated
$chk = $db->prepare("SELECT id FROM knit_card WHERE program_id = ?");
if ($chk) {
    $chk->bind_param("i", $program_id);
    $chk->execute();
    $res_chk = $chk->get_result();
    if ($res_chk && $res_chk->num_rows > 0) {
        $existing_card = $res_chk->fetch_assoc();
        header("Location: knit_card_view.php?id=" . $existing_card['id'] . "&msg=Knit Card already exists for this program");
        exit();
    }
    $chk->close();
}

// Fetch program data with fallback for both KPTID and id
$stmt = $db->prepare("SELECT * FROM knitting_program WHERE KPTID = ? OR id = ?");
if (!$stmt) {
    header("Location: knitting_program_list.php?error=Database query prepare failed");
    exit();
}
$stmt->bind_param("ii", $program_id, $program_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows == 0) {
    header("Location: knitting_program_list.php?error=Knitting Program not found");
    exit();
}

$prog = $res->fetch_assoc();
$stmt->close();

// Prepare insert into knit_card with fallback for column names
$prepared_by = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$authorised_by = '';
$card_date = !empty($prog['program_date']) ? $prog['program_date'] : (!empty($prog['CREATED_DATE']) ? date('Y-m-d', strtotime($prog['CREATED_DATE'])) : date('Y-m-d'));

$p_id          = $prog['KPTID'] ?? $prog['id'] ?? $program_id;
$p_mc_no       = $prog['MCNO'] ?? $prog['mc_no'] ?? '';
$p_mc_dia      = $prog['FINISH_DIA'] ?? $prog['mc_dia'] ?? '';
$p_mc_gauge    = $prog['FINISH_GSM'] ?? $prog['mc_gauge'] ?? '';
$p_finish_dia  = $prog['FINISH_DIA'] ?? $prog['finish_dia'] ?? '';
$p_grey_gsm    = $prog['FINISH_GSM'] ?? $prog['grey_gsm'] ?? '';
$p_finish_gsm  = $prog['FINISH_GSM'] ?? $prog['finish_gsm'] ?? '';
$p_sl_vdq      = floatval($prog['sl_vdq'] ?? 0);
$p_buyer       = $prog['BUYER'] ?? $prog['buyer'] ?? '';
$p_booking_no = $prog['BOOKING'] ?? $prog['booking_no'] ?? '';
$p_style_no   = $prog['STYLE'] ?? $prog['style_no'] ?? '';
$p_fabrics     = $prog['FABRICS_TYPE'] ?? $prog['fabrics_type'] ?? '';
$p_yarn        = $prog['YARN_TYPE'] ?? $prog['yarn_type'] ?? '';
$p_lot_no      = $prog['LOT_NO'] ?? $prog['lot_no'] ?? '';
$p_colour      = $prog['LOT_NO'] ?? $prog['colour'] ?? '';
$p_qty         = floatval($prog['QTY'] ?? $prog['req_qty'] ?? 0);

$ins = $db->prepare("INSERT INTO knit_card (
    program_id, card_date, mc_no, mc_dia, mc_gauge, finish_dia, grey_gsm, finish_gsm, sl_vdq, 
    buyer, booking_no, style_no, fabrics_type, yarn_type, lot_no, colour, quantity, prepared_by, authorised_by
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$ins) {
    header("Location: knitting_program_list.php?error=Failed+to+prepare+knit_card+insertion");
    exit();
}

$ins->bind_param(
    "isssssssdsssssssdss",
    $p_id,
    $card_date,
    $p_mc_no,
    $p_mc_dia,
    $p_mc_gauge,
    $p_finish_dia,
    $p_grey_gsm,
    $p_finish_gsm,
    $p_sl_vdq,
    $p_buyer,
    $p_booking_no,
    $p_style_no,
    $p_fabrics,
    $p_yarn,
    $p_lot_no,
    $p_colour,
    $p_qty,
    $prepared_by,
    $authorised_by
);

if ($ins->execute()) {
    $new_card_id = $ins->insert_id;
    $ins->close();

    // Set CARD_GENERATED = 1 on source program row
    $upd = $db->prepare("UPDATE knitting_program SET CARD_GENERATED = 1 WHERE KPTID = ? OR id = ?");
    if ($upd) {
        $upd->bind_param("ii", $program_id, $program_id);
        $upd->execute();
        $upd->close();
    }

    header("Location: knit_card_view.php?id=" . $new_card_id . "&msg=Knit Card generated successfully!");
    exit();
} else {
    header("Location: knitting_program_list.php?error=Failed to generate Knit Card: " . urlencode($db->error));
    exit();
}
?>
