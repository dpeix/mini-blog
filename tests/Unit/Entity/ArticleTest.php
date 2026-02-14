<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\Users;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ArticleTest extends TestCase
{
    private Article $article;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->article = new Article();
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    // --- Valeurs par défaut ---

    public function testIdIsNullBeforePersist(): void
    {
        $this->assertNull($this->article->getId());
    }

    public function testLikesDefaultToZero(): void
    {
        $this->assertSame(0, $this->article->getLikes());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->article->getCreatedAt());
    }

    public function testCreatedAtIsNeverNull(): void
    {
        $article = new Article();
        $this->assertNotNull($article->getCreatedAt());
    }

    // --- Setters / Getters ---

    public function testSetAndGetTitle(): void
    {
        $this->article->setTitle('Mon premier article');
        $this->assertSame('Mon premier article', $this->article->getTitle());
    }

    public function testSetAndGetSlug(): void
    {
        $this->article->setSlug('mon-premier-article');
        $this->assertSame('mon-premier-article', $this->article->getSlug());
    }

    public function testSetAndGetContent(): void
    {
        $content = 'Contenu de l\'article avec du texte long.';
        $this->article->setContent($content);
        $this->assertSame($content, $this->article->getContent());
    }

    public function testSetAndGetLikes(): void
    {
        $this->article->setLikes(42);
        $this->assertSame(42, $this->article->getLikes());
    }

    public function testSetAndGetCreatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-06-01 09:00:00');
        $this->article->setCreatedAt($date);
        $this->assertSame($date, $this->article->getCreatedAt());
    }

    public function testSetAndGetUser(): void
    {
        $user = new Users();
        $user->setUsername('author');

        $this->article->setUsers($user);

        $this->assertSame($user, $this->article->getUsers());
    }

    public function testLikesCanBeIncremented(): void
    {
        $this->article->setLikes($this->article->getLikes() + 1);
        $this->assertSame(1, $this->article->getLikes());
    }

    // --- Contraintes de validation ---

    public function testValidArticleHasNoViolations(): void
    {
        $article = $this->createValidArticle();
        $violations = $this->validator->validate($article);

        $this->assertCount(0, $violations);
    }

    public function testTitleIsRequired(): void
    {
        $article = $this->createValidArticle();
        $article->setTitle('');

        $violations = $this->validator->validate($article);

        $this->assertGreaterThanOrEqual(1, count($violations));
        $this->assertViolationOnProperty($violations, 'title');
    }

    public function testContentIsRequired(): void
    {
        $article = $this->createValidArticle();
        $article->setContent('');

        $violations = $this->validator->validate($article);

        $this->assertGreaterThanOrEqual(1, count($violations));
        $this->assertViolationOnProperty($violations, 'content');
    }

    public function testSlugIsRequired(): void
    {
        $article = $this->createValidArticle();
        $article->setSlug('');

        $violations = $this->validator->validate($article);

        $this->assertGreaterThanOrEqual(1, count($violations));
        $this->assertViolationOnProperty($violations, 'slug');
    }

    public function testUserIsRequired(): void
    {
        $article = $this->createValidArticle();
        $article->setUsers(null);

        $violations = $this->validator->validate($article);

        $this->assertGreaterThanOrEqual(1, count($violations));
        $this->assertViolationOnProperty($violations, 'users');
    }

    public function testLikesCannotBeNegative(): void
    {
        $article = $this->createValidArticle();
        $article->setLikes(-1);

        $violations = $this->validator->validate($article);

        $this->assertGreaterThanOrEqual(1, count($violations));
        $this->assertViolationOnProperty($violations, 'likes');
    }

    // --- Helpers ---

    private function createValidArticle(): Article
    {
        $user = new Users();
        $user->setUsername('author');
        $user->setEmail('author@example.com');
        $user->setPassword('$2y$13$hashedpasswordvalue');

        $article = new Article();
        $article->setTitle('Mon article de test');
        $article->setSlug('mon-article-de-test');
        $article->setContent('Un contenu suffisant pour le test.');
        $article->setUsers($user);

        return $article;
    }

    private function assertViolationOnProperty(iterable $violations, string $property): void
    {
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === $property) {
                $this->assertTrue(true);
                return;
            }
        }

        $this->fail(sprintf('Expected a violation on property "%s" but none was found.', $property));
    }
}
