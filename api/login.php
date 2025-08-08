<?php
include_once('../common.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS request for CORS preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get raw POST data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (is_null($data) || !isset($data['username']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input. Please provide username and password.']);
    exit;
}

$mb_id = trim($data['username']);
$mb_password = trim($data['password']);

if (!$mb_id || !$mb_password) {
    echo json_encode(['success' => false, 'message' => 'Username or password cannot be empty.']);
    exit;
}

$member = get_member($mb_id);

// Check if member exists and password is correct
if ($member['mb_id'] && check_password($mb_password, $member['mb_password'])) {
    // Login success
    set_session('ss_mb_id', $member['mb_id']);

    // Regenerate session id for security
    session_regenerate_id();
    $token = session_id();

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'token' => $token,
        'level' => $member['mb_level'],
        'member' => [
            'id' => $member['mb_id'],
            'name' => $member['mb_name'],
            'nick' => $member['mb_nick'],
            'email' => $member['mb_email'],
        ]
    ]);

} else {
    // Login failed
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
}
