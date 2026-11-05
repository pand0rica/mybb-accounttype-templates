<?php
/**
 * Group Templates -- ACP module (Templates & Style -> Group Templates).
 *
 * @package grouptemplates
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

$lang->load('grouptemplates');

define('GROUPTEMPLATES_ACP_URL', 'index.php?module=style-grouptemplates');

// -----------------------------------------------------------------------------------
// Helpers (module-local, all prefixed grouptemplates_acp_*)
// -----------------------------------------------------------------------------------

/**
 * Gemeinsame Tab-Navigation. Schaltstellen-bezogene Tabs erscheinen nur mit $switch_id.
 */
function grouptemplates_acp_nav_tabs(string $active, int $switch_id = 0, int $alt_id = 0): void
{
    global $page, $lang;

    $tabs = [
        'switches' => [
            'title'       => $lang->grouptemplates_tab_switches,
            'link'        => GROUPTEMPLATES_ACP_URL,
            'description' => $lang->grouptemplates_tab_desc_switches,
        ],
        'add_switch' => [
            'title'       => $lang->grouptemplates_tab_add_switch,
            'link'        => GROUPTEMPLATES_ACP_URL . '&amp;action=add_switch',
            'description' => $lang->grouptemplates_tab_desc_add_switch,
        ],
    ];

    if ($active === 'edit_switch' && $switch_id > 0) {
        $tabs['edit_switch'] = [
            'title'       => $lang->grouptemplates_tab_edit_switch,
            'link'        => GROUPTEMPLATES_ACP_URL . '&amp;action=edit_switch&amp;id=' . $switch_id,
            'description' => $lang->grouptemplates_tab_desc_edit_switch,
        ];
    }

    if ($switch_id > 0) {
        $tabs['alternatives'] = [
            'title'       => $lang->grouptemplates_tab_alternatives,
            'link'        => GROUPTEMPLATES_ACP_URL . '&amp;action=alternatives&amp;id=' . $switch_id,
            'description' => $lang->grouptemplates_tab_desc_alternatives,
        ];
        $tabs['add_alternative'] = [
            'title'       => $lang->grouptemplates_tab_add_alternative,
            'link'        => GROUPTEMPLATES_ACP_URL . '&amp;action=add_alternative&amp;id=' . $switch_id,
            'description' => $lang->grouptemplates_tab_desc_add_alternative,
        ];
    }

    if ($active === 'edit_alternative' && $alt_id > 0) {
        $tabs['edit_alternative'] = [
            'title'       => $lang->grouptemplates_tab_edit_alternative,
            'link'        => GROUPTEMPLATES_ACP_URL . '&amp;action=edit_alternative&amp;aid=' . $alt_id,
            'description' => $lang->grouptemplates_tab_desc_edit_alternative,
        ];
    }

    $page->output_nav_tabs($tabs, $active);
}

/**
 * Template-Sets für das Auswahlfeld: -1 (global) zuerst, dann alle Sets.
 */
function grouptemplates_acp_set_options(): array
{
    global $db, $lang;

    $options = [-1 => $lang->grouptemplates_set_global];
    $query = $db->simple_select('templatesets', 'sid, title', '', ['order_by' => 'title']);
    while ($row = $db->fetch_array($query)) {
        $options[(int)$row['sid']] = $row['title'] . ' (sid ' . (int)$row['sid'] . ')';
    }
    return $options;
}

function grouptemplates_acp_set_name(int $sid): string
{
    static $names = null;

    if ($names === null) {
        $names = grouptemplates_acp_set_options();
    }
    return isset($names[$sid]) ? $names[$sid] : ('sid ' . $sid);
}

function grouptemplates_acp_context_options(): array
{
    global $lang;

    $options = [];
    foreach (grouptemplates_get_contexts() as $context) {
        $options[$context] = $lang->{'grouptemplates_context_' . $context};
    }
    return $options;
}

function grouptemplates_acp_mode_options(): array
{
    global $lang;

    $options = [];
    foreach (grouptemplates_get_group_modes() as $mode) {
        $options[$mode] = $lang->{'grouptemplates_mode_' . $mode};
    }
    return $options;
}

/**
 * Lesbare Gruppennamen einer gespeicherten Gruppenliste.
 */
