<?php

declare(strict_types=1);

namespace Prometheus\Core;

abstract class Controller
{
    protected function view(string $title, string $content): Response
    {
        $body = <<<HTML
        <!doctype html>
        <html lang="it">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$title} - Prometheus2</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="/assets/css/app.css" rel="stylesheet">
        </head>
        <body>
            {$content}
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        HTML;

        return new Response($body);
    }
}
