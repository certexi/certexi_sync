<?php
// SPDX-License-Identifier: AGPL-3.0-only
declare(strict_types=1);

namespace OCA\CertexiSync\Controller;

use OCA\CertexiSync\Service\ArchiveState;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

final class ArchiveController extends OCSController {
    public function __construct(IRequest $request, private ArchiveState $archive) {
        parent::__construct('certexi_sync', $request);
    }

    #[NoAdminRequired]
    public function state(int $tableId, int $viewId, int $rowId): DataResponse {
        $headers = ['Cache-Control' => 'private, no-store'];
        if (!$this->archive->canReadViewRow($tableId, $viewId, $rowId)) {
            return new DataResponse([], 404, $headers);
        }
        return new DataResponse(['tableId' => $tableId, 'viewId' => $viewId, 'rowId' => $rowId,
            'archived' => $this->archive->isArchived($tableId, $rowId)], 200, $headers);
    }
}
