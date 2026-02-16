<?php

namespace App\Tests\Functional\ArticleController;

use App\Entity\Article;
use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ArticleSearchTest extends WebTestCase
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

        $article1 = new Article();
        $article1->setTitle('Symfony Tutorial');
        $article1->setSlug('symfony-tutorial');
        $article1->setContent('Learn Symfony framework.');
        $article1->setUsers($user);
        $em->persist($article1);

        $article2 = new Article();
        $article2->setTitle('Docker Guide');
        $article2->setSlug('docker-guide');
        $article2->setContent('Docker containers explained.');
        $article2->setUsers($user);
        $em->persist($article2);

        $article3 = new Article();
        $article3->setTitle('Symfony Security');
        $article3->setSlug('symfony-security');
        $article3->setContent('Authentication and authorization.');
        $article3->setUsers($user);
        $em->persist($article3);

        $em->flush();
    }

    public function testSearchPageIsAccessible(): void
    {
        $this->client->request('GET', '/articles?q=Symfony');
        self::assertResponseIsSuccessful();
    }

    public function testSearchFiltersArticlesByTitle(): void
    {
        $crawler = $this->client->request('GET', '/articles?q=Symfony');
        self::assertCount(2, $crawler->filter('.article-card'));
    }

    public function testSearchExcludesNonMatchingArticles(): void
    {
        $crawler = $this->client->request('GET', '/articles?q=Docker');
        self::assertCount(1, $crawler->filter('.article-card'));
        self::assertSelectorTextContains('.article-card', 'Docker Guide');
    }

    public function testEmptySearchReturnsAllArticles(): void
    {
        $crawler = $this->client->request('GET', '/articles?q=');
        self::assertCount(3, $crawler->filter('.article-card'));
    }

    public function testSearchWithNoResultsShowsEmptyState(): void
    {
        $crawler = $this->client->request('GET', '/articles?q=nonexistent');
        self::assertCount(0, $crawler->filter('.article-card'));
    }
}
