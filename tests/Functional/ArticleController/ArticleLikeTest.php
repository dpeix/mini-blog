<?php

namespace App\Tests\Functional\ArticleController;

use App\Entity\Article;
use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ArticleLikeTest extends WebTestCase
{
    private KernelBrowser $client;
    private int $articleId;

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
        $article->setTitle('Likeable Article');
        $article->setSlug('likeable-article');
        $article->setContent('Content of the likeable article.');
        $article->setUsers($user);
        $em->persist($article);
        $em->flush();

        $this->articleId = $article->getId();
    }

    public function testLikeReturnsJsonWithLikesCount(): void
    {
        $this->client->request('POST', '/articles/' . $this->articleId . '/like');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('likes', $data);
        self::assertSame(1, $data['likes']);
    }

    public function testLikeIncrementsCounter(): void
    {
        $this->client->request('POST', '/articles/' . $this->articleId . '/like');
        $this->client->request('POST', '/articles/' . $this->articleId . '/like');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(2, $data['likes']);
    }

    public function testLikeRejectsGetMethod(): void
    {
        $this->client->request('GET', '/articles/' . $this->articleId . '/like');
        self::assertResponseStatusCodeSame(405);
    }

    public function testLikeReturns404ForNonExistentArticle(): void
    {
        $this->client->request('POST', '/articles/99999/like');
        self::assertResponseStatusCodeSame(404);
    }
}
