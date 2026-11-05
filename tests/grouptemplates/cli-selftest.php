<?php
/**
 * Group Templates -- CLI self test: selection logic (no DB) and, if the plugin is installed,
 * a DB round trip with throwaway SELFTEST templates (markers, copies, validation, runtime swap).
 * Usage (from the forum root): php tests/grouptemplates/cli-selftest.php [sid]   (default sid: first template set)
 */

if (PHP_SAPI !== 'cli') {
    die('CLI only.');
}

define('IN_MYBB', 1);
chdir(dirname(__DIR__, 2)); // script lives in <forum root>/tests/grouptemplates/
require_once './inc/init.php';
require_once MYBB_ROOT . 'inc/class_templates.php';
require_once MYBB_ROOT . 'inc/plugins/grouptemplates.php';

$pass = 0;
$fail = 0;

function check(string $name, $expected, $actual): void
{
    global $pass, $fail;
    if ($expected === $actual) {
        $pass++;
        echo "  ok   {$name}\n";
    } else {
        $fail++;
        echo "  FAIL {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
    }
}

// ---------------------------------------------------------------------------------------
echo "== Group resolution\n";

$u = ['uid' => 5, 'usergroup' => 2, 'displaygroup' => 4, 'additionalgroups' => '7,8'];
check('primary', [2], grouptemplates_get_user_groups($u, 'primary'));
check('display set', [4], grouptemplates_get_user_groups($u, 'display'));
check('display fallback', [2], grouptemplates_get_user_groups(['uid' => 5, 'usergroup' => 2, 'displaygroup' => 0], 'display'));
check('any', [2, 4, 7, 8], grouptemplates_get_user_groups($u, 'any'));
check('any without additional key', [2], grouptemplates_get_user_groups(['uid' => 5, 'usergroup' => 2], 'any'));
check('guest uid 0 -> group 1', [1], grouptemplates_get_user_groups(['uid' => 0, 'usergroup' => ''], 'primary'));
check('missing uid -> group 1', [1], grouptemplates_get_user_groups([], 'any'));
check('parse groups', ['all' => false, 'groups' => [43, 32]], grouptemplates_parse_groups('43, 32,0,,43'));
check('parse all', true, grouptemplates_parse_groups('-1')['all']);
check('normalize all wins', '-1', grouptemplates_normalize_groups([43, -1]));
check('normalize sorted unique', '1,32,43', grouptemplates_normalize_groups(['43', 32, 1, 43, 0]));

echo "== Matching\n";
$alt_primary = ['groups' => [2], 'all' => false, 'mode' => 'primary'];
$alt_add = ['groups' => [8], 'all' => false, 'mode' => 'primary'];
$alt_add_any = ['groups' => [8], 'all' => false, 'mode' => 'any'];
$alt_disp = ['groups' => [4], 'all' => false, 'mode' => 'display'];
$alt_all = ['groups' => [], 'all' => true, 'mode' => 'primary'];
$alt_guest = ['groups' => [1], 'all' => false, 'mode' => 'primary'];
check('primary hit', true, grouptemplates_match($u, $alt_primary));
check('additional ignored in primary', false, grouptemplates_match($u, $alt_add));
check('additional hit in any', true, grouptemplates_match($u, $alt_add_any));
check('display hit', true, grouptemplates_match($u, $alt_disp));
check('all groups', true, grouptemplates_match($u, $alt_all));
check('guest matches group 1', true, grouptemplates_match(['uid' => 0], $alt_guest));
check('member not group 1', false, grouptemplates_match($u, $alt_guest));
check('empty groups never match', false, grouptemplates_match($u, ['groups' => [], 'all' => false, 'mode' => 'any']));

