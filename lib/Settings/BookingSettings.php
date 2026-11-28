<?php
// SPDX-License-Identifier: AGPL-3.0-only
declare(strict_types=1);
namespace OCA\CertexiSync\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\Settings\ISettings;

final class BookingSettings implements ISettings {
    public function __construct(private IAppConfig $config) {}
    public function getForm(): TemplateResponse {
        return new TemplateResponse('certexi_sync','booking-admin',[
            'origin'=>$this->config->getValueString('certexi_sync','booking_origin',''),
        ]);
    }
    public function getSection(): string { return 'additional'; }
    public function getPriority(): int { return 70; }
}
