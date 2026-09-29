<?php

/**
 * Adds entities from a separate process for DbTestCase::testConcurrentInsertIds(),
 * and outputs a JSON list of the IDs returned for each batch.
 */

use DevTheorem\PeachySQL\PeachySql;
use DevTheorem\Phaster\Test\DbTestCase;
use DevTheorem\Phaster\Test\src\ConcurrentRows;

require __DIR__ . '/bootstrap.php';

if (!isset($argv) || count($argv) !== 6) {
    throw new Exception('Usage: php insert-worker.php <test class> <worker> <batches> <rows per batch> <start time>');
}

[, $class, $worker, $batches, $rowsPerBatch, $startTime] = $argv;

if (!is_subclass_of($class, DbTestCase::class)) {
    throw new Exception("{$class} is not a DbTestCase");
}

$entities = new ConcurrentRows(new PeachySql($class::createConnection()));
$ids = [];

// wait until every worker has connected, so the inserts run concurrently
if ((float) $startTime > microtime(true)) {
    time_sleep_until((float) $startTime);
}

for ($batch = 0; $batch < (int) $batches; $batch++) {
    $rows = [];

    for ($seq = 0; $seq < (int) $rowsPerBatch; $seq++) {
        $rows[] = ['worker' => (int) $worker, 'batch' => $batch, 'seq' => $seq];
    }

    $ids[] = $entities->addEntities($rows);
}

echo json_encode($ids);
