<?php

declare(strict_types=1);

namespace App\User\Command;

use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:create-admin', description: 'Create the first administrator interactively')]
final class CreateAdminCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher, private ValidatorInterface $validator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->isInteractive()) {
            $io->error('Run interactively; the password is read from a hidden prompt.');
            return Command::FAILURE;
        }
        $login = (string) $io->ask('Login');
        $name = (string) $io->ask('Name');
        if ($this->em->getRepository(User::class)->findOneBy(['login' => $login])) {
            $io->error('Login is already in use.');
            return Command::FAILURE;
        }
        $user = new User($login, $name);
        $user->setRoles(['ROLE_ADMIN']);
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $io->error((string) $errors);
            return Command::FAILURE;
        }
        $question = new Question('Password (minimum 12 characters): ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $password = $io->askQuestion($question);
        if (!is_string($password) || strlen($password) < 12) {
            $io->error('Password must have at least 12 characters.');
            return Command::FAILURE;
        }
        $user->setPasswordHash($this->hasher->hashPassword($user, $password));
        unset($password);
        $this->em->persist($user);
        $this->em->flush();
        $io->success('Administrator created.');
        return Command::SUCCESS;
    }
}
