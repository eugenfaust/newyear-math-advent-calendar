<?php

declare(strict_types=1);

// Run in the backend container: php tests/calendar_assignment_smoke.php
// Each scenario uses an outer transaction and leaves no fixture rows behind.
require dirname(__DIR__).'/vendor/autoload.php';

use App\Calendar\Entity\AdventCalendar;
use App\Calendar\Entity\AdventDayProgress;
use App\Calendar\Service\AssignAdventCalendarService;
use App\Calendar\Service\CalendarAssignmentException;
use App\Kernel;
use App\Task\Entity\Task;
use App\Template\Entity\AdventTemplate;
use App\Template\Entity\AdventTemplateTask;
use App\Template\Service\TemplateException;
use App\Template\Service\TemplateService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function scenario(callable $verify): void
{
    $kernel = new Kernel('prod', false);
    $kernel->boot();
    /** @var EntityManagerInterface $em */
    $em = $kernel->getContainer()->get('doctrine')->getManager();
    $connection = $em->getConnection();
    $connection->beginTransaction();
    try {
        $user = new User('assignment-'.bin2hex(random_bytes(6)), 'Assignment test');
        $user->setPasswordHash(password_hash('temporary', PASSWORD_BCRYPT));
        $task = new Task('Original', 'Content');
        $replacement = new Task('Replacement', 'Content');
        $template = new AdventTemplate('Test template');
        foreach ([$user, $task, $replacement, $template] as $entity) $em->persist($entity);
        $em->flush();
        $templates = new TemplateService($em);
        $assignment = new AssignAdventCalendarService($em, $templates);
        $verify($em, $user, $task, $replacement, $template, $templates, $assignment);
    } finally {
        $connection->rollBack();
        $kernel->shutdown();
    }
}

scenario(function (EntityManagerInterface $em, User $user, Task $task, Task $replacement, AdventTemplate $template, TemplateService $templates, AssignAdventCalendarService $assignment): void {
    for ($day = 1; $day < 31; $day++) $em->persist(new AdventTemplateTask($template, $day, $task));
    $em->flush();
    try { $assignment->assign($user, $template, 2026); throw new RuntimeException('Incomplete template was accepted.'); }
    catch (TemplateException $error) { check($error->errorCode === 'TEMPLATE_INCOMPLETE', 'Wrong incomplete-template error.'); }
    $count = $em->getConnection()->fetchOne('SELECT COUNT(*) FROM advent_calendar WHERE user_id = ?', [$user->getUuid()]);
    check((int) $count === 0, 'Incomplete assignment left a calendar row.');
});

scenario(function (EntityManagerInterface $em, User $user, Task $task, Task $replacement, AdventTemplate $template, TemplateService $templates, AssignAdventCalendarService $assignment): void {
    for ($day = 1; $day <= 31; $day++) $em->persist(new AdventTemplateTask($template, $day, $task));
    $em->flush();
    $calendar = $assignment->assign($user, $template, 2026);
    check(count($em->getRepository(AdventDayProgress::class)->findBy(['calendar' => $calendar])) === 31, 'Expected 31 days.');
    $templates->setDay($template, 1, $replacement->getUuid());
    $first = $em->getRepository(AdventDayProgress::class)->findOneBy(['calendar' => $calendar, 'day' => 1]);
    check($first->getTask()->getUuid() === $task->getUuid(), 'Template edit changed the snapshot.');
    $assignment->assign($user, $template, 2027);
    check(count($em->getRepository(AdventCalendar::class)->findBy(['user' => $user])) === 2, 'Year history was lost.');
    try { $assignment->assign($user, $template, 2026); throw new RuntimeException('Duplicate year was accepted.'); }
    catch (CalendarAssignmentException $error) { check($error->errorCode === 'CALENDAR_ALREADY_ASSIGNED', 'Wrong duplicate-year error.'); }
});

echo "Calendar assignment smoke checks passed.\n";
