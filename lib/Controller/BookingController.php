<?php
// SPDX-License-Identifier: AGPL-3.0-only
declare(strict_types=1);

namespace OCA\CertexiSync\Controller;

use OCA\CertexiSync\Service\BookingGrant;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

final class BookingController extends OCSController {
    public function __construct(IRequest $request, private IUserSession $session, private BookingGrant $grants) {
        parent::__construct('certexi_sync', $request);
    }
    #[NoAdminRequired]
    public function bind(int $tableId, string $calendarUri): DataResponse {
        $headers = ['Cache-Control'=>'private, no-store'];
        $owner = $this->session->getUser()?->getUID();
        if (!$owner || $tableId < 1 || $calendarUri === '' || strlen($calendarUri)>255 || str_contains($calendarUri,'/'))
            return new DataResponse([], 403, $headers);
        $result = $this->grants->bind($owner, $tableId, $calendarUri);
        return new DataResponse($result ?? [], $result ? 200 : 403, $headers);
    }
}
