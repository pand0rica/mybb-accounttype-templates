<?php
/**
 * Group Templates -- Admin-Sprachdatei (Deutsch, du).
 */

$l['grouptemplates']            = 'Group Templates';
$l['grouptemplates_can_manage'] = 'Darf Group Templates verwalten?';
$l['grouptemplates_acp_title']  = 'Group Templates';

// Tabs
$l['grouptemplates_tab_switches']              = 'Schaltstellen';
$l['grouptemplates_tab_desc_switches']         = 'Alle Schaltstellen. Jede Schaltstelle ersetzt ein Ziel-Template durch ihr Standard-Template oder durch die erste passende Alternative – abhängig von den Benutzergruppen des angezeigten Accounts.';
$l['grouptemplates_tab_add_switch']            = 'Schaltstelle hinzufügen';
$l['grouptemplates_tab_desc_add_switch']       = 'Neue Schaltstelle anlegen: Template-Set, Kontext und Ziel-Template wählen und das Standard-Template festlegen. Alternativen fügst du im nächsten Schritt hinzu.';
$l['grouptemplates_tab_edit_switch']           = 'Schaltstelle bearbeiten';
$l['grouptemplates_tab_desc_edit_switch']      = 'Standard-Template und Kennung ändern. Set, Kontext oder Ziel änderst du, indem du die Schaltstelle löschst und neu anlegst.';
$l['grouptemplates_tab_alternatives']          = 'Alternativen';
$l['grouptemplates_tab_desc_alternatives']     = 'Alternative Templates dieser Schaltstelle, jeweils für bestimmte Benutzergruppen. Die erste passende Alternative in der Reihenfolge gewinnt; passt keine, gilt das Standard-Template.';
$l['grouptemplates_tab_add_alternative']       = 'Alternative hinzufügen';
$l['grouptemplates_tab_desc_add_alternative']  = 'Alternatives Template hinzufügen und festlegen, für welche Benutzergruppen es gilt.';
$l['grouptemplates_tab_edit_alternative']      = 'Alternative bearbeiten';
$l['grouptemplates_tab_desc_edit_alternative'] = 'Template, Kennung, Benutzergruppen, Gruppenmodus oder Position dieser Alternative ändern.';

// Template-Sets, Kontexte, Gruppenmodi
$l['grouptemplates_set_global'] = 'Globale Templates (alle Sets)';

$l['grouptemplates_context_post']            = 'Beitrag';
$l['grouptemplates_context_post_desc']       = 'Prüft den Autor des Beitrags (Thread-Ansicht, Vorschau, private Nachrichten, Ankündigungen). Wirksam für Templates, die nach dem Postbit-Hook ausgewertet werden, z. B. postbit und postbit_classic. Sub-Templates wie postbit_author_user werden früher ausgewertet und lassen sich hier nicht umschalten.';
$l['grouptemplates_context_profile']         = 'Profil';
$l['grouptemplates_context_profile_desc']    = 'Prüft den Profilinhaber. Wirksam für alle member_profile*-Templates.';
$l['grouptemplates_context_memberlist']      = 'Mitgliederliste';
$l['grouptemplates_context_memberlist_desc'] = 'Prüft jedes gelistete Mitglied. Wirksam für memberlist_user und Templates, die pro Zeile danach ausgewertet werden.';
$l['grouptemplates_context_viewer']          = 'Betrachter';
$l['grouptemplates_context_viewer_desc']     = 'Prüft den eingeloggten Besucher (Gäste = Gruppe Gäste). Wirksam für Header, Footer und alle Seiten-Templates, die nach global_intermediate ausgewertet werden.';

$l['grouptemplates_mode_primary'] = 'Nur primäre Gruppe';
$l['grouptemplates_mode_display'] = 'Anzeigegruppe (primäre Gruppe, falls keine gesetzt)';
$l['grouptemplates_mode_any']     = 'Primäre, Anzeige- oder eine Zusatzgruppe';

$l['grouptemplates_all_groups'] = 'Alle Gruppen';

