<?php
/**
 * Group Templates -- CLI lifecycle helper (install / uninstall / status) without ACP login.
 * Usage (from the forum root): php tests/grouptemplates/cli-plugin.php install|uninstall|status
 */

if (PHP_SAPI !== 'cli') {
    die('CLI only.');
}

define('IN_MYBB', 1);
chdir(dirname(__DIR__, 2)); // script lives in <forum root>/tests/grouptemplates/
require_once './inc/init.php';

if (!defined('MYBB_ADMIN_DIR')) {
    $admin_dir = isset($config['admin_dir']) ? $config['admin_dir'] : 'admin';
    define('MYBB_ADMIN_DIR', MYBB_ROOT . $admin_dir . '/');
}
require_once MYBB_ROOT . 'inc/plugins/grouptemplates.php';

function grouptemplates_cli_set_active(bool $active): void
{
    global $cache;

    $plugins_cache = $cache->read('plugins');
    if (!is_array($plugins_cache)) {
        $plugins_cache = [];
    }
    if (!isset($plugins_cache['active']) || !is_array($plugins_cache['active'])) {
        $plugins_cache['active'] = [];
    }
    if ($active) {
        $plugins_cache['active']['grouptemplates'] = 'grouptemplates';
    } else {
        unset($plugins_cache['active']['grouptemplates']);
    }
    $cache->update('plugins', $plugins_cache);
}

function grouptemplates_cli_status(): void
{
    global $db, $cache;

    foreach (['grouptemplates_switches', 'grouptemplates_alternatives'] as $table) {
        if ($db->table_exists($table)) {
            $count = (int)$db->fetch_field($db->simple_select($table, 'COUNT(*) AS c'), 'c');
            echo "table {$table}: EXISTS ({$count} rows)\n";
        } else {
            echo "table {$table}: MISSING\n";
        }
    }

    $datacache = (int)$db->fetch_field($db->simple_select('datacache', 'COUNT(*) AS c', "title = 'grouptemplates'"), 'c');
    echo "datacache rows: {$datacache}\n";

    $markers = (int)$db->fetch_field($db->simple_select('templates', 'COUNT(*) AS c', "template LIKE '%<!-- grouptemplates:%'"), 'c');
    echo "templates with marker: {$markers}\n";

    $plugins_cache = $cache->read('plugins');
    $active = is_array($plugins_cache) && isset($plugins_cache['active']['grouptemplates']);
    echo 'plugin active: ' . ($active ? 'yes' : 'no') . "\n";
}

$cmd = isset($argv[1]) ? $argv[1] : 'status';

switch ($cmd) {
    case 'install':
        grouptemplates_install();
        grouptemplates_activate();
        grouptemplates_cli_set_active(true);
        echo "installed + activated\n";
        break;
    case 'uninstall':
        grouptemplates_deactivate();
        grouptemplates_uninstall();
        grouptemplates_cli_set_active(false);
        echo "deactivated + uninstalled\n";
        break;
    case 'deactivate':
        grouptemplates_deactivate();
        grouptemplates_cli_set_active(false);
        echo "deactivated\n";
        break;
    case 'activate':
        grouptemplates_activate();
        grouptemplates_cli_set_active(true);
        echo "activated\n";
        break;
}

grouptemplates_cli_status();
