<?php

// SPDX-FileCopyrightText: 2026 Red Sustentable Internacional S.A. de C.V.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OCA\CertexiSync\Listener;

use OCA\CertexiSync\Service\Journal;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event> */
class JournalListener implements IEventListener {
	public function __construct(
		private Journal $journal,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!method_exists($event, 'getRow')) {
			return;
		}
		$op = match (true) {
			is_a($event, 'OCA\Tables\Event\RowAddedEvent') => Journal::OP_CREATE,
			is_a($event, 'OCA\Tables\Event\RowUpdatedEvent') => Journal::OP_UPDATE,
			is_a($event, 'OCA\Tables\Event\RowDeletedEvent') => Journal::OP_DELETE,
			default => 0,
		};
		if ($op === 0) {
			return;
		}
		try {
			$row = $event->getRow(); // OCA\Tables\Model\Public\Row (Tables >= 0.8)
			$this->journal->record((int)$row->tableId, (int)$row->rowId, $op);
		} catch (\Throwable $e) {
			// Never break a Tables write because the journal failed; readers
			// recover with a full read (the node's daily safety pass).
			$this->logger->error('certexi_sync: could not journal a row change', ['exception' => $e]);
		}
	}
}
