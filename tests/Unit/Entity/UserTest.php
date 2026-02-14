<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\Users;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserTest extends TestCase
{
    private Users $user;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->user = new Users();
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    // --- Valeurs par défaut ---

    public function testIdIsNullBeforePersist(): void
    {
        $this->assertNull($this->user->getId());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->user->getCreatedAt());
    }

    public function testCreatedAtIsNeverNull(): void
    {
        $user = new Users();
        $this->assertNotNull($user->getCreatedAt());
    }

    public function testArticlesCollectionIsInitializedOnConstruction(): void
    {
        $this->assertInstanceOf(Collection::class, $this->user->getArticles());
        $this->assertCount(0, $this->user->getArticles());
    }

    // --- Setters / Getters ---

    public function testSetAndGetUsername(): void
    {
        $this->user->setUsername('johndoe');
        $this->assertSame('johndoe', $this->user->getUsername());
    }

    public function testSetAndGetEmail(): void
    {
        $this->user->setEmail('john@example.com');
        $this->assertSame('john@example.com', $this->user->getEmail());
    }

    public function testSetAndGetPassword(): void
    {
        $hashed = '$2y$13$hashedpassword';
        $this->user->setPassword($hashed);
        $this->assertSame($hashed, $this->user->getPassword());
    }

    public function testSetAndGetCreatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-15 10:00:00');
        $this->user->setCreatedAt($date);
        $this->assertSame($date, $this->user->getCreatedAt());
    }

    // --- Contraintes de validation ---

    public function testValidUserHasNoViolations(): void
    {
        $user = $this->createValidUser();
        $violations = $this->validator->validate($user);

        $this->assertCount(0, $violations);
    }

    public function testUsernameIsRequired(): void
    {
        $user = $this->createValidUser();
        $user->setUsername('');

        $violations = $this->validator->validate($user);

        $this->assertGreaterThanOrEqual(1, count($violations));
        $this->assertViolationOnProperty($violations, 'username');
    }

    public function testEmailIsRequired(): void
    {
        $user = $this->createValidUser();
        $user->setEmail('');

        $violations = $this->validator->validate($user);

        $this->assertGreaterThanOrEqual(1, count($violations));
        $this->assertViolationOnProperty($violations, 'email');
    }

    public function testEmailMustBeValid(): void
    {
        $user = $this->createValidUser();
        $user->setEmail('not-an-email');

        $violations = $this->validator->validate($user);

        $this->assertGreaterThanOrEqual(1, count($violations));
        $this->assertViolationOnProperty($violations, 'email');
    }

    public function testPasswordIsRequired(): void
    {
        $user = $this->createValidUser();
        $user->setPassword('');

        $violations = $this->validator->validate($user);

        $this->assertGreaterThanOrEqual(1, count($violations));
        $this->assertViolationOnProperty($violations, 'password');
    }

    // --- Relation User <-> Article ---

    public function testAddArticle(): void
    {
        $article = new Article();
        $article->setTitle('Test Article');

        $this->user->addArticle($article);

        $this->assertCount(1, $this->user->getArticles());
        $this->assertTrue($this->user->getArticles()->contains($article));
    }

    public function testAddArticleSetsUserOnArticle(): void
    {
        $article = new Article();
        $this->user->addArticle($article);

        $this->assertSame($this->user, $article->getUsers());
    }

    public function testAddArticleDoesNotAddDuplicate(): void
    {
        $article = new Article();

        $this->user->addArticle($article);
        $this->user->addArticle($article);

        $this->assertCount(1, $this->user->getArticles());
    }

    public function testRemoveArticle(): void
    {
        $article = new Article();
        $this->user->addArticle($article);

        $this->user->removeArticle($article);

        $this->assertCount(0, $this->user->getArticles());
        $this->assertFalse($this->user->getArticles()->contains($article));
    }

    public function testRemoveArticleUnsetsUserOnArticle(): void
    {
        $article = new Article();
        $this->user->addArticle($article);

        $this->user->removeArticle($article);

        $this->assertNull($article->getUsers());
    }

    public function testGetArticlesReturnsAllAddedArticles(): void
    {
        $article1 = new Article();
        $article1->setTitle('First');

        $article2 = new Article();
        $article2->setTitle('Second');

        $this->user->addArticle($article1);
        $this->user->addArticle($article2);

        $articles = $this->user->getArticles();

        $this->assertCount(2, $articles);
        $this->assertTrue($articles->contains($article1));
        $this->assertTrue($articles->contains($article2));
    }

    // --- Helpers ---

    private function createValidUser(): Users
    {
        $user = new Users();
        $user->setUsername('johndoe');
        $user->setEmail('john@example.com');
        $user->setPassword('$2y$13$hashedpasswordvalue');

        return $user;
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
