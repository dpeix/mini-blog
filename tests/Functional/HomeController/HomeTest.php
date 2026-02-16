<?php

namespace App\Tests\Functional\HomeController;

use App\Entity\Article;
use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeTest extends WebTestCase
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

        for ($i = 1; $i <= 3; $i++) {
            $article = new Article();
            $article->setTitle("Article $i");
            $article->setSlug("article-$i");
            $article->setContent("Content of article $i");
            $article->setUsers($user);
            $em->persist($article);
        }
        $em->flush();
    }

    public function testHomePageIsAccessible(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
    }

    public function testHomePageContainsArticles(): void
    {
        $this->client->request('GET', '/');
        self::assertSelectorExists('.article-card');
    }

    public function testHomePageContainsNavbar(): void
    {
        $this->client->request('GET', '/');
        self::assertSelectorExists('nav');
    }

    public function testHomePageContainsHeroSection(): void
    {
        $this->client->request('GET', '/');
        self::assertSelectorExists('.hero');
    }
}
