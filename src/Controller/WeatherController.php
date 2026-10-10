<?php

namespace App\Controller;

use App\Weather\ForecastAssembler;
use App\Weather\OpenMeteoClient;
use App\Weather\Place;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WeatherController extends AbstractController
{
    #[Route('/', name: 'app_weather', methods: ['GET'])]
    public function __invoke(Request $request, OpenMeteoClient $forecasts, ForecastAssembler $assembler, LoggerInterface $logger): Response
    {
        $place = Place::fromSlug($request->query->getString('city'));

        try {
            $page = $assembler->assemble($forecasts->forecast($place), $place);
        } catch (GuzzleException|\JsonException|\RuntimeException $exception) {
            $logger->error('Не удалось получить прогноз погоды.', ['exception' => $exception]);

            return $this->render('weather/unavailable.html.twig', [], new Response('', Response::HTTP_SERVICE_UNAVAILABLE));
        }

        return $this->render('weather/show.html.twig', [
            'page' => $page,
            'place' => $place,
            'places' => Place::all(),
        ]);
    }
}
