<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleFormType;
use App\Repository\ArticleRepository;
use App\Service\ArticleCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/articles')]
final class ArticleController extends AbstractController
{
    #[Route('', name: 'app_article_list')]
    public function list(Request $request, ArticleRepository $articleRepository): Response
    {
        $query = $request->query->getString('q');
        $articles = $query !== ''
            ? $articleRepository->searchByTitle($query)
            : $articleRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('article/list.html.twig', [
            'articles' => $articles,
            'query' => $query,
        ]);
    }

    #[Route('/new', name: 'app_article_create')]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, ArticleCacheService $cache): Response
    {
        $article = new Article();
        $form = $this->createForm(ArticleFormType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $title = $article->getTitle() ?? '';
            $article->setSlug(strtolower($slugger->slug($title)));
            $article->setUsers($this->getUser());
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($article);
            $em->flush();
            $cache->invalidateOnCreate();

            return $this->redirectToRoute('app_article_show', ['slug' => $article->getSlug()]);
        }

        return $this->render('article/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/like', name: 'app_article_like', methods: ['POST'])]
    public function like(Article $article, EntityManagerInterface $em, ArticleCacheService $cache): JsonResponse
    {
        $cache->initLikes($article->getId(), $article->getLikes());
        $newCount = $cache->incrementLike($article->getId());

        if ($newCount === null) {
            $article->setLikes($article->getLikes() + 1);
            $em->flush();
            $newCount = $article->getLikes();
        }

        return new JsonResponse(['likes' => $newCount]);
    }

    #[Route('/{slug}', name: 'app_article_show')]
    public function show(string $slug, ArticleRepository $articleRepository): Response
    {
        $article = $articleRepository->findOneBy(['slug' => $slug]);

        if (!$article) {
            throw $this->createNotFoundException('Article not found.');
        }

        return $this->render('article/show.html.twig', [
            'article' => $article,
        ]);
    }
}
