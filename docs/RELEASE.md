# Release Notes

## 0.1.0 (unreleased)

First implementation of **Yak Shaver CB GJ Event Manager** (`plug_cbgjeventmanager`), a Community Builder GroupJive user plugin that lets group Moderators/Admins assign an event's owner from within the native event edit form — scoped per-group, without CB global-moderator rights.

### Features

- **"Change Owner" control** injected into the native GroupJive event edit form (existing events only) and a matching entry in the event ⋮ menu, shown only to eligible group Moderators/Admins (not to CB global moderators, who keep the native inline Owner field).
- **Searchable member picker** (typeahead) scoped to the event group's effective, active members, backed by a raw-JSON `owner/search` endpoint with its own token + native-equivalent access guard.
- **Assignment through the native save lifecycle**: the selected owner is applied in `gj_onBeforeUpdateEvent`, immediately before `EventTable::store()`, so normal GroupJive update hooks still run.
- **Configurable**: master enable, owner-assign level (group Moderator ≥2 default, or Admin ≥3), picker minimum characters and maximum results.

### Security

- Owner assignment re-verifies the Joomla form token in the save hook (the same table update hook is reachable from token-less publish/unpublish paths), resolves the actor from the CB session, and re-checks group status and target eligibility server-side. A forged or stale owner field drops only the owner change; the rest of an authorized save proceeds.
- Membership is validated **only at assignment time**; existing non-member owners are out of scope and never scanned, flagged, or altered.

### Requirements

- Joomla 5.x, PHP 8.1+
- Community Builder with GroupJive and the GroupJive **Events** plugin installed and active

### Notes

- No CB core files are modified; the plugin installs and uninstalls cleanly via the standard CB plugin installer.
- Not yet installed/verified on a live DDEV environment (see execution plan test matrix for the pending manual checks).
