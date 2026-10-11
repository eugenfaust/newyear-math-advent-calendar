<?php

declare(strict_types=1);

namespace App\User\Command;

use App\User\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:create-admin', description: 'Create an administrator from arguments or interactive prompts')]
final class CreateAdminCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher, private ValidatorInterface $validator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('login', InputArgument::OPTIONAL, 'Administrator login')
            ->addArgument('password', InputArgument::OPTIONAL, 'Administrator password (minimum 6 characters)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $login = $input->getArgument('login');
        $password = $input->getArgument('password');

        if (($login === null) !== ($password === null)) {
            $io->error('Pass both login and password, or omit both for interactive input.');
            return Command::FAILURE;
        }

        if ($login === null) {
            if (!$input->isInteractive()) {
                $io->error('Pass login and password in non-interactive mode.');
                return Command::FAILURE;
            }
            $login = (string) $io->ask('Login');
            $name = (string) $io->ask('Name');
            $question = new Question('Password (minimum 6 characters): ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $io->askQuestion($question);
        } else {
            $name = 'Администратор';
        }

        if (!is_string($password) || mb_strlen($password) < 6 || mb_strlen($password) > 1024) {
            $io->error('Password must be 6–1024 characters.');
            return Command::FAILURE;
        }

        $user = new User($login, $name);
        $user->setRoles(['ROLE_ADMIN']);
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $io->error((string) $errors);
            return Command::FAILURE;
        }
        if ($this->em->getRepository(User::class)->findOneBy(['login' => $login])) {
            $io->error('Login is already in use.');
            return Command::FAILURE;
        }

        $user->setPasswordHash($this->hasher->hashPassword($user, $password));
        unset($password);
        $this->em->persist($user);
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            $io->error('Login is already in use.');
            return Command::FAILURE;
        }
        $io->success('Administrator created.');
        return Command::SUCCESS;
    }
}