function grouptemplates_acp_group_names(string $csv): string
{
    global $cache, $lang;

    $parsed = grouptemplates_parse_groups($csv);
    if ($parsed['all']) {
        return htmlspecialchars_uni($lang->grouptemplates_all_groups);
    }

    $usergroups = $cache->read('usergroups');
    $names = [];
    foreach ($parsed['groups'] as $gid) {
        $title = (is_array($usergroups) && isset($usergroups[$gid])) ? $usergroups[$gid]['title'] : ('gid ' . $gid);
        $names[] = htmlspecialchars_uni($title) . ' (' . (int)$gid . ')';
    }
    return implode(', ', $names);
}

/**
 * Übersetzt Fehler-Keys aus den Validatoren.
 */
function grouptemplates_acp_errors(array $keys): array
{
    global $lang;

    $errors = [];
    foreach (array_unique($keys) as $key) {
        $errors[] = isset($lang->$key) ? $lang->$key : $key;
    }
    return $errors;
}

/**
 * Kleines Formular mit einem Button (GET = Navigation, POST = schreibende Aktion).
 */
function grouptemplates_acp_button(string $action, array $params, string $label): string
{
    $html = '<form action="index.php" method="get" style="display:inline;">';
    $html .= '<input type="hidden" name="module" value="style-grouptemplates" />';
    $html .= '<input type="hidden" name="action" value="' . htmlspecialchars_uni($action) . '" />';
    foreach ($params as $name => $value) {
        $html .= '<input type="hidden" name="' . htmlspecialchars_uni($name) . '" value="' . htmlspecialchars_uni((string)$value) . '" />';
    }
    $html .= '<input type="submit" class="button" value="' . htmlspecialchars_uni($label) . '" />';
    $html .= '</form> ';
    return $html;
}

function grouptemplates_acp_load_switch(int $id): array
{
    global $lang;

    $switch = grouptemplates_db_get_switch($id);
    if (!$switch) {
        flash_message($lang->grouptemplates_error_invalid_switch, 'error');
        admin_redirect(GROUPTEMPLATES_ACP_URL);
    }
    return $switch;
}

/**
 * Formular Schaltstelle hinzufügen / bearbeiten.
 */
function grouptemplates_acp_switch_form(array $values, array $errors, int $id): void
{
    global $page, $lang, $mybb;

    grouptemplates_acp_nav_tabs($id > 0 ? 'edit_switch' : 'add_switch', $id);

    if (!empty($errors)) {
        $page->output_inline_error(grouptemplates_acp_errors($errors));
    }

    $action = $id > 0 ? 'edit_switch&amp;id=' . $id : 'add_switch';
    $form = new Form(GROUPTEMPLATES_ACP_URL . '&amp;action=' . $action, 'post'); // adds my_post_key itself

    $container = new FormContainer($id > 0 ? $lang->grouptemplates_form_edit_switch : $lang->grouptemplates_form_add_switch);

    if ($id > 0) {
        $container->output_row($lang->grouptemplates_field_set, $lang->grouptemplates_field_fixed_desc,
            htmlspecialchars_uni(grouptemplates_acp_set_name((int)$values['sid'])));
        $container->output_row($lang->grouptemplates_field_context, '',
            htmlspecialchars_uni($lang->{'grouptemplates_context_' . $values['context']}));
        $container->output_row($lang->grouptemplates_field_target, '', htmlspecialchars_uni($values['target']));
    } else {
        $container->output_row(
            $lang->grouptemplates_field_set,
            $lang->grouptemplates_field_set_desc,
            $form->generate_select_box('sid', grouptemplates_acp_set_options(), (int)$values['sid'], ['id' => 'sid'])
        );

        $context_desc = $lang->grouptemplates_field_context_desc . '<ul>';
        foreach (grouptemplates_get_contexts() as $context) {
            $context_desc .= '<li><strong>' . htmlspecialchars_uni($lang->{'grouptemplates_context_' . $context}) . ':</strong> '
                . htmlspecialchars_uni($lang->{'grouptemplates_context_' . $context . '_desc'}) . '</li>';
        }
        $context_desc .= '</ul>';
        $container->output_row(
            $lang->grouptemplates_field_context,
            $context_desc,
            $form->generate_select_box('context', grouptemplates_acp_context_options(), $values['context'], ['id' => 'context'])
        );

        $container->output_row(
            $lang->grouptemplates_field_target,
            $lang->grouptemplates_field_target_desc,
            $form->generate_text_box('target', $values['target'], ['id' => 'target', 'maxlength' => 120])
        );
    }

    $container->output_row(
        $lang->grouptemplates_field_default,
        $lang->grouptemplates_field_default_desc,
        $form->generate_text_box('template_default', $values['template_default'], ['id' => 'template_default', 'maxlength' => 120])
    );
    $container->output_row(
        $lang->grouptemplates_field_slug_default,
        $lang->grouptemplates_field_slug_default_desc,
        $form->generate_text_box('slug_default', $values['slug_default'], ['id' => 'slug_default', 'maxlength' => 50])
    );

    $container->end();
    $form->output_submit_wrapper([$form->generate_submit_button($lang->grouptemplates_save)]);
    $form->end();
}

