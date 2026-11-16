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

The capability `certexi_sync.version` tells clients the app is present.

## Install

From **Apps → Integration → Certexi Sync**, or `occ app:install certexi_sync`.
Requires Nextcloud 30–34 with Tables 2.0 or later.

## Licence

Apache License 2.0. Copyright 2026 Red Sustentable Internacional S.A. de C.V.
