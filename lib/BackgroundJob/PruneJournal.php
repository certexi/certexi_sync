<?php

// SPDX-FileCopyrightText: 2026 Red Sustentable Internacional S.A. de C.V.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OCA\CertexiSync\BackgroundJob;

use OCA\CertexiSync\Service\Journal;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class PruneJournal extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private Journal $journal,
	) {
		parent::__construct($time);
		$this->setInterval(24 * 3600);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$this->journal->prune();
	}
}
