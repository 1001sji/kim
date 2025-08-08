<?php
include_once('../common.php');
include_once('./api.lib.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$stx = isset($_GET['stx']) ? trim($_GET['stx']) : ''; // Search text

if (mb_strlen($stx) < 2) {
    echo json_encode(['error' => 'Search term must be at least 2 characters long.']);
    exit;
}

// List of boards to search across
$bo_tables = ['wallpaper_free', 'wallpaper_premium', 'wallpaper_video'];
$all_posts = [];
$total_count = 0;

$stx_safe = sql_real_escape_string($stx);

foreach ($bo_tables as $bo_table) {
    $board = get_board_db($bo_table, true);
    if (!isset($board['bo_table']) || !$board['bo_table']) {
        continue;
    }

    $search_sql = " SELECT * FROM {$g5['write_prefix']}{$bo_table} WHERE wr_is_comment = 0 AND (wr_subject LIKE '%{$stx_safe}%' OR wr_content LIKE '%{$stx_safe}%' OR wr_1 LIKE '%{$stx_safe}%' OR wr_2 LIKE '%{$stx_safe}%') ORDER BY wr_id DESC ";
    $result = sql_query($search_sql);

    while ($row = sql_fetch_array($result)) {
        $total_count++;
        $all_posts[] = [
            'id' => $row['wr_id'],
            'bo_table' => $bo_table, // Include board identifier
            'title' => get_text($row['wr_subject']),
            'content' => $row['wr_content'],
            'thumbnail' => get_api_thumbnail_url($row, $bo_table),
            'files' => get_api_file_list($row['wr_id'], $bo_table),
            'date' => $row['wr_datetime'],
            'author' => get_text($row['wr_name']),
            'views' => $row['wr_hit'],
        ];
    }
}

// Sort aggregated results by date descending
usort($all_posts, function($a, $b) {
    return strcmp($b['date'], $a['date']);
});

$response = [
    'posts' => $all_posts,
    'total' => $total_count,
];

echo json_encode($response);
?>
