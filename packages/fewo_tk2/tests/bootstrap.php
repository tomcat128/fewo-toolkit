<?php

require __DIR__ . '/../vendor/autoload.php';

// Minimaler Ersatz für die Übersetzungsfunktion von Concrete CMS.
if (!function_exists('t')) {
    function t(string $text, ...$args): string
    {
        return $args === [] ? $text : vsprintf($text, $args);
    }
}

// … und für die Mehrzahlform: Wie in Concrete ist die Anzahl der erste Platzhalter.
if (!function_exists('t2')) {
    function t2(string $singular, string $plural, int $number, ...$args): string
    {
        return vsprintf($number === 1 ? $singular : $plural, [$number, ...$args]);
    }
}
