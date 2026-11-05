# Group Templates – Project Context

## Identität
- **Name:** Group Templates
- **Codename / Dateiname:** `grouptemplates` (Präfix: `grouptemplates_`)
- **Version:** 1.1.0
- **Autor:** pand0rica (GitHub: pand0rica)
- **Kurzbeschreibung:** Schaltet beliebige Templates abhängig von den Benutzergruppen des
  jeweiligen Accounts um (Beitragsautor, Profil, Mitgliederliste, Betrachter) – mit einem
  Standard-Template und beliebig vielen gruppenbasierten Alternativen, ohne PHP in Templates.
- **MyBB:** 1.8.x · **PHP:** 7.4 + 8.x

## Zweck & Zielgruppe
Foren (typisch RPG: User-Account vs. Chara-Account) unterscheiden Darstellungen heute oft per
PHP-in-Templates mit hartkodierten Gruppen-IDs, mehrfach und uneinheitlich. Das Plugin
verwaltet alle Umschaltungen („Schaltstellen") zentral auf einer ACP-Seite: Pro Schaltstelle
gibt es ein **Standard-Template** und beliebig viele **Alternativen**, jede mit eigener
Gruppenliste und eigenem Gruppenmodus. Zur Laufzeit tauscht das Plugin per Hook den
Template-Cache aus, sodass MyBB selbst das passende Template im Original-Scope auswertet.

**Das Plugin ist forenunabhängig und generisch.** Es kennt keinen Begriff „User/Chara", keine
Gruppen-IDs als Default und keinen Forennamen. Die User/Chara-Unterscheidung von H4W ist nur
eine Konfiguration (Standard = Chara-Template, Alternative = User-Template für 43/32/1).

Ursprünglicher Anforderungstext: `plugin-prompt-accounttype.md` (historisch – verbindlich ist
diese Datei; die dortige User/Chara-Definition wurde bewusst durch das generische Modell
ersetzt).

## Begriffe
- **Schaltstelle (switch):** Template-Set + Kontext + Ziel-Template + Standard-Template +
  Standard-Kennung.
- **Alternative (alternative):** gehört zu einer Schaltstelle; Template + Kennung +
  Gruppenliste + Gruppenmodus + Sortierung.
- **Kennung (slug):** frei wählbarer, CSS-tauglicher Bezeichner (z. B. `user`, `chara`,
  `team`). Wird als Template-Variable bereitgestellt, damit Templates CSS-Klassen setzen
  können. Die Standard-Kennung wird pro Schaltstelle festgelegt (Default `default`).

## Auswahlregel (pro Schaltstelle und Account)
1. Alternativen in Sortierreihenfolge (`disporder` ASC, dann `id` ASC) durchgehen.
2. Eine Alternative **passt**, sobald **eine** der nach ihrem Gruppenmodus geprüften Gruppen
   des Accounts in ihrer Gruppenliste steht (oder die Liste `-1` = alle Gruppen enthält).
3. Die **erste** passende Alternative gewinnt. Passt keine → Standard-Template.

Gruppenmodus (pro Alternative, `group_mode`):
| Wert | Geprüfte Gruppen des Accounts |
|---|---|
| `primary` | nur `usergroup` |
| `display` | `displaygroup`, falls > 0, sonst `usergroup` |
| `any` | `usergroup` + `displaygroup` + alle `additionalgroups` |

**Gäste / gelöschte Autoren (uid 0):** gelten immer als Gruppe **1** (Gäste), unabhängig vom
Modus. In `build_postbit()` ist `usergroup` für Gastbeiträge leer (LEFT JOIN) – daher über
`uid` entscheiden, nicht über `usergroup`. Wer Gäste wie eine Alternative behandeln will, wählt
dort die Gruppe „Gäste".

## Kernmechanik (verifiziert gegen Core auf dem Testserver)

Im passenden Hook wird `$templates->cache[<target>] = $templates->cache[<gewähltes Template>]`
gesetzt. MyBB wertet danach `eval(... $templates->get('<target>') ...)` im Original-Scope aus
(alle lokalen Variablen verfügbar). `$templates->get()` liefert aus dem Cache, wenn der Key
gesetzt ist (`inc/class_templates.php`, `get()` → `isset($this->cache[$title])`).

Fest hinterlegte **Kontexte** – frei wählbar sind nur Set, Ziel-, Standard- und
Alternativ-Templates, Kennungen und Gruppen:

| Kontext (`context`) | Hook(s) | Prio | Geprüfter Account | Wirksam für Templates, die … |
|---|---|---|---|---|
| `post` | `postbit`, `postbit_prev`, `postbit_pm`, `postbit_announcement` | -10 | `$post` (Hook-Argument) | nach dem Hook ausgewertet werden: `postbit`, `postbit_classic` (`inc/functions_post.php`: Hook-Block Z. ~905–917, `eval` Z. ~966/970) sowie Templates, die spätere `postbit`-Handler anderer Plugins auswerten |
| `profile` | `member_profile_start` | -10 | global `$memprofile` | im Profil ausgewertet werden – `$memprofile` ist bei `member_profile_start` geladen (`member.php` `get_user` Z. ~2035, Hook Z. ~2048); **alle** `member_profile*`-Templates liegen danach |
| `memberlist` | `memberlist_user` | -10 | `$user` (Hook-Argument) | pro Zeile nach dem Hook ausgewertet werden (`memberlist.php` Hook Z. ~385, `eval memberlist_user` Z. ~529) |
| `viewer` | `global_intermediate` | -5 | `$mybb->user` (Betrachter) | nach `global_intermediate` ausgewertet werden (header, footer, headerinclude, Seiten-Templates …) |

- Pro Beitrag/Zeile wird für **jede** Schaltstelle des Kontexts genau ein Template zugewiesen
  (Alternative oder Standard) → kein Zurücksetzen nötig, keine Leaks zwischen Beiträgen.
- Templates, die in `build_postbit()` **vor** dem Hook-Block ausgewertet werden
  (`postbit_author_user`, `postbit_avatar`, `postbit_online`, …), würden erst im **nächsten**
  Beitrag wirken → für Kontext `post` per fester Sperrliste abgelehnt.
- `viewer` mit Prio **-5**: nach Theme Variants (`global_intermediate`, -10), vor
  Headertabellen-Plugins mit Prio 0 (`debugging.md` §17).
- `post`/`profile`/`memberlist` laufen nach `global_intermediate`, also nach den
  Theme-Variants-Overrides → **Zusammenspiel mit Theme Variants funktioniert automatisch**
  (Theme Variants ersetzt z. B. `cache['postbit_charaacc_a']` für Theme 40; Group Templates
  kopiert später genau diesen Cache-Inhalt).

### Aktives Template-Set
Es gelten die Schaltstellen des Template-Sets des aktuellen Themes (`$theme['templateset']`)
**plus** die des globalen Sets `-1`; ein Eintrag im konkreten Set überschreibt einen
`-1`-Eintrag mit gleichem Ziel. Andere Sets werden ignoriert.

### Marker im Template
Beim Anlegen einer Schaltstelle setzt das Plugin an den **Anfang** des Ziel-Templates im
gewählten Set einen HTML-Kommentar, der die aktuelle Konfiguration zeigt:

```
<!-- grouptemplates: default=postbit_charaacc_a; alternatives=postbit_useracc_a -->
```

(mehrere Alternativen kommagetrennt in Sortierreihenfolge, keine Alternative → `alternatives=`).
Bei jeder Änderung an Schaltstelle oder Alternativen wird der Marker neu geschrieben. Der
bisherige Inhalt (z. B. ein alter PHP-Block) **bleibt dahinter erhalten** – er wird zur Laufzeit
komplett ersetzt und läuft daher nicht mit (keine doppelte Ausgabe). Beim Löschen der
Schaltstelle wird der Marker entfernt → altes Verhalten sofort zurück. Regex:
`/<!-- grouptemplates:[^>]*-->\R?/`.

Existiert das Ziel-Template im gewählten Set nicht (nur Master `-2` bzw. global `-1`), legt das
Plugin eine Kopie im Set an (Marker + Fallback-Inhalt, `created_copy = 1`). Beim Löschen wird
die Kopie entfernt, **sofern** ihr Inhalt nach Marker-Entfernung noch dem Fallback entspricht;
sonst bleibt sie stehen, die ACP-Meldung weist darauf hin.

## Datenbank-Tabellen
| Tabelle (`TABLE_PREFIX .`) | Zweck | Spalten |
|---|---|---|
| `grouptemplates_switches` | Schaltstellen | `id` INT AI PK · `sid` INT NOT NULL (`-1` = global) · `context` VARCHAR(20) · `target` VARCHAR(120) · `template_default` VARCHAR(120) · `slug_default` VARCHAR(50) DEFAULT 'default' · `created_copy` TINYINT(1) DEFAULT 0 · `dateline` INT · **UNIQUE KEY `sid_target` (`sid`,`target`)** |
| `grouptemplates_alternatives` | Alternativen je Schaltstelle | `id` INT AI PK · `switch_id` INT NOT NULL (KEY) · `template` VARCHAR(120) · `slug` VARCHAR(50) · `usergroups` TEXT (Komma-Liste int, `-1` = alle) · `group_mode` VARCHAR(10) (`primary`/`display`/`any`) · `disporder` INT DEFAULT 0 · `dateline` INT |

`VARCHAR(120)` entspricht `templates.title`. Engine/Charset nach `conventions.md` §4.
Löschen einer Schaltstelle löscht ihre Alternativen im Code mit (keine FK-Constraints).

### Datacache `grouptemplates`
Nach jeder Änderung im ACP (und bei install/activate) neu gebaut → zur Laufzeit **keine Query**:

```php
[
  'switches' => [
    '<context>' => [
      <sid> => [
        '<target>' => [
          'default'      => '<template>',
          'default_slug' => '<slug>',
          'alternatives' => [   // bereits sortiert
            ['template' => '<tpl>', 'slug' => '<slug>', 'groups' => [43, 32], 'all' => false, 'mode' => 'primary'],
          ],
        ],
      ],
    ],
  ],
  'templates' => ['<alle Standard- und Alternativ-Template-Namen, eindeutig>'],
]
```

## Settings
**Keine.** Alle Konfiguration liegt in den Schaltstellen/Alternativen (Gruppen und
Gruppenmodus pro Alternative, Gäste = Gruppe 1). Daher auch keine settinggroup.

## Hooks
| Hook | Handler | Prio | Zweck |
|---|---|---|---|
| `global_start` | `grouptemplates_handle_global_start` | -10 | Template-Namen aus dem Datacache an `$templatelist` anhängen (werden in `global.php` Z. ~463–480 mitgeladen → keine Extra-Query) |
| `global_intermediate` | `grouptemplates_handle_viewer` | -5 | Kontext `viewer` tauschen, `$mybb->user['grouptemplate']` setzen |
| `postbit` / `postbit_prev` / `postbit_pm` / `postbit_announcement` | `grouptemplates_handle_postbit` | -10 | Kontext `post` tauschen, `$post['grouptemplate']` setzen, `$post` zurückgeben |
| `member_profile_start` | `grouptemplates_handle_profile` | -10 | Kontext `profile` tauschen, `$memprofile['grouptemplate']` setzen |
| `memberlist_user` | `grouptemplates_handle_memberlist` | -10 | Kontext `memberlist` tauschen, `$user['grouptemplate']` setzen, `$user` zurückgeben |
| `admin_style_menu` | `grouptemplates_admin_menu` | 10 | Menüpunkt unter „Templates & Style" |
| `admin_style_action_handler` | `grouptemplates_admin_action_handler` | 10 | Action `grouptemplates` → `admin/modules/style/grouptemplates.php` |
| `admin_style_permissions` | `grouptemplates_admin_permissions` | 10 | Admin-Berechtigung |

ACP-Hooks nur bei `defined('IN_ADMINCP')`, Frontend-Hooks sonst.

## Template-Variablen (CSS-Hook ohne PHP)
Pro Kontext ein Array **Ziel-Template → Kennung** der gewählten Variante (Alternative oder
Standard):
- `{$post['grouptemplate']['postbit']}` (alle vier Postbit-Hooks)
- `{$memprofile['grouptemplate']['member_profile']}`
- `{$user['grouptemplate']['memberlist_user']}`
- `{$mybb->user['grouptemplate']['header']}`

Beispiel: `class="post post-{$post['grouptemplate']['postbit']}"` → `post-user` / `post-default`.
Kennungen sind validiert (`^[a-z0-9_-]{1,50}$`), daher HTML/CSS-sicher. Für Ziele ohne
Schaltstelle ist der Key nicht gesetzt (in Templates dann leer – dokumentieren).

## Öffentliche API (für andere Plugins)
- `grouptemplates_get_user_groups(array $user, string $mode): array` – geprüfte Gruppen-IDs
  laut Modus (uid 0 → `[1]`).
- `grouptemplates_match(array $user, array $alternative): bool`
- `grouptemplates_resolve(string $context, string $target, array $user): ?array` – liefert
  `['template' => …, 'slug' => …]` der gewählten Variante für das aktive Set, `null` ohne
  Schaltstelle.

Fehlende Keys (`displaygroup`, `additionalgroups`) per `isset()` tolerieren. Keine eigenen
Hooks in v1.0.

## Abhängigkeiten
Keine. Optionales Zusammenspiel mit „Theme Variants" (Tabelle `themevariants`, Override bei
`global_intermediate` Prio -10) funktioniert ohne Code-Kopplung über die Hook-Reihenfolge.
Template Conditionals / PHP in Templates ist nicht nötig.