// Formulare
$l['grouptemplates_form_add_switch']       = 'Schaltstelle hinzufügen';
$l['grouptemplates_form_edit_switch']      = 'Schaltstelle bearbeiten';
$l['grouptemplates_form_add_alternative']  = 'Alternative für {1} hinzufügen';
$l['grouptemplates_form_edit_alternative'] = 'Alternative für {1} bearbeiten';

$l['grouptemplates_field_set']               = 'Template-Set';
$l['grouptemplates_field_set_desc']          = 'Die Schaltstelle gilt nur für Themes mit diesem Template-Set. „Globale Templates" gilt für alle Sets; eine Schaltstelle in einem konkreten Set überschreibt eine globale mit demselben Ziel.';
$l['grouptemplates_field_fixed_desc']        = 'Template-Set, Kontext und Ziel lassen sich nicht ändern. Lösche die Schaltstelle stattdessen und lege sie neu an.';
$l['grouptemplates_field_context']           = 'Kontext';
$l['grouptemplates_field_context_desc']      = 'Wessen Benutzergruppen geprüft werden und wann umgeschaltet wird:';
$l['grouptemplates_field_target']            = 'Ziel-Template';
$l['grouptemplates_field_target_desc']       = 'Das Template, das MyBB auswertet, z. B. postbit oder member_profile. Oben wird ein Marker-Kommentar eingefügt; der bisherige Inhalt bleibt dahinter stehen und ist nach dem Löschen der Schaltstelle wieder aktiv. Im Kontext „Betrachter“ darf das Ziel auch ein Name sein, den es noch nicht als Template gibt (virtuelles Ziel): Es existiert dann nur über die Schaltstelle und wird in anderen Templates mit {&#36;grouptemplates[\'name\']} eingebunden.';
$l['grouptemplates_field_default']           = 'Standard-Template';
$l['grouptemplates_field_default_desc']      = 'Wird verwendet, wenn keine Alternative passt. Leer lassen, wenn dann nichts ausgegeben werden soll.';
$l['grouptemplates_field_slug_default']      = 'Standard-Kennung';
$l['grouptemplates_field_slug_default_desc'] = 'Kleinbuchstaben, Ziffern, - und _ (max. 50). In Templates verfügbar, z. B. {$post[\'grouptemplate\'][\'postbit\']}, für CSS-Klassen.';
$l['grouptemplates_field_alternative']       = 'Alternatives Template';
$l['grouptemplates_field_alternative_desc']  = 'Name des Templates, das für die gewählten Gruppen verwendet wird. Muss im Template-Set der Schaltstelle (oder global) existieren. Leer lassen, wenn für diese Gruppen nichts ausgegeben werden soll.';
$l['grouptemplates_field_slug']              = 'Kennung';
$l['grouptemplates_field_slug_desc']         = 'Kleinbuchstaben, Ziffern, - und _ (max. 50), z. B. user. Als Template-Variable verfügbar, wenn diese Alternative gewählt wird.';
$l['grouptemplates_field_groups']            = 'Benutzergruppen';
$l['grouptemplates_field_groups_desc']       = 'Die Alternative greift, sobald eine der geprüften Gruppen des Accounts hier ausgewählt ist. Gäste und gelöschte Autoren zählen als Gruppe „Gäste".';
$l['grouptemplates_field_mode']              = 'Gruppenmodus';
$l['grouptemplates_field_mode_desc']         = 'Welche Gruppen des Accounts geprüft werden.';
$l['grouptemplates_field_disporder']         = 'Reihenfolge';
$l['grouptemplates_field_disporder_desc']    = 'Alternativen werden aufsteigend geprüft; die erste passende gewinnt.';

$l['grouptemplates_save']       = 'Speichern';
$l['grouptemplates_save_order'] = 'Reihenfolge speichern';

