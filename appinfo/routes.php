<?php

// SPDX-FileCopyrightText: 2026 Red Sustentable Internacional S.A. de C.V.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

return [
	'ocs' => [
		['name' => 'api#cursor', 'url' => '/api/v1/cursor', 'verb' => 'GET'],
		['name' => 'api#changes', 'url' => '/api/v1/changes', 'verb' => 'GET'],
		['name' => 'api#batch', 'url' => '/api/v1/tables/{tableId}/batch', 'verb' => 'POST'],
	],
];
