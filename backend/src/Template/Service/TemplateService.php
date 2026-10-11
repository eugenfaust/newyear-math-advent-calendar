<?php

declare(strict_types=1);

namespace App\Template\Service;

use App\Task\Entity\Task;
use App\Template\Entity\AdventTemplate;
use App\Template\Entity\AdventTemplateTask;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class TemplateService
{
    public function __construct(private EntityManagerInterface $em) {}

    /** @return list<AdventTemplateTask> */
    public function assignments(AdventTemplate $template): array
    {
        return $this->em->getRepository(AdventTemplateTask::class)->createQueryBuilder('a')
            ->addSelect('task')->join('a.task', 'task')
            ->where('a.template = :template')->setParameter('template', $template)
            ->orderBy('a.day', 'ASC')->getQuery()->getResult();
    }

    /** @return list<int> */
    public function missingDays(AdventTemplate $template): array
    {
        $filled = array_map(static fn (AdventTemplateTask $assignment): int => $assignment->getDay(), $this->assignments($template));
        return array_values(array_diff(range(1, 31), $filled));
    }

    /** For calendar assignment in task 10. */
    public function assertAssignable(AdventTemplate $template): void
    {
        if (!$template->isActive()) throw new TemplateException('TEMPLATE_INACTIVE', 409, 'Template is inactive.');
        if ($this->missingDays($template) !== []) throw new TemplateException('TEMPLATE_INCOMPLETE', 422, 'All 31 days must have tasks.');
        foreach ($this->assignments($template) as $assignment) {
            if (!$assignment->getTask()->isActive()) throw new TemplateException('TEMPLATE_HAS_INACTIVE_TASK', 409, 'Template contains an inactive task.');
        }
    }

    public function setDay(AdventTemplate $template, int $day, ?string $taskUuid): void
    {
        $this->em->wrapInTransaction(function () use ($template, $day, $taskUuid): void {
            $this->em->lock($template, LockMode::PESSIMISTIC_WRITE);
            $repository = $this->em->getRepository(AdventTemplateTask::class);
            $assignment = $repository->findOneBy(['template' => $template, 'day' => $day]);
            if ($taskUuid === null) {
                if ($assignment) {
                    $this->em->remove($assignment);
                    $template->touch();
                }
                return;
            }
            $task = $this->em->find(Task::class, $taskUuid, LockMode::PESSIMISTIC_READ);
            if (!$task) throw new TemplateException('TASK_NOT_FOUND', 404, 'Task not found.');
            if (!$task->isActive()) throw new TemplateException('TASK_INACTIVE', 422, 'An inactive task cannot be selected.');
            if ($assignment) $assignment->setTask($task);
            else $this->em->persist(new AdventTemplateTask($template, $day, $task));
            $template->touch();
        });
    }

    public function duplicate(AdventTemplate $source, string $name): AdventTemplate
    {
        return $this->em->wrapInTransaction(function () use ($source, $name): AdventTemplate {
            $this->em->lock($source, LockMode::PESSIMISTIC_READ);
            $copy = new AdventTemplate($name);
            $copy->setDescription($source->getDescription());
            $copy->setYear($source->getYear());
            $copy->setActive($source->isActive());
            $this->em->persist($copy);
            foreach ($this->assignments($source) as $assignment) {
                $this->em->persist(new AdventTemplateTask($copy, $assignment->getDay(), $assignment->getTask()));
            }
            return $copy;
        });
    }
}
