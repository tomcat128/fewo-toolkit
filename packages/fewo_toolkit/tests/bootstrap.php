<?php

require __DIR__ . '/../vendor/autoload.php';

// Minimaler Ersatz für die Übersetzungsfunktion von Concrete CMS.
if (!function_exists('t')) {
    function t(string $text, ...$args): string
    {
        return $args === [] ? $text : vsprintf($text, $args);
    }
}
