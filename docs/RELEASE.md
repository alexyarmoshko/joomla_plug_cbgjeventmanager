# Release Notes

## 0.1.1 (2026-08-06)

**A build-workflow release with no functional changes.** Every shipped PHP, JavaScript and language file is byte-identical to 0.1.0; the only change to a packaged file is the `<release>` version in the manifest.

### Build

- The release package is now built from a **`git archive` export of the tag** rather than the working tree, and written by a vendored deterministic ZIP writer (`tools/jzip.php`) rather than `zip`. That removes the three things which previously let the builder's machine into the archive: working-tree line endings, file mtimes, and the host-OS and permission bits `zip` records.
- **The package is therefore reproducible**: rebuilding a tag produces the same bytes, and so the same `sha256`, on any machine. Verified byte-identical between Windows and Linux.
- The release is gated. Tagging refuses a dirty tree, an existing tag, or a version with no matching heading here; packaging refuses to run unless the checkout is a clean tree at the tag, and refuses command-line overrides of anything that decides the published bytes.
- The update descriptor is now generated beside the package as a build artifact. The copy tracked in the repository is a **template** whose `<sha256>` is a zero placeholder, so a template can never be published in place of a real descriptor.

### Notes

- **The package bytes differ from 0.1.0 even though its contents do not.** The previous archive stored separate directory entries; the deterministic writer emits only the listed files. The installed result is the same 15 files in the same layout.
- This release is not an update *notification* — Community Builder plugins have no update channel, from Joomla or from CB. Install the ZIP over the existing plugin through **Community Builder → Plugin Management**, as for 0.1.0. See the README's "Updating" section for why.

## 0.1.0 (2026-07-01)

First implementation of **Yak Shaver CB GJ Event Manager** (`plug_cbgjeventmanager`), a Community Builder GroupJive user plugin that lets group Moderators/Admins assign an event's owner from within the native event edit form — scoped per-group, without CB global-moderator rights.

### Features

- **"Change Owner" control** injected into the native GroupJive event edit form (existing events only) and a matching entry in the event ⋮ menu, shown only to eligible group Moderators/Admins (not to CB global moderators, who keep the native inline Owner field).
- **Searchable member picker** (typeahead) scoped to the event group's effective, active members, backed by a raw-JSON `owner/search` endpoint with its own token + native-equivalent access guard.
- **Assignment through the native save lifecycle**: the selected owner is applied in `gj_onBeforeUpdateEvent`, immediately before `EventTable::store()`, so normal GroupJive update hooks still run.
- **Configurable**: master enable, owner-assign level (group Moderator ≥2 default, or Admin ≥3), picker minimum characters and maximum results.

### Security

- Owner assignment re-verifies the Joomla form token in the save hook (the same table update hook is reachable from token-less publish/unpublish paths), resolves the actor from the CB session, and re-checks group status and target eligibility server-side. A forged or stale owner field drops only the owner change; the rest of an authorized save proceeds.
- The owner-search endpoint fails closed with controlled JSON if GroupJive Events is unavailable, instead of calling GroupJive Events classes blindly.
- Membership is validated **only at assignment time**; existing non-member owners are out of scope and never scanned, flagged, or altered.

### Requirements

- Joomla 5.x, PHP 8.1+
- Community Builder with GroupJive and the GroupJive **Events** plugin installed and active

### Notes

- No CB core files are modified; the plugin installs and uninstalls cleanly via the standard CB plugin installer.
- Released as `plug_cbgjeventmanager-v0-1-0.zip`; the update XML points at the GitHub release asset and includes SHA256 `ea098f735425fdd57c564fd7f131f243b36b622aad33b5ece82dd7565d288820`.
- Verified through live install feedback before release; the owner picker renders above the native Update Event/Cancel button row and remains inside the native form.
