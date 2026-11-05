<?php
/**
 * Group Templates -- admin language file (English).
 */

$l['grouptemplates']            = 'Group Templates';
$l['grouptemplates_can_manage'] = 'Can manage Group Templates?';
$l['grouptemplates_acp_title']  = 'Group Templates';

// Tabs
$l['grouptemplates_tab_switches']              = 'Switches';
$l['grouptemplates_tab_desc_switches']         = 'All template switches. Each switch replaces a target template with its default template or with the first matching alternative, depending on the user groups of the displayed account.';
$l['grouptemplates_tab_add_switch']            = 'Add Switch';
$l['grouptemplates_tab_desc_add_switch']       = 'Create a new switch: choose template set, context and target template, and set the default template. Alternatives are added in the next step.';
$l['grouptemplates_tab_edit_switch']           = 'Edit Switch';
$l['grouptemplates_tab_desc_edit_switch']      = 'Change the default template and its identifier. To change set, context or target, delete the switch and create a new one.';
$l['grouptemplates_tab_alternatives']          = 'Alternatives';
$l['grouptemplates_tab_desc_alternatives']     = 'Alternative templates of this switch, each valid for specific user groups. The first matching alternative in display order wins; if none matches, the default template is used.';
$l['grouptemplates_tab_add_alternative']       = 'Add Alternative';
$l['grouptemplates_tab_desc_add_alternative']  = 'Add an alternative template and choose the user groups it applies to.';
$l['grouptemplates_tab_edit_alternative']      = 'Edit Alternative';
$l['grouptemplates_tab_desc_edit_alternative'] = 'Change template, identifier, user groups, group mode or position of this alternative.';

// Template sets, contexts, group modes
$l['grouptemplates_set_global'] = 'Global Templates (all sets)';

$l['grouptemplates_context_post']            = 'Post';
$l['grouptemplates_context_post_desc']       = 'Checks the post author (thread view, preview, private messages, announcements). Works for templates evaluated after the postbit hook, e.g. postbit and postbit_classic. Sub-templates such as postbit_author_user are evaluated earlier and cannot be switched here.';
$l['grouptemplates_context_profile']         = 'Profile';
$l['grouptemplates_context_profile_desc']    = 'Checks the profile owner. Works for all member_profile* templates.';
$l['grouptemplates_context_memberlist']      = 'Member List';
$l['grouptemplates_context_memberlist_desc'] = 'Checks each listed member. Works for memberlist_user and templates evaluated per row after it.';
$l['grouptemplates_context_viewer']          = 'Viewer';
$l['grouptemplates_context_viewer_desc']     = 'Checks the logged-in visitor (guests = group Guests). Works for header, footer and all page templates evaluated after global_intermediate.';

$l['grouptemplates_mode_primary'] = 'Primary group only';
$l['grouptemplates_mode_display'] = 'Display group (primary group if none is set)';
$l['grouptemplates_mode_any']     = 'Primary, display or any additional group';

$l['grouptemplates_all_groups'] = 'All groups';

// Forms
$l['grouptemplates_form_add_switch']       = 'Add Switch';
$l['grouptemplates_form_edit_switch']      = 'Edit Switch';
$l['grouptemplates_form_add_alternative']  = 'Add Alternative for {1}';
$l['grouptemplates_form_edit_alternative'] = 'Edit Alternative for {1}';

