<?php

declare(strict_types=1);

// Run in the backend container after migrations: php tests/tree_api_smoke.php
require dirname(__DIR__).'/vendor/autoload.php';

use App\Calendar\Entity\AdventCalendar;
use App\Calendar\Entity\AdventDayProgress;
use App\Kernel;
use App\Ornament\Entity\Ornament;
use App\Ornament\Entity\UserOrnament;
use App\Task\Entity\Task;
use App\Template\Entity\AdventTemplate;
use App\User\Entity\AccessToken;
use App\User\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Request;

function checkTree(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

if (($argv[1] ?? '') === 'worker') {
    $workerKernel = new Kernel('prod', false);
    $workerKernel->boot();
    $request = Request::create('/api/v1/tree/ornaments/'.$argv[3].'/place?year=2026', 'POST', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer '.$argv[2], 'CONTENT_TYPE' => 'application/json',
    ], '{"slot":10}');
    $response = $workerKernel->handle($request);
    echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)], JSON_THROW_ON_ERROR);
    $workerKernel->terminate($request, $response);
    $workerKernel->shutdown();
    exit;
}

$kernel = new Kernel('prod', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$now = new DateTimeImmutable('2026-12-31 12:00:00', new DateTimeZone('UTC'));
$owner = new User('tree-owner-'.bin2hex(random_bytes(6)), 'Tree owner');
$other = new User('tree-other-'.bin2hex(random_bytes(6)), 'Other owner');
foreach ([$owner, $other] as $user) {
    $user->setPasswordHash(password_hash('temporary', PASSWORD_BCRYPT));
    $em->persist($user);
}
$template = new AdventTemplate('Tree smoke');
$task = new Task('Tree task', 'Tree content');
$ornament = new Ornament('Tree ornament');
$ornament->setImagePath('ornaments/'.bin2hex(random_bytes(16)).'.svg');
$ornament->setActive(true);
foreach ([$template, $task, $ornament] as $entity) $em->persist($entity);
$calendar = new AdventCalendar($owner, $template, 2026);
$otherCalendar = new AdventCalendar($other, $template, 2026);
$oldCalendar = new AdventCalendar($owner, $template, 2025);
foreach ([$calendar, $otherCalendar, $oldCalendar] as $entity) $em->persist($entity);
$progresses = [];
for ($day = 1; $day <= 31; $day++) {
    $progresses[$day] = new AdventDayProgress($calendar, $day, $task);
    $em->persist($progresses[$day]);
}
$foreignProgress = new AdventDayProgress($otherCalendar, 1, $task);
$oldProgress = new AdventDayProgress($oldCalendar, 1, $task);
$em->persist($foreignProgress);
$em->persist($oldProgress);
$progresses[1]->complete($now);
$progresses[2]->complete($now);
$foreignProgress->complete($now);
$oldProgress->complete($now);
$rewards = [
    new UserOrnament($progresses[1], $ornament, $now),
    new UserOrnament($progresses[2], $ornament, $now),
];
$foreignReward = new UserOrnament($foreignProgress, $ornament, $now);
$oldReward = new UserOrnament($oldProgress, $ornament, $now);
foreach ([...$rewards, $foreignReward, $oldReward] as $reward) $em->persist($reward);
$ownerToken = bin2hex(random_bytes(32));
$otherToken = bin2hex(random_bytes(32));
$issued = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$em->persist(new AccessToken(hash('sha256', $ownerToken), $owner, $issued, $issued->modify('+1 hour')));
$em->persist(new AccessToken(hash('sha256', $otherToken), $other, $issued, $issued->modify('+1 hour')));
$em->flush();

try {
    $request = static function (string $method, string $path, string $token, ?string $body = null) use ($kernel): array {
        $request = Request::create($path, $method, [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR), $response->headers->get('Cache-Control')];
    };
    [$status, $tree, $cache] = $request('GET', '/api/v1/tree?year=2026', $ownerToken);
    checkTree($status === 200 && str_contains((string) $cache, 'no-store') && $tree['completedDays'] === 2 && $tree['completed'] === false && count($tree['ornaments']) === 2, 'Incorrect initial tree: '.json_encode([$status, $tree, $cache]));
    $path = '/api/v1/tree/ornaments/'.$rewards[0]->getUuid().'/place?year=2026';
    [$status, $placed] = $request('POST', $path, $ownerToken, '{"slot":5}');
    checkTree($status === 200 && $placed['treeSlot'] === 5 && $placed['placedAt'] !== null, 'Placement failed.');
    [$status, $again] = $request('POST', $path, $ownerToken, '{"slot":5}');
    checkTree($status === 200 && $again === $placed, 'Same-slot retry changed the result.');
    [$status, $occupied] = $request('POST', '/api/v1/tree/ornaments/'.$rewards[1]->getUuid().'/place?year=2026', $ownerToken, '{"slot":5}');
    checkTree($status === 409 && $occupied['code'] === 'TREE_SLOT_OCCUPIED', 'Occupied slot was accepted.');
    [$status, $moved] = $request('POST', $path, $ownerToken, '{"slot":6}');
    checkTree($status === 200 && $moved['treeSlot'] === 6, 'Move failed.');
    [$status, $reloaded] = $request('GET', '/api/v1/tree?year=2026', $ownerToken);
    $placedRows = array_values(array_filter($reloaded['ornaments'], static fn (array $row): bool => $row['uuid'] === $rewards[0]->getUuid()));
    checkTree($status === 200 && count($placedRows) === 1 && $placedRows[0]['treeSlot'] === 6, 'Placement was not visible after reading the tree again.');
    foreach ([$foreignReward->getUuid(), $oldReward->getUuid()] as $uuid) {
        [$status, $body] = $request('POST', '/api/v1/tree/ornaments/'.$uuid.'/place?year=2026', $ownerToken, '{"slot":7}');
        checkTree($status === 404 && $body['code'] === 'USER_ORNAMENT_NOT_FOUND', 'Foreign or historical ornament was accessible.');
    }
    [$status, $body] = $request('POST', $path, $otherToken, '{"slot":7}');
    checkTree($status === 404 && $body['code'] === 'USER_ORNAMENT_NOT_FOUND', 'Owner ornament was accessible to another user.');
    [$status, $body] = $request('POST', $path, $ownerToken, '{"slot":32}');
    checkTree($status === 422 && $body['code'] === 'VALIDATION_ERROR', 'Out-of-range slot was accepted.');
    $workers = [];
    foreach ($rewards as $reward) {
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $ownerToken, $reward->getUuid()], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        checkTree(is_resource($process), 'Could not start placement worker.');
        $workers[] = [$process, $pipes];
    }
    $race = [];
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        checkTree(proc_close($process) === 0, 'Placement worker failed: '.$errors);
        $race[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    $statuses = array_column($race, 'status');
    sort($statuses);
    checkTree($statuses === [200, 409], 'Concurrent placement did not yield one winner and one conflict.');
    checkTree((int) $db->fetchOne('SELECT count(*) FROM user_ornament WHERE calendar_id = ? AND tree_slot = 10', [$calendar->getUuid()]) === 1, 'Concurrent placement occupied slot twice.');
    for ($day = 3; $day <= 31; $day++) {
        $db->executeStatement('UPDATE advent_day_progress SET completed_at = ?, opened_at = ? WHERE calendar_id = ? AND day = ?', [$now->format('Y-m-d H:i:sP'), $now->format('Y-m-d H:i:sP'), $calendar->getUuid(), $day]);
        $db->executeStatement(
            'INSERT INTO user_ornament (uuid, calendar_id, day_progress_id, ornament_id, received_at, updated_at) SELECT gen_random_uuid(), calendar_id, id, ?, ?, ? FROM advent_day_progress WHERE calendar_id = ? AND day = ?',
            [$ornament->getUuid(), $now->format('Y-m-d H:i:sP'), $now->format('Y-m-d H:i:sP'), $calendar->getUuid(), $day],
        );
    }
    [$status, $tree] = $request('GET', '/api/v1/tree?year=2026', $ownerToken);
    checkTree($status === 200 && $tree['completedDays'] === 31 && $tree['completed'] === true && count($tree['ornaments']) === 31, 'Final tree state was not reported.');
    $db->executeStatement('UPDATE user_ornament SET tree_slot = 8 WHERE uuid = ?', [$rewards[0]->getUuid()]);
    $db->beginTransaction();
    try {
        $db->executeStatement('UPDATE user_ornament SET tree_slot = 8 WHERE uuid = ?', [$rewards[1]->getUuid()]);
        throw new RuntimeException('Database allowed a duplicate tree slot.');
    } catch (UniqueConstraintViolationException $error) {
        checkTree(str_contains($error->getMessage(), 'uniq_user_ornament_calendar_slot'), 'Wrong constraint rejected the duplicate slot.');
    } finally {
        $db->rollBack();
    }
} finally {
    $db->executeStatement('DELETE FROM access_token WHERE user_uuid IN (?, ?)', [$owner->getUuid(), $other->getUuid()]);
    $db->executeStatement('DELETE FROM user_ornament WHERE calendar_id IN (?, ?, ?)', [$calendar->getUuid(), $otherCalendar->getUuid(), $oldCalendar->getUuid()]);
    $db->executeStatement('DELETE FROM advent_day_progress WHERE calendar_id IN (?, ?, ?)', [$calendar->getUuid(), $otherCalendar->getUuid(), $oldCalendar->getUuid()]);
    $db->executeStatement('DELETE FROM advent_calendar WHERE uuid IN (?, ?, ?)', [$calendar->getUuid(), $otherCalendar->getUuid(), $oldCalendar->getUuid()]);
    $db->executeStatement('DELETE FROM ornament WHERE uuid = ?', [$ornament->getUuid()]);
    $db->executeStatement('DELETE FROM task WHERE uuid = ?', [$task->getUuid()]);
    $db->executeStatement('DELETE FROM advent_template WHERE uuid = ?', [$template->getUuid()]);
    $db->executeStatement('DELETE FROM app_user WHERE uuid IN (?, ?)', [$owner->getUuid(), $other->getUuid()]);
    $kernel->shutdown();
}

echo "Tree API smoke checks passed.\n";
