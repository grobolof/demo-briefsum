<?php

namespace App\Controller;

use App\Entity\News;
use App\Repository\NewsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NewsController extends AbstractController
{
    #[Route('/news', name: 'app_news_index', methods: ['GET'])]
    public function index(Request $request, NewsRepository $news): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $sort = 'asc' === $request->query->getString('sort') ? 'asc' : 'desc';
        ['items' => $items, 'hasMore' => $hasMore] = $news->page($page, $sort);

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'html' => $this->renderView('news/_cards.html.twig', ['items' => $items]),
                'hasMore' => $hasMore,
            ]);
        }

        return $this->render('news/index.html.twig', [
            'items' => $items,
            'has_more' => $hasMore,
            'next_page' => $page + 1,
            'sort' => $sort,
        ]);
    }

    #[Route('/news/{id}', name: 'app_news_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(News $news): Response
    {
        return $this->render('news/show.html.twig', [
            'news' => $news,
        ]);
    }
}