/**
 * Formular Alternative hinzufügen / bearbeiten.
 */
function grouptemplates_acp_alternative_form(array $switch, array $values, array $errors, int $alt_id): void
{
    global $page, $lang, $mybb;

    $switch_id = (int)$switch['id'];
    grouptemplates_acp_nav_tabs($alt_id > 0 ? 'edit_alternative' : 'add_alternative', $switch_id, $alt_id);

    if (!empty($errors)) {
        $page->output_inline_error(grouptemplates_acp_errors($errors));
    }

    $action = $alt_id > 0 ? 'edit_alternative&amp;aid=' . $alt_id : 'add_alternative&amp;id=' . $switch_id;
    $form = new Form(GROUPTEMPLATES_ACP_URL . '&amp;action=' . $action, 'post');

    $title =$lang->sprintf(
        $alt_id > 0 ? $lang->grouptemplates_form_edit_alternative : $lang->grouptemplates_form_add_alternative,
        htmlspecialchars_uni($switch['target'])
    );
    $container = new FormContainer($title);

    $container->output_row(
        $lang->grouptemplates_field_alternative,
        $lang->grouptemplates_field_alternative_desc,
        $form->generate_text_box('template', $values['template'], ['id' => 'template', 'maxlength' => 120])
    );
    $container->output_row(
        $lang->grouptemplates_field_slug,
        $lang->grouptemplates_field_slug_desc,
        $form->generate_text_box('slug', $values['slug'], ['id' => 'slug', 'maxlength' => 50])
    );

    $all = in_array(-1, $values['usergroups'], true);
    $group_html = $form->generate_check_box('all_groups', 1, $lang->grouptemplates_all_groups, ['checked' => $all, 'id' => 'all_groups'])
        . '<br /><br />'
        . $form->generate_group_select('usergroups[]', $all ? [] : $values['usergroups'], ['multiple' => true, 'size' => 10, 'id' => 'usergroups']);
    $container->output_row($lang->grouptemplates_field_groups, $lang->grouptemplates_field_groups_desc, $group_html);

    $container->output_row(
        $lang->grouptemplates_field_mode,
        $lang->grouptemplates_field_mode_desc,
        $form->generate_select_box('group_mode', grouptemplates_acp_mode_options(), $values['group_mode'], ['id' => 'group_mode'])
    );
    $container->output_row(
        $lang->grouptemplates_field_disporder,
        $lang->grouptemplates_field_disporder_desc,
        $form->generate_numeric_field('disporder', (int)$values['disporder'], ['id' => 'disporder', 'min' => 0])
    );

    $container->end();
    $form->output_submit_wrapper([$form->generate_submit_button($lang->grouptemplates_save)]);
    $form->end();
}

/**
 * Alternativen-Daten aus dem POST lesen.
 */
function grouptemplates_acp_alternative_input(): array
{
    global $mybb;

    $groups = [];
    if ($mybb->get_input('all_groups', MyBB::INPUT_INT) === 1) {
        $groups = [-1];
    } else {
        foreach ($mybb->get_input('usergroups', MyBB::INPUT_ARRAY) as $gid) {
            $gid = (int)$gid;
            if ($gid > 0) {
                $groups[] = $gid;
            }
        }
    }

    return [
        'template'   => trim($mybb->get_input('template')),
        'slug'       => trim($mybb->get_input('slug')),
        'usergroups' => $groups,
        'group_mode' => $mybb->get_input('group_mode'),
        'disporder'  => $mybb->get_input('disporder', MyBB::INPUT_INT),
    ];
}

/**
 * Nächste freie Sortierposition (Zehnerschritte).
 */
