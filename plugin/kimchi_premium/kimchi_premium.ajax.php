<?php
include_once('./_common.php'); // GnuBoard common file
include_once(G5_PLUGIN_PATH.'/kimchi_premium/kimchi_premium.lib.php');

// Ensure the library defines G5_PLUGIN_PATH if it's not already.
// Typically, _common.php or a similar core file would define G5_PATH,
// and G5_PLUGIN_PATH could be derived or explicitly set.
// For safety, if G5_PLUGIN_PATH is not defined, try to define it.
if (!defined('G5_PLUGIN_PATH')) {
    // Adjust the path according to your GnuBoard structure if necessary.
    // This assumes plugins are in G5_PATH/plugin
    if (defined('G5_PATH')) {
        define('G5_PLUGIN_PATH', G5_PATH.'/plugin');
    } else {
        // Fallback if G5_PATH is also not defined, though this is unlikely in a GB environment.
        // This path assumes kimchi_premium.ajax.php is in /plugin/kimchi_premium/
        // and _common.php is in the root.
        // Adjust ../../.. if your plugin depth or common file location differs.
        $current_dir = dirname(__FILE__); // Should be /path/to/gb5/plugin/kimchi_premium
        define('G5_PLUGIN_PATH', dirname($current_dir)); // Should be /path/to/gb5/plugin
    }
}


// Check if kimchi_premium.lib.php was included correctly and the function exists
if (!function_exists('get_kimchi_premium_data')) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Kimchi premium library not loaded or function missing.',
        'premium' => 0,
        'timestamp' => time(),
        'rows' => []
    ]);
    exit;
}

$data = get_kimchi_premium_data();

// Set content type to JSON and output the data
header('Content-Type: application/json');
echo json_encode($data);

exit;
?>