echo "== Selection rule\n";
$switch = [
    'default' => 'tpl_default', 'default_slug' => 'default',
    'alternatives' => [
        ['template' => 'tpl_a', 'slug' => 'a', 'groups' => [7], 'all' => false, 'mode' => 'any'],
        ['template' => 'tpl_b', 'slug' => 'b', 'groups' => [2], 'all' => false, 'mode' => 'primary'],
    ],
];
$memo = [];
check('first matching wins', ['template' => 'tpl_a', 'slug' => 'a'], grouptemplates_pick($switch, $u, $memo));
$memo = [];
check('second when first fails', ['template' => 'tpl_b', 'slug' => 'b'], grouptemplates_pick($switch, ['uid' => 9, 'usergroup' => 2], $memo));
$memo = [];
check('default when none', ['template' => 'tpl_default', 'slug' => 'default'], grouptemplates_pick($switch, ['uid' => 9, 'usergroup' => 3], $memo));
$memo = [];
check('no alternatives -> default', ['template' => 'tpl_default', 'slug' => 'default'],
    grouptemplates_pick(['default' => 'tpl_default', 'default_slug' => 'default', 'alternatives' => []], $u, $memo));
$memo = [];
grouptemplates_pick($switch, $u, $memo);
check('memo filled per mode', true, isset($memo['any']));

// ---------------------------------------------------------------------------------------
if (!grouptemplates_is_installed()) {
    echo "\n(plugin not installed - DB round trip skipped)\n";
    echo "\n{$pass} passed, {$fail} failed\n";
    exit($fail === 0 ? 0 : 1);
}

echo "== DB round trip\n";

$sid = isset($argv[1]) ? (int)$argv[1] : 0;
if ($sid <= 0) {
    $sid = (int)$db->fetch_field($db->simple_select('templatesets', 'sid', '', ['order_by' => 'sid', 'limit' => 1]), 'sid');
}
echo "  (template set sid {$sid})\n";

$stamp = TIME_NOW;
$p = 'SELFTEST_gt_' . $stamp . '_';
$target = $p . 'target';
$def = $p . 'default';
$alt1 = $p . 'alt1';
$alt2 = $p . 'alt2';
$global_target = $p . 'globaltarget';
$original = "<div class=\"orig\">ORIGINAL {\$post['subject']}</div>\n<?php echo 'legacy'; ?>";

foreach ([
    [$target, $sid, $original],
    [$def, $sid, 'CONTENT_DEFAULT'],
    [$alt1, $sid, 'CONTENT_ALT1'],
    [$alt2, $sid, 'CONTENT_ALT2'],
    [$global_target, -1, 'GLOBAL_ORIGINAL'],
] as $fixture) {
    $db->insert_query('templates', [
        'title'    => $db->escape_string($fixture[0]),
        'template' => $db->escape_string($fixture[2]),
        'sid'      => (int)$fixture[1],
        'version'  => '1800',
        'dateline' => TIME_NOW,
    ]);
}

// Validation (switch)
$data = ['sid' => $sid, 'context' => 'post', 'target' => $target, 'template_default' => $def, 'slug_default' => 'default'];
check('valid switch', [], grouptemplates_validate_switch($data));
check('invalid set', true, in_array('grouptemplates_error_invalid_set', grouptemplates_validate_switch(['sid' => 999999] + $data), true));
check('invalid context', true, in_array('grouptemplates_error_invalid_context', grouptemplates_validate_switch(['context' => 'nope'] + $data), true));
check('invalid name', true, in_array('grouptemplates_error_invalid_target', grouptemplates_validate_switch(['target' => 'bad name!'] + $data), true));
check('invalid slug', true, in_array('grouptemplates_error_invalid_slug', grouptemplates_validate_switch(['slug_default' => 'Bad Slug'] + $data), true));
check('default = target', true, in_array('grouptemplates_error_default_is_target', grouptemplates_validate_switch(['template_default' => $target] + $data), true));
check('unknown target', true, in_array('grouptemplates_error_target_missing', grouptemplates_validate_switch(['target' => $p . 'nothere'] + $data), true));
check('unknown default', true, in_array('grouptemplates_error_default_missing', grouptemplates_validate_switch(['template_default' => $p . 'nothere'] + $data), true));
check('blocklist in post', true, in_array('grouptemplates_error_blocklisted', grouptemplates_validate_switch(['target' => 'postbit_author_user'] + $data), true));
check('blocklist not in profile', false, in_array('grouptemplates_error_blocklisted', grouptemplates_validate_switch(['target' => 'postbit_author_user', 'context' => 'profile'] + $data), true));
check('global target must be in -1', true, in_array('grouptemplates_error_target_not_global', grouptemplates_validate_switch(['sid' => -1] + $data), true));