$l['grouptemplates_field_set']               = 'Template Set';
$l['grouptemplates_field_set_desc']          = 'The switch only applies to themes using this template set. "Global Templates" applies to all sets; a switch in a specific set overrides a global one with the same target.';
$l['grouptemplates_field_fixed_desc']        = 'Template set, context and target cannot be changed. Delete the switch and create a new one instead.';
$l['grouptemplates_field_context']           = 'Context';
$l['grouptemplates_field_context_desc']      = 'Whose user groups are checked and when the template is switched:';
$l['grouptemplates_field_target']            = 'Target Template';
$l['grouptemplates_field_target_desc']       = 'The template MyBB evaluates, e.g. postbit or member_profile. A marker comment is added to its top; the existing content stays behind it and is restored when the switch is deleted. In the viewer context the target may also be a name that does not exist as a template (virtual target): it only exists through the switch and is included in other templates with {&#36;grouptemplates[\'name\']}.';
$l['grouptemplates_field_default']           = 'Default Template';
$l['grouptemplates_field_default_desc']      = 'Used when no alternative matches. Leave empty to output nothing in that case.';
$l['grouptemplates_field_slug_default']      = 'Default Identifier';
$l['grouptemplates_field_slug_default_desc'] = 'Lowercase letters, digits, - and _ (max. 50). Available in templates, e.g. {$post[\'grouptemplate\'][\'postbit\']}, for CSS classes.';
$l['grouptemplates_field_alternative']       = 'Alternative Template';
$l['grouptemplates_field_alternative_desc']  = 'Name of the template used for the selected groups. Must exist in the template set of the switch (or globally). Leave empty to output nothing for these groups.';
$l['grouptemplates_field_slug']              = 'Identifier';
$l['grouptemplates_field_slug_desc']         = 'Lowercase letters, digits, - and _ (max. 50), e.g. user. Available as template variable when this alternative is chosen.';
$l['grouptemplates_field_groups']            = 'User Groups';
$l['grouptemplates_field_groups_desc']       = 'The alternative applies as soon as one of the checked groups of the account is selected here. Guests and deleted authors count as group "Guests".';
$l['grouptemplates_field_mode']              = 'Group Mode';
$l['grouptemplates_field_mode_desc']         = 'Which groups of the account are checked.';
$l['grouptemplates_field_disporder']         = 'Display Order';
$l['grouptemplates_field_disporder_desc']    = 'Alternatives are checked in ascending order; the first match wins.';

$l['grouptemplates_save']       = 'Save';
$l['grouptemplates_save_order'] = 'Save Order';

// Tables
$l['grouptemplates_switches_title']        = 'Switches';
$l['grouptemplates_alternatives_title']    = 'Alternatives';
$l['grouptemplates_col_set']               = 'Template Set';
$l['grouptemplates_col_context']           = 'Context';
$l['grouptemplates_col_target']            = 'Target';
$l['grouptemplates_col_default']           = 'Default (Identifier)';
$l['grouptemplates_col_alternatives']      = 'Alternatives';
$l['grouptemplates_col_status']            = 'Status';
$l['grouptemplates_col_options']           = 'Options';
$l['grouptemplates_col_disporder']         = 'Order';
$l['grouptemplates_col_alternative']       = 'Template';
$l['grouptemplates_col_slug']              = 'Identifier';
$l['grouptemplates_col_groups']            = 'User Groups';
$l['grouptemplates_col_mode']              = 'Group Mode';
$l['grouptemplates_no_switches']           = 'No switches yet. Use "Add Switch" to create the first one.';
$l['grouptemplates_no_alternatives']       = 'No alternatives yet - the default template is always used. Use "Add Alternative" to add one.';
$l['grouptemplates_no_alternatives_short'] = 'none';
$l['grouptemplates_alternatives_head']     = 'Template set: <strong>{1}</strong> &middot; Context: <strong>{2}</strong> &middot; Target: <strong>{3}</strong> &middot; Default: <strong>{4}</strong> ({5})';
$l['grouptemplates_rule_hint']             = 'The first matching alternative in display order wins. If none matches, the default template is used.';

$l['grouptemplates_manage_alternatives'] = 'Alternatives';
$l['grouptemplates_edit']                = 'Edit';
$l['grouptemplates_delete']              = 'Delete';

// Status
$l['grouptemplates_status_current']          = 'Marker present';
$l['grouptemplates_status_outdated']         = 'Marker outdated - save the switch again';
$l['grouptemplates_status_missing']          = 'Marker missing - the template was changed manually';
$l['grouptemplates_status_target_missing']   = 'Target template missing in this set';
$l['grouptemplates_status_virtual']          = 'Virtual target – included with {&#36;grouptemplates[\'{1}\']}';
$l['grouptemplates_empty_template']          = '(empty)';
$l['grouptemplates_switch_deleted_virtual']   = 'The switch has been deleted. Its target was virtual, inclusions with {&#36;grouptemplates[...]} now output nothing.';
$l['grouptemplates_status_template_missing'] = 'Template "{1}" not found';
$l['grouptemplates_status_copy']             = 'Template copy created by the plugin';

