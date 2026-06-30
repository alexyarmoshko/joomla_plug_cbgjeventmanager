# Yak Shaver CB GJ Event Manager

A standalone **Community Builder GroupJive** plugin (`plug_cbgjeventmanager`) that lets a group's **Moderators/Admins reassign the owner of an event** in their group — without making them site-wide CB global moderators.

It adds a single missing capability with least privilege and **touches no CB core files**, so it survives CB updates and can be uninstalled cleanly.

## Why

In CB GroupJive Events, a group Moderator/Admin can already edit, delete, publish, and unpublish events in their group. The only thing they can't do is **change an event's owner** — CB reserves that for global moderators, whose privileges span the entire CB suite (profiles, account deletion, Activity, Gallery, Blogs, PMS, every group). This plugin grants *only* per-group ownership reassignment.

Typical use: a club "Meet Officer" / secretary who schedules events and assigns each to the member who will run it, so that member can then manage their own event natively.

## What it does

- Adds a **"Change Owner"** control to the native event edit form (and a matching event ⋮ menu entry), shown only to group Moderators/Admins of that event's group.
- Lets them assign the event owner via a **searchable member picker** (typeahead), so it scales to large groups.
- Enforces the rule **"an event's owner must be a current, effective member of that event's group"** — in the search and again server-side on save.
- Does **not** show the control to CB global moderators — they already have CB's native inline Owner field, so it would be redundant. Their native behaviour is unchanged.

## How it works

- Hooks three GroupJive triggers — `gj_onDisplayEvent` (menu entry), `gj_onAfterDisplayEventEdit` (injects the owner picker into the native edit form), and `gj_onBeforeUpdateEvent` (assigns the selected owner immediately before GroupJive's own `EventTable::store()` call) — so the reassignment rides on the **native** event edit/save lifecycle. No HTML rewriting, no template edits, no isolated database write outside that lifecycle.
- All authorization (CB token, actor's effective group status, target membership/account status) is enforced server-side on every save; the UI is never the security boundary. A forged or stale owner field is silently ignored rather than applied.
- The only other surface touched is a small JSON search endpoint (`format=raw`) backing the typeahead — verified not to leak into the GroupJive Events module, which renders this trigger's return value as page content rather than a menu.

See [`docs/requirements.md`](docs/requirements.md) and [`docs/execution_plan.md`](docs/execution_plan.md) for the full design.

## Requirements

- Joomla 5.x (PHP 8.1+)
- Community Builder with **GroupJive** and the **GroupJive Events** plugin installed and active

## Installation

Install the ZIP from `installation/` via **Joomla → System → Install → Extensions**, then enable it in **Community Builder → Plugin Management**.

## Configuration

Plugin parameters (CB → Plugin Management → CB GJ Event Manager):

| Setting | Default | Description |
|---|---|---|
| Enabled | Yes | Master on/off. |
| Owner assign level | Moderator (≥2) | Minimum group status allowed to reassign owners: **Moderator (≥2)** or **Admin (≥3)**. |
| Picker min chars | 2 | Characters typed before the member search runs. |
| Picker max results | 20 | Maximum members returned per search query. |

## Repository layout

| Path | Purpose |
|---|---|
| `components/com_comprofiler/plugin/user/plug_cbgjeventmanager/` | Installable plugin source (manifest, entry, controller, library, templates, language). |
| `Makefile` | Build the installable ZIP and refresh the update-server XML. |
| `plug_cbgjeventmanager.update.xml` | Joomla update-server descriptor. |
| `docs/` | Requirements, execution plan, changelog, release notes. |
| `installation/` | Build output (gitignored). |

## Build

From a Unix-like shell (WSL / Git Bash) at the repo root:

- `make info` — print plugin name, version, and package paths.
- `make dist` — build the ZIP in `installation/` and update the update-server XML (version, download URL, sha256).
- `make clean` — remove the built ZIP.

## License

GNU General Public License v2 or later. © 2026 Yak Shaver — https://www.kayakshaver.com