// Create switch -> marker at top, original behind it
$switch_id = grouptemplates_create_switch($data);
check('switch created', true, $switch_id > 0);
$row = grouptemplates_template_find($target, $sid);
$marker = grouptemplates_build_marker($switch_id);
check('marker text', '<!-- grouptemplates: default=' . $def . '; alternatives= -->', $marker);
check('marker at top + original kept', $marker . "\n" . $original, $row['template']);
check('duplicate rejected', true, in_array('grouptemplates_error_duplicate', grouptemplates_validate_switch($data), true));
check('chain: default of other switch as target', true,
    in_array('grouptemplates_error_chain', grouptemplates_validate_switch(['target' => $def, 'template_default' => $alt2, 'context' => 'profile'] + $data), true));

// Alternatives
$sw = grouptemplates_db_get_switch($switch_id);
$alt_data = ['template' => $alt1, 'slug' => 'one', 'usergroups' => [2], 'group_mode' => 'primary', 'disporder' => 10];
check('valid alternative', [], grouptemplates_validate_alternative($alt_data, $sw));
check('alt = target', true, in_array('grouptemplates_error_alternative_is_target', grouptemplates_validate_alternative(['template' => $target] + $alt_data, $sw), true));
check('alt missing', true, in_array('grouptemplates_error_alternative_missing', grouptemplates_validate_alternative(['template' => $p . 'nothere'] + $alt_data, $sw), true));
check('no groups', true, in_array('grouptemplates_error_no_groups', grouptemplates_validate_alternative(['usergroups' => []] + $alt_data, $sw), true));
check('unknown group', true, in_array('grouptemplates_error_invalid_group', grouptemplates_validate_alternative(['usergroups' => [987654]] + $alt_data, $sw), true));
check('bad mode', true, in_array('grouptemplates_error_invalid_mode', grouptemplates_validate_alternative(['group_mode' => 'x'] + $alt_data, $sw), true));
check('all groups ok', [], grouptemplates_validate_alternative(['usergroups' => [-1]] + $alt_data, $sw));

$a1 = grouptemplates_create_alternative($switch_id, $alt_data);
$a2 = grouptemplates_create_alternative($switch_id, ['template' => $alt2, 'slug' => 'two', 'usergroups' => [2, 4], 'group_mode' => 'any', 'disporder' => 20]);
$row = grouptemplates_template_find($target, $sid);
$expected_marker = '<!-- grouptemplates: default=' . $def . '; alternatives=' . $alt1 . ',' . $alt2 . ' -->';
check('marker updated with alternatives', $expected_marker . "\n" . $original, $row['template']);
check('exactly one marker', 1, preg_match_all(grouptemplates_marker_regex(), $row['template']));
check('status current', 'current', grouptemplates_get_switch_status(grouptemplates_db_get_switch($switch_id))['marker']);

// Runtime swap (simulated request in this template set)
$theme = ['templateset' => $sid];
$templates = new templates;
$cached = grouptemplates_get_cache();
check('cache has switch', true, isset($cached['switches']['post'][$sid][$target]));
grouptemplates_get_active_map('post', true);

$slugs = grouptemplates_apply('post', ['uid' => 3, 'usergroup' => 2]);
check('runtime: group 2 -> alt1', 'CONTENT_ALT1', $templates->cache[$target]);
check('runtime: slug one', ['one'], array_values(array_intersect_key($slugs, [$target => 1])));
grouptemplates_apply('post', ['uid' => 3, 'usergroup' => 3, 'additionalgroups' => '4']);
check('runtime: additional group 4 in any -> alt2', 'CONTENT_ALT2', $templates->cache[$target]);
grouptemplates_apply('post', ['uid' => 3, 'usergroup' => 3]);
check('runtime: no match -> default', 'CONTENT_DEFAULT', $templates->cache[$target]);