// Validation errors
$l['grouptemplates_error_invalid_switch']          = 'The selected switch does not exist.';
$l['grouptemplates_error_invalid_alternative_id']  = 'The selected alternative does not exist.';
$l['grouptemplates_error_invalid_set']             = 'Please choose a valid template set.';
$l['grouptemplates_error_invalid_context']         = 'Please choose a valid context.';
$l['grouptemplates_error_invalid_target']          = 'Please enter a valid target template name (letters, digits, - and _).';
$l['grouptemplates_error_invalid_default']         = 'Please enter a valid default template name (letters, digits, - and _).';
$l['grouptemplates_error_invalid_slug']            = 'Please enter a valid identifier (lowercase letters, digits, - and _, max. 50).';
$l['grouptemplates_error_default_is_target']       = 'The default template must differ from the target template.';
$l['grouptemplates_error_target_not_global']       = 'For "Global Templates" the target template must exist in the global templates.';
$l['grouptemplates_error_target_missing']          = 'The target template does not exist in this template set.';
$l['grouptemplates_error_default_missing']         = 'The default template does not exist in this template set.';
$l['grouptemplates_error_duplicate']               = 'There already is a switch for this target template in this template set.';
$l['grouptemplates_error_blocklisted']             = 'This template is evaluated before the postbit hook and cannot be switched in the context "Post". Switch postbit or postbit_classic instead.';
$l['grouptemplates_error_chain']                   = 'A template must not be the target of one switch and the default or alternative of another switch in the same template set.';
$l['grouptemplates_error_invalid_alternative']     = 'Please enter a valid alternative template name (letters, digits, - and _).';
$l['grouptemplates_error_alternative_is_target']   = 'The alternative template must differ from the target template.';
$l['grouptemplates_error_alternative_missing']     = 'The alternative template does not exist in the template set of this switch.';
$l['grouptemplates_error_no_groups']               = 'Please select at least one user group or "All groups".';
$l['grouptemplates_error_invalid_group']           = 'At least one of the selected user groups does not exist.';
$l['grouptemplates_error_invalid_mode']            = 'Please choose a valid group mode.';
$l['grouptemplates_error_invalid_disporder']       = 'The display order must not be negative.';

// Messages
$l['grouptemplates_switch_saved']                   = 'The switch has been saved.';
$l['grouptemplates_switch_deleted_marker_removed']  = 'The switch has been deleted and the marker removed from the target template.';
$l['grouptemplates_switch_deleted_copy_deleted']    = 'The switch has been deleted and the template copy created by the plugin removed.';
$l['grouptemplates_switch_deleted_copy_kept']       = 'The switch has been deleted. The template copy created by the plugin was changed in the meantime and has been kept (marker removed).';
$l['grouptemplates_switch_deleted_target_missing']  = 'The switch has been deleted. The target template no longer exists in this set, so no marker had to be removed.';
$l['grouptemplates_order_saved']                    = 'The order has been saved.';
$l['grouptemplates_alternative_saved']              = 'The alternative has been saved.';
$l['grouptemplates_alternative_deleted']            = 'The alternative has been deleted.';
$l['grouptemplates_confirm_delete_switch']          = 'Do you really want to delete the switch for "{1}" in "{2}"? All its alternatives are deleted and the marker is removed from the template.';
$l['grouptemplates_confirm_delete_alternative']     = 'Do you really want to delete the alternative "{1}"?';

// Admin log
$l['admin_log_style_grouptemplates_add_switch']         = 'Added group template switch #{1} ({2})';
$l['admin_log_style_grouptemplates_edit_switch']        = 'Edited group template switch #{1} ({2})';
$l['admin_log_style_grouptemplates_delete_switch']      = 'Deleted group template switch #{1} ({2})';
$l['admin_log_style_grouptemplates_save_order']         = 'Reordered alternatives of group template switch #{1} ({2})';
$l['admin_log_style_grouptemplates_add_alternative']    = 'Added alternative #{1} ({3}) to group template switch {2}';
$l['admin_log_style_grouptemplates_edit_alternative']   = 'Edited alternative #{1} ({3}) of group template switch {2}';
$l['admin_log_style_grouptemplates_delete_alternative'] = 'Deleted group template alternative #{1} ({2})';
