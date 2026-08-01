<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeInterfaceTerminology
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type'));
        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $html = (string) $response->getContent();
        if ($html === '') {
            return $response;
        }

        $html = $this->replaceTerms($html);
        $html = $this->removeHolySpringControls($html);
        $html = $this->injectMapEnhancements($html);

        $response->setContent($html);

        return $response;
    }

    private function replaceTerms(string $html): string
    {
        return str_replace(
            [
                'Места веры рядом',
                'Достижения и квесты',
                'достижения и квесты',
                'достижения квесты',
                'Квест дня',
                'квест дня',
                'Квестами',
                'квестами',
                'Квестах',
                'квестах',
                'Квестов',
                'квестов',
                'Квестом',
                'квестом',
                'Квеста',
                'квеста',
                'Квесты',
                'квесты',
                'Квест',
                'квест',
                'Храмы, монастыри и святые источники',
                'храмы, монастыри и святые источники',
                'Монастыри, храмы и святые источники',
                'монастыри, храмы и святые источники',
            ],
            [
                'Храмы и монастыри',
                'Достижения и паломнические маршруты',
                'достижения и паломнические маршруты',
                'достижения паломнические маршруты',
                'Маршрут дня',
                'маршрут дня',
                'Паломническими маршрутами',
                'паломническими маршрутами',
                'Паломнических маршрутах',
                'паломнических маршрутах',
                'Паломнических маршрутов',
                'паломнических маршрутов',
                'Паломническим маршрутом',
                'паломническим маршрутом',
                'Паломнического маршрута',
                'паломнического маршрута',
                'Паломнические маршруты',
                'паломнические маршруты',
                'Паломнический маршрут',
                'паломнический маршрут',
                'Храмы и монастыри',
                'храмы и монастыри',
                'Монастыри и храмы',
                'монастыри и храмы',
            ],
            $html
        );
    }

    private function removeHolySpringControls(string $html): string
    {
        $patterns = [
            '~<option\b[^>]*>\s*Святые источники\s*</option>~iu',
            '~<option\b[^>]*>\s*Святой источник\s*</option>~iu',
            '~<div\s+class="col-md-6\s+col-xl-\d+"[^>]*>\s*<(a|div)\b[^>]*class="[^"]*category-card[^"]*"[^>]*>(?:(?!</\1>).)*?<span\b[^>]*>\s*Святые источники\s*</span>(?:(?!</\1>).)*?</\1>\s*</div>~isu',
            '~<div\s+class="[^"]*col-[^"]*"[^>]*>\s*<article\b[^>]*class="[^"]*card-pm[^"]*"[^>]*>(?:(?!</article>).)*?<span\b[^>]*class="[^"]*object-type-badge[^"]*"[^>]*>\s*Святой источник\s*</span>(?:(?!</article>).)*?</article>\s*</div>~isu',
        ];

        return preg_replace($patterns, '', $html) ?: $html;
    }

    private function injectMapEnhancements(string $html): string
    {
        $cssUrl = htmlspecialchars(asset('css/map-enhancements.css'), ENT_QUOTES, 'UTF-8');
        $enhancementsUrl = htmlspecialchars(asset('js/map-enhancements.js'), ENT_QUOTES, 'UTF-8');
        $religiousIconsUrl = htmlspecialchars(asset('js/map-religious-icons.js'), ENT_QUOTES, 'UTF-8');
        $focusedPointsUrl = htmlspecialchars(asset('js/map-focused-points.js'), ENT_QUOTES, 'UTF-8');
        $cssTag = '<link rel="stylesheet" href="'.$cssUrl.'">';
        $scriptTags = '<script src="'.$enhancementsUrl.'"></script>'."\n"
            .'<script src="'.$religiousIconsUrl.'"></script>'."\n"
            .'<script src="'.$focusedPointsUrl.'"></script>';

        if (! str_contains($html, $cssUrl)) {
            $html = str_contains($html, '</head>')
                ? str_replace('</head>', $cssTag."\n</head>", $html)
                : $cssTag.$html;
        }

        if (str_contains($html, $religiousIconsUrl)) {
            return $html;
        }

        $viewportUrl = htmlspecialchars(asset('js/map-viewport.js'), ENT_QUOTES, 'UTF-8');
        $viewportTag = '<script src="'.$viewportUrl.'"></script>';

        if (str_contains($html, $viewportTag)) {
            return str_replace(
                $viewportTag,
                $scriptTags."\n".$viewportTag,
                $html
            );
        }

        return str_contains($html, '</body>')
            ? str_replace('</body>', $scriptTags."\n</body>", $html)
            : $html.$scriptTags;
    }
}