// Pre-existing cache entry (e.g. Theme Variants override) must be kept
$templates = new templates;
$templates->cache[$alt1] = 'OVERRIDDEN_ALT1';
grouptemplates_get_active_map('post', true);
grouptemplates_apply('post', ['uid' => 3, 'usergroup' => 2]);
check('runtime: existing cache entry kept (override)', 'OVERRIDDEN_ALT1', $templates->cache[$target]);

// Reorder -> alt2 first
grouptemplates_update_alternative_order($switch_id, [$a1 => 30, $a2 => 5]);
$row = grouptemplates_template_find($target, $sid);
check('marker after reorder', '<!-- grouptemplates: default=' . $def . '; alternatives=' . $alt2 . ',' . $alt1 . ' -->' . "\n" . $original, $row['template']);
$templates = new templates;
grouptemplates_get_active_map('post', true);
grouptemplates_apply('post', ['uid' => 3, 'usergroup' => 2]);
check('runtime after reorder: alt2 wins', 'CONTENT_ALT2', $templates->cache[$target]);

// Chain check for alternatives: target of other switch not allowed as alternative
$gsw = grouptemplates_create_switch(['sid' => $sid, 'context' => 'profile', 'target' => $global_target, 'template_default' => $def, 'slug_default' => 'default']);
check('second switch in set (copy from -1)', true, $gsw > 0);
$copy = grouptemplates_template_find($global_target, $sid);
check('copy created in set', true, $copy !== null && strpos($copy['template'], 'GLOBAL_ORIGINAL') !== false);
check('created_copy flag', 1, (int)grouptemplates_db_get_switch($gsw)['created_copy']);
check('chain: other target as alternative', true,
    in_array('grouptemplates_error_chain', grouptemplates_validate_alternative(['template' => $global_target] + $alt_data, grouptemplates_db_get_switch($switch_id)), true));

// Delete copy switch -> copy removed; global original untouched
check('delete copy switch', 'copy_deleted', grouptemplates_delete_switch($gsw));
check('copy gone', null, grouptemplates_template_find($global_target, $sid));
check('global original untouched', 'GLOBAL_ORIGINAL', grouptemplates_template_find($global_target, -1)['template']);

// Changed copy is kept
$gsw = grouptemplates_create_switch(['sid' => $sid, 'context' => 'profile', 'target' => $global_target, 'template_default' => $def, 'slug_default' => 'default']);
$copy = grouptemplates_template_find($global_target, $sid);
$db->update_query('templates', ['template' => $db->escape_string($copy['template'] . ' EDITED')], 'tid = ' . (int)$copy['tid']);
check('delete changed copy', 'copy_kept', grouptemplates_delete_switch($gsw));
check('changed copy kept without marker', 'GLOBAL_ORIGINAL EDITED', grouptemplates_template_find($global_target, $sid)['template']);

// Delete alternative, then switch -> original restored byte-identical
grouptemplates_delete_alternative($a1);
check('marker after alt delete', '<!-- grouptemplates: default=' . $def . '; alternatives=' . $alt2 . ' -->' . "\n" . $original,
    grouptemplates_template_find($target, $sid)['template']);
check('delete switch', 'marker_removed', grouptemplates_delete_switch($switch_id));
check('original restored', $original, grouptemplates_template_find($target, $sid)['template']);
check('alternatives removed', 0, (int)$db->fetch_field($db->simple_select('grouptemplates_alternatives', 'COUNT(*) AS c', 'switch_id = ' . (int)$switch_id), 'c'));
check('cache without switch', false, isset(grouptemplates_get_cache()['switches']['post'][$sid][$target]));

// Cleanup
$db->delete_query('templates', "title LIKE 'SELFTEST\\_gt\\_%'");
$left = (int)$db->fetch_field($db->simple_select('templates', 'COUNT(*) AS c', "title LIKE 'SELFTEST\\_gt\\_%'"), 'c');
check('fixtures cleaned up', 0, $left);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
