<?php
include_once('../common.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

include_once('./api.lib.php');


$bo_table = isset($_GET['bo_table']) ? preg_replace('/[^a-zA-Z0-9_]/', '', trim($_GET['bo_table'])) : '';
if (empty($bo_table)) {
    echo json_encode(['error' => 'Board table is required.']);
    exit;
}

// check that bo_table is valid
$board = get_board_db($bo_table);
if (!isset($board['bo_table']) || !$board['bo_table']) {
    echo json_encode(['error' => 'Board not found.']);
    exit;
}


$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;

$offset = ($page - 1) * $limit;


// Get total count
$sql = "SELECT count(*) as cnt FROM {$g5['write_prefix']}{$bo_table} WHERE wr_is_comment = 0";
$row = sql_fetch($sql);
$total_count = isset($row['cnt']) ? (int)$row['cnt'] : 0;

// Get posts
$sql = "SELECT * FROM {$g5['write_prefix']}{$bo_table}
        WHERE wr_is_comment = 0
        ORDER BY wr_num ASC, wr_reply ASC
        LIMIT {$offset}, {$limit}";

$result = sql_query($sql);
$posts = [];

while($row = sql_fetch_array($result)) {
    $posts[] = [
        'id' => $row['wr_id'],
        'title' => get_text($row['wr_subject']),
        'content' => $row['wr_content'],
        'thumbnail' => get_api_thumbnail_url($row, $bo_table),
        'files' => get_api_file_list($row['wr_id'], $bo_table),
        'date' => $row['wr_datetime'],
        'author' => get_text($row['wr_name']),
        'views' => $row['wr_hit'],
    ];
}

echo json_encode(['posts' => $posts, 'total' => $total_count, 'page' => $page, 'limit' => $limit]);
