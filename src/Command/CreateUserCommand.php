<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create:user',
    description: 'Crea un usuario administrador o normal'
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email del usuario')
            ->addArgument('password', InputArgument::REQUIRED, 'Password')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Crear como admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = strtolower(trim($input->getArgument('email')));
        $password = $input->getArgument('password');
        $isAdmin = $input->getOption('admin');

        // 🔎 comprobar si ya existe
        $existing = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($existing) {
            $output->writeln("<error>El usuario ya existe</error>");
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);

        // 🔐 hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // 🎭 roles
        if ($isAdmin) {
            $user->setRoles(['ROLE_ADMIN']);
        } else {
            $user->setRoles(['ROLE_USER']);
        }

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln("<info>Usuario creado correctamente</info>");
        $output->writeln("Email: $email");
        $output->writeln("Rol: " . ($isAdmin ? 'ADMIN' : 'USER'));

        return Command::SUCCESS;
    }
}