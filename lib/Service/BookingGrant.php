<?php
// SPDX-License-Identifier: AGPL-3.0-only
declare(strict_types=1);

namespace OCA\CertexiSync\Service;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\Tables\Service\PermissionsService;
use OCA\CertexiSync\BackgroundJob\DeliverBookings;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use OCP\IUserManager;
use OCA\DAV\CalDAV\BirthdayService;
use OCP\Lock\ILockingProvider;

final class BookingGrant {
    public function __construct(private IAppConfig $config, private CalDavBackend $dav,
        private IUserManager $users, private PermissionsService $permissions, private ILockingProvider $locks, private IJobList $jobs) {}

    public function origin(bool $required = true): string {
        $origin = $this->config->getValueString('certexi_sync', 'booking_origin', '');
        if ($origin === '' && !$required) return '';
        if (!preg_match('~^https://[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$~D', $origin)) {
            throw new \RuntimeException('Configure the HTTPS booking origin in Nextcloud administration');
        }
        return $origin;
    }

    public function calendar(string $owner, int $tableId, string $uri): ?int {
        if (!$this->users->get($owner)?->isEnabled() || $uri === BirthdayService::BIRTHDAY_CALENDAR_URI) return null;
        if (!$this->permissions->canManageTableById($tableId, $owner)) return null;
        $cal = $this->dav->getCalendarByUri('principals/users/'.$owner, $uri);
        if (!$cal || !empty($cal['{http://nextcloud.org/ns}deleted-at']) ||
            !empty($cal['{http://owncloud.org/ns}read-only'])) return null;
        $components = $cal['{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set'] ?? null;
        return in_array('VEVENT', $components?->getValue() ?? [], true) ? (int)$cal['id'] : null;
    }

    public function load(string $key): ?array {
        if (!preg_match('/^booking_[a-f0-9]{48}$/D', $key)) return null;
        $value = $this->config->getValueString('certexi_sync', $key, '', true);
        return $value === '' ? null : json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }

    public function retireRejected(string $key): void {
        $lock = 'certexi_sync/'.$key;
        $this->locks->acquireLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
        try {
            $grant = $this->load($key);
            // A manager can retry registration while the policy transaction is still pending.
            if ($grant && time() - $grant['registeredAt'] < 3600) return;
            $this->jobs->remove(DeliverBookings::class, $key);
            $this->config->deleteKey('certexi_sync', $key);
        } finally {
            $this->locks->releaseLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
        }
    }

    public function bind(string $owner, int $tableId, string $uri, int $revision): ?array {
        $id = $this->calendar($owner, $tableId, $uri);
        if ($id === null) return null;
        $origin = $this->origin();
        $key = 'booking_'.substr(hash('sha256', json_encode([$origin, $owner, $tableId, $id, $revision])), 0, 48);
        $lock = 'certexi_sync/'.$key;
        $this->locks->acquireLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
        try {
            $grant = $this->load($key);
            if ($grant === null) {
                $grant = ['owner'=>$owner, 'tableId'=>$tableId, 'uri'=>$uri, 'calendarId'=>$id, 'origin'=>$origin,
                    'token'=>bin2hex(random_bytes(32))];
            }
            $grant['registeredAt'] = time();
            $this->config->setValueString('certexi_sync', $key, json_encode($grant, JSON_THROW_ON_ERROR), true, true);
            $this->jobs->add(DeliverBookings::class, $key);
            return ['origin'=>$origin, 'digest'=>hash('sha256', $grant['token'])];
        } finally {
            $this->locks->releaseLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
        }
    }
}
