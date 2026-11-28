<?php
// SPDX-License-Identifier: AGPL-3.0-only
declare(strict_types=1);
namespace OCA\CertexiSync\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IAppConfig;
use OCP\IRequest;

final class AdminController extends OCSController {
    public function __construct(IRequest $request, private IAppConfig $config) { parent::__construct('certexi_sync',$request); }
    /** Nextcloud enforces administrator access and CSRF protection. */
    public function origin(string $origin): DataResponse {
        $origin=rtrim(trim($origin),'/');
        if ($origin!=='' && !preg_match('~^https://[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$~D',$origin)) return new DataResponse([],400);
        $this->config->setValueString('certexi_sync','booking_origin',$origin);
        return new DataResponse(['origin'=>$origin]);
    }
}
