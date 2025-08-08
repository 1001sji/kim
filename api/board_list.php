<?php
include_once('../common.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// This function is not standard in Gnuboard.
// We will define it here as a placeholder.
// It should return the URL of the first attached image as a thumbnail.
if (!function_exists('get_thumbnail_url')) {
    function get_thumbnail_url($write_row) {
        global $g5, $bo_table;
        // Check for thumbnail in board files
        $file = get_file($bo_table, $write_row['wr_id']);
        for ($i=0; $i<count($file); $i++) {
            if (isset($file[$i]['view']) && $file[$i]['view']) {
                return $file[$i]['path'].'/'.$file[$i]['file'];
            }
        }

        // If no file thumbnail, check for image in content
        $matches = get_editor_image($write_row['wr_content']);
        if (isset($matches[1]) && $matches[1]) {
             return $matches[1];
        }

        return ''; // No thumbnail found
    }
}


// This function is not standard in Gnuboard.
// We will define it here as a placeholder.
// It should return a list of files attached to the post.
if (!function_exists('get_file_list')) {
    function get_file_list($wr_id) {
        global $g5, $bo_table;
        $files = array();
        $board_file = get_file($bo_table, $wr_id);
        if (is_array($board_file)) {
            for ($i=0; $i<count($board_file); $i++) {
                $files[] = array(
                    'href' => $board_file[$i]['href'],
                    'source' => $board_file[$i]['source'],
                    'size' => $board_file[$i]['size'],
                    'download' => $board_file[$i]['download'],
                    'content' => $board_file[$i]['content'],
                    'view_url' => G5_BBS_URL.'/view_file.php?bo_table='.$bo_table.'&wr_id='.$wr_id.'&no='.$i,
                );
            }
        }
        return $files;
    }
}


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
        'thumbnail' => get_thumbnail_url($row),
        'files' => get_file_list($row['wr_id']),
        'date' => $row['wr_datetime'],
        'author' => get_text($row['wr_name']),
        'views' => $row['wr_hit'],
    ];
}

echo json_encode(['posts' => $posts, 'total' => $total_count, 'page' => $page, 'limit' => $limit]);