function grouptemplates_acp_next_disporder(int $switch_id): int
{
    $max = 0;
    foreach (grouptemplates_db_get_alternatives($switch_id) as $alt) {
        $max = max($max, (int)$alt['disporder']);
    }
    return $max + 10;
}

/**
 * Übersicht aller Schaltstellen.
 */
function grouptemplates_acp_show_switches(): void
{
    global $db, $lang;

    grouptemplates_acp_nav_tabs('switches');

    $switches = [];
    $query = $db->simple_select('grouptemplates_switches', '*', '', ['order_by' => 'sid ASC, target', 'order_dir' => 'ASC']);
    while ($row = $db->fetch_array($query)) {
        $switches[] = $row;
    }

    $table = new Table();
    $table->construct_header($lang->grouptemplates_col_set);
    $table->construct_header($lang->grouptemplates_col_context);
    $table->construct_header($lang->grouptemplates_col_target);
    $table->construct_header($lang->grouptemplates_col_default);
    $table->construct_header($lang->grouptemplates_col_alternatives);
    $table->construct_header($lang->grouptemplates_col_status);
    $table->construct_header($lang->grouptemplates_col_options, ['class' => 'align_center']);

    if (empty($switches)) {
        $table->construct_cell(htmlspecialchars_uni($lang->grouptemplates_no_switches), ['colspan' => 7]);
        $table->construct_row();
        $table->output($lang->grouptemplates_switches_title);
        return;
    }

    foreach ($switches as $switch) {
        $id = (int)$switch['id'];

        $alt_names = [];
        foreach (grouptemplates_db_get_alternatives($id) as $alt) {
            $alt_names[] = grouptemplates_acp_template_name((string)$alt['template']) . ' <small>(' . htmlspecialchars_uni($alt['slug']) . ')</small>';
        }
        $alternatives = empty($alt_names)
            ? '<em>' . htmlspecialchars_uni($lang->grouptemplates_no_alternatives_short) . '</em>'
            : count($alt_names) . ': ' . implode('<br />', $alt_names);

        $status = grouptemplates_get_switch_status($switch);
        $status_lines = [];
        switch ($status['marker']) {
            case 'current':
                $status_lines[] = '<span style="color:green;">' . htmlspecialchars_uni($lang->grouptemplates_status_current) . '</span>';
                break;
            case 'outdated':
                $status_lines[] = '<span class="error">' . htmlspecialchars_uni($lang->grouptemplates_status_outdated) . '</span>';
                break;
            case 'target_missing':
                $status_lines[] = '<span class="error">' . htmlspecialchars_uni($lang->grouptemplates_status_target_missing) . '</span>';
                break;
            case 'virtual':
                $status_lines[] = '<span style="color:green;">' . $lang->sprintf($lang->grouptemplates_status_virtual, htmlspecialchars_uni($switch['target'])) . '</span>';
                break;
            default:
                $status_lines[] = '<span class="error">' . htmlspecialchars_uni($lang->grouptemplates_status_missing) . '</span>';
        }
        foreach ($status['missing'] as $name) {
            $status_lines[] = '<span class="error">' . $lang->sprintf($lang->grouptemplates_status_template_missing, htmlspecialchars_uni($name)) . '</span>';
        }
        if ($status['copy']) {
            $status_lines[] = '<small>' . htmlspecialchars_uni($lang->grouptemplates_status_copy) . '</small>';
        }

        $options = grouptemplates_acp_button('alternatives', ['id' => $id], $lang->grouptemplates_manage_alternatives)
            . grouptemplates_acp_button('edit_switch', ['id' => $id], $lang->grouptemplates_edit)
            . grouptemplates_acp_button('delete_switch', ['id' => $id], $lang->grouptemplates_delete);

        $table->construct_cell(htmlspecialchars_uni(grouptemplates_acp_set_name((int)$switch['sid'])));
        $table->construct_cell(htmlspecialchars_uni($lang->{'grouptemplates_context_' . $switch['context']}));
        $table->construct_cell('<strong>' . htmlspecialchars_uni($switch['target']) . '</strong>');
        $table->construct_cell(grouptemplates_acp_template_name((string)$switch['template_default']) . ' <small>(' . htmlspecialchars_uni($switch['slug_default']) . ')</small>');
        $table->construct_cell($alternatives);
        $table->construct_cell(implode('<br />', $status_lines));
        $table->construct_cell($options, ['class' => 'align_center']);
        $table->construct_row();
    }

    $table->output($lang->grouptemplates_switches_title);
}

