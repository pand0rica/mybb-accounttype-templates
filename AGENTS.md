# Group Templates – Agent Instructions

Dieses Repo wird mit dem **MyBB Plugin Dev Kit** entwickelt. Bevor du hier etwas tust:

1. Lies zuerst das Dev-Kit: **`<dev-kit>/AGENTS.md`** und halte dich an alle dort
   verlinkten Standards. Das Kit ist die kanonische Quelle für Konventionen, Boilerplate,
   Debugging und Testing.
2. Lies dann in diesem Repo: `PROJECT_CONTEXT.md` (Kontext) und – in der Umsetzungsphase –
   `IMPLEMENTATION_PLAN.md` (die Arbeitsschritte).

Das Dev-Kit wird **nicht** in dieses Repo kopiert; es wird per absolutem Pfad referenziert.

## Kurz-Kontext
Group Templates (`grouptemplates`) schaltet beliebige Templates abhängig von den Benutzergruppen
des jeweiligen Accounts um: pro Schaltstelle ein Standard-Template plus beliebig viele
Alternativen (je Gruppenliste + Gruppenmodus, erste passende gewinnt). Umgesetzt per Hook-Tausch
im Template-Cache (`$templates->cache[target] = cache[gewählt]`), ohne PHP in Templates.
Verwaltung auf einer ACP-Seite (Templates & Style); beim Anlegen setzt das Plugin einen
Marker-Kommentar ins Ziel-Template, beim Löschen entfernt es ihn. Forenunabhängig und generisch
– kein User/Chara-Begriff, keine Gruppen-IDs im Code. `plugin-prompt-accounttype.md` ist der
ursprüngliche Anforderungstext (historisch; verbindlich ist `PROJECT_CONTEXT.md`).

## Sprache
Antworten Deutsch, Code/Bezeichner Englisch. PHP 7.4 + 8.x. Keine externen Libraries.

## Phasen (der Nutzer löst sie einzeln aus)

| Nutzer sagt | Phase | Folge dieser Kit-Datei |
|---|---|---|
| „umsetzen / implementieren" | Umsetzung | `<dev-kit>/workflows/2-implement.md` + `IMPLEMENTATION_PLAN.md` |
| „reviewe das Projekt" | Review | `<dev-kit>/workflows/3-review.md` |
| „release das Projekt" | Release | `<dev-kit>/workflows/4-release.md` |

## Die 5 goldenen Regeln (Kurzform, Details im Kit)
Keine Core-Edits · `TABLE_PREFIX` · `$mybb->get_input()` · POST + `verify_post_check()` (Frontend; im ACP nur POST-Pflicht, Key prüft MyBB) ·
alles mit Präfix `grouptemplates_`. Ausgabe `htmlspecialchars_uni()`, SQL `escape_string()`/`(int)`.
