<?php
// SPDX-License-Identifier: AGPL-3.0-only
declare(strict_types=1);
namespace OCA\CertexiSync\BackgroundJob;

use OCA\CertexiSync\Service\BookingGrant;
use OCA\CertexiSync\Service\BookingProjection;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Http\Client\IClientService;

final class DeliverBookings extends TimedJob {
    public function __construct(ITimeFactory $time, private BookingGrant $grants, private BookingProjection $projection,
        private IClientService $http) {
        parent::__construct($time);
        $this->setInterval(60);
        $this->setAllowParallelRuns(false);
    }

    protected function run($argument): void {
        $grant=$this->grants->load((string)$argument);
        if (!$grant || $this->grants->origin() !== $grant['origin']) return;
        $client=$this->http->newClient();
        $send=function(array $extra) use ($grant,$client,$argument): array {
            $response=$client->post($grant['origin'].'/api/booking/records/companion', [
                'allow_redirects'=>false,'timeout'=>10,'http_errors'=>false,
                'headers'=>['Authorization'=>'Bearer '.$grant['token'],'Content-Type'=>'application/json'],
                'body'=>json_encode(['tableId'=>$grant['tableId']]+$extra,JSON_THROW_ON_ERROR),
            ]);
            if ($response->getStatusCode() === 403) {
                // Registration happens before the platform commits its policy revision.
                $this->grants->retireRejected((string)$argument);
                return [];
            }
            if ($response->getStatusCode()!==200) throw new \RuntimeException('Booking delivery origin unavailable');
            return json_decode((string)$response->getBody(),true,flags:JSON_THROW_ON_ERROR);
        };
        for ($i=0;$i<12;$i++) {
            $event=$send([])['event'] ?? null;
            if ($event===null) break;
            if ($this->grants->calendar($grant['owner'],$grant['tableId'],$grant['uri']) !== $grant['calendarId']) return;
            $result=$this->projection->apply($grant['calendarId'],$event);
            $send(['receipt'=>['id'=>$event['id'],'result'=>$result]]);
        }
    }
}
