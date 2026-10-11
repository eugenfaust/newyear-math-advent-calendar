<?php

declare(strict_types=1);

// Run in the backend container after migrations: php tests/completion_rewards_smoke.php
require dirname(__DIR__).'/vendor/autoload.php';

use App\Calendar\Entity\AdventCalendar;
use App\Calendar\Entity\AdventDayProgress;
use App\Calendar\Service\CalendarAccessException;
use App\Calendar\Service\CalendarAvailability;
use App\Calendar\Service\CompleteAdventDayService;
use App\Kernel;
use App\Ornament\Entity\Ornament;
use App\Ornament\Storage\LocalOrnamentImageStorage;
use App\Task\Entity\Task;
use App\Template\Entity\AdventTemplate;
use App\User\Entity\AccessToken;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function boot(): array
{
    $kernel = new Kernel('prod', false);
    $kernel->boot();
    $em = $kernel->getContainer()->get('doctrine')->getManager();
    check($em instanceof EntityManagerInterface, 'Entity manager unavailable.');
    return [$kernel, $em];
}

$at = new DateTimeImmutable('2026-12-02 12:00:00', new DateTimeZone('UTC'));
if (($argv[1] ?? '') === 'worker') {
    [$kernel, $em] = boot();
    $user = $em->find(User::class, $argv[2]);
    check($user instanceof User, 'Worker user missing.');
    $service = new CompleteAdventDayService($em, new CalendarAvailability('Europe/Moscow'), new LocalOrnamentImageStorage('/uploads'));
    echo json_encode($service->complete($user, 2026, 1, $at), JSON_THROW_ON_ERROR);
    exit;
}

[$kernel, $em] = boot();
$db = $em->getConnection();
$owner = new User('reward-owner-'.bin2hex(random_bytes(6)), 'Reward owner');
$other = new User('reward-other-'.bin2hex(random_bytes(6)), 'Other user');
$owner->setPasswordHash(password_hash('temporary', PASSWORD_BCRYPT));
$other->setPasswordHash(password_hash('temporary', PASSWORD_BCRYPT));
$template = new AdventTemplate('Reward smoke');
$task = new Task('Reward task', 'Reward content');
$ornaments = [new Ornament('Reward A'), new Ornament('Reward B')];
foreach ($ornaments as $ornament) {
    $ornament->setImagePath('ornaments/'.bin2hex(random_bytes(16)).'.svg');
    $ornament->setActive(true);
}
foreach ([$owner, $other, $template, $task, ...$ornaments] as $entity) $em->persist($entity);
$calendar = new AdventCalendar($owner, $template, 2026);
$em->persist($calendar);
for ($day = 1; $day <= 31; $day++) $em->persist(new AdventDayProgress($calendar, $day, $task));
$tokenValue = bin2hex(random_bytes(32));
$foreignTokenValue = bin2hex(random_bytes(32));
$issued = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$tokens = [
    new AccessToken(hash('sha256', $tokenValue), $owner, $issued, $issued->modify('+1 hour')),
    new AccessToken(hash('sha256', $foreignTokenValue), $other, $issued, $issued->modify('+1 hour')),
];
foreach ($tokens as $token) $em->persist($token);
$em->flush();