/**
 * Alternativen einer Schaltstelle inkl. Reihenfolge-Formular.
 */
function grouptemplates_acp_show_alternatives(array $switch): void
{
    global $lang, $mybb;

    $switch_id = (int)$switch['id'];
    grouptemplates_acp_nav_tabs('alternatives', $switch_id);

    echo '<div class="notice">'
        . $lang->sprintf(
            $lang->grouptemplates_alternatives_head,
            htmlspecialchars_uni(grouptemplates_acp_set_name((int)$switch['sid'])),
            htmlspecialchars_uni($lang->{'grouptemplates_context_' . $switch['context']}),
            htmlspecialchars_uni($switch['target']),
            htmlspecialchars_uni($switch['template_default']),
            htmlspecialchars_uni($switch['slug_default'])
        )
        . '<br />' . htmlspecialchars_uni($lang->grouptemplates_rule_hint)
        . '</div><br />';

    $alternatives = grouptemplates_db_get_alternatives($switch_id);

    $form = new Form(GROUPTEMPLATES_ACP_URL . '&amp;action=save_order&amp;id=' . $switch_id, 'post');

    $table = new Table();
    $table->construct_header($lang->grouptemplates_col_disporder, ['width' => '8%']);
    $table->construct_header($lang->grouptemplates_col_alternative);
    $table->construct_header($lang->grouptemplates_col_slug);
    $table->construct_header($lang->grouptemplates_col_groups);
    $table->construct_header($lang->grouptemplates_col_mode);
    $table->construct_header($lang->grouptemplates_col_options, ['class' => 'align_center']);

    if (empty($alternatives)) {
        $table->construct_cell(htmlspecialchars_uni($lang->grouptemplates_no_alternatives), ['colspan' => 6]);
        $table->construct_row();
        $table->output($lang->grouptemplates_alternatives_title);
        $form->end();
        return;
    }

    foreach ($alternatives as $alt) {
        $aid = (int)$alt['id'];
        $options = grouptemplates_acp_button('edit_alternative', ['aid' => $aid], $lang->grouptemplates_edit)
            . grouptemplates_acp_button('delete_alternative', ['aid' => $aid], $lang->grouptemplates_delete);

        $mode = in_array($alt['group_mode'], grouptemplates_get_group_modes(), true) ? $alt['group_mode'] : 'primary';

        $table->construct_cell($form->generate_numeric_field('disporder[' . $aid . ']', (int)$alt['disporder'], ['min' => 0, 'style' => 'width:60px;']));
        $table->construct_cell('<strong>' . grouptemplates_acp_template_name((string)$alt['template']) . '</strong>');
        $table->construct_cell(htmlspecialchars_uni($alt['slug']));
        $table->construct_cell(grouptemplates_acp_group_names((string)$alt['usergroups']));
        $table->construct_cell(htmlspecialchars_uni($lang->{'grouptemplates_mode_' . $mode}));
        $table->construct_cell($options, ['class' => 'align_center']);
        $table->construct_row();
    }

    $table->output($lang->grouptemplates_alternatives_title);
    $form->output_submit_wrapper([$form->generate_submit_button($lang->grouptemplates_save_order)]);
    $form->end();
}

// -----------------------------------------------------------------------------------
// Dispatch
// -----------------------------------------------------------------------------------

$page->add_breadcrumb_item($lang->grouptemplates, GROUPTEMPLATES_ACP_URL);

$action = $mybb->get_input('action');
$is_post = ($mybb->request_method === 'post');



/**
 * Template name for the lists; an empty name means "render nothing".
 */
