# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security
- ACP CSRF protection uses MyBB's native mechanism (dev kit `debugging.md` #23): write
  handlers only act on POST and `admin/index.php` validates the `my_post_key` itself –
  no own key check or helper.

### Changed
- Plugin website in `grouptemplates_info()` points to `pand0rica/mybb-accounttype-templates`.
- Added README, LICENSE, FUNDING, `.gitignore`, `.claudeignore`.

## [1.1.0] – 2026-10-30

### Added
- Including templates with `{$grouptemplates['name']}` (rendered in the global scope).
- Empty default and alternative templates (“render nothing”).
- Virtual targets in the viewer context.

## [1.0.0]

### Added
- Switches per template set and context (post, profile, member list, viewer) with a default
  template and ordered, group-based alternatives (primary / display / any group mode).
- Slugs exposed as template variables for CSS.
- Marker comment in the target template; template copy in the chosen set if needed.
- Datacache so the front end needs no extra queries.
- ACP module under Templates & Style; English and German (du) admin language files.
