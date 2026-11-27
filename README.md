# Certexi Sync

A small Nextcloud app that lets [Certexi](https://certexi.com) Home nodes keep
[Nextcloud Tables](https://apps.nextcloud.com/apps/tables) in sync efficiently.

- **Change journal.** Listens to the Tables app's own row events (added,
  updated, deleted) and records `(sequence, table, row, operation)`. A node
  asks "what changed since N?" with one request instead of re-reading whole
  tables.
- **Batch writes.** Applies up to 500 row creates, updates and deletes in one
  request and one database transaction: all succeed or none do.

## Privacy and permissions

- Every read and write goes through the Tables app's own services, so an
  account can only see or change rows it already can in Tables.
- The journal stores row and table IDs only, never cell values, and is pruned
  after 30 days.
- The app adds no permissions and no settings. To limit it to some users, use
  **Apps → Certexi Sync → Limit to groups**.

## API

All endpoints are OCS (`/ocs/v2.php/apps/certexi_sync/api/v1`) and need a
signed-in user.

| Method | Path                 | Purpose                                                 |
| ------ | -------------------- | ------------------------------------------------------- |
| GET    | `/cursor`            | Latest journal position                                 |
| GET    | `/changes`           | Rows changed and deleted after a position, per table    |
| POST   | `/tables/{id}/batch` | Up to 500 creates, updates and deletes, one transaction |

Version 0.2.0 adds an authenticated GET at `/tables/{tableId}/views/{viewId}/rows/{rowId}/archive`. It checks the signed-in user’s view and row access before returning the archive flag. Tables API v1 row edits respect the Certexi lifecycle marker. When Certexi Forms already protects these edits, it retains ownership of the write lock.

The capability `certexi_sync.version` tells clients the app is present.

## Install

From **Apps → Integration → Certexi Sync**, or `occ app:install certexi_sync`.
Requires Nextcloud 30–34 with Tables 2.0 or later.

## Licence

The archive controller, state reader, and write middleware are distributed under the GNU Affero General Public License v3, as in Certexi Forms (see LICENSES/AGPL-3.0.txt). The remaining app code is distributed under the Apache License 2.0. Copyright 2026 Red Sustentable Internacional S.A. de C.V.