// Tabellen
$l['grouptemplates_switches_title']        = 'Schaltstellen';
$l['grouptemplates_alternatives_title']    = 'Alternativen';
$l['grouptemplates_col_set']               = 'Template-Set';
$l['grouptemplates_col_context']           = 'Kontext';
$l['grouptemplates_col_target']            = 'Ziel';
$l['grouptemplates_col_default']           = 'Standard (Kennung)';
$l['grouptemplates_col_alternatives']      = 'Alternativen';
$l['grouptemplates_col_status']            = 'Status';
$l['grouptemplates_col_options']           = 'Optionen';
$l['grouptemplates_col_disporder']         = 'Reihenfolge';
$l['grouptemplates_col_alternative']       = 'Template';
$l['grouptemplates_col_slug']              = 'Kennung';
$l['grouptemplates_col_groups']            = 'Benutzergruppen';
$l['grouptemplates_col_mode']              = 'Gruppenmodus';
$l['grouptemplates_no_switches']           = 'Noch keine Schaltstellen. Lege über „Schaltstelle hinzufügen" die erste an.';
$l['grouptemplates_no_alternatives']       = 'Noch keine Alternativen – es wird immer das Standard-Template verwendet. Füge über „Alternative hinzufügen" eine hinzu.';
$l['grouptemplates_no_alternatives_short'] = 'keine';
$l['grouptemplates_alternatives_head']     = 'Template-Set: <strong>{1}</strong> &middot; Kontext: <strong>{2}</strong> &middot; Ziel: <strong>{3}</strong> &middot; Standard: <strong>{4}</strong> ({5})';
$l['grouptemplates_rule_hint']             = 'Die erste passende Alternative in der Reihenfolge gewinnt. Passt keine, gilt das Standard-Template.';

$l['grouptemplates_manage_alternatives'] = 'Alternativen';
$l['grouptemplates_edit']                = 'Bearbeiten';
$l['grouptemplates_delete']              = 'Löschen';

// Status
$l['grouptemplates_status_current']          = 'Marker vorhanden';
$l['grouptemplates_status_outdated']         = 'Marker veraltet – Schaltstelle erneut speichern';
$l['grouptemplates_status_missing']          = 'Marker fehlt – Template wurde manuell geändert';
$l['grouptemplates_status_target_missing']   = 'Ziel-Template fehlt in diesem Set';
$l['grouptemplates_status_virtual']          = 'Virtuelles Ziel – eingebunden mit {&#36;grouptemplates[\'{1}\']}';
$l['grouptemplates_empty_template']          = '(leer)';
$l['grouptemplates_switch_deleted_virtual']   = 'Die Schaltstelle wurde gelöscht. Das Ziel war virtuell, Einbindungen mit {&#36;grouptemplates[...]} geben jetzt nichts mehr aus.';
$l['grouptemplates_status_template_missing'] = 'Template „{1}" nicht gefunden';
$l['grouptemplates_status_copy']             = 'Template-Kopie vom Plugin angelegt';

