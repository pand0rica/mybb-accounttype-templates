# Prompt: MyBB-Plugin „H4W Account Type" – User- vs. Chara-Account ohne PHP im Template

> Diesen Text als Start-Prompt für eine neue Session verwenden. Er ist bewusst
> selbst-erklärend (kein Vorwissen aus anderen Chats nötig).

---

Schreib mir ein MyBB-1.8-Plugin **„H4W Account Type"** (Codename `h4waccounttype`),
das zentral festlegt, ob ein Account ein **User-Account** (Spieler/OOC) oder ein
**Chara-Account** (Inplay-Charakter) ist, und an allen Stellen, die heute danach
unterscheiden, das passende Template wählt – **ohne PHP-Blöcke in Templates**.

## Kontext / Ist-Zustand (den das Plugin ersetzen soll)
- Forum: MyBB 1.8, Netcup, **PHP 8.x** (7.4-kompatibel halten). Testumgebung:
  dem Test-Forum. Nie Core-Dateien ändern, nur Hooks.
- Im Forum läuft das Plugin „PHP in Templates / Template Conditionals". Damit
  wird die Account-Art heute direkt in Templates entschieden, mit hartkodierten
  Gruppen-IDs:
  - Template `postbit` (Template-Set 32 „ICW Claude"):
    ```php
    <?php
    if($post['usergroup'] == 43 OR $post['usergroup'] == 32 OR $post['usergroup'] == 1) {
        eval("\$pb = \"" . $templates->get("postbit_useracc_a") . "\";");
    } else {
        eval("\$pb = \"" . $templates->get("postbit_charaacc_a") . "\";");
    }
    ?>
    {$pb}
    ```
  - Template `member_profile` (Set 32):
    ```php
    <?php
    if($memprofile['usergroup'] == 43 OR $memprofile['usergroup'] == 32) {
        eval("\$profile_custom = \"" . $templates->get("member_profile_user_b") . "\";");
    } else {
        eval("\$profile_custom = \"" . $templates->get("member_profile_chara_b") . "\";");
    }
    ?>
    {$profile_custom}
    ```
  - Ältere Designs (z. B. Template-Set 30) haben die Unterscheidung inline per
    `<if $post['usergroup'] == 9 OR … then> … <else> … </if>` – mit **veralteten**
    Gruppen-IDs.
  - Gruppen: 43 = „User-Accounts", 32 = „Teamaccounts", 1 = Gäste.
- **Probleme:** Gruppenlisten stehen mehrfach und uneinheitlich in Templates
  (Postbit zählt Gäste mit, Profil nicht), PHP im Template ist schwer wartbar,
  die klassische Beitragsansicht (`postbit_classic`) unterscheidet gar nicht.
- Weitere Stellen mit derselben Unterscheidung sind wahrscheinlich (Mitgliederliste,
  Teamseite, Wer-ist-online, Plugin-Templates …). **Bitte zuerst in der Test-DB alle
  Templates (sid -1 und alle Sets) nach `usergroup` + `43`/`32`/`<?php` durchsuchen
  und mir die Fundstellen auflisten**, bevor festgelegt wird, welche Stellen das
  Plugin in Version 1 abdeckt.

## Ziel-Verhalten
1. **Eine zentrale Definition** der Account-Art.
   - ACP-Einstellung: Liste der Gruppen-IDs, die als **User-Account** gelten
     (Gruppenauswahl `groupselect`, Default: 43, 32).
   - Einstellung, ob Gäste/gelöschte Autoren (uid 0) als User-Account zählen.
   - Einstellung, welche Gruppe geprüft wird: nur primäre Gruppe (heutiges
     Verhalten), oder auch Zusatzgruppen / Anzeigegruppe. Default = primäre Gruppe.
   - Öffentliche Helper-Funktion, die auch andere Plugins nutzen können, z. B.
     `h4waccounttype_get(array $user): string` → `'user'` oder `'chara'` und
     `h4waccounttype_is_user(array $user): bool`.
2. **Template-Auswahl per Hook statt PHP im Template.** Prinzip (im Core geprüft:
   in `inc/functions_post.php` läuft `run_hooks("postbit", $post)` **vor**
   `eval(... $templates->get("postbit") ...)`): Das Plugin tauscht im jeweiligen
   Hook den Template-Cache aus, sodass MyBB anschließend selbst das richtige
   Template auswertet – im Original-Scope, mit allen lokalen Variablen
   (`$ignore_bit`, `$post_visibility`, …):
   ```php
   $templates->cache['postbit'] = $templates->cache['postbit_useracc_a'];
   ```
   - Beim Postbit pro Beitrag neu setzen (auch `postbit_classic`, und die Hooks
     `postbit_prev`, `postbit_pm`, `postbit_announcement` berücksichtigen).
   - Beim Profil über den passenden Hook in `member.php` (bitte prüfen, dass er
     vor dem `eval` von `member_profile` läuft).
   - Weitere Stellen je nach Fundliste aus dem Kontext-Abschnitt.
   - Sub-Templates müssen im Cache sein → über `$templatelist` vorladen oder
     `$templates->get()` einmal aufrufen.
3. **Konfigurierbare Zuordnung statt fester Template-Namen.** Pro „Schaltstelle"
   festlegen: Ziel-Template → Template für User-Account / Template für Chara-Account
   (z. B. `postbit` → `postbit_useracc_a` / `postbit_charaacc_a`,
   `member_profile` → `member_profile_user_b` / `member_profile_chara_b`).
   Entweder als ACP-Seite (Liste, hinzufügen, bearbeiten, löschen) oder – falls
   einfacher und ausreichend – als Einstellungen je Schaltstelle. Bitte begründet
   wählen. Welche Hooks es gibt, ist im Plugin fest hinterlegt; frei wählbar sind
   nur die Template-Namen.
4. **CSS-Hook ohne PHP:** Zusätzlich eine Template-Variable mit der Account-Art
   bereitstellen, z. B. `{$post['accounttype']}` und `{$memprofile['accounttype']}`
   (Werte `user`/`chara`), damit Templates Klassen wie
   `class="post post-{$post['accounttype']}"` setzen können.
5. **Zusammenspiel mit dem Plugin „Theme Variants"** (eigenes Plugin, Tabelle
   `themevariants`): Es ersetzt pro Theme `$templates->cache[<ziel>]` bei
   `global_intermediate` durch ein Varianten-Template (Override). Beispiel: Für
   Theme 40 wird `postbit_charaacc_a` durch `tv_postbit_charaacc_always` ersetzt.
   Da „H4W Account Type" erst später (im Postbit-/Profil-Hook) aus dem Cache kopiert,
   muss das automatisch zusammen funktionieren – **bitte explizit testen**: Theme 38
   bekommt die normalen, Theme 40 die überschriebenen Sub-Templates.
