<?php

namespace App\Tests\Functional\SecurityController;

use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoginTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository(Users::class);

        foreach ($userRepository->findAll() as $user) {
            $em->remove($user);
        }
        $em->flush();

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get('security.user_password_hasher');

        $user = new Users();
        $user->setUsername('johndoe');
        $user->setEmail('john@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'password'));

        $em->persist($user);
        $em->flush();
    }

    public function testLoginPageIsAccessible(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
    }

    public function testLoginWithValidCredentials(): void
    {
        $this->client->request('GET', '/login');

        $this->client->submitForm('Sign in', [
            '_username' => 'johndoe',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorNotExists('.alert-danger');
    }

    public function testLoginWithInvalidUsername(): void
    {
        $this->client->request('GET', '/login');

        $this->client->submitForm('Sign in', [
            '_username' => 'unknownuser',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');
    }

    public function testLoginWithInvalidPassword(): void
    {
        $this->client->request('GET', '/login');

        $this->client->submitForm('Sign in', [
            '_username' => 'johndoe',
            '_password' => 'wrong-password',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');
    }

    public function testLoginWithEmptyCredentials(): void
    {
        $this->client->request('GET', '/login');

        $this->client->submitForm('Sign in', [
            '_username' => '',
            '_password' => '',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');
    }

    public function testLogout(): void
    {
        // Login first
        $this->client->request('GET', '/login');
        $this->client->submitForm('Sign in', [
            '_username' => 'johndoe',
            '_password' => 'password',
        ]);
        $this->client->followRedirect();

        // Logout
        $this->client->request('GET', '/logout');

        self::assertResponseRedirects();
    }
}
