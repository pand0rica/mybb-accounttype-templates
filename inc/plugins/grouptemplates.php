<?php
/**
 * Group Templates -- switch any template depending on the user groups of the account
 * being displayed (post author, profile owner, member list row, viewer).
 *
 * Mechanism: in the matching hook the plugin copies the chosen template into the template
 * cache slot of the target ($templates->cache[target] = $templates->cache[chosen]); MyBB then
 * evaluates the target itself, in its original scope. No PHP in templates required.
 *
 * Since 1.1.0:
 * - Including templates: {$grouptemplates['name']} renders the template "name" (the variant
 *   chosen for the viewer if it is a switch target) on first use, in the global scope. Templates
 *   evaluated inside a function need "global $grouptemplates;" there.
 * - Empty templates: default and alternatives may be empty ("render nothing").
 * - Virtual targets (viewer context): the target does not have to exist as a template; it
 *   only exists through the switch and is included with {$grouptemplates['target']}.
 *
 * @package grouptemplates
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

if (defined('IN_ADMINCP')) {
    $plugins->add_hook('admin_style_menu', 'grouptemplates_admin_menu', 10);
    $plugins->add_hook('admin_style_action_handler', 'grouptemplates_admin_action_handler', 10);
    $plugins->add_hook('admin_style_permissions', 'grouptemplates_admin_permissions', 10);
} else {
    $plugins->add_hook('global_start', 'grouptemplates_handle_global_start', -10);
    // -5: after Theme Variants overrides (-10), before header plugins rendering at 0
    $plugins->add_hook('global_intermediate', 'grouptemplates_handle_viewer', -5);
    $plugins->add_hook('postbit', 'grouptemplates_handle_postbit', -10);
    $plugins->add_hook('postbit_prev', 'grouptemplates_handle_postbit', -10);
    $plugins->add_hook('postbit_pm', 'grouptemplates_handle_postbit', -10);
    $plugins->add_hook('postbit_announcement', 'grouptemplates_handle_postbit', -10);
    $plugins->add_hook('member_profile_start', 'grouptemplates_handle_profile', -10);
    $plugins->add_hook('memberlist_user', 'grouptemplates_handle_memberlist', -10);
}

// ---------------------------------------------------------------------------------------
// info()
// ---------------------------------------------------------------------------------------

function grouptemplates_info(): array
{
    return [
        'name'          => 'Group Templates',
        'description'   => 'Switch any template depending on the user groups of the displayed account (post author, profile, member list, viewer): one default template plus any number of group-based alternatives, without PHP in templates. Templates can include other templates with {&#36;grouptemplates[\'name\']}.',
        'website'       => 'https://github.com/pand0rica/mybb-accounttype-templates',
        'author'        => 'pand0rica',
        'authorsite'    => 'https://github.com/pand0rica',
        'version'       => '1.1.0',
        'guid'          => '',
        'codename'      => 'grouptemplates',
        'compatibility' => '18*',
    ];
}

// ---------------------------------------------------------------------------------------
// Lifecycle
// ---------------------------------------------------------------------------------------

/**
 * Legt die Tabellen für Schaltstellen und Alternativen an. Das Plugin hat keine Settings.
 */
