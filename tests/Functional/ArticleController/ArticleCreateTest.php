<?php

namespace App\Tests\Functional\ArticleController;

use App\Entity\Article;
use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ArticleCreateTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');

        foreach ($em->getRepository(Article::class)->findAll() as $article) {
            $em->remove($article);
        }
        foreach ($em->getRepository(Users::class)->findAll() as $user) {
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

    public function testCreatePageRequiresLogin(): void
    {
        $this->client->request('GET', '/articles/new');

        self::assertResponseRedirects('/login');
    }

    public function testCreatePageIsAccessibleWhenLoggedIn(): void
    {
        $this->login();
        $this->client->request('GET', '/articles/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testCreateArticleWithValidData(): void
    {
        $this->login();
        $this->client->request('GET', '/articles/new');

        $this->client->submitForm('Publish', [
            'article_form[title]' => 'My First Article',
            'article_form[content]' => 'This is the content of my first article.',
        ]);

        self::assertResponseRedirects('/articles/my-first-article');

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $article = $em->getRepository(Article::class)->findOneBy(['slug' => 'my-first-article']);

        self::assertNotNull($article);
        self::assertSame('My First Article', $article->getTitle());
        self::assertSame('my-first-article', $article->getSlug());
        self::assertSame('johndoe', $article->getUsers()->getUsername());
    }

    public function testCreateArticleWithBlankTitle(): void
    {
        $this->login();
        $this->client->request('GET', '/articles/new');

        $this->client->submitForm('Publish', [
            'article_form[title]' => '',
            'article_form[content]' => 'Some content.',
        ]);

        self::assertResponseIsUnprocessable();
    }

    public function testCreateArticleWithBlankContent(): void
    {
        $this->login();
        $this->client->request('GET', '/articles/new');

        $this->client->submitForm('Publish', [
            'article_form[title]' => 'A Title',
            'article_form[content]' => '',
        ]);

        self::assertResponseIsUnprocessable();
    }

    private function login(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Sign in', [
            '_username' => 'johndoe',
            '_password' => 'password',
        ]);
        $this->client->followRedirect();
    }
}
