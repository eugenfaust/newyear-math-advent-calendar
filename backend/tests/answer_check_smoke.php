<?php

declare(strict_types=1);

// Run in the backend container after migrations: php tests/answer_check_smoke.php
require dirname(__DIR__).'/vendor/autoload.php';

use App\Calendar\Entity\AdventCalendar;
use App\Calendar\Entity\AdventDayProgress;
use App\Calendar\Service\CalendarAvailability;
use App\Calendar\Service\UserCalendarService;
use App\Kernel;
use App\Ornament\Entity\Ornament;
use App\Task\AnswerType;
use App\Task\Entity\Task;
use App\Task\Storage\LocalImageStorage;
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
$db = $em->getConnection();
$db->beginTransaction();
try {
    $user = new User('answer-smoke-'.bin2hex(random_bytes(5)), 'Answer smoke');
    $user->setPasswordHash(password_hash('temporary', PASSWORD_BCRYPT));
    $user->setTestUser(true);
    $user->setRoles(['ROLE_ADMIN']);
    $template = new AdventTemplate('Answer smoke');
    $ornament = new Ornament('Answer reward');
    $ornament->setImagePath('ornaments/'.bin2hex(random_bytes(16)).'.svg');
    $ornament->setActive(true);
    $tasks = [];
    foreach ([
        [AnswerType::NUMBER, '2', null],
        [AnswerType::TEXT, 'Пифагор', null],
        [AnswerType::MULTIPLE_CHOICE, '8', ['6', '8', '10']],
    ] as $index => [$type, $answer, $choices]) {
        $task = new Task('Answer task '.($index + 1), 'Solve this task.');
        $task->setAnswerType($type);
        $task->setCorrectAnswer($answer);
        $task->setChoices($choices);
        $tasks[] = $task;
        $em->persist($task);
    }
    foreach ([$user, $template, $ornament] as $entity) $em->persist($entity);
    $availability = new CalendarAvailability('Europe/Moscow');
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $year = $availability->currentYear($now);
    $calendar = new AdventCalendar($user, $template, $year);
    $em->persist($calendar);
    foreach ($tasks as $index => $task) $em->persist(new AdventDayProgress($calendar, $index + 1, $task));
    $token = bin2hex(random_bytes(32));
    $em->persist(new AccessToken(hash('sha256', $token), $user, $now, $now->modify('+1 hour')));
    $em->flush();

    $calendarService = new UserCalendarService($em, $availability, new LocalImageStorage('/uploads'));
    $request = static function (int $day, ?string $answer) use ($kernel, $token, $year): array {
        $path = '/api/v1/calendar/days/'.$day.'/complete?year='.$year;
        $req = Request::create($path, 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($answer === null ? [] : ['answer' => $answer], JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT));
        $res = $kernel->handle($req);
        $kernel->terminate($req, $res);
        return [$res->getStatusCode(), json_decode((string) $res->getContent(), true, 512, JSON_THROW_ON_ERROR)];
    };

    foreach ([1 => ['wrong' => '3', 'right' => '02,0'], 2 => ['wrong' => 'Евклид', 'right' => '  пИфАгоР  '], 3 => ['wrong' => '6', 'right' => '8']] as $day => $answers) {
        $before = $calendarService->day($user, $year, $day, $now);
        check(!array_key_exists('correctAnswer', $before['task']), 'Answer leaked before completion.');
        if ($day === 3) check($before['task']['choices'] === ['6', '8', '10'], 'Choices missing from task.');
        [$missingStatus, $missingBody] = $request($day, null);
        check($missingStatus === 422 && $missingBody['code'] === 'VALIDATION_ERROR', 'Missing answer was accepted.');
        [$wrongStatus, $wrongBody] = $request($day, $answers['wrong']);
        check($wrongStatus === 422 && $wrongBody['code'] === 'INCORRECT_ANSWER', 'Wrong answer was accepted.');
        check($calendarService->day($user, $year, $day, $now)['status'] === 'available', 'Wrong answer completed a day.');
        check((int) $db->fetchOne('SELECT COUNT(*) FROM user_ornament WHERE calendar_id = ?', [$calendar->getUuid()]) === $day - 1, 'Wrong answer issued a reward.');
        [$rightStatus, $rightBody] = $request($day, $answers['right']);
        check($rightStatus === 200 && $rightBody['completed'] === true, 'Correct answer was rejected.');
        [$repeatStatus, $repeatBody] = $request($day, $answers['wrong']);
        check($repeatStatus === 200 && $repeatBody === $rightBody, 'Duplicate completion changed the reward.');
        $after = $calendarService->day($user, $year, $day, $now);
        check($after['task']['correctAnswer'] === $tasks[$day - 1]->getCorrectAnswer(), 'Answer not revealed after completion.');
    }
    check((int) $db->fetchOne('SELECT COUNT(*) FROM user_ornament WHERE calendar_id = ?', [$calendar->getUuid()]) === 3, 'Incorrect reward count.');
    $db->executeStatement('SAVEPOINT answer_config_check');
    $constraintRejected = false;
    try {
        $db->executeStatement('UPDATE task SET correct_answer = NULL WHERE uuid = ?', [$tasks[0]->getUuid()]);
    } catch (\Doctrine\DBAL\Exception $error) {
        $constraintRejected = true;
    } finally {
        $db->executeStatement('ROLLBACK TO SAVEPOINT answer_config_check');
    }
    check($constraintRejected, 'Database allowed a checked task without an answer.');

    $adminRequest = Request::create('/api/v1/admin/tasks', 'POST', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer '.$token, 'CONTENT_TYPE' => 'application/json',
    ], json_encode(['title' => 'Choice validation', 'content' => 'Pick one', 'answerType' => 'MULTIPLE_CHOICE',
        'choices' => ['A', 'B'], 'correctAnswer' => 'C'], JSON_THROW_ON_ERROR));
    $adminResponse = $kernel->handle($adminRequest);
    check($adminResponse->getStatusCode() === 422, 'Admin API accepted an answer outside the options.');
    $kernel->terminate($adminRequest, $adminResponse);
} finally {
    $db->rollBack();
    $kernel->shutdown();
}

echo "Answer check smoke checks passed.\n";
