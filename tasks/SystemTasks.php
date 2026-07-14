<?php
declare(strict_types=1);

use Crunz\Schedule;
use Symfony\Component\Lock\Store\FlockStore;

$home = getenv('HOME');
if (!is_string($home) || $home === '') {
    throw new RuntimeException('HOME is not available to the scheduler.');
}

$privateDir = $home . '/.rossi-tools';
$lockDir = $privateDir . '/cron-locks';
foreach ([$privateDir, $lockDir] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create scheduler directory: ' . $directory);
    }
    chmod($directory, 0700);
}

$projectDir = dirname(__DIR__);
$lockStore = new FlockStore($lockDir);
$schedule = new Schedule();

$schedule
    ->run(PHP_BINARY . ' ' . escapeshellarg($projectDir . '/bin/cron-health.php'))
    ->everyFiveMinutes()
    ->preventOverlapping($lockStore)
    ->description('Update the private ROSSI TOOLS scheduler health status.');

$schedule
    ->run(PHP_BINARY . ' ' . escapeshellarg($projectDir . '/bin/cron-maintenance.php'))
    ->daily()
    ->at('03:20')
    ->preventOverlapping($lockStore)
    ->description('Trim private scheduler logs when they grow too large.');

return $schedule;
