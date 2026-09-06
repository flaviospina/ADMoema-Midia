<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
responder(200, ['ok' => true, 'frentes' => FRENTES]);