try {
    $service = new CompleteAdventDayService($em, new CalendarAvailability('Europe/Moscow'), new LocalOrnamentImageStorage('/uploads'));
    $workers = [];
    for ($i = 0; $i < 2; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $owner->getUuid()], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        check(is_resource($process), 'Could not start concurrent worker.');
        $workers[] = [$process, $pipes];
    }
    $workerResults = [];
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        check(proc_close($process) === 0, 'Concurrent worker failed: '.$errors);
        $workerResults[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    check($workerResults[0] === $workerResults[1], 'Concurrent requests issued different rewards.');
    $em->clear();
    $owner = $em->find(User::class, $owner->getUuid());
    check($owner instanceof User, 'Owner disappeared after concurrent requests.');
    $first = $workerResults[0];
    $again = $service->complete($owner, 2026, 1, $at->modify('+3 days'));
    check($first === $again, 'Duplicate completion changed the result.');
    check($first['completedAt'] === '2026-12-02T12:00:00Z', 'Incorrect completion timestamp.');
    $second = $service->complete($owner, 2026, 2, $at);
    check($first['reward']['uuid'] !== $second['reward']['uuid'], 'Different days got the same reward row.');
    check($first['reward']['name'] !== $second['reward']['name'], 'Repeated ornament before unused variants were exhausted.');
    $duplicateInserted = $db->executeStatement(
        'INSERT INTO user_ornament (uuid, calendar_id, day_progress_id, ornament_id, received_at, updated_at) SELECT ?, calendar_id, day_progress_id, ornament_id, received_at, updated_at FROM user_ornament WHERE uuid = ? ON CONFLICT DO NOTHING',
        [($first['reward']['uuid'][0] === 'a' ? 'b' : 'a').substr($first['reward']['uuid'], 1), $first['reward']['uuid']],
    );
    check($duplicateInserted === 0, 'Database allowed a second reward for one day.');
    $db->executeStatement('UPDATE user_ornament SET tree_slot = 1 WHERE uuid = ?', [$first['reward']['uuid']]);
    $slotInserted = $db->executeStatement(
        'INSERT INTO user_ornament (uuid, calendar_id, day_progress_id, ornament_id, tree_slot, received_at, updated_at) SELECT ?, uo.calendar_id, p.id, uo.ornament_id, 1, uo.received_at, uo.updated_at FROM user_ornament uo JOIN advent_day_progress p ON p.calendar_id = uo.calendar_id AND p.day = 3 WHERE uo.uuid = ? ON CONFLICT DO NOTHING',
        [($first['reward']['uuid'][0] === 'c' ? 'd' : 'c').substr($first['reward']['uuid'], 1), $first['reward']['uuid']],
    );
    check($slotInserted === 0, 'Database allowed two ornaments in one tree slot.');

    $request = static function (string $path, string $token) use ($kernel): array {
        $req = Request::create($path, 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], '{}');
        $response = $kernel->handle($req);
        $kernel->terminate($req, $response);
        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)];
    };
    [$repeatStatus, $repeatBody] = $request('/api/v1/calendar/days/1/complete?year=2026', $tokenValue);
    check($repeatStatus === 200 && $repeatBody === $first, 'HTTP retry did not return the original result.');
    $db->beginTransaction();
    try {
        $db->executeStatement('UPDATE ornament SET is_active = FALSE');
        try {
            $service->complete($owner, 2026, 3, $at->modify('+1 day'));
            throw new RuntimeException('Day completed without an active ornament.');
        } catch (CalendarAccessException $error) {
            check($error->errorCode === 'NO_ACTIVE_ORNAMENTS' && $error->httpStatus === 409, 'Wrong no-ornament error.');
        }
    } finally {
        $db->rollBack();
    }
    $state = $db->fetchAssociative('SELECT completed_at, opened_at FROM advent_day_progress WHERE calendar_id = ? AND day = 3', [$calendar->getUuid()]);
    check($state['completed_at'] === null && $state['opened_at'] === null, 'Failed award left partial progress.');
    check((int) $db->fetchOne('SELECT count(*) FROM user_ornament WHERE calendar_id = ?', [$calendar->getUuid()]) === 2, 'Incorrect award count.');

    $kernel->shutdown();
    [$kernel, $em] = boot();
    $foreignReq = Request::create('/api/v1/calendar/days/1/complete?year=2026', 'POST', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer '.$foreignTokenValue, 'CONTENT_TYPE' => 'application/json',
    ], '{}');
    $foreignResponse = $kernel->handle($foreignReq);
    check($foreignResponse->getStatusCode() === 404 && str_contains((string) $foreignResponse->getContent(), 'ADVENT_CALENDAR_NOT_FOUND'), 'Foreign calendar was accessible.');
    $kernel->terminate($foreignReq, $foreignResponse);
    $kernel->shutdown();
    if ($issued < new DateTimeImmutable('2026-12-31 00:00:00', new DateTimeZone('Europe/Moscow'))) {
        [$kernel, $em] = boot();
        $futureReq = Request::create('/api/v1/calendar/days/31/complete?year=2026', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenValue, 'CONTENT_TYPE' => 'application/json',
        ], '{}');
        $futureResponse = $kernel->handle($futureReq);
        check($futureResponse->getStatusCode() === 403 && str_contains((string) $futureResponse->getContent(), 'ADVENT_DAY_LOCKED'), 'Future day was completed.');
        $kernel->terminate($futureReq, $futureResponse);
    }
} finally {
    $db->executeStatement('DELETE FROM access_token WHERE user_uuid IN (?, ?)', [$owner->getUuid(), $other->getUuid()]);
    $db->executeStatement('DELETE FROM user_ornament WHERE calendar_id = ?', [$calendar->getUuid()]);
    $db->executeStatement('DELETE FROM advent_day_progress WHERE calendar_id = ?', [$calendar->getUuid()]);
    $db->executeStatement('DELETE FROM advent_calendar WHERE uuid = ?', [$calendar->getUuid()]);
    $db->executeStatement('DELETE FROM ornament WHERE uuid IN (?, ?)', [$ornaments[0]->getUuid(), $ornaments[1]->getUuid()]);
    $db->executeStatement('DELETE FROM task WHERE uuid = ?', [$task->getUuid()]);
    $db->executeStatement('DELETE FROM advent_template WHERE uuid = ?', [$template->getUuid()]);
    $db->executeStatement('DELETE FROM app_user WHERE uuid IN (?, ?)', [$owner->getUuid(), $other->getUuid()]);
    $kernel->shutdown();
}

echo "Completion reward smoke checks passed.\n";
