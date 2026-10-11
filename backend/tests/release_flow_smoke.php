<?php

declare(strict_types=1);

// Run after migrations: php tests/release_flow_smoke.php
require dirname(__DIR__).'/vendor/autoload.php';

use App\Calendar\Service\AssignAdventCalendarService;
use App\Calendar\Service\CalendarAvailability;
use App\Calendar\Service\CompleteAdventDayService;
use App\Calendar\Service\UserCalendarService;
use App\Kernel;
use App\Ornament\Entity\Ornament;
use App\Ornament\Service\UserTreeService;
use App\Ornament\Storage\LocalOrnamentImageStorage;
use App\Task\Entity\Task;
use App\Task\Storage\LocalImageStorage;
use App\Template\Entity\AdventTemplate;
use App\Template\Entity\AdventTemplateTask;
use App\Template\Service\TemplateService;
use App\User\Entity\User;

function releaseCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$kernel = new Kernel('prod', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$db->beginTransaction();
try {
    $user = new User('release-'.bin2hex(random_bytes(6)), 'Release smoke');
    $user->setPasswordHash(password_hash('temporary', PASSWORD_BCRYPT));
    $template = new AdventTemplate('Release calendar');
    $ornament = new Ornament('Release ornament');
    $ornament->setImagePath('ornaments/'.bin2hex(random_bytes(16)).'.svg');
    $ornament->setActive(true);
    foreach ([$user, $template, $ornament] as $entity) $em->persist($entity);
    for ($day = 1; $day <= 31; $day++) {
        $task = new Task('Task '.$day, 'Solve '.$day);
        $em->persist($task);
        $em->persist(new AdventTemplateTask($template, $day, $task));
    }
    $em->flush();

    $templates = new TemplateService($em);
    $copy = $templates->duplicate($template, 'Release copy');
    releaseCheck($copy->getUuid() !== $template->getUuid() && count($templates->assignments($copy)) === 31, 'Template copy is incomplete.');
    $calendar = (new AssignAdventCalendarService($em, $templates))->assign($user, $template, 2026);
    $availability = new CalendarAvailability('Europe/Moscow');
    $calendarService = new UserCalendarService($em, $availability, new LocalImageStorage('/uploads'));
    $images = new LocalOrnamentImageStorage('/uploads');
    $completion = new CompleteAdventDayService($em, $availability, $images);
    $treeService = new UserTreeService($em, $images);
    $now = new DateTimeImmutable('2027-01-01 12:00:00', new DateTimeZone('UTC'));
    releaseCheck($calendarService->summary($user, 2026, $now)['availableDays'] === 2, 'Only December 30 and 31 should remain available.');
    $user->setTestUser(true);
    releaseCheck($calendarService->summary($user, 2026, $now)['availableDays'] === 31, 'Test user should have all days available.');
    for ($day = 1; $day <= 31; $day++) {
        $opened = $calendarService->day($user, 2026, $day, $now);
        releaseCheck($opened['task']['title'] === 'Task '.$day, 'Assigned task mismatch.');
        $result = $completion->complete($user, 2026, $day, $now);
        releaseCheck($completion->complete($user, 2026, $day, $now) === $result, 'Completion retry changed reward.');
        $treeService->place($user, 2026, $result['reward']['uuid'], $day, $now);
    }
    $user->setTestUser(false);
    $summary = $calendarService->summary($user, 2026, $now);
    $tree = $treeService->summary($user, 2026);
    releaseCheck($summary['completedDays'] === 31 && $tree['completed'] === true, 'Final progress was not reached.');
    releaseCheck(count($tree['ornaments']) === 31 && count(array_unique(array_column($tree['ornaments'], 'treeSlot'))) === 31, 'Tree did not retain 31 unique placements.');
    releaseCheck((int) $db->fetchOne('SELECT COUNT(*) FROM user_ornament WHERE calendar_id = ?', [$calendar->getUuid()]) === 31, 'Database reward count differs.');
} finally {
    $db->rollBack();
    $kernel->shutdown();
}

echo "Release flow smoke checks passed.\n";