function grouptemplates_install(): void
{
    global $db;

    $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "grouptemplates_switches` (
        `id`               INT(11) NOT NULL AUTO_INCREMENT,
        `sid`              INT(11) NOT NULL,
        `context`          VARCHAR(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `target`           VARCHAR(120) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `template_default` VARCHAR(120) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `slug_default`     VARCHAR(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'default',
        `created_copy`     TINYINT(1) NOT NULL DEFAULT 0,
        `dateline`         INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `sid_target` (`sid`, `target`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "grouptemplates_alternatives` (
        `id`         INT(11) NOT NULL AUTO_INCREMENT,
        `switch_id`  INT(11) NOT NULL,
        `template`   VARCHAR(120) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `slug`       VARCHAR(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `usergroups` TEXT COLLATE utf8_unicode_ci,
        `group_mode` VARCHAR(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'primary',
        `disporder`  INT(11) NOT NULL DEFAULT 0,
        `dateline`   INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `switch_id` (`switch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    grouptemplates_rebuild_cache();
}

function grouptemplates_is_installed(): bool
{
    global $db;
    return $db->table_exists('grouptemplates_switches');
}

/**
 * Entfernt alle Marker (und vom Plugin angelegte, unveränderte Template-Kopien), dann
 * Tabellen und Datacache.
 */
function grouptemplates_uninstall(): void
{
    global $db;

    if ($db->table_exists('grouptemplates_switches')) {
        $query = $db->simple_select('grouptemplates_switches', '*');
        while ($switch = $db->fetch_array($query)) {
            grouptemplates_template_remove_marker($switch);
        }
    }

    if ($db->table_exists('grouptemplates_alternatives')) {
        $db->drop_table('grouptemplates_alternatives');
    }
    if ($db->table_exists('grouptemplates_switches')) {
        $db->drop_table('grouptemplates_switches');
    }

    $db->delete_query('datacache', "title = 'grouptemplates'");
}

function grouptemplates_activate(): void
{
    grouptemplates_rebuild_cache();
}

/**
 * Bewusst ohne Template-Änderungen: Der Originalinhalt steht hinter dem Marker, ohne die
 * Laufzeit-Hooks greift er sofort wieder. Marker entfernt erst uninstall().
 */
function grouptemplates_deactivate(): void
{
}

// ---------------------------------------------------------------------------------------
// Fixed definitions
// ---------------------------------------------------------------------------------------

/**
 * Kontexte (fest im Code): welcher Account wird geprüft, in welchem Hook wird getauscht.
 */
function grouptemplates_get_contexts(): array
{
    return ['post', 'profile', 'memberlist', 'viewer'];
}

function grouptemplates_get_group_modes(): array
{
    return ['primary', 'display', 'any'];
}

/**
 * Templates, die build_postbit() (inc/functions_post.php, MyBB 1.8) VOR dem Hook-Block
 * switch($post_type) ... run_hooks("postbit...") auswertet. Ein Tausch im Postbit-Hook würde
 * bei ihnen erst im nächsten Beitrag wirken, daher sind sie im Kontext "post" gesperrt.
 * Ermittelt per grep 'templates->get("' in build_postbit() bis zur Hook-Zeile.
 */
function grouptemplates_get_post_blocklist(): array
{
    return [
        'announcement_edit', 'announcement_quickdelete',
        'postbit_author_guest', 'postbit_author_user', 'postbit_avatar', 'postbit_away',
        'postbit_delete_pm', 'postbit_edit', 'postbit_editedby', 'postbit_editedby_editreason',
        'postbit_editreason', 'postbit_email', 'postbit_find', 'postbit_forward_pm',
        'postbit_groupimage', 'postbit_icon', 'postbit_inlinecheck', 'postbit_iplogged_hiden',
        'postbit_iplogged_show', 'postbit_multiquote', 'postbit_offline', 'postbit_online',
        'postbit_pm', 'postbit_posturl', 'postbit_profilefield', 'postbit_profilefield_multiselect',
        'postbit_profilefield_multiselect_value', 'postbit_quickdelete', 'postbit_quickrestore',
        'postbit_quote', 'postbit_rep_button', 'postbit_replyall_pm', 'postbit_reply_pm',
        'postbit_report', 'postbit_reputation', 'postbit_signature', 'postbit_status',
        'postbit_userstar', 'postbit_warn', 'postbit_warninglevel', 'postbit_www',
    ];
}

// ---------------------------------------------------------------------------------------
// Group logic / public API
// ---------------------------------------------------------------------------------------

/**
 * Zerlegt eine gespeicherte Gruppenliste ("43,32" bzw. "-1" = alle).
 *
 * @return array{all: bool, groups: int[]}
 */
function grouptemplates_parse_groups(string $csv): array
{
    $all = false;
    $groups = [];
    foreach (explode(',', $csv) as $part) {
        $gid = (int)trim($part);
        if ($gid === -1) {
            $all = true;
        } elseif ($gid > 0) {
            $groups[$gid] = $gid;
        }
    }
    return ['all' => $all, 'groups' => array_values($groups)];
}

/**
 * Liefert die laut Modus geprüften Gruppen eines Accounts. uid 0 (Gast, gelöschter Autor)
 * zählt immer als Gruppe 1 -- in build_postbit() ist usergroup bei Gastbeiträgen leer.
 *
 * @return int[]
 */
function grouptemplates_get_user_groups(array $user, string $mode): array
{
    $uid = isset($user['uid']) ? (int)$user['uid'] : 0;
    if ($uid <= 0) {
        return [1];
    }

    $primary = isset($user['usergroup']) ? (int)$user['usergroup'] : 0;
    $display = isset($user['displaygroup']) ? (int)$user['displaygroup'] : 0;

    switch ($mode) {
        case 'display':
            $groups = [$display > 0 ? $display : $primary];
            break;
        case 'any':
            $groups = [$primary, $display];
            if (isset($user['additionalgroups']) && $user['additionalgroups'] !== '') {
                foreach (explode(',', (string)$user['additionalgroups']) as $gid) {
                    $groups[] = (int)$gid;
                }
            }
            break;
        default:
            $groups = [$primary];
    }

    $result = [];
    foreach ($groups as $gid) {
        if ($gid > 0) {
            $result[$gid] = $gid;
        }
    }
    return array_values($result);
}

/**
 * Passt die Alternative zum Account? Eine Übereinstimmung einer geprüften Gruppe genügt.
 * $memo cacht die Gruppen je Modus (pro Beitrag nur einmal berechnen).
 *
 * @param array $alternative ['groups' => int[], 'all' => bool, 'mode' => string]
 */
function grouptemplates_match(array $user, array $alternative, ?array &$memo = null): bool
{
    if (!empty($alternative['all'])) {
        return true;
    }
    if (empty($alternative['groups'])) {
        return false;
    }

    $mode = isset($alternative['mode']) ? (string)$alternative['mode'] : 'primary';
    if ($memo !== null && isset($memo[$mode])) {
        $groups = $memo[$mode];
    } else {
        $groups = grouptemplates_get_user_groups($user, $mode);
        if ($memo !== null) {
            $memo[$mode] = $groups;
        }
    }

    foreach ($groups as $gid) {
        if (in_array($gid, $alternative['groups'], true)) {
            return true;
        }
    }
    return false;
}

/**
 * Auswahlregel: erste passende Alternative (bereits sortiert), sonst Standard.
 *
 * @return array{template: string, slug: string}
 */
function grouptemplates_pick(array $switch, array $user, array &$memo): array
{
    if (!empty($switch['alternatives'])) {
        foreach ($switch['alternatives'] as $alternative) {
            if (grouptemplates_match($user, $alternative, $memo)) {
                return ['template' => $alternative['template'], 'slug' => $alternative['slug']];
            }
        }
    }
    return ['template' => $switch['default'], 'slug' => $switch['default_slug']];
}

/**
 * Öffentliche API: gewählte Variante einer Schaltstelle im aktiven Template-Set.
 *
 * @return array{template: string, slug: string}|null
 */
function grouptemplates_resolve(string $context, string $target, array $user): ?array
{
    $map = grouptemplates_get_active_map($context);
    if (!isset($map[$target])) {
        return null;
    }
    $memo = [];
    return grouptemplates_pick($map[$target], $user, $memo);
}

// ---------------------------------------------------------------------------------------
// Datacache
// ---------------------------------------------------------------------------------------

function grouptemplates_empty_cache(): array
{
    return ['switches' => [], 'templates' => []];
}

/**
 * Baut den Datacache aus beiden Tabellen neu (nach jeder Änderung).
 */
function grouptemplates_rebuild_cache(): void
{
    global $db, $cache;

    $data = grouptemplates_empty_cache();
    if (!$db->table_exists('grouptemplates_switches')) {
        $cache->update('grouptemplates', $data);
        return;
    }

    $alternatives = [];
    $query = $db->simple_select('grouptemplates_alternatives', '*', '', ['order_by' => 'disporder ASC, id', 'order_dir' => 'ASC']);
    while ($row = $db->fetch_array($query)) {
        $parsed = grouptemplates_parse_groups((string)$row['usergroups']);
        $alternatives[(int)$row['switch_id']][] = [
            'template' => (string)$row['template'],
            'slug'     => (string)$row['slug'],
            'groups'   => $parsed['groups'],
            'all'      => $parsed['all'],
            'mode'     => (string)$row['group_mode'],
        ];
    }

    $names = [];
    $query = $db->simple_select('grouptemplates_switches', '*');
    while ($row = $db->fetch_array($query)) {
        $id = (int)$row['id'];
        $alts = isset($alternatives[$id]) ? $alternatives[$id] : [];
        $data['switches'][(string)$row['context']][(int)$row['sid']][(string)$row['target']] = [
            'default'      => (string)$row['template_default'],
            'default_slug' => (string)$row['slug_default'],
            'alternatives' => $alts,
        ];
        $names[(string)$row['template_default']] = true;
        foreach ($alts as $alt) {
            $names[$alt['template']] = true;
        }
    }
    unset($names['']); // empty template = render nothing, nothing to load
    $data['templates'] = array_keys($names);

    $cache->update('grouptemplates', $data);
}

function grouptemplates_get_cache(): array
{
    global $cache;

    $data = $cache->read('grouptemplates');
    if (!is_array($data) || !isset($data['switches']) || !is_array($data['switches'])) {
        return grouptemplates_empty_cache();
    }
    if (!isset($data['templates']) || !is_array($data['templates'])) {
        $data['templates'] = [];
    }
    return $data;
}

/**
 * Schaltstellen eines Kontexts für das aktive Template-Set (-1 + Set des Themes, Set
 * gewinnt). Wird einmal pro Request für alle Kontexte berechnet; fehlende Templates werden
 * dabei einmalig gesammelt nachgeladen. Bereits gecachte Templates (z.B. von Theme Variants
 * ersetzt) werden nicht neu geladen, sonst ginge deren Override verloren.
 */
function grouptemplates_get_active_map(string $context, bool $reset = false): array
{
    static $maps = null;

    if ($reset) {
        $maps = null;
    }

    if ($maps === null) {
        global $theme, $templates, $db;

        $data = grouptemplates_get_cache();
        $sid = isset($theme['templateset']) ? (int)$theme['templateset'] : 0;

        $maps = [];
        foreach (grouptemplates_get_contexts() as $ctx) {
            $map = [];
            if (isset($data['switches'][$ctx][-1])) {
                $map = $data['switches'][$ctx][-1];
            }
            if ($sid > 0 && isset($data['switches'][$ctx][$sid])) {
                foreach ($data['switches'][$ctx][$sid] as $target => $switch) {
                    $map[$target] = $switch;
                }
            }
            $maps[$ctx] = $map;
        }

        $missing = [];
        foreach ($maps as $map) {
            foreach ($map as $switch) {
                $names = [$switch['default']];
                foreach ($switch['alternatives'] as $alt) {
                    $names[] = $alt['template'];
                }
                foreach ($names as $name) {
                    if ($name !== '' && !isset($templates->cache[$name])) {
                        $missing[$name] = $db->escape_string($name);
                    }
                }
            }
        }
        if (!empty($missing) && is_object($templates)) {
            $templates->cache(implode(',', $missing));
        }

        // Missing default -> leave the target untouched; missing alternative -> skip it
        // ('' = empty template, always available)
        foreach ($maps as $ctx => $map) {
            foreach ($map as $target => $switch) {
                if ($switch['default'] !== '' && !isset($templates->cache[$switch['default']])) {
                    unset($maps[$ctx][$target]);
                    continue;
                }
                $alts = [];
                foreach ($switch['alternatives'] as $alt) {
                    if ($alt['template'] === '' || isset($templates->cache[$alt['template']])) {
                        $alts[] = $alt;
                    }
                }
                $maps[$ctx][$target]['alternatives'] = $alts;
            }
        }
    }

    return isset($maps[$context]) ? $maps[$context] : [];
}

/**
 * Tauscht alle Schaltstellen eines Kontexts für einen Account und liefert target => slug.
 * Keine Query: nur Array-Zugriffe und Zuweisungen.
 */
function grouptemplates_apply(string $context, array $user): array
{
    global $templates;

    $map = grouptemplates_get_active_map($context);
    if (empty($map)) {
        return [];
    }

    $memo = [];
    $slugs = [];
    foreach ($map as $target => $switch) {
        $pick = grouptemplates_pick($switch, $user, $memo);
        $templates->cache[$target] = $pick['template'] === '' ? '' : $templates->cache[$pick['template']];
        $slugs[$target] = $pick['slug'];
    }
    return $slugs;
}

// ---------------------------------------------------------------------------------------
// Runtime hooks (frontend)
// ---------------------------------------------------------------------------------------

/**
 * Hängt alle Standard-/Alternativ-Templates an $templatelist an, damit global.php sie in
 * seiner einen Template-Query mitlädt.
 */
function grouptemplates_handle_global_start(): void
{
    global $templatelist, $grouptemplates;

    $grouptemplates = new GrouptemplatesInclude();

    $data = grouptemplates_get_cache();
    if (empty($data['templates'])) {
        return;
    }

    $names = implode(',', $data['templates']);
    if (isset($templatelist) && $templatelist !== '') {
        $templatelist .= ',' . $names;
    } else {
        $templatelist = $names;
    }
}

function grouptemplates_handle_viewer(): void
{
    global $mybb;

    $user = is_array($mybb->user) ? $mybb->user : [];
    $mybb->user['grouptemplate'] = grouptemplates_apply('viewer', $user);
}

function grouptemplates_handle_postbit(array &$post): void
{
    $post['grouptemplate'] = grouptemplates_apply('post', $post);
}

function grouptemplates_handle_profile(): void
{
    global $memprofile;

    if (is_array($memprofile)) {
        $memprofile['grouptemplate'] = grouptemplates_apply('profile', $memprofile);
    }
}

function grouptemplates_handle_memberlist(array &$user): void
{
    $user['grouptemplate'] = grouptemplates_apply('memberlist', $user);
}

/**
 * {$grouptemplates['name']}: renders the template "name" on first access and returns the
 * result (for a switch target the variant chosen for the viewer, since the swap happened in
 * global_intermediate). Unknown or invalid names yield ''. Included templates may include
 * further templates; a template including itself yields '' for the inner use.
 */
class GrouptemplatesInclude implements ArrayAccess
{
    /** @var array<string, string> */
    private $rendered = [];

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        global $templates, $db;

        $name = (string)$offset;
        if (isset($this->rendered[$name])) {
            return $this->rendered[$name];
        }
        $this->rendered[$name] = '';
        if (!grouptemplates_is_valid_name($name) || !is_object($templates)) {
            return '';
        }
        if (!isset($templates->cache[$name])) {
            $templates->cache($db->escape_string($name));
        }
        if (isset($templates->cache[$name]) && $templates->cache[$name] !== '') {
            $this->rendered[$name] = grouptemplates_render_global($name);
        }
        return $this->rendered[$name];
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->rendered[(string)$offset]);
    }
}

/**
 * Evaluates a template in the global variable scope, so included templates can use the same
 * variables as the template that includes them.
 */
function grouptemplates_render_global(string $grouptemplates_title): string
{
    global $templates;
    extract($GLOBALS, EXTR_SKIP | EXTR_REFS);
    $grouptemplates_out = '';
    eval('$grouptemplates_out = "' . $templates->get($grouptemplates_title) . '";');
    return $grouptemplates_out;
}

// ---------------------------------------------------------------------------------------
// Template / marker helpers (ACP, CLI, uninstall)
// ---------------------------------------------------------------------------------------

function grouptemplates_marker_regex(): string
{
    return '/<!-- grouptemplates:[^>]*-->\R?/';
}

function grouptemplates_strip_marker(string $template): string
{
    return (string)preg_replace(grouptemplates_marker_regex(), '', $template);
}

/**
 * Template-Zeile genau im Set $sid (oder null).
 */
function grouptemplates_template_find(string $title, int $sid): ?array
{
    global $db;

    $query = $db->simple_select(
        'templates',
        'tid, title, template, sid, version',
        "title = '" . $db->escape_string($title) . "' AND sid = " . (int)$sid,
        ['limit' => 1]
    );
    $row = $db->fetch_array($query);
    return $row ? $row : null;
}

/**
 * Template-Zeile, wie MyBB sie für das Set laden würde (höchste sid zuerst). Mit
 * $include_own = false nur die Eltern-Ebenen (-1/-2), z.B. um eine Kopie zu vergleichen.
 */
function grouptemplates_template_find_fallback(string $title, int $sid, bool $include_own = true): ?array
{
    global $db;

    $sids = [-2];
    if ($sid > 0) {
        $sids[] = -1;
    }
    if ($include_own) {
        $sids[] = $sid;
    }
    $sids = array_unique(array_map('intval', $sids));

    $query = $db->simple_select(
        'templates',
        'tid, title, template, sid, version',
        "title = '" . $db->escape_string($title) . "' AND sid IN (" . implode(',', $sids) . ")",
        ['order_by' => 'sid', 'order_dir' => 'DESC', 'limit' => 1]
    );
    $row = $db->fetch_array($query);
    return $row ? $row : null;
}

function grouptemplates_db_get_switch(int $id): ?array
{
    global $db;

    $row = $db->fetch_array($db->simple_select('grouptemplates_switches', '*', 'id = ' . (int)$id, ['limit' => 1]));
    return $row ? $row : null;
}

function grouptemplates_db_get_alternative(int $id): ?array
{
    global $db;

    $row = $db->fetch_array($db->simple_select('grouptemplates_alternatives', '*', 'id = ' . (int)$id, ['limit' => 1]));
    return $row ? $row : null;
}

/**
 * Alternativen einer Schaltstelle in Auswahlreihenfolge.
 */
function grouptemplates_db_get_alternatives(int $switch_id): array
{
    global $db;

    $rows = [];
    $query = $db->simple_select(
        'grouptemplates_alternatives',
        '*',
        'switch_id = ' . (int)$switch_id,
        ['order_by' => 'disporder ASC, id', 'order_dir' => 'ASC']
    );
    while ($row = $db->fetch_array($query)) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Marker-Kommentar mit der aktuellen Konfiguration der Schaltstelle.
 */
function grouptemplates_build_marker(int $switch_id): string
{
    $switch = grouptemplates_db_get_switch($switch_id);
    if (!$switch) {
        return '';
    }

    $names = [];
    foreach (grouptemplates_db_get_alternatives($switch_id) as $alt) {
        $names[] = (string)$alt['template'];
    }

    return '<!-- grouptemplates: default=' . $switch['template_default']
        . '; alternatives=' . implode(',', $names) . ' -->';
}

/**
 * Schreibt den Marker (neu) an den Anfang des Ziel-Templates im Set. Existiert das Ziel dort
 * nicht, wird eine Kopie aus -1/-2 angelegt und created_copy gesetzt (nicht im Set -1).
 */
function grouptemplates_template_write_marker(int $switch_id): void
{
    global $db;

    $switch = grouptemplates_db_get_switch($switch_id);
    if (!$switch) {
        return;
    }

    $sid = (int)$switch['sid'];
    $target = (string)$switch['target'];
    $marker = grouptemplates_build_marker($switch_id);

    $row = grouptemplates_template_find($target, $sid);
    if ($row) {
        $content = $marker . "\n" . grouptemplates_strip_marker((string)$row['template']);
        if ($content !== $row['template']) {
            $db->update_query('templates', [
                'template' => $db->escape_string($content),
                'dateline' => TIME_NOW,
            ], 'tid = ' . (int)$row['tid']);
        }
        return;
    }

    if ($sid === -1) {
        return; // no copies into the global set
    }

    $fallback = grouptemplates_template_find_fallback($target, $sid, false);
    if (!$fallback) {
        return;
    }

    $db->insert_query('templates', [
        'title'    => $db->escape_string($target),
        'template' => $db->escape_string($marker . "\n" . grouptemplates_strip_marker((string)$fallback['template'])),
        'sid'      => $sid,
        'version'  => $db->escape_string((string)$fallback['version']),
        'dateline' => TIME_NOW,
    ]);
    $db->update_query('grouptemplates_switches', ['created_copy' => 1], 'id = ' . (int)$switch_id);
}

/**
 * Entfernt den Marker aus dem Ziel-Template. Eine vom Plugin angelegte Kopie wird gelöscht,
 * wenn sie danach noch dem Eltern-Template entspricht.
 *
 * @return string marker_removed | copy_deleted | copy_kept | target_missing
 */
function grouptemplates_template_remove_marker(array $switch): string
{
    global $db;

    $sid = (int)$switch['sid'];
    $row = grouptemplates_template_find((string)$switch['target'], $sid);
    if (!$row) {
        return (string)($switch['context'] ?? '') === 'viewer' ? 'virtual' : 'target_missing';
    }

    $rest = grouptemplates_strip_marker((string)$row['template']);

    if (!empty($switch['created_copy']) && $sid !== -1) {
        $parent = grouptemplates_template_find_fallback((string)$switch['target'], $sid, false);
        if ($parent && $rest === grouptemplates_strip_marker((string)$parent['template'])) {
            $db->delete_query('templates', 'tid = ' . (int)$row['tid']);
            return 'copy_deleted';
        }
        $db->update_query('templates', [
            'template' => $db->escape_string($rest),
            'dateline' => TIME_NOW,
        ], 'tid = ' . (int)$row['tid']);
        return 'copy_kept';
    }

    if ($rest !== $row['template']) {
        $db->update_query('templates', [
            'template' => $db->escape_string($rest),
            'dateline' => TIME_NOW,
        ], 'tid = ' . (int)$row['tid']);
    }
    return 'marker_removed';
}

// ---------------------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------------------

function grouptemplates_is_valid_name(string $name): bool
{
    return (bool)preg_match('/^[A-Za-z0-9_\-]{1,120}$/', $name);
}

function grouptemplates_is_valid_slug(string $slug): bool
{
    return (bool)preg_match('/^[a-z0-9_\-]{1,50}$/', $slug);
}

function grouptemplates_templateset_exists(int $sid): bool
{
    global $db;

    if ($sid === -1) {
        return true;
    }
    if ($sid <= 0) {
        return false;
    }
    $query = $db->simple_select('templatesets', 'sid', 'sid = ' . (int)$sid, ['limit' => 1]);
    return (bool)$db->fetch_array($query);
}

/**
 * Ziele und Quell-Templates (Standard + Alternativen) aller anderen Schaltstellen, die im
 * selben Set zusammen wirken können: eigenes Set + -1 (bei Set -1: alle Sets).
 *
 * @return array{targets: array<string,bool>, sources: array<string,bool>}
 */
function grouptemplates_collect_scope(int $sid, int $exclude_switch_id): array
{
    global $db;

    $where = 'id <> ' . (int)$exclude_switch_id;
    if ($sid !== -1) {
        $where .= ' AND sid IN (-1, ' . (int)$sid . ')';
    }

    $targets = [];
    $sources = [];
    $ids = [];
    $query = $db->simple_select('grouptemplates_switches', 'id, target, template_default', $where);
    while ($row = $db->fetch_array($query)) {
        $targets[(string)$row['target']] = true;
        $sources[(string)$row['template_default']] = true;
        $ids[] = (int)$row['id'];
    }

    if (!empty($ids)) {
        $query = $db->simple_select('grouptemplates_alternatives', 'template', 'switch_id IN (' . implode(',', $ids) . ')');
        while ($row = $db->fetch_array($query)) {
            $sources[(string)$row['template']] = true;
        }
    }

    unset($sources['']);

    return ['targets' => $targets, 'sources' => $sources];
}

/**
 * Prüft Schaltstellen-Daten. Beim Bearbeiten ($id > 0) sind nur template_default und
 * slug_default änderbar; sid/context/target kommen dann aus der gespeicherten Zeile.
 *
 * @return string[] Sprach-Keys der Fehler
 */
function grouptemplates_validate_switch(array $data, int $id = 0): array
{
    global $db;

    $errors = [];
    $sid = isset($data['sid']) ? (int)$data['sid'] : 0;
    $context = isset($data['context']) ? (string)$data['context'] : '';
    $target = isset($data['target']) ? (string)$data['target'] : '';
    $default = isset($data['template_default']) ? (string)$data['template_default'] : '';
    $slug = isset($data['slug_default']) ? (string)$data['slug_default'] : '';

    if (!grouptemplates_templateset_exists($sid)) {
        $errors[] = 'grouptemplates_error_invalid_set';
    }
    if (!in_array($context, grouptemplates_get_contexts(), true)) {
        $errors[] = 'grouptemplates_error_invalid_context';
    }
    if (!grouptemplates_is_valid_name($target)) {
        $errors[] = 'grouptemplates_error_invalid_target';
    }
    // '' = empty default (render nothing when no alternative matches)
    if ($default !== '' && !grouptemplates_is_valid_name($default)) {
        $errors[] = 'grouptemplates_error_invalid_default';
    }
    if (!grouptemplates_is_valid_slug($slug)) {
        $errors[] = 'grouptemplates_error_invalid_slug';
    }
    if (!empty($errors)) {
        return $errors;
    }

    if ($target === $default) {
        $errors[] = 'grouptemplates_error_default_is_target';
    }

    $target_row = ($sid === -1)
        ? grouptemplates_template_find($target, -1)
        : grouptemplates_template_find_fallback($target, $sid);
    // viewer context: a missing target is a virtual target ({$grouptemplates['target']})
    if (!$target_row && $context !== 'viewer') {
        $errors[] = ($sid === -1) ? 'grouptemplates_error_target_not_global' : 'grouptemplates_error_target_missing';
    }
    if ($default !== '' && !grouptemplates_template_find_fallback($default, $sid)) {
        $errors[] = 'grouptemplates_error_default_missing';
    }

    $query = $db->simple_select(
        'grouptemplates_switches',
        'id',
        'sid = ' . $sid . " AND target = '" . $db->escape_string($target) . "' AND id <> " . (int)$id,
        ['limit' => 1]
    );
    if ($db->fetch_array($query)) {
        $errors[] = 'grouptemplates_error_duplicate';
    }

    if ($context === 'post' && in_array($target, grouptemplates_get_post_blocklist(), true)) {
        $errors[] = 'grouptemplates_error_blocklisted';
    }

    $scope = grouptemplates_collect_scope($sid, $id);
    if (isset($scope['sources'][$target]) || ($default !== '' && isset($scope['targets'][$default]))) {
        $errors[] = 'grouptemplates_error_chain';
    }

    return $errors;
}

/**
 * Prüft Alternativen-Daten gegen ihre Schaltstelle.
 *
 * @param array $data ['template','slug','usergroups' => int[] (-1 = all),'group_mode','disporder']
 * @return string[] Sprach-Keys der Fehler
 */
function grouptemplates_validate_alternative(array $data, array $switch, int $id = 0): array
{
    global $cache;

    $errors = [];
    $template = isset($data['template']) ? (string)$data['template'] : '';
    $slug = isset($data['slug']) ? (string)$data['slug'] : '';
    $groups = isset($data['usergroups']) && is_array($data['usergroups']) ? $data['usergroups'] : [];
    $mode = isset($data['group_mode']) ? (string)$data['group_mode'] : '';
    $disporder = isset($data['disporder']) ? (int)$data['disporder'] : 0;
    $sid = (int)$switch['sid'];

    if ($template === '') {
        // empty alternative: render nothing for these groups
    } elseif (!grouptemplates_is_valid_name($template)) {
        $errors[] = 'grouptemplates_error_invalid_alternative';
    } else {
        if ($template === (string)$switch['target']) {
            $errors[] = 'grouptemplates_error_alternative_is_target';
        }
        if (!grouptemplates_template_find_fallback($template, $sid)) {
            $errors[] = 'grouptemplates_error_alternative_missing';
        }
        $scope = grouptemplates_collect_scope($sid, (int)$switch['id']);
        if (isset($scope['targets'][$template])) {
            $errors[] = 'grouptemplates_error_chain';
        }
    }

    if (!grouptemplates_is_valid_slug($slug)) {
        $errors[] = 'grouptemplates_error_invalid_slug';
    }

    if (empty($groups)) {
        $errors[] = 'grouptemplates_error_no_groups';
    } else {
        $usergroups = $cache->read('usergroups');
        foreach ($groups as $gid) {
            $gid = (int)$gid;
            if ($gid !== -1 && (!is_array($usergroups) || !isset($usergroups[$gid]))) {
                $errors[] = 'grouptemplates_error_invalid_group';
                break;
            }
        }
    }

    if (!in_array($mode, grouptemplates_get_group_modes(), true)) {
        $errors[] = 'grouptemplates_error_invalid_mode';
    }
    if ($disporder < 0) {
        $errors[] = 'grouptemplates_error_invalid_disporder';
    }

    return $errors;
}

/**
 * Gruppenliste aus dem Formular normalisieren: "-1" gewinnt, sonst sortierte positive IDs.
 */
function grouptemplates_normalize_groups(array $groups): string
{
    $result = [];
    foreach ($groups as $gid) {
        $gid = (int)$gid;
        if ($gid === -1) {
            return '-1';
        }
        if ($gid > 0) {
            $result[$gid] = $gid;
        }
    }
    sort($result);
    return implode(',', $result);
}

// ---------------------------------------------------------------------------------------
// Write operations (used by the ACP module and CLI tests; callers validate first)
// ---------------------------------------------------------------------------------------

function grouptemplates_create_switch(array $data): int
{
    global $db;

    $id = (int)$db->insert_query('grouptemplates_switches', [
        'sid'              => (int)$data['sid'],
        'context'          => $db->escape_string((string)$data['context']),
        'target'           => $db->escape_string((string)$data['target']),
        'template_default' => $db->escape_string((string)$data['template_default']),
        'slug_default'     => $db->escape_string((string)$data['slug_default']),
        'created_copy'     => 0,
        'dateline'         => TIME_NOW,
    ]);

    grouptemplates_template_write_marker($id);
    grouptemplates_rebuild_cache();
    return $id;
}

function grouptemplates_update_switch(int $id, array $data): void
{
    global $db;

    $db->update_query('grouptemplates_switches', [
        'template_default' => $db->escape_string((string)$data['template_default']),
        'slug_default'     => $db->escape_string((string)$data['slug_default']),
    ], 'id = ' . (int)$id);

    grouptemplates_template_write_marker($id);
    grouptemplates_rebuild_cache();
}

/**
 * @return string Ergebnis von grouptemplates_template_remove_marker() oder 'not_found'
 */
function grouptemplates_delete_switch(int $id): string
{
    global $db;

    $switch = grouptemplates_db_get_switch($id);
    if (!$switch) {
        return 'not_found';
    }

    $result = grouptemplates_template_remove_marker($switch);
    $db->delete_query('grouptemplates_alternatives', 'switch_id = ' . (int)$id);
    $db->delete_query('grouptemplates_switches', 'id = ' . (int)$id);
    grouptemplates_rebuild_cache();
    return $result;
}

function grouptemplates_create_alternative(int $switch_id, array $data): int
{
    global $db;

    $id = (int)$db->insert_query('grouptemplates_alternatives', [
        'switch_id'  => (int)$switch_id,
        'template'   => $db->escape_string((string)$data['template']),
        'slug'       => $db->escape_string((string)$data['slug']),
        'usergroups' => $db->escape_string(grouptemplates_normalize_groups((array)$data['usergroups'])),
        'group_mode' => $db->escape_string((string)$data['group_mode']),
        'disporder'  => (int)$data['disporder'],
        'dateline'   => TIME_NOW,
    ]);

    grouptemplates_template_write_marker($switch_id);
    grouptemplates_rebuild_cache();
    return $id;
}

function grouptemplates_update_alternative(int $id, array $data): void
{
    global $db;

    $alt = grouptemplates_db_get_alternative($id);
    if (!$alt) {
        return;
    }

    $db->update_query('grouptemplates_alternatives', [
        'template'   => $db->escape_string((string)$data['template']),
        'slug'       => $db->escape_string((string)$data['slug']),
        'usergroups' => $db->escape_string(grouptemplates_normalize_groups((array)$data['usergroups'])),
        'group_mode' => $db->escape_string((string)$data['group_mode']),
        'disporder'  => (int)$data['disporder'],
    ], 'id = ' . (int)$id);

    grouptemplates_template_write_marker((int)$alt['switch_id']);
    grouptemplates_rebuild_cache();
}

function grouptemplates_delete_alternative(int $id): void
{
    global $db;

    $alt = grouptemplates_db_get_alternative($id);
    if (!$alt) {
        return;
    }

    $db->delete_query('grouptemplates_alternatives', 'id = ' . (int)$id);
    grouptemplates_template_write_marker((int)$alt['switch_id']);
    grouptemplates_rebuild_cache();
}

/**
 * @param array<int,int> $orders alternative id => disporder
 */
function grouptemplates_update_alternative_order(int $switch_id, array $orders): void
{
    global $db;

    foreach ($orders as $alt_id => $disporder) {
        $db->update_query(
            'grouptemplates_alternatives',
            ['disporder' => max(0, (int)$disporder)],
            'id = ' . (int)$alt_id . ' AND switch_id = ' . (int)$switch_id
        );
    }

    grouptemplates_template_write_marker($switch_id);
    grouptemplates_rebuild_cache();
}

// ---------------------------------------------------------------------------------------
// Status (ACP overview)
// ---------------------------------------------------------------------------------------

/**
 * @return array{marker: string, missing: string[], copy: bool}
 *         marker: current | outdated | missing | target_missing
 */
function grouptemplates_get_switch_status(array $switch): array
{
    $sid = (int)$switch['sid'];
    $status = ['marker' => 'missing', 'missing' => [], 'copy' => !empty($switch['created_copy'])];

    $row = grouptemplates_template_find((string)$switch['target'], $sid);
    if (!$row && (string)$switch['context'] === 'viewer'
        && !grouptemplates_template_find_fallback((string)$switch['target'], $sid)) {
        $status['marker'] = 'virtual';
    } elseif (!$row) {
        $status['marker'] = 'target_missing';
    } else {
        $marker = grouptemplates_build_marker((int)$switch['id']);
        if ($marker !== '' && strpos((string)$row['template'], $marker) === 0) {
            $status['marker'] = 'current';
        } elseif (preg_match(grouptemplates_marker_regex(), (string)$row['template'])) {
            $status['marker'] = 'outdated';
        }
    }

    $names = [(string)$switch['template_default']];
    foreach (grouptemplates_db_get_alternatives((int)$switch['id']) as $alt) {
        $names[] = (string)$alt['template'];
    }
    foreach ($names as $name) {
        if ($name !== '' && !grouptemplates_template_find_fallback($name, $sid)) {
            $status['missing'][] = $name;
        }
    }

    return $status;
}

// ---------------------------------------------------------------------------------------
// ACP integration
// ---------------------------------------------------------------------------------------

function grouptemplates_admin_menu(array &$sub_menu): void
{
    global $lang;
    $lang->load('grouptemplates');

    $sub_menu[] = [
        'id'    => 'grouptemplates',
        'title' => $lang->grouptemplates,
        'link'  => 'index.php?module=style-grouptemplates',
    ];
}

function grouptemplates_admin_action_handler(array &$actions): void
{
    $actions['grouptemplates'] = ['active' => 'grouptemplates', 'file' => 'grouptemplates.php'];
}

function grouptemplates_admin_permissions(array &$admin_permissions): void
{
    global $lang;
    $lang->load('grouptemplates');

    $admin_permissions['grouptemplates'] = $lang->grouptemplates_can_manage;
}
