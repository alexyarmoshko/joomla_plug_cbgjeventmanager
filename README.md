# Yak Shaver CB GJ Event Manager

A standalone **Community Builder GroupJive** plugin (`plug_cbgjeventmanager`) that lets a group's **Moderators/Admins reassign the owner of an event** in their group — without making them site-wide CB global moderators.

It adds a single missing capability with least privilege and **touches no CB core files**, so it survives CB updates and can be uninstalled cleanly.

## Why

In CB GroupJive Events, a group Moderator/Admin can already edit, delete, publish, and unpublish events in their group. The only thing they can't do is **change an event's owner** — CB reserves that for global moderators, whose privileges span the entire CB suite (profiles, account deletion, Activity, Gallery, Blogs, PMS, every group). This plugin grants *only* per-group ownership reassignment.

Typical use: a club "Meet Officer" / secretary who schedules events and assigns each to the member who will run it, so that member can then manage their own event natively.

## What it does

- Adds a **"Change Owner"** control to the native event edit form (and a matching event ⋮ menu entry), shown only to group Moderators/Admins of that event's group.
- Lets them assign the event owner via a **searchable member picker** (typeahead), so it scales to large groups.
- Validates, **at assignment time only**, that the chosen owner is a current, effective member of that event's group — in the search and again server-side on save. (Membership isn't a maintained invariant: events whose existing owner's membership later lapses are left untouched and are out of scope.)
- Does **not** show the control to CB global moderators — they already have CB's native inline Owner field, so it would be redundant. Their native behaviour is unchanged.

## How it works

