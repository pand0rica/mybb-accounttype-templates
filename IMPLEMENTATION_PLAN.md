# Group Templates – Implementation Plan

**Umsetzung:** folge `<dev-kit>/workflows/2-implement.md`. Schritte der Reihe
nach. Bei Unklarheit gezielt nachfragen, aber nicht umplanen. Kontext, Auswahlregel,
Tabellenschema, Hooks und Prioritäten stehen verbindlich in `PROJECT_CONTEXT.md`.

Testumgebung: Test-Forum (SSH/CLI laut `<dev-kit>/standards/testing.md`;
Verbindungsdaten nur aus den privaten globalen Notizen, nie ins Repo). Deploy per tar-Pipe
(`testing.md` §1), nach jedem Deploy `php -l` über SSH (§2).

---

## Schritt 1 – Grundgerüst & Zugriffsschutz
- [x] **Ziel:** Hauptdatei mit `IN_MYBB`-Schutz, `grouptemplates_info()` (Name „Group
      Templates", Autor pand0rica, Version 1.0.0, `compatibility` `18*`, codename
      `grouptemplates`), Hook-Registrierung laut `PROJECT_CONTEXT.md` → Hooks (ACP-Hooks nur
      bei `defined('IN_ADMINCP')`, Frontend-Hooks sonst), leere Handler-Stubs.
- **Dateien:** `inc/plugins/grouptemplates.php`
- **Vorlage:** `<dev-kit>/standards/boilerplate.md` §1, §2
- **Abnahme:** `php -l` sauber; Plugin erscheint in der ACP-Pluginliste.

