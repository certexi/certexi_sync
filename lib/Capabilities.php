<?php

// SPDX-FileCopyrightText: 2026 Red Sustentable Internacional S.A. de C.V.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OCA\CertexiSync;

use OCP\Capabilities\ICapability;

class Capabilities implements ICapability {
	/** @return array{certexi_sync: array{version: int, batchLimit: int}} */
	public function getCapabilities(): array {
		return ['certexi_sync' => ['version' => 1, 'bookingDelivery' => 1, 'batchLimit' => Service\Journal::BATCH_LIMIT]];
	}
}