function grouptemplates_acp_template_name(string $name): string
{
    global $lang;

    return $name === '' ? '<em>' . htmlspecialchars_uni($lang->grouptemplates_empty_template) . '</em>' : htmlspecialchars_uni($name);
}
switch ($action) {
    case 'add_switch':
        $values = [
            'sid'              => $mybb->get_input('sid', MyBB::INPUT_INT),
            'context'          => $mybb->get_input('context'),
            'target'           => trim($mybb->get_input('target')),
            'template_default' => trim($mybb->get_input('template_default')),
            'slug_default'     => $is_post ? trim($mybb->get_input('slug_default')) : 'default',
        ];
        if (!in_array($values['context'], grouptemplates_get_contexts(), true)) {
            $values['context'] = 'post';
        }

        $errors = [];
        if ($is_post) {
            $errors = grouptemplates_validate_switch($values);
            if (empty($errors)) {
                $id = grouptemplates_create_switch($values);
                log_admin_action($id, $values['target']);
                flash_message($lang->grouptemplates_switch_saved, 'success');
                admin_redirect(GROUPTEMPLATES_ACP_URL . '&action=alternatives&id=' . $id);
            }
        }

        $page->add_breadcrumb_item($lang->grouptemplates_tab_add_switch);
        $page->output_header($lang->grouptemplates_acp_title);
        grouptemplates_acp_switch_form($values, $errors, 0);
        $page->output_footer();
        break;

    case 'edit_switch':
        $id = $mybb->get_input('id', MyBB::INPUT_INT);
        $switch = grouptemplates_acp_load_switch($id);

        $values = [
            'sid'              => (int)$switch['sid'],
            'context'          => (string)$switch['context'],
            'target'           => (string)$switch['target'],
            'template_default' => $is_post ? trim($mybb->get_input('template_default')) : (string)$switch['template_default'],
            'slug_default'     => $is_post ? trim($mybb->get_input('slug_default')) : (string)$switch['slug_default'],
        ];

        $errors = [];
        if ($is_post) {
            $errors = grouptemplates_validate_switch($values, $id);
            if (empty($errors)) {
                grouptemplates_update_switch($id, $values);
                log_admin_action($id, $values['target']);
                flash_message($lang->grouptemplates_switch_saved, 'success');
                admin_redirect(GROUPTEMPLATES_ACP_URL);
            }
        }

        $page->add_breadcrumb_item($lang->grouptemplates_tab_edit_switch);
        $page->output_header($lang->grouptemplates_acp_title);
        grouptemplates_acp_switch_form($values, $errors, $id);
        $page->output_footer();
        break;

    case 'delete_switch':
        $id = $mybb->get_input('id', MyBB::INPUT_INT);
        $switch = grouptemplates_acp_load_switch($id);

        if ($is_post) {
            if ($mybb->get_input('no')) {
                admin_redirect(GROUPTEMPLATES_ACP_URL);
            }
            $result = grouptemplates_delete_switch($id);
            log_admin_action($id, $switch['target']);
            $message_key = 'grouptemplates_switch_deleted_' . $result;
            flash_message(isset($lang->$message_key) ? $lang->$message_key : $lang->grouptemplates_switch_deleted_marker_removed, $result === 'copy_kept' || $result === 'target_missing' ? 'error' : 'success');
            admin_redirect(GROUPTEMPLATES_ACP_URL);
        }

        $page->output_confirm_action(
            GROUPTEMPLATES_ACP_URL . '&amp;action=delete_switch&amp;id=' . $id,
            $lang->sprintf($lang->grouptemplates_confirm_delete_switch, htmlspecialchars_uni($switch['target']), htmlspecialchars_uni(grouptemplates_acp_set_name((int)$switch['sid']))),
            $lang->grouptemplates_acp_title
        );
        break;

    case 'alternatives':
        $switch = grouptemplates_acp_load_switch($mybb->get_input('id', MyBB::INPUT_INT));

        $page->add_breadcrumb_item($switch['target']);
        $page->output_header($lang->grouptemplates_acp_title);
        grouptemplates_acp_show_alternatives($switch);
        $page->output_footer();
        break;

    case 'save_order':
        $id = $mybb->get_input('id', MyBB::INPUT_INT);
        $switch = grouptemplates_acp_load_switch($id);
        if (!$is_post) {
            admin_redirect(GROUPTEMPLATES_ACP_URL . '&action=alternatives&id=' . $id);
        }

        $orders = [];
        foreach ($mybb->get_input('disporder', MyBB::INPUT_ARRAY) as $aid => $disporder) {
            $orders[(int)$aid] = (int)$disporder;
        }
        grouptemplates_update_alternative_order($id, $orders);
        log_admin_action($id, $switch['target']);
        flash_message($lang->grouptemplates_order_saved, 'success');
        admin_redirect(GROUPTEMPLATES_ACP_URL . '&action=alternatives&id=' . $id);
        break;

    case 'add_alternative':
        $switch = grouptemplates_acp_load_switch($mybb->get_input('id', MyBB::INPUT_INT));
        $switch_id = (int)$switch['id'];

        if ($is_post) {
            $values = grouptemplates_acp_alternative_input();
        } else {
            $values = [
                'template'   => '',
                'slug'       => '',
                'usergroups' => [],
                'group_mode' => 'primary',
                'disporder'  => grouptemplates_acp_next_disporder($switch_id),
            ];
        }

        $errors = [];
        if ($is_post) {
            $errors = grouptemplates_validate_alternative($values, $switch);
            if (empty($errors)) {
                $aid = grouptemplates_create_alternative($switch_id, $values);
                log_admin_action($aid, $switch['target'], $values['template']);
                flash_message($lang->grouptemplates_alternative_saved, 'success');
                admin_redirect(GROUPTEMPLATES_ACP_URL . '&action=alternatives&id=' . $switch_id);
            }
        }

        $page->add_breadcrumb_item($switch['target'], GROUPTEMPLATES_ACP_URL . '&amp;action=alternatives&amp;id=' . $switch_id);
        $page->add_breadcrumb_item($lang->grouptemplates_tab_add_alternative);
        $page->output_header($lang->grouptemplates_acp_title);
        grouptemplates_acp_alternative_form($switch, $values, $errors, 0);
        $page->output_footer();
        break;

    case 'edit_alternative':
        $aid = $mybb->get_input('aid', MyBB::INPUT_INT);
        $alt = grouptemplates_db_get_alternative($aid);
        if (!$alt) {
            flash_message($lang->grouptemplates_error_invalid_alternative_id, 'error');
            admin_redirect(GROUPTEMPLATES_ACP_URL);
        }
        $switch = grouptemplates_acp_load_switch((int)$alt['switch_id']);
        $switch_id = (int)$switch['id'];

        if ($is_post) {
            $values = grouptemplates_acp_alternative_input();
        } else {
            $parsed = grouptemplates_parse_groups((string)$alt['usergroups']);
            $values = [
                'template'   => (string)$alt['template'],
                'slug'       => (string)$alt['slug'],
                'usergroups' => $parsed['all'] ? [-1] : $parsed['groups'],
                'group_mode' => (string)$alt['group_mode'],
                'disporder'  => (int)$alt['disporder'],
            ];
        }

        $errors = [];
        if ($is_post) {
            $errors = grouptemplates_validate_alternative($values, $switch, $aid);
            if (empty($errors)) {
                grouptemplates_update_alternative($aid, $values);
                log_admin_action($aid, $switch['target'], $values['template']);
                flash_message($lang->grouptemplates_alternative_saved, 'success');
                admin_redirect(GROUPTEMPLATES_ACP_URL . '&action=alternatives&id=' . $switch_id);
            }
        }

        $page->add_breadcrumb_item($switch['target'], GROUPTEMPLATES_ACP_URL . '&amp;action=alternatives&amp;id=' . $switch_id);
        $page->add_breadcrumb_item($lang->grouptemplates_tab_edit_alternative);
        $page->output_header($lang->grouptemplates_acp_title);
        grouptemplates_acp_alternative_form($switch, $values, $errors, $aid);
        $page->output_footer();
        break;

    case 'delete_alternative':
        $aid = $mybb->get_input('aid', MyBB::INPUT_INT);
        $alt = grouptemplates_db_get_alternative($aid);
        if (!$alt) {
            flash_message($lang->grouptemplates_error_invalid_alternative_id, 'error');
            admin_redirect(GROUPTEMPLATES_ACP_URL);
        }
        $switch_id = (int)$alt['switch_id'];

        if ($is_post) {
            if ($mybb->get_input('no')) {
                admin_redirect(GROUPTEMPLATES_ACP_URL . '&action=alternatives&id=' . $switch_id);
            }
            grouptemplates_delete_alternative($aid);
            log_admin_action($aid, $alt['template']);
            flash_message($lang->grouptemplates_alternative_deleted, 'success');
            admin_redirect(GROUPTEMPLATES_ACP_URL . '&action=alternatives&id=' . $switch_id);
        }

        $page->output_confirm_action(
            GROUPTEMPLATES_ACP_URL . '&amp;action=delete_alternative&amp;aid=' . $aid,
            $lang->sprintf($lang->grouptemplates_confirm_delete_alternative, htmlspecialchars_uni($alt['template'])),
            $lang->grouptemplates_acp_title
        );
        break;

    default:
        $page->output_header($lang->grouptemplates_acp_title);
        grouptemplates_acp_show_switches();
        $page->output_footer();
        break;
}
