<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private Generator $faker;

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
        $this->faker = Factory::create('fr_FR');
    }

    public function load(ObjectManager $manager): void
    {
        // $product = new Product();
        // $manager->persist($product);

        $user = new User;
        $user
            ->setUsername('admin')
            ->setFirstName('Admin')
            ->setLastName('User')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword(
                $this->passwordHasher->hashPassword(
                    $user,
                    'admin',
                )
            );

        $manager->persist($user);

        for ($i = 1; $i <= 15; $i++) {
            $user = new User;
            $user
                ->setUsername($this->faker->unique()->userName())
                ->setFirstName($this->faker->firstName())
                ->setLastName($this->faker->lastName())
                ->setPassword(
                    $this->passwordHasher->hashPassword(
                        $user,
                        'user',
                    )
                )
            ;

            $manager->persist($user);
        }

        $manager->flush();
    }
}
