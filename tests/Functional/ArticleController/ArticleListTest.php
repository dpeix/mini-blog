<?php

namespace App\Tests\Functional\ArticleController;

use App\Entity\Article;
use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ArticleListTest extends WebTestCase
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

        for ($i = 1; $i <= 5; $i++) {
            $article = new Article();
            $article->setTitle("Test Article $i");
            $article->setSlug("test-article-$i");
            $article->setContent("Content for test article $i");
            $article->setUsers($user);
            $em->persist($article);
        }
        $em->flush();
    }

    public function testArticleListPageIsAccessible(): void
    {
        $this->client->request('GET', '/articles');
        self::assertResponseIsSuccessful();
    }

    public function testArticleListDisplaysArticles(): void
    {
        $this->client->request('GET', '/articles');
        self::assertSelectorExists('.article-card');
    }

    public function testArticleListContainsArticleTitle(): void
    {
        $this->client->request('GET', '/articles');
        self::assertSelectorTextContains('.article-card', 'Test Article');
    }
}
