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
$chk->bind_param("i", $program_id);
$chk->execute();
$res_chk = $chk->get_result();
if ($res_chk && $res_chk->num_rows > 0) {
    $existing_card = $res_chk->fetch_assoc();
    header("Location: knit_card_view.php?id=" . $existing_card['id'] . "&msg=Knit Card already exists for this program");
    exit();
}

// Fetch program data
$stmt = $db->prepare("SELECT * FROM knitting_program WHERE id = ?");
$stmt->bind_param("i", $program_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows == 0) {
    header("Location: knitting_program_list.php?error=Knitting Program not found");
    exit();
}

$prog = $res->fetch_assoc();

// Prepare insert into knit_card
$prepared_by = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$authorised_by = '';
$card_date = !empty($prog['program_date']) ? $prog['program_date'] : date('Y-m-d');

$ins = $db->prepare("INSERT INTO knit_card (
    program_id, card_date, mc_no, mc_dia, mc_gauge, finish_dia, grey_gsm, finish_gsm, sl_vdq, 
    buyer, order_no, style_no, fabrics_type, yarn_type, lot_no, colour, quantity, prepared_by, authorised_by
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$ins->bind_param(
    "isssssssdsssssssdss",
    $prog['id'],
    $card_date,
    $prog['mc_no'],
    $prog['mc_dia'],
    $prog['mc_gauge'],
    $prog['finish_dia'],
    $prog['grey_gsm'],
    $prog['finish_gsm'],
    $prog['sl_vdq'],
    $prog['buyer'],
    $prog['booking_no'],
    $prog['style_no'],
    $prog['fabrics_type'],
    $prog['yarn_type'],
    $prog['lot_no'],
    $prog['colour'],
    $prog['req_qty'],
    $prepared_by,
    $authorised_by
);

if ($ins->execute()) {
    $new_card_id = $ins->insert_id;

    // Set card_generated = 1 on source program row
    $upd = $db->prepare("UPDATE knitting_program SET card_generated = 1 WHERE id = ?");
    $upd->bind_param("i", $program_id);
    $upd->execute();

    header("Location: knit_card_view.php?id=" . $new_card_id . "&msg=Knit Card generated successfully!");
    exit();
} else {
    header("Location: knitting_program_list.php?error=Failed to generate Knit Card: " . urlencode($db->error));
    exit();
}
