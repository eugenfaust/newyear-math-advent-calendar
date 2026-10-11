<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Calendar\Entity\AdventCalendar;
use App\Calendar\Entity\AdventDayProgress;
use App\Calendar\Service\CalendarAvailability;
use App\Task\Entity\Task;
use App\Template\Entity\AdventTemplate;
use App\User\Entity\User;

$user = new User('calendar_test', 'Calendar test');
$calendar = new AdventCalendar($user, new AdventTemplate('Test'), 2026);
$day = new AdventDayProgress($calendar, 31, new Task('Test', 'Test'));
$firstDay = new AdventDayProgress($calendar, 1, new Task('First', 'First'));
$availability = new CalendarAvailability('Europe/Moscow');

$check = static function (string $expected, \DateTimeImmutable $now) use ($availability, $day): void {
    $actual = $availability->status($day, $now);
    if ($actual !== $expected) {
        throw new \RuntimeException("Expected {$expected}, got {$actual} at {$now->format('c')}.");
    }
};

$before = new \DateTimeImmutable('2026-12-30T20:59:59Z');
$opening = new \DateTimeImmutable('2026-12-30T21:00:00Z');
$check('locked', $before);
$check('available', $opening);
$checkFirst = static function (string $expected, string $instant) use ($availability, $firstDay): void {
    $actual = $availability->status($firstDay, new \DateTimeImmutable($instant));
    if ($actual !== $expected) throw new \RuntimeException("Expected {$expected}, got {$actual} at {$instant}.");
};
$checkFirst('locked', '2026-11-30T20:59:59Z');
$checkFirst('available', '2026-11-30T21:00:00Z');
$checkFirst('available', '2026-12-03T20:59:59Z');
$checkFirst('expired', '2026-12-03T21:00:00Z');
$check('available', new \DateTimeImmutable('2027-01-02T20:59:59Z'));
$check('expired', new \DateTimeImmutable('2027-01-02T21:00:00Z'));
$user->setTestUser(true);
$check('available', new \DateTimeImmutable('2026-01-01T00:00:00Z'));
$check('available', $before);
$checkFirst('available', '2026-12-04T00:00:00Z');
$user->setTestUser(false);
$check('locked', $before);
$checkFirst('expired', '2026-12-04T00:00:00Z');
$day->complete($opening);
$check('completed', $before);
$check('completed', new \DateTimeImmutable('2027-01-05T00:00:00Z'));

echo "Calendar availability checks passed.\n";