## Schritt 2 – Lifecycle (install / is_installed / uninstall / activate / deactivate)
- [x] **Ziel:**
  - `install()`: beide Tabellen exakt nach Schema (UNIQUE `sid_target`, KEY `switch_id`),
    `grouptemplates_rebuild_cache()`. Keine Settings.
  - `is_installed()`: `$db->table_exists('grouptemplates_switches')`.
  - `activate()`: nur `grouptemplates_rebuild_cache()`.
  - `deactivate()`: bewusst **leer** bzgl. Templates (siehe „What NOT to change").
  - `uninstall()`: für **jede** Schaltstelle `grouptemplates_template_remove_marker()`
    (Schritt 4), beide Tabellen droppen, Datacache-Zeile löschen
    (`$db->delete_query('datacache', "title='grouptemplates'")`).
- **Dateien:** `inc/plugins/grouptemplates.php`
- **Vorlage:** `boilerplate.md` §3, §9 · `conventions.md` §4 · `debugging.md` §8, §9
- **Abnahme:** `tests/grouptemplates/cli-plugin.php` (Vorlage `testing.md` §3) mit `install`/`uninstall`/
  `status`; `status` zeigt beide Tabellen, Datacache-Zeile, Anzahl Marker
  (`templates.template LIKE '%<!-- grouptemplates:%'`). Zyklus install → uninstall
  (alles 0/MISSING) → install.

## Schritt 3 – Kernlogik & öffentliche API
- [x] **Ziel:**
  - `grouptemplates_get_user_groups(array $user, string $mode): array` – uid fehlt/0 →
    `[1]`; sonst nach Modus (`primary`/`display`/`any`, Tabelle in `PROJECT_CONTEXT.md`),
    `additionalgroups` explodieren, alles `(int)`, Nullen/Leere raus, `array_unique`.
  - `grouptemplates_match(array $user, array $alternative): bool` – `all` → true, sonst
    Schnittmenge Gruppen ∩ `groups` nicht leer. Optionaler 3. Parameter: bereits berechnete
    Gruppen je Modus (Array `mode => groups`), damit pro Beitrag jeder Modus nur einmal
    berechnet wird.
  - `grouptemplates_pick(array $switch, array $user, array &$group_memo): array` – liefert
    `['template','slug']`: erste passende Alternative, sonst Standard (Auswahlregel).
  - `grouptemplates_rebuild_cache(): void` – Schaltstellen + Alternativen per `while` lesen
    (Alternativen `ORDER BY disporder, id`), Struktur laut „Datacache", `usergroups` parsen
    (`-1` → `all = true`), `$cache->update('grouptemplates', …)`.
  - `grouptemplates_get_active_map(string $context): array` – einmal pro Request (static):
    `-1` + aktuelles `$theme['templateset']` mergen (Set gewinnt). Beim ersten Aufruf fehlende
    Templates (`!isset($templates->cache[$name])`) **gesammelt** per `$templates->cache(...)`
    nachladen (escaped). Danach: fehlt das Standard-Template → ganze Schaltstelle aus der Map
    werfen; fehlende Alternativen einzeln entfernen. Bereits gecachte Templates (z. B. durch
    Theme Variants ersetzt) **nicht** neu laden.
  - `grouptemplates_apply(string $context, array $user): array` – für jede Map-Zeile
    `grouptemplates_pick()`, `$templates->cache[$target] = $templates->cache[$picked]`,
    Rückgabe `target => slug` (für die Template-Variable). Keine Query.
  - `grouptemplates_resolve(string $context, string $target, array $user): ?array` (API).
- **Dateien:** `inc/plugins/grouptemplates.php`
- **Vorlage:** `conventions.md` §3 (Naming), §4, §6
- **Abnahme:** `tests/grouptemplates/cli-selftest.php` (Vorlage `testing.md` §4) mit Fixture-Arrays (ohne DB):
  alle drei Modi × (primär trifft / Anzeigegruppe trifft / Zusatzgruppe trifft / nichts
  trifft), uid 0 → Gruppe 1 (auch bei leerem `usergroup`), `-1` = alle, fehlende Keys,
  mehrere passende Alternativen → erste gewinnt, keine passt → Standard + Standard-Kennung,
  Schaltstelle ohne Alternativen → Standard.

## Schritt 4 – Marker-, Template- und Validierungs-Helfer (für ACP und uninstall)
- [x] **Ziel:**
  - `grouptemplates_get_contexts(): array` – `post`, `profile`, `memberlist`, `viewer`.
  - `grouptemplates_get_group_modes(): array` – `primary`, `display`, `any`.
  - `grouptemplates_get_post_blocklist(): array` – **alle** Templates, die in
    `build_postbit()` (`inc/functions_post.php`) **vor** dem Hook-Block
    `switch($post_type) … run_hooks("postbit…")` ausgewertet werden. Per Grep auf dem
    Testserver ermitteln (`grep -n 'templates->get("' inc/functions_post.php`, nur innerhalb
    `build_postbit` bis zur Hook-Zeile) und als Array hartkodieren, mit Herkunftskommentar.
  - `grouptemplates_build_marker(int $switch_id): string` – liest Standard + Alternativen
    (sortiert) → `<!-- grouptemplates: default=…; alternatives=a,b -->`.
  - `grouptemplates_template_find(string $title, int $sid): ?array` (nur im Set) und
    `grouptemplates_template_find_fallback(string $title, int $sid): ?array` (`sid`, `-1`,
    `-2`; höchste sid zuerst wie `templates->get()`).
  - `grouptemplates_template_write_marker(int $switch_id): void` – Ziel im Set:
    vorhandenen Marker per Regex entfernen, neuen Marker + `"\n"` voranstellen,
    `update_query` (`template`, `dateline`). Existiert es im Set nicht: Kopie aus Fallback
    mit Marker anlegen (`version` übernehmen), `created_copy = 1` an der Schaltstelle setzen.
    Bei `sid = -1` muss das Ziel in `-1` existieren (keine Kopien aus `-2` ins globale Set).
    Wird nach **jeder** Änderung an Schaltstelle oder Alternativen aufgerufen.
  - `grouptemplates_template_remove_marker(array $switch): string` – Marker entfernen; bei
    `created_copy = 1` und Rest === Fallback-Inhalt → Kopie löschen (`'copy_deleted'`),
    sonst speichern (`'copy_kept'`); Normalfall `'marker_removed'`; Ziel fehlt →
    `'target_missing'` (kein Fehler).
  - `grouptemplates_validate_switch(array $data, int $id = 0): array` (Fehler-Sprachkeys):
    `sid` existiert in `templatesets` oder ist `-1`; `context` gültig; `target` und
    `template_default` gültige Namen (`^[A-Za-z0-9_\-]{1,120}$`) und verschieden;
    `slug_default` `^[a-z0-9_-]{1,50}$`; Ziel + Standard per Fallback im Set auffindbar
    (`sid = -1`: Ziel in `-1`); `(sid, target)` frei (außer eigene `id`); Kontext `post`:
    Ziel nicht in der Sperrliste; **keine Ketten** (s. u.).
  - `grouptemplates_validate_alternative(array $data, array $switch, int $id = 0): array`:
    `template` gültiger Name, auffindbar per Fallback im Set der Schaltstelle, ≠ Ziel;
    `slug` `^[a-z0-9_-]{1,50}$`; `usergroups` nicht leer, nur existierende gids oder `-1`;
    `group_mode` gültig; `disporder` int ≥ 0; **keine Ketten**.
  - **Ketten-Regel** (für beide Validatoren, betrachtet das Set der Schaltstelle **und**
    `-1`): Ein Ziel darf nirgends als Standard- oder Alternativ-Template vorkommen, und ein
    Standard-/Alternativ-Template darf nirgends Ziel sein.
  - `grouptemplates_get_switch_status(array $switch): array` – Marker im Set-Template
    vorhanden und aktuell (= `grouptemplates_build_marker()`)? Standard-/Alternativ-Templates
    auffindbar? Kopie angelegt?
- **Dateien:** `inc/plugins/grouptemplates.php`
- **Vorlage:** `conventions.md` §4, §6 · `debugging.md` §6, §14, §19
- **Abnahme:** `cli-selftest.php` erweitern um DB-Roundtrip mit Wegwerf-Templates
  (`SELFTEST_gt_target_<TIME>`, `…_default_…`, `…_alt1_…`, `…_alt2_…` in Set 32; zusätzlich
  ein Ziel nur in `-1` für den Kopie-Fall): Schaltstelle anlegen → Marker genau einmal am
  Anfang, Originalinhalt unverändert dahinter; Alternative hinzufügen/umsortieren/löschen →
  Marker aktualisiert, weiterhin genau einmal; Schaltstelle löschen → Inhalt byte-identisch
  zum Original, Alternativen weg; Kopie-Fall → angelegt und wieder gelöscht; geänderte Kopie
  bleibt. Validator-Negativfälle (Sperrliste, Kette, Duplikat, unbekanntes Template,
  ungültiger Name/Slug, leere Gruppen, unbekannte gid). Am Ende alle `SELFTEST_%`-Templates
  und Testzeilen per `LIKE`/ID entfernen.

## Schritt 5 – Laufzeit-Hooks
- [x] **Ziel:**
  - `grouptemplates_handle_global_start()`: Namen aus
    `$cache->read('grouptemplates')['templates']` an global `$templatelist` anhängen
    (Komma-Handling wie `global.php`: wenn gesetzt und nicht leer, `','` voran).
  - `grouptemplates_handle_viewer()`: `$mybb->user['grouptemplate'] =
    grouptemplates_apply('viewer', $mybb->user)`.
  - `grouptemplates_handle_postbit(array $post): array`: `$post['grouptemplate'] =
    grouptemplates_apply('post', $post)`; `return $post`.
  - `grouptemplates_handle_profile()`: `global $memprofile`; analog.
  - `grouptemplates_handle_memberlist(array $user): array`: analog, `return $user`.
  - Früher Ausstieg, wenn die Map des Kontexts leer ist (dann `grouptemplate` = `[]`).
- **Dateien:** `inc/plugins/grouptemplates.php`
- **Vorlage:** `conventions.md` §5 · `debugging.md` §17 (Timing/Prioritäten)
- **Abnahme:** `php -l`; Prioritäten per `grep add_hook` gegen `PROJECT_CONTEXT.md` prüfen.
  Verhalten in Schritt 9.

## Schritt 6 – ACP-Modul „Templates & Style → Group Templates"
- [x] **Ziel:** `admin/modules/style/grouptemplates.php`; Menü/Action/Permission über die
      `admin_style_*`-Hooks (analog `boilerplate.md` §12, Modul `style`, Link
      `index.php?module=style-grouptemplates`). `$lang->load('grouptemplates')` als erste
      Anweisung. Tabs über `grouptemplates_acp_nav_tabs(string $active, int $switch_id = 0):
      void`, jede `description` nicht leer: `switches` (Übersicht), `add_switch`
      (Schaltstelle hinzufügen); kontextabhängig sichtbar: `edit_switch`, `alternatives`
      (Alternativen einer Schaltstelle), `add_alternative`, `edit_alternative`.
  - **Übersicht Schaltstellen:** `Table` – Template-Set (Name, `-1` = „Globale Templates"),
    Kontext, Ziel-Template, Standard-Template (+ Kennung), Alternativen (Anzahl + Namen in
    Reihenfolge), Status (aus `grouptemplates_get_switch_status()`: „Marker aktuell" /
    „Marker fehlt – Template manuell geändert" / „Marker veraltet" / „Template fehlt" /
    „Kopie vom Plugin angelegt"), Optionen (Alternativen verwalten, Bearbeiten, Löschen).
    Sortierung Set, Ziel. Leerzustand mit Hinweis.
  - **Schaltstelle hinzufügen:** `Form` (POST): Template-Set (Select: alle `templatesets` +
    `-1`), Kontext (Select; Beschreibung nennt, **welche Templates im Kontext wirksam sind**),
    Ziel-Template, Standard-Template, Standard-Kennung (Default `default`). Speichern →
    Validieren (`output_inline_error`), `insert_query`, `grouptemplates_template_write_marker()`,
    Cache neu, `log_admin_action()`, Flash, Redirect **direkt zu „Alternativen"** der neuen
    Schaltstelle.
  - **Schaltstelle bearbeiten:** Standard-Template + Standard-Kennung änderbar; Set/Kontext/
    Ziel nur angezeigt (Hinweis: zum Ändern löschen und neu anlegen). Marker neu schreiben.
  - **Schaltstelle löschen:** GET → `$page->output_confirm_action()`; POST +
    `verify_post_check` → `grouptemplates_template_remove_marker()`, Alternativen + Zeile
    löschen, Cache neu, Log, Flash mit Ergebnis.
  - **Alternativen einer Schaltstelle:** Kopfzeile mit Set/Ziel/Standard; `Table` –
    Sortierung (editierbares Zahlenfeld, Button „Reihenfolge speichern" per POST),
    Template, Kennung, Gruppen (Namen aus `$groupscache`, `-1` = „Alle Gruppen"),
    Gruppenmodus, Optionen (Bearbeiten, Löschen). Hinweis zur Auswahlregel („erste passende
    Alternative gewinnt, sonst Standard; Gäste = Gruppe Gäste").
  - **Alternative hinzufügen/bearbeiten:** Template, Kennung, Gruppen
    (`$form->generate_group_select('usergroups[]', …, ['multiple' => true, 'size' => 8])`
    plus Checkbox „Alle Gruppen" → `-1`), Gruppenmodus (Select mit Erklärung der drei Modi),
    Sortierung. Speichern → Validieren, Insert/Update, Marker neu, Cache neu, Log, Redirect
    zur Alternativenliste.
  - **Alternative löschen:** Bestätigung wie oben, danach Marker neu, Cache neu.
  - Ausgaben mit `htmlspecialchars_uni()`, IDs `(int)`, Strings `escape_string`,
    Gruppenlisten als int-Array → `implode(',')`.
- **Dateien:** `admin/modules/style/grouptemplates.php`, `inc/plugins/grouptemplates.php`
  (Menü-/Action-/Permission-Handler)
- **Vorlage:** `boilerplate.md` §12 · `debugging.md` §3, §4, §5 · Methodenabgleich gegen
  `admin/modules/style/templates.php` bzw. das ACP-Modul von Theme Variants auf dem Testserver
- **Abnahme:** `php -l`; genutzte Form-/Table-/Page-Methoden (u. a. `generate_group_select`,
  `output_confirm_action`, `output_inline_error`) per Grep im Server-Core auf Existenz und
  Signatur prüfen; **manueller Klicktest an Nutzer übergeben** (`testing.md` §8).

## Schritt 7 – Sprachdateien (nur Admin; englisch + deutsch_du)
- [x] **Ziel:** alle `$lang->grouptemplates_*`-Keys: Menü, Tabs + Tab-Beschreibungen,
      Spalten, Formularfelder + Beschreibungen, Kontextnamen + „wirksam für"-Beschreibungen,
      Gruppenmodi + Erklärungen, Status-Texte, Validierungsfehler, Lösch-Bestätigungen,
      Flash-Meldungen (inkl. `copy_kept`/`target_missing`), Permission-Text, Auswahlregel-
      Hinweis.
- **Dateien:** `inc/languages/english/admin/grouptemplates.lang.php`,
  `inc/languages/deutsch_du/admin/grouptemplates.lang.php`
- **Vorlage:** `boilerplate.md` §13
- **Abnahme:** `<dev-kit>/scripts/lang_check.ps1` bzw. Grep aus `testing.md` §6
  ohne fehlende Keys; beide Sprachen gleiche Key-Menge.

## Schritt 8 – Selbsttests finalisieren
- [x] **Ziel:** `tests/grouptemplates/cli-plugin.php` und `tests/grouptemplates/cli-selftest.php` vollständig;
      hinterlassen keine `SELFTEST_%`-Templates/-Zeilen.
- **Vorlage:** `testing.md` §3, §4, §7
- **Abnahme:** Lifecycle grün, Selbsttest „0 failed".

## Schritt 9 – Integrationstest auf dem Test-Forum
- [ ] **Ziel:** echtes Verhalten in Theme 38 und 40 (beide Template-Set 32) nachweisen.
  **Stand 2026-09-27:** erledigt 1–4, 6 (außer TVTEST), 7, 8. Offen: 5 (Theme-Variants-
  Fixture wartet auf Freigabe), 9 (Abstimmung, ob die zwei Schaltstellen stehen bleiben).
  Mehrere Alternativen / Umsortieren / Modus `any` sind im Selbsttest mit echtem DB- und
  Laufzeit-Roundtrip abgedeckt.
  1. **Backup** der Set-32-Templates `postbit` und `member_profile` (Scratchpad bzw.
     `tests/backup/`, per `.gitignore` ausgeschlossen) vor jeder Änderung.
  2. Plugin per `cli-plugin.php install`.
  3. Schaltstellen per CLI mit denselben Funktionen wie das ACP anlegen (entspricht der
     heutigen H4W-Logik):
     - Set 32 · `post` · Ziel `postbit` · Standard `postbit_charaacc_a` (Kennung `chara`) ·
       Alternative `postbit_useracc_a`, Kennung `user`, Gruppen 43, 32, 1, Modus `primary`.
     - Set 32 · `profile` · Ziel `member_profile` · Standard `member_profile_chara_b`
       (`chara`) · Alternative `member_profile_user_b`, `user`, Gruppen 43, 32, `primary`.
  4. Per DB einen Thread mit Beiträgen von Accounts beider Arten ermitteln sowie je eine uid.
     Erkennungsmerkmale: je ein String, der nur im User- bzw. nur im Chara-Template vorkommt
     (per Diff ermitteln), analog Profil.
  5. **Theme Variants:** In der Test-DB existiert derzeit **keine** Override-Zeile für
     `postbit_charaacc_a`. **Vor dem Anlegen den Nutzer fragen.** Nach Freigabe: Template
     `tv_postbit_charaacc_always` (Set 32, Inhalt = `postbit_charaacc_a` + `<!-- TVTEST -->`)
     und `themevariants`-Zeile (tid 40, `override`, target `postbit_charaacc_a`, variant
     `tv_postbit_charaacc_always`, active 1) anlegen, Theme-Variants-Cache über dessen eigene
     Rebuild-Funktion neu bauen. Nach dem Test entfernen, sofern der Nutzer nichts anderes sagt.
  6. Prüfen (curl als Gast mit `&theme=38`/`&theme=40`, vorher `themes.allowedgroups` auf
     Gäste prüfen; sonst CLI-Render-Harness, das `$theme` setzt, `global_intermediate`
     auslöst und `build_postbit()` aufruft):
     - Thread horizontal: User-Beitrag → User-Merkmal, Chara-Beitrag → Chara-Merkmal, **kein**
       Beitrag doppelt; Theme 40 Chara-Beitrag enthält `TVTEST`, Theme 38 nicht.
     - Mehrere Alternativen: Wegwerf-Schaltstelle auf SELFTEST-Templates mit zwei
       überlappenden Alternativen → erste gewinnt; nach Umsortieren die andere.
     - Modus `any`: Account mit passender Zusatzgruppe → Alternative; bei `primary` nicht.
     - klassisch (`postbit_classic`): ohne Schaltstelle unverändert; mit Wegwerf-Schaltstelle
       korrekt umgeschaltet (CLI-Render mit `$mybb->user['classicpostbit'] = 1`), danach löschen.
     - Vorschau/PN/Ankündigung: CLI-Render `build_postbit($post, 1|2|3)`.
     - Gastbeitrag (uid 0) → Alternative mit Gruppe 1.
     - Profil beider Accounts in Theme 38 und 40.
     - `{$post['grouptemplate']['postbit']}` = `user`/`chara` (CLI-Render).
     - Sets 30/31 (z. B. Theme 26, 36): **unverändert**.
  7. **Deaktivieren** (Plugin-Cache ohne `grouptemplates`): Seiten wie vor der Installation
     (alter PHP-Block hinter dem Marker greift). Wieder aktivieren.
  8. Server-Errorlog / PHP-Warnings prüfen.
  9. Test-Fixtures entfernen; mit dem Nutzer abstimmen, ob die zwei echten Schaltstellen auf
     dem Test-Forum stehen bleiben.
- **Vorlage:** `testing.md` §5, §7
- **Abnahme:** alle Punkte mit Zählwerten (`grep -c`) dokumentiert; offene Punkte
  (ACP-Klicktest, echte Klassik-Templates) explizit an den Nutzer übergeben.

---

Begleitdateien (README mit Migrationsanleitung für `postbit`/`member_profile` in Set 32 inkl.
Backup-Hinweis, Erklärung Kontexte/Auswahlregel/Gruppenmodi, Beispielkonfiguration
„User- vs. Chara-Account"; PLUGIN_POST.html, CHANGELOG, LICENSE, FUNDING,
.gitignore/.claudeignore) macht **Phase 3 (Review)**.