- Hooks three GroupJive triggers — `gj_onDisplayEvent` (menu entry on existing events), `gj_onAfterDisplayEventEdit` (adds the owner picker inside the native edit form, existing events only; GroupJive renders it after the buttons and `owner-picker.js` moves it above them), and `gj_onBeforeUpdateEvent` (assigns the selected owner immediately before GroupJive's own `EventTable::store()` call) — so the reassignment rides on the **native** event edit/save lifecycle. No HTML rewriting, no template edits, no isolated database write outside that lifecycle.
- All authorization (form/CSRF token, actor's effective group status resolved from the session, target membership/account status) is enforced server-side on every save — the save hook re-verifies the token itself, since it also fires from token-less paths. The UI is never the security boundary; a forged or stale owner field is dropped (only the owner change — the rest of an authorized save proceeds).
- The only other surface touched is a small JSON search endpoint (`format=raw`) backing the typeahead; it enforces its own token and event-access checks before returning member data.
- The display trigger always returns an empty string after mutating the event menu, so GroupJive Events module surfaces do not render plugin content.

See [`docs/requirements.md`](docs/requirements.md) for the full design.

## Requirements

- Joomla 5.x (PHP 8.1+)
- Community Builder 2.12 with **GroupJive 4** and the **GroupJive Events** plugin installed and active. Release 0.2.0 dropped GroupJive 3.x; on 3.x the plugin loads but stays inert. Use 0.1.1 with GroupJive 3.x.

## Installation

Install the ZIP from the GitHub release (`plug_cbgjeventmanager-v<version>.zip`) through **Community Builder → Plugin Management**, on the **Upload Package File** tab, then enable it in the plugin list.

This is a Community Builder plugin, not a Joomla one. Joomla's own **System → Install → Extensions** will not accept it: the manifest root is `<cbinstall>`, which Joomla's installer does not recognise. CB has its own installer, and it is the only one that can install this package.

For local development builds, use the ZIP generated in `installation/dev/`.

## Updating

**There is no automatic update notification for this plugin, and there is no way to add one.** To update, install the newer ZIP over the existing one — Community Builder replaces the plugin in place.

This is a property of Community Builder, not an omission here. Joomla's update system only considers extensions registered in `#__extensions`, and CB plugins are registered in `#__comprofiler_plugin` instead, so Joomla never sees them. CB has its own version check in Plugin Management, but it reads a single hard-coded feed at `update.joomlapolis.net` — Joomlapolis's own product catalogue, with no third-party entries and no configuration to point it elsewhere.

Community Builder does read the manifest's `<release>` element, so Plugin Management shows this plugin's version correctly. It simply has nothing to compare it against, and so never flags an update.

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
| `Makefile` | Tag a release, and build the package reproducibly from that tag. |
| `tools/jzip.php` | Deterministic ZIP writer used by the build. Never shipped in the package. |
| `plug_cbgjeventmanager.update.xml` | Update-descriptor **template**; its `<sha256>` is a zero placeholder. |
| `docs/` | Requirements and release notes. |
| `build/`, `installation/` | Staging and build output (both gitignored). |

## Build

From a Unix-like shell (WSL / Git Bash) at the repo root. Needs only `php`, `git` and `make` — plus `node` for the JavaScript lint.

| Target | What it does |
|---|---|
| `make info` | Print the version, the archive root, and every path the build will read or write. |
| `make lint` | `php -l` every shipped PHP file and the packager, check the manifest and descriptor template parse as XML, and `node --check` the picker script. |
| `make test` | No automated harness exists; verification is manual. The target is kept so `release` has a uniform gate. |
| `make dist_dev` | Build a throwaway package into `installation/dev/` from the **working tree**, for installing on a test site. Generates no descriptor. |
| `make release` | Tag the version. Refuses a dirty tree, an existing tag, or a version with no `## <version>` heading in `docs/RELEASE.md`, then runs `test` and `lint`. |
| `make dist_release` | Build the publishable package into `installation/release/` from **the tag**, and generate the update descriptor beside it. Requires a clean tree with `HEAD` at the tag. |
| `make clean` | Remove `build/` and both package output directories. |

### Why the package is reproducible

The release package is built from `git archive` of the tag rather than the working tree, and written by `tools/jzip.php` rather than `zip`. That removes the three things which otherwise leak the builder's machine into the archive: working-tree line endings, file mtimes, and the host-OS and permission bits the `zip` binary writes. jzip stamps every entry from the tag's commit date and emits every header field itself.

The result is that anyone can rebuild the tag and get the same bytes, and therefore the same `<sha256>` the descriptor claims. Verified byte-identical between Windows (PHP 8.3.19) and Linux (PHP 8.3.6).

One caveat on the scope of that guarantee: entries are deflated at level 9, and deflate hands the bytes to whatever zlib PHP was built against. Measured stable across ordinary zlib versions, but a different *implementation* — zlib-ng, which some distributions ship as their zlib — compresses differently while still producing a valid archive. So the guarantee holds for the same packager, the same level, and a compatible zlib. Build with `ZIP_LEVEL=0` if a third party must be able to re-derive the hash without that assumption; it costs roughly 2.5x in size and depends on no compression library at all.

Dev packages are stamped from the **HEAD commit date**, not the wall clock, so two dev builds of an unchanged commit are identical. Note the property is *per commit*: committing anything at all restamps the package even when no shipped file changed.

`dist_release` requires a clean tree with `HEAD` at the tag, and refuses a command-line `ZIP_LEVEL`, `JZIP` or `PACKAGE_FILES`. This is not ceremony. `git archive` supplies only the *payload* files; the inventory, the packager, the compression level and the descriptor template are all read from the checkout. Building tag `X` from a later commit would produce bytes that a clean checkout of `X` cannot reproduce, with every other gate still passing. In the normal flow the condition is already met — `release` has just tagged `HEAD`. To rebuild an older release, check its tag out first.

### Cutting a release

1. Bump `<release>` in the manifest and `<version>` in the descriptor template, add the matching `## <version>` heading to `docs/RELEASE.md`, and commit.
2. `make release` — validates, then tags.
3. `make dist_release` — builds the package and writes `installation/release/plug_cbgjeventmanager.update.xml` beside it.
4. Upload **that exact ZIP** as the release asset. The descriptor and the asset must come from the same build.
5. Keep the generated descriptor with the release for reference. It is a build artifact and is deliberately not tracked here: it carries the checksum of a package built *from a tag in this repository*, so it can never live inside the tag it describes.

> The descriptor is **reference only** — nothing consumes it, for the reasons in [Updating](#updating). It is generated because it records the published checksum in a standard shape, and because it keeps this repository's build identical to the other extensions'. Do not expect it to drive an update.

> The tracked `plug_cbgjeventmanager.update.xml` is a template, not a published descriptor — its `<sha256>` is 64 zeros. `dist_release` refuses to run if that placeholder has been replaced, so a template cannot quietly be published in place of a real artifact.

> **The reproducible guarantee starts after 0.1.0.** That release was packaged by the retired `zip`-based build, so rebuilding tag `0.1.0` with `make dist_release` yields a different — and equally valid — archive that does **not** hash to the `sha256` published for it. Do not regenerate 0.1.0's descriptor; its published asset and checksum stand as they are.

Note that a `<cbinstall>` manifest spends `<version>` on the Community Builder compatibility version and `<release>` on the extension's own semver. Everything version-derived — the tag, the ZIP name, the download URL, the descriptor — comes from `<release>`.

## License

GNU General Public License v2 or later. © 2026 Yak Shaver — https://www.kayakshaver.com
