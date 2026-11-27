<?php

// SPDX-FileCopyrightText: 2026 Red Sustentable Internacional S.A. de C.V.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OCA\CertexiSync\AppInfo;

use OCA\CertexiSync\Capabilities;
use OCA\CertexiSync\Listener\JournalListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'certexi_sync';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		// Class names as strings: the listeners are inert if Tables is absent.
		foreach ([
			'OCA\Tables\Event\RowAddedEvent',
			'OCA\Tables\Event\RowUpdatedEvent',
			'OCA\Tables\Event\RowDeletedEvent',
		] as $event) {
			$context->registerEventListener($event, JournalListener::class);
		}
		$context->registerMiddleware(\OCA\CertexiSync\Service\ArchiveWriteMiddleware::class, true);
		$context->registerCapability(Capabilities::class);
	}

	public function boot(IBootContext $context): void {
	}
}