## Berechtigungen
- ACP-Seite: Admin-Berechtigung `grouptemplates` im Modul `style`.
- Alle schreibenden ACP-Aktionen nur per POST; den `my_post_key` prüft `admin/index.php` selbst
  (Dev-Kit `debugging.md` #23, native Variante).
- Frontend: keine Aktionen, nur Anzeige-Logik.

## Dateilayout (prozedural, eine Datei + ACP-Modul)
```
inc/plugins/grouptemplates.php                     # info, lifecycle, API, runtime hooks, marker helpers
admin/modules/style/grouptemplates.php             # ACP page (switches + alternatives CRUD)
inc/languages/english/admin/grouptemplates.lang.php
inc/languages/deutsch_du/admin/grouptemplates.lang.php
tests/grouptemplates/cli-plugin.php                # lifecycle helper (testing.md §3)
tests/grouptemplates/cli-selftest.php              # logic + marker round trip (testing.md §4)
```
Keine Frontend-Templates, kein Stylesheet, keine Frontend-Sprachdatei, keine Settings.

## What NOT to change
- **Keine Template-Überschreibung außer dem Marker.** Alte Inhalte (PHP-Blöcke) werden nie
  automatisch gelöscht – das macht der Admin laut README von Hand, nach Backup.
- **Kontexte/Hooks sind fest im Code**, frei sind nur Set, Template-Namen, Kennungen, Gruppen.
- **Auswahlregel:** erste passende Alternative in Sortierreihenfolge gewinnt; sonst Standard.
- **Hook-Prioritäten** (`viewer` -5 nach Theme Variants -10; übrige -10, damit spätere
  Handler anderer Plugins schon den getauschten Cache sehen) nicht „normalisieren".
- **Keine Query pro Beitrag/Zeile.** Laufzeit liest nur den Datacache; fehlende Templates
  höchstens einmal pro Seite gesammelt nachladen (`$templates->cache('a,b')`). Gruppen eines
  Accounts pro Modus höchstens einmal pro Beitrag berechnen.
- **Fehlendes Template → Ziel unangetastet lassen** (nie leer ausgeben); fehlt nur eine
  Alternative, wird sie übersprungen.
- **`deactivate()` fasst keine Templates an** – der Originalinhalt steht hinter dem Marker,
  das Standardverhalten ist nach dem Deaktivieren sofort wieder da. Erst `uninstall()` entfernt
  Marker und angelegte Kopien.
- **Forenneutral und generisch:** kein User/Chara-Begriff, keine Gruppen-IDs als Default,
  kein Forenname in Bezeichnern.

## Testskripte auf dem Server
Die CLI-Tests liegen bewusst in `tests/grouptemplates/` (nicht direkt in `tests/`): Auf dem
Test-Forum teilen sich alle Plugins das Verzeichnis `tests/`, gleichnamige `cli-plugin.php`/
`cli-selftest.php` würden sich beim Deploy gegenseitig überschreiben. Aufruf aus dem Forum-Root:
`php tests/grouptemplates/cli-plugin.php install|uninstall|activate|deactivate|status` und
`php tests/grouptemplates/cli-selftest.php [sid]`.

## Version 1.1.0 (2026-10-30)

- **Templates einbinden:** `{$grouptemplates['name']}` rendert das Template „name“ beim ersten Zugriff im globalen Scope (bei einer Schaltstelle die für den Betrachter gewählte Variante, der Tausch passiert in `global_intermediate`). Templates, die in einer Funktion ausgewertet werden, brauchen dort `global $grouptemplates;`.
- **Leere Templates:** Standard und Alternativen dürfen leer sein („nichts ausgeben“), im ACP als „(leer)“ angezeigt.
- **Virtuelle Ziele:** Im Kontext „Betrachter“ muss das Ziel-Template nicht existieren; es gibt es dann nur über die Schaltstelle, eingebunden mit `{$grouptemplates['ziel']}`. Status im ACP: „Virtuelles Ziel“.