6. **Migration:** Nach Aktivierung sollen die Templates `postbit` und
   `member_profile` in Set 32 nur noch Platzhalter sein (die Inhalte kommen
   aus den Sub-Templates). **Die Templates nicht automatisch überschreiben**, sondern
   in der README beschreiben, was ich von Hand ändern muss. Vorher die alten
   Versionen sichern. Solange ein Template noch den PHP-Block enthält, darf nichts
   kaputtgehen (keine doppelte Ausgabe).

## Anforderungen
- Konventionen aus meiner globalen CLAUDE.md (Plugin-Struktur, Sprachdateien
  deutsch_du + english, `$db->escape_string`, `htmlspecialchars_uni`,
  README/PLUGIN_POST.html, LICENSE, FUNDING, .gitignore).
- Einstellungen per `settinggroups`/`settings`, Cache der Gruppen-IDs vermeiden
  unnötige Queries (Einstellungen sind ohnehin gecacht).
- Sauberes install/uninstall/activate/deactivate; Deaktivieren stellt das
  Standardverhalten wieder her (keine Cache-Tausche mehr).
- Performance: Pro Beitrag nur ein Array-Zugriff und eine Zuweisung, keine Queries.

## Vorgehen
1. Fundliste aller Templates mit Account-Art-Unterscheidung erstellen (siehe oben)
   und mit mir abstimmen, welche Stellen Version 1 abdeckt.
2. Kurz den Ansatz für Punkt 3 (ACP-Seite vs. Einstellungen) begründen.
3. Plugin umsetzen, auf dem Test-Forum mit einem User- und einem Chara-Account in
   Theme 38 und 40 testen (Thread-Ansicht horizontal + klassisch, Vorschau, PN,
   Profil).