// Validierungsfehler
$l['grouptemplates_error_invalid_switch']          = 'Die gewählte Schaltstelle existiert nicht.';
$l['grouptemplates_error_invalid_alternative_id']  = 'Die gewählte Alternative existiert nicht.';
$l['grouptemplates_error_invalid_set']             = 'Bitte wähle ein gültiges Template-Set.';
$l['grouptemplates_error_invalid_context']         = 'Bitte wähle einen gültigen Kontext.';
$l['grouptemplates_error_invalid_target']          = 'Bitte gib einen gültigen Namen für das Ziel-Template ein (Buchstaben, Ziffern, - und _).';
$l['grouptemplates_error_invalid_default']         = 'Bitte gib einen gültigen Namen für das Standard-Template ein (Buchstaben, Ziffern, - und _).';
$l['grouptemplates_error_invalid_slug']            = 'Bitte gib eine gültige Kennung ein (Kleinbuchstaben, Ziffern, - und _, max. 50).';
$l['grouptemplates_error_default_is_target']       = 'Das Standard-Template muss sich vom Ziel-Template unterscheiden.';
$l['grouptemplates_error_target_not_global']       = 'Bei „Globale Templates" muss das Ziel-Template in den globalen Templates existieren.';
$l['grouptemplates_error_target_missing']          = 'Das Ziel-Template existiert in diesem Template-Set nicht.';
$l['grouptemplates_error_default_missing']         = 'Das Standard-Template existiert in diesem Template-Set nicht.';
$l['grouptemplates_error_duplicate']               = 'Für dieses Ziel-Template gibt es in diesem Template-Set bereits eine Schaltstelle.';
$l['grouptemplates_error_blocklisted']             = 'Dieses Template wird vor dem Postbit-Hook ausgewertet und lässt sich im Kontext „Beitrag" nicht umschalten. Schalte stattdessen postbit oder postbit_classic um.';
$l['grouptemplates_error_chain']                   = 'Ein Template darf nicht Ziel der einen und Standard oder Alternative einer anderen Schaltstelle im selben Template-Set sein.';
$l['grouptemplates_error_invalid_alternative']     = 'Bitte gib einen gültigen Namen für das alternative Template ein (Buchstaben, Ziffern, - und _).';
$l['grouptemplates_error_alternative_is_target']   = 'Das alternative Template muss sich vom Ziel-Template unterscheiden.';
$l['grouptemplates_error_alternative_missing']     = 'Das alternative Template existiert im Template-Set dieser Schaltstelle nicht.';
$l['grouptemplates_error_no_groups']               = 'Bitte wähle mindestens eine Benutzergruppe oder „Alle Gruppen".';
$l['grouptemplates_error_invalid_group']           = 'Mindestens eine der gewählten Benutzergruppen existiert nicht.';
$l['grouptemplates_error_invalid_mode']            = 'Bitte wähle einen gültigen Gruppenmodus.';
$l['grouptemplates_error_invalid_disporder']       = 'Die Reihenfolge darf nicht negativ sein.';

// Meldungen
$l['grouptemplates_switch_saved']                   = 'Die Schaltstelle wurde gespeichert.';
$l['grouptemplates_switch_deleted_marker_removed']  = 'Die Schaltstelle wurde gelöscht und der Marker aus dem Ziel-Template entfernt.';
$l['grouptemplates_switch_deleted_copy_deleted']    = 'Die Schaltstelle wurde gelöscht und die vom Plugin angelegte Template-Kopie entfernt.';
$l['grouptemplates_switch_deleted_copy_kept']       = 'Die Schaltstelle wurde gelöscht. Die vom Plugin angelegte Template-Kopie wurde inzwischen geändert und bleibt deshalb bestehen (Marker entfernt).';
$l['grouptemplates_switch_deleted_target_missing']  = 'Die Schaltstelle wurde gelöscht. Das Ziel-Template existiert in diesem Set nicht mehr, es musste kein Marker entfernt werden.';
$l['grouptemplates_order_saved']                    = 'Die Reihenfolge wurde gespeichert.';
$l['grouptemplates_alternative_saved']              = 'Die Alternative wurde gespeichert.';
$l['grouptemplates_alternative_deleted']            = 'Die Alternative wurde gelöscht.';
$l['grouptemplates_confirm_delete_switch']          = 'Willst du die Schaltstelle für „{1}" in „{2}" wirklich löschen? Alle Alternativen werden mitgelöscht und der Marker wird aus dem Template entfernt.';
$l['grouptemplates_confirm_delete_alternative']     = 'Willst du die Alternative „{1}" wirklich löschen?';

// Admin-Log
$l['admin_log_style_grouptemplates_add_switch']         = 'Schaltstelle #{1} ({2}) angelegt';
$l['admin_log_style_grouptemplates_edit_switch']        = 'Schaltstelle #{1} ({2}) bearbeitet';
$l['admin_log_style_grouptemplates_delete_switch']      = 'Schaltstelle #{1} ({2}) gelöscht';
$l['admin_log_style_grouptemplates_save_order']         = 'Reihenfolge der Alternativen von Schaltstelle #{1} ({2}) geändert';
$l['admin_log_style_grouptemplates_add_alternative']    = 'Alternative #{1} ({3}) zu Schaltstelle {2} hinzugefügt';
$l['admin_log_style_grouptemplates_edit_alternative']   = 'Alternative #{1} ({3}) von Schaltstelle {2} bearbeitet';
$l['admin_log_style_grouptemplates_delete_alternative'] = 'Alternative #{1} ({2}) gelöscht';
