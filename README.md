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

## Booking calendars

Version 0.3.0 adds calendar delivery for record bookings. In Nextcloud Administration → Additional settings, enter the trusted HTTPS origin of the Certexi booking authority. Administrators can also use `occ config:app:set certexi_sync booking_origin --value=https://certexi.example.com`. Enable normal Nextcloud background jobs. In Certexi, select the companion delivery mode and save the table's scheduling policy while signed in as both table manager and calendar owner.

The app generates a separate credential for each authorized table, calendar and policy revision, keeps it in sensitive app configuration and returns only its digest. The background job checks the current owner and table management grant before projecting the booking. Calendar events show “Reserved” and the time interval. The app records revisions and native event tags so retries are idempotent and externally edited events can be reported as conflicts. After outstanding reservations and deliveries are settled, disabling the table policy revokes the feed. Nextcloud removes rejected jobs and their credentials after a one-hour registration grace period. Clearing the trusted origin stops this integration for all calendars.
