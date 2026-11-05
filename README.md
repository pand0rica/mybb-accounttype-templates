# Group Templates

Switch any MyBB template depending on the user groups of the account being shown – the post
author, the profile owner, a member list row or the viewer – without PHP in templates. Every
switch has a **default template** and any number of **alternatives**, each with its own group
list and group mode. Typical use in RPG forums: one postbit for player accounts, another one
for character accounts – configured centrally in the ACP instead of with hard-coded group IDs
in templates.

## Requirements
- MyBB 1.8.x
- PHP 7.4 or 8.x
- Dependencies: none. Works together with *Theme Variants* (variants are replaced first,
  Group Templates switches afterwards).

## Installation
1. Upload the files to your MyBB root directory, keeping the folder structure:
   - `inc/plugins/grouptemplates.php`
   - `admin/modules/style/grouptemplates.php`
   - `inc/languages/english/admin/grouptemplates.lang.php`
   - `inc/languages/deutsch_du/admin/grouptemplates.lang.php`
2. ACP → Configuration → Plugins → **Group Templates** → Install & Activate.
3. Give the admin permission **Group Templates** (Templates & Style) to the admin groups that
   should manage switches. The plugin has no settings.

## Usage

### Concepts
- **Switch** – template set + context + target template + default template + default slug.
- **Alternative** – belongs to a switch: template, slug, group list, group mode, order.
- **Slug** – a CSS-safe identifier (e.g. `user`, `chara`, `team`) that is exposed as a
  template variable so templates can set CSS classes.

**Selection rule:** alternatives are checked in their order; the **first** alternative whose
group list contains one of the account's checked groups wins (`-1` = all groups). If none
matches, the default template is used.

**Group mode** (per alternative):
| Mode | Checked groups |
|---|---|
| Primary group | `usergroup` only |
| Display group | `displaygroup` if set, otherwise `usergroup` |
| Any group | primary + display + all additional groups |

Guests and deleted authors (uid 0) always count as group 1 (*Guests*).

**Contexts** (fixed):
| Context | Account checked | Affects templates evaluated … |
|---|---|---|
| Post | post author (thread view, preview, PMs, announcements) | after the postbit hook, e.g. `postbit`, `postbit_classic`. Sub-templates evaluated earlier (`postbit_author_user`, `postbit_avatar`, …) cannot be switched and are rejected. |
| Profile | profile owner | all `member_profile*` templates |
| Member list | member of the row | `memberlist_user` |
| Viewer | the visitor | everything evaluated after `global_intermediate` (header, footer, page templates …) |

Only switches of the current theme's template set and of the global set apply; a switch in
the theme's set wins over a global one with the same target.

### Creating a switch
ACP → Templates & Style → **Group Templates** → *Add switch*: choose template set, context,
target template, default template and default slug. Then add alternatives on the switch's
page (template, slug, groups, group mode) and sort them.

When a switch is created, the plugin writes a marker comment at the top of the target
template, e.g.

```
<!-- grouptemplates: default=postbit_charaacc_a; alternatives=postbit_useracc_a -->
```

The old content of the target template (for example an old PHP block) stays below the
marker but is no longer used at runtime. **Remove it by hand once everything works – make a
backup first.** Deleting the switch removes the marker and the old behaviour is back
immediately. If the target template only exists in the master/global set, the plugin creates
a copy in the chosen set and removes it again on deletion as long as you did not edit it.

### Template variables (CSS hooks)
Per context the slug of the chosen variant is available:
- `{$post['grouptemplate']['postbit']}`
- `{$memprofile['grouptemplate']['member_profile']}`
- `{$user['grouptemplate']['memberlist_user']}`
- `{$mybb->user['grouptemplate']['header']}`

Example: `class="post post-{$post['grouptemplate']['postbit']}"` → `post-user` /
`post-default`. For targets without a switch the variable is empty.

### Including templates (since 1.1.0)
`{$grouptemplates['name']}` renders the template *name* where it is used – for a switch target
in the viewer context, the variant chosen for the viewer. Templates evaluated inside a
function need `global $grouptemplates;` there. In the viewer context the target does not have
to exist as a template (“virtual target”); it then only exists through the switch. Default
and alternatives may be empty to render nothing.

## Configuration
There are no settings – everything is configured per switch and alternative.

## Uninstallation
ACP → Configuration → Plugins → **Group Templates** → Deactivate, then Uninstall. This
removes both tables, the cache entry, the markers and the template copies the plugin created
(unless you edited them).

## Changelog
See [CHANGELOG.md](CHANGELOG.md).
