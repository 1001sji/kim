<?php
include_once('../common.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Gnuboard's download handler requires session
// We can check the token sent from the client to resume the session
if (isset($_GET['token'])) {
    session_id($_GET['token']);
    session_start();
    // After starting the session, we need to re-initialize the member object
    if ($_SESSION['ss_mb_id']) {
        $member = get_member($_SESSION['ss_mb_id']);
    }
}

$bo_table = isset($_GET['bo_table']) ? preg_replace('/[^a-zA-Z0-9_]/', '', trim($_GET['bo_table'])) : '';
$wr_id = isset($_GET['wr_id']) ? intval($_GET['wr_id']) : 0;
$no = isset($_GET['no']) ? intval($_GET['no']) : 0;

if (!$bo_table || !$wr_id || $no < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request: Missing required parameters.']);
    exit;
}

$board = get_board_db($bo_table);
$write = get_write($g5['write_prefix'] . $bo_table, $wr_id);

if (!$board['bo_table'] || !$write['wr_id']) {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found: The requested resource does not exist.']);
    exit;
}

// Permission Check
$user_level = isset($member['mb_level']) ? $member['mb_level'] : 1;

$premium_boards = ['wallpaper_premium', 'wallpaper_video'];
if (in_array($bo_table, $premium_boards) && $user_level < 5) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: You do not have permission to download this file.']);
    exit;
}

// Gnuboard's own download level check
if ($board['bo_download_level'] > $user_level) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Your level is not high enough to download from this board.']);
    exit;
}

// If all checks pass, redirect to the actual download handler
$download_url = G5_BBS_URL.'/download.php?bo_table='.$bo_table.'&wr_id='.$wr_id.'&no='.$no;

// To maintain the session for the download handler, we pass the session ID.
if (isset($member['mb_id'])) {
    $download_url .= '&token='.session_id();
}

header("Location: ".$download_url);
exit;
