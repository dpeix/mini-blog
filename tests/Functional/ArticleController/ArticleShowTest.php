<?php

namespace App\Tests\Functional\ArticleController;

use App\Entity\Article;
use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ArticleShowTest extends WebTestCase
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

        $user = new Users();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPassword('hashed');
        $em->persist($user);

        $article = new Article();
        $article->setTitle('My Test Article');
        $article->setSlug('my-test-article');
        $article->setContent('Full content of the test article.');
        $article->setUsers($user);
        $em->persist($article);
        $em->flush();
    }

    public function testArticleShowPageIsAccessible(): void
    {
        $this->client->request('GET', '/articles/my-test-article');
        self::assertResponseIsSuccessful();
    }

    public function testArticleShowDisplaysTitle(): void
    {
        $this->client->request('GET', '/articles/my-test-article');
        self::assertSelectorTextContains('h1', 'My Test Article');
    }

    public function testArticleShowDisplaysContent(): void
    {
        $this->client->request('GET', '/articles/my-test-article');
        self::assertSelectorTextContains('.article-content', 'Full content of the test article.');
    }

    public function testArticleShowReturns404ForInvalidSlug(): void
    {
        $this->client->request('GET', '/articles/non-existent-slug');
        self::assertResponseStatusCodeSame(404);
    }
}
