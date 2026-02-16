<?php

namespace App\Tests\Functional\SecurityController;

use App\Entity\Article;
use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegisterTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        foreach ($em->getRepository(Article::class)->findAll() as $article) {
            $em->remove($article);
        }
        foreach ($em->getRepository(Users::class)->findAll() as $user) {
            $em->remove($user);
        }
        $em->flush();
    }

    public function testRegisterPageIsAccessible(): void
    {
        $this->client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testRegisterWithValidData(): void
    {
        $this->client->request('GET', '/register');

        $this->client->submitForm('Register', [
            'registration_form[username]' => 'johndoe',
            'registration_form[email]' => 'john@example.com',
            'registration_form[plainPassword][first]' => 'securePassword123',
            'registration_form[plainPassword][second]' => 'securePassword123',
        ]);

        // Controller does $security->login() — auto-login then redirect
        self::assertResponseRedirects();

        // Verify user was persisted
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(Users::class)->findOneBy(['username' => 'johndoe']);

        self::assertNotNull($user);
        self::assertSame('john@example.com', $user->getEmail());
        self::assertSame('johndoe', $user->getUsername());
        // Password should be hashed, not plain text
        self::assertNotSame('securePassword123', $user->getPassword());
    }

    public function testRegisterWithBlankUsername(): void
    {
        $this->client->request('GET', '/register');

        $this->client->submitForm('Register', [
            'registration_form[username]' => '',
            'registration_form[email]' => 'john@example.com',
            'registration_form[plainPassword][first]' => 'securePassword123',
            'registration_form[plainPassword][second]' => 'securePassword123',
        ]);

        self::assertResponseIsUnprocessable();
    }

    public function testRegisterWithBlankEmail(): void
    {
        $this->client->request('GET', '/register');

        $this->client->submitForm('Register', [
            'registration_form[username]' => 'johndoe',
            'registration_form[email]' => '',
            'registration_form[plainPassword][first]' => 'securePassword123',
            'registration_form[plainPassword][second]' => 'securePassword123',
        ]);

        self::assertResponseIsUnprocessable();
    }

    public function testRegisterWithInvalidEmail(): void
    {
        $this->client->request('GET', '/register');

        $this->client->submitForm('Register', [
            'registration_form[username]' => 'johndoe',
            'registration_form[email]' => 'not-an-email',
            'registration_form[plainPassword][first]' => 'securePassword123',
            'registration_form[plainPassword][second]' => 'securePassword123',
        ]);

        self::assertResponseIsUnprocessable();
    }

    public function testRegisterWithBlankPassword(): void
    {
        $this->client->request('GET', '/register');

        $this->client->submitForm('Register', [
            'registration_form[username]' => 'johndoe',
            'registration_form[email]' => 'john@example.com',
            'registration_form[plainPassword][first]' => '',
            'registration_form[plainPassword][second]' => '',
        ]);

        self::assertResponseIsUnprocessable();
    }

    public function testRegisterWithMismatchedPasswords(): void
    {
        $this->client->request('GET', '/register');

        $this->client->submitForm('Register', [
            'registration_form[username]' => 'johndoe',
            'registration_form[email]' => 'john@example.com',
            'registration_form[plainPassword][first]' => 'securePassword123',
            'registration_form[plainPassword][second]' => 'differentPassword',
        ]);

        self::assertResponseIsUnprocessable();
    }

    public function testRegisterWithDuplicateUsername(): void
    {
        $this->createExistingUser();

        $this->client->request('GET', '/register');

        $this->client->submitForm('Register', [
            'registration_form[username]' => 'johndoe',
            'registration_form[email]' => 'other@example.com',
            'registration_form[plainPassword][first]' => 'securePassword123',
            'registration_form[plainPassword][second]' => 'securePassword123',
        ]);

        self::assertResponseIsUnprocessable();
    }

    public function testNewUserHasRoleUser(): void
    {
        $this->client->request('GET', '/register');

        $this->client->submitForm('Register', [
            'registration_form[username]' => 'johndoe',
            'registration_form[email]' => 'john@example.com',
            'registration_form[plainPassword][first]' => 'securePassword123',
            'registration_form[plainPassword][second]' => 'securePassword123',
        ]);

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(Users::class)->findOneBy(['username' => 'johndoe']);

        self::assertNotNull($user);
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    private function createExistingUser(): void
    {
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $passwordHasher = $container->get('security.user_password_hasher');

        $user = new Users();
        $user->setUsername('johndoe');
        $user->setEmail('john@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'password'));

        $em->persist($user);
        $em->flush();
    }
}
