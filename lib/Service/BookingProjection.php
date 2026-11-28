<?php
// SPDX-License-Identifier: AGPL-3.0-only
declare(strict_types=1);
namespace OCA\CertexiSync\Service;

use OCA\DAV\CalDAV\CalDavBackend;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;

final class BookingProjection {
    public function __construct(private CalDavBackend $dav, private IDBConnection $db, private ILockingProvider $locks) {}

    /** Apply the event and its receipt in the same Nextcloud database transaction. */
    public function apply(int $calendar, array $event): string {
        $id = $event['operationId'] ?? null;
        $revision = $event['revision'] ?? null;
        $status = $event['status'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',$id)
            || !is_int($revision) || $revision < 1 || !in_array($status,['held','confirmed','expired','cancelled'],true))
            throw new \UnexpectedValueException('Invalid booking delivery');

        $uid = 'booking-'.$id.'-0@certexi';
        $uri = $uid.'.ics';
        $lock = 'certexi_sync/booking/'.$calendar.'/'.$id;
        $this->locks->acquireLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
        try {
            $this->db->beginTransaction();
            try {
                $q=$this->db->getQueryBuilder();
                $q->select('revision','etag')->from('cxsync_booking_events')
                    ->where($q->expr()->eq('calendar_id',$q->createNamedParameter($calendar)))
                    ->andWhere($q->expr()->eq('operation_id',$q->createNamedParameter($id)));
                $r=$q->executeQuery(); $prior=$r->fetch(); $r->closeCursor();
                if ($prior && (int)$prior['revision'] >= $revision) {
                    $this->db->commit();
                    return 'delivered';
                }
                // Acquire the same database row lock a concurrent DAV editor needs, across supported NC versions.
                $q=$this->db->getQueryBuilder();
                $where=$q->expr()->andX($q->expr()->eq('calendarid',$q->createNamedParameter($calendar)),
                    $q->expr()->eq('calendartype',$q->createNamedParameter(CalDavBackend::CALENDAR_TYPE_CALENDAR)),
                    $q->expr()->eq('uri',$q->createNamedParameter($uri)));
                $q->update('calendarobjects')->set('etag','etag')->where($where)->executeStatement();
                $q=$this->db->getQueryBuilder();
                $q->select('etag','deleted_at')->from('calendarobjects')->where($q->expr()->eq('calendarid',$q->createNamedParameter($calendar)))
                    ->andWhere($q->expr()->eq('calendartype',$q->createNamedParameter(CalDavBackend::CALENDAR_TYPE_CALENDAR)))
                    ->andWhere($q->expr()->eq('uri',$q->createNamedParameter($uri)));
                $r=$q->executeQuery(); $object=$r->fetch(); $r->closeCursor();
                $etag=$object && $object['deleted_at']===null ? '"'.$object['etag'].'"' : '';
                if ($etag !== ($prior['etag'] ?? '')) {
                    $this->db->rollBack();
                    return 'conflict';
                }
                if ($status === 'confirmed') {
                    $utc=new \DateTimeZone('UTC');
                    $start=(new \DateTimeImmutable($event['start']))->setTimezone($utc);
                    $end=(new \DateTimeImmutable($event['end']))->setTimezone($utc);
                    if ($end <= $start) throw new \UnexpectedValueException('Invalid booking interval');
                    $data=implode("\r\n",['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Certexi//Booking//EN','BEGIN:VEVENT',
                        'UID:'.$uid,'DTSTAMP:'.gmdate('Ymd\THis\Z'),'DTSTART:'.$start->format('Ymd\THis\Z'),
                        'DTEND:'.$end->format('Ymd\THis\Z'),'SUMMARY:Reserved','STATUS:CONFIRMED','SEQUENCE:'.$revision,
                        'X-CERTEXI-OPERATION-ID:'.$id,'END:VEVENT','END:VCALENDAR','']);
                    $etag=$etag === '' ? $this->dav->createCalendarObject($calendar,$uri,$data)
                        : $this->dav->updateCalendarObject($calendar,$uri,$data);
                } elseif ($etag !== '') {
                    $this->dav->deleteCalendarObject($calendar,$uri);
                    $etag='';
                }
                $q=$this->db->getQueryBuilder();
                if ($prior) {
                    $q->update('cxsync_booking_events')->set('revision',$q->createNamedParameter($revision))
                        ->set('etag',$q->createNamedParameter($etag))
                        ->where($q->expr()->eq('calendar_id',$q->createNamedParameter($calendar)))
                        ->andWhere($q->expr()->eq('operation_id',$q->createNamedParameter($id)));
                } else {
                    $q->insert('cxsync_booking_events')->values(['calendar_id'=>$q->createNamedParameter($calendar),
                        'operation_id'=>$q->createNamedParameter($id),'revision'=>$q->createNamedParameter($revision),'etag'=>$q->createNamedParameter($etag)]);
                }
                $q->executeStatement();
                $this->db->commit();
                return 'delivered';
            } catch (\Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }
        } finally {
            $this->locks->releaseLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
        }
    }
}
