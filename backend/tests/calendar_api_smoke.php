<?php

declare(strict_types=1);

// Run in the backend container: php tests/calendar_api_smoke.php
require dirname(__DIR__).'/vendor/autoload.php';

use App\Calendar\Entity\AdventCalendar;
use App\Calendar\Entity\AdventDayProgress;
use App\Calendar\Service\CalendarAccessException;
use App\Calendar\Service\CalendarAvailability;
use App\Calendar\Service\CompleteAdventDayService;
use App\Calendar\Service\UserCalendarService;
use App\Kernel;
use App\Task\Entity\Task;
use App\Task\Storage\LocalImageStorage;
use App\Ornament\Storage\LocalOrnamentImageStorage;
use App\Template\Entity\AdventTemplate;
use App\User\Entity\AccessToken;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$kernel = new Kernel('prod', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
check($em instanceof EntityManagerInterface, 'Entity manager unavailable.');
$connection = $em->getConnection();
$connection->beginTransaction();
try {
    $owner = new User('calendar-owner-'.bin2hex(random_bytes(5)), 'Owner');
    $other = new User('calendar-other-'.bin2hex(random_bytes(5)), 'Other');
    $owner->setPasswordHash(password_hash('temporary', PASSWORD_BCRYPT));
    $other->setPasswordHash(password_hash('temporary', PASSWORD_BCRYPT));
    $other->setRoles(['ROLE_ADMIN']);
    $template = new AdventTemplate('Calendar API smoke');
    $task = new Task('Safe title', 'Safe content');
    $task->setCorrectAnswer('private answer');
    $task->setSolution('private solution');
    foreach ([$owner, $other, $template, $task] as $entity) $em->persist($entity);
    $calendar = new AdventCalendar($owner, $template, 2026);
    $em->persist($calendar);
    for ($day = 1; $day <= 31; $day++) $em->persist(new AdventDayProgress($calendar, $day, $task));
    $em->flush();

    $availability = new CalendarAvailability('Europe/Moscow');
    $images = new LocalImageStorage('/uploads');
    $service = new UserCalendarService($em, $availability, $images);
    $time = static fn (string $value): DateTimeImmutable => new DateTimeImmutable($value, new DateTimeZone('UTC'));

    check($availability->currentYear($time('2026-12-31 20:59:59')) === 2026, 'Wrong year before local midnight.');
    check($availability->currentYear($time('2026-12-31 21:00:00')) === 2027, 'Wrong year after local midnight.');
    $cases = [
        ['2026-11-30 20:59:59', 0],
        ['2026-11-30 21:00:00', 1],
        ['2026-12-03 20:59:59', 3],
        ['2026-12-03 21:00:00', 3],
        ['2026-12-30 20:59:59', 3],
        ['2026-12-30 21:00:00', 3],
        ['2026-12-31 20:59:59', 3],
        ['2026-12-31 21:00:00', 2],
        ['2027-01-02 21:00:00', 0],
    ];
    foreach ($cases as [$instant, $expected]) {
        $summary = $service->summary($owner, 2026, $time($instant));
        check($summary['availableDays'] === $expected, "Wrong availability at $instant.");
        check(count($summary['days']) === 31, 'Missing days in summary.');
        check(!str_contains(json_encode($summary, JSON_THROW_ON_ERROR), 'Safe content'), 'Summary disclosed a task.');
    }

    try { $service->day($owner, 2026, 1, $time('2026-11-30 20:59:59')); throw new RuntimeException('Locked day opened.'); }
    catch (CalendarAccessException $error) { check($error->errorCode === 'ADVENT_DAY_LOCKED' && $error->httpStatus === 403, 'Wrong locked-day error.'); }
    check($calendar->getStartedAt() === null, 'Locked day started calendar.');
    $first = $em->getRepository(AdventDayProgress::class)->findOneBy(['calendar' => $calendar, 'day' => 1]);
    check($first->getOpenedAt() === null, 'Locked day recorded opening.');

    $opened = $service->day($owner, 2026, 1, $time('2026-11-30 21:00:00'));
    check($opened['openedAt'] === '2026-11-30T21:00:00Z', 'First opening not recorded.');
    check($calendar->getStartedAt()?->format('Y-m-d\TH:i:s\Z') === $opened['openedAt'], 'Calendar start not recorded.');
    check(!array_key_exists('correctAnswer', $opened['task']) && !array_key_exists('solution', $opened['task']), 'Private task data disclosed.');
    $completedProgress = $em->getRepository(AdventDayProgress::class)->findOneBy(['calendar' => $calendar, 'day' => 2]);
    $completedProgress->complete($time('2026-12-02 12:00:00'));
    $em->flush();
    $completed = $service->day($owner, 2026, 2, $time('2026-12-05 12:00:00'));
    check($completed['status'] === 'completed', 'Completed day was not readable after the deadline.');
    check($completed['task']['correctAnswer'] === 'private answer' && $completed['task']['solution'] === 'private solution', 'Completed day did not reveal its answer and solution.');
    $reopened = $service->day($owner, 2026, 1, $time('2026-12-02 12:00:00'));
    check($reopened['openedAt'] === $opened['openedAt'], 'Repeated opening changed openedAt.');
    try { $service->day($owner, 2026, 1, $time('2026-12-03 21:00:00')); throw new RuntimeException('Expired day opened.'); }
    catch (CalendarAccessException $error) { check($error->errorCode === 'ADVENT_DAY_EXPIRED' && $error->httpStatus === 403, 'Wrong expired-day error.'); }
    check($first->getCompletedAt() === null, 'Expired day was completed.');

    try { $service->day($owner, 2026, 31, $time('2026-12-30 20:59:59')); throw new RuntimeException('Future day opened.'); }
    catch (CalendarAccessException $error) { check($error->errorCode === 'ADVENT_DAY_LOCKED', 'Future day returned wrong error.'); }
    check($service->day($owner, 2026, 31, $time('2027-01-01 00:00:00'))['status'] === 'available', 'Day 31 should remain available on January 1.');
    try { $service->day($owner, 2026, 31, $time('2027-01-03 00:00:00')); throw new RuntimeException('Expired December 31 opened.'); }
    catch (CalendarAccessException $error) { check($error->errorCode === 'ADVENT_DAY_EXPIRED', 'Wrong expiration after December.'); }

    foreach (['summary', 'day'] as $method) {
        try {
            $method === 'summary' ? $service->summary($other, 2026, $time('2026-12-01')) : $service->day($other, 2026, 1, $time('2026-12-01'));
            throw new RuntimeException('Foreign calendar disclosed.');
        } catch (CalendarAccessException $error) {
            check($error->errorCode === 'ADVENT_CALENDAR_NOT_FOUND', 'Foreign calendar returned wrong error.');
        }
    }

    $tokenTime = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $ownerToken = bin2hex(random_bytes(32));
    $otherToken = bin2hex(random_bytes(32));
    $em->persist(new AccessToken(hash('sha256', $ownerToken), $owner, $tokenTime, $tokenTime->modify('+1 hour')));
    $em->persist(new AccessToken(hash('sha256', $otherToken), $other, $tokenTime, $tokenTime->modify('+1 hour')));
    $em->flush();
    $requestDay = static function (string $token) use ($kernel): \Symfony\Component\HttpFoundation\Response {
        $request = Request::create('/api/v1/calendar/days/31?year=2026', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        return $response;
    };
    // This smoke run is before December 2026; the service cases above cover fixed boundary instants.
    if ($tokenTime < new DateTimeImmutable('2026-12-31 00:00:00', new DateTimeZone('Europe/Moscow'))) {
        $lockedResponse = $requestDay($ownerToken);
        check($lockedResponse->getStatusCode() === 403, 'Direct future-day request did not return 403.');
        check(str_contains((string) $lockedResponse->getContent(), 'ADVENT_DAY_LOCKED'), 'Direct request returned wrong error.');
        check(!str_contains((string) $lockedResponse->getContent(), 'Safe content'), 'Direct request disclosed task content.');
    }
    $foreignResponse = $requestDay($otherToken);
    check($foreignResponse->getStatusCode() === 404, 'Foreign calendar request did not return 404.');
    check(!str_contains((string) $foreignResponse->getContent(), 'Safe content'), 'Foreign request disclosed task content.');
    $progressPath = '/api/v1/admin/users/'.$owner->getUuid().'/calendars/2026/progress';
    $requestProgress = static function (string $path, string $token) use ($kernel): \Symfony\Component\HttpFoundation\Response {
        $request = Request::create($path, 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        return $response;
    };
    check($requestProgress($progressPath, $ownerToken)->getStatusCode() === 403, 'Non-admin read administrative progress.');
    $adminResponse = $requestProgress($progressPath, $otherToken);
    $adminBody = json_decode((string) $adminResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
    check($adminResponse->getStatusCode() === 200 && count($adminBody['progress']['days']) === 31 && $adminBody['tree']['ornaments'] === [], 'Admin progress response is incomplete.');
    check(str_contains((string) $adminResponse->headers->get('Cache-Control'), 'no-store'), 'Admin progress was cacheable.');
    check(!str_contains((string) $adminResponse->getContent(), 'Safe content'), 'Admin progress disclosed task content.');
    $missing = $requestProgress('/api/v1/admin/users/'.$owner->getUuid().'/calendars/2027/progress', $otherToken);
    check($missing->getStatusCode() === 404, 'Missing calendar progress was accessible.');
    $completion = new CompleteAdventDayService($em, $availability, new LocalOrnamentImageStorage('/uploads'));
    $managedOwner = $em->find(User::class, $owner->getUuid());
    check($managedOwner instanceof User, 'Owner disappeared before completion check.');
    try { $completion->complete($managedOwner, 2026, 1, $time('2026-12-03 21:00:00')); throw new RuntimeException('Expired day was completed.'); }
    catch (CalendarAccessException $error) { check($error->errorCode === 'ADVENT_DAY_EXPIRED' && $error->httpStatus === 403, 'Wrong expired completion error.'); }
} finally {
    $connection->rollBack();
    $kernel->shutdown();
}

echo "Calendar API smoke checks passed.\n";
