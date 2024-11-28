<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


class AppFixtures extends Fixture
{
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('bkb@test.com');
        $user->setRoles(["ROLE_USER"]);
        $user->setHasAccessRessource(false);
        $user->setSubscriptionStatus('none');

        // Générer un mot de passe factice (ex : "password123")
        $password = $this->userPasswordHasher->hashPassword($user, 'password');
        $user->setPassword($password);

        // Persister l'utilisateur
        $manager->persist($user);

        $manager->flush();
    }
}
