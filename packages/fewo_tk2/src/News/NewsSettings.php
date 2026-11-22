<?php

namespace FewoTk2\News;

/**
 * Darstellung der Neuigkeiten auf der ganzen Website (Verwaltung › Fewo Toolkit 2 › Neuigkeiten): die Stufen der
 * Wichtigkeit (Code aus dem Feed, Bezeichnung, Farbe, Reihenfolge) und ob der Gültigkeitszeitraum erscheint.
 */
final class NewsSettings
{
    /**
     * @param array<int, array{code: string, label: string, color: string}> $levels
     */
    public function __construct(
        public readonly array $levels,
        public readonly bool $showPeriod = true,
    ) {
    }

    /**
     * Gespeicherte Werte; fehlen sie, gelten die Vorgaben.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $levels = ImportanceLevels::sanitize($data['levels'] ?? null);

        return new self(
            $levels !== [] ? $levels : ImportanceLevels::defaults(),
            !array_key_exists('showPeriod', $data) || !empty($data['showPeriod']),
        );
    }

    /**
     * @return array{levels: array<int, array{code: string, label: string, color: string}>, showPeriod: bool}
     */
    public function toArray(): array
    {
        return ['levels' => $this->levels, 'showPeriod' => $this->showPeriod];
    }

    /**
     * Eingabefehler der Stufen aus dem Formular (leer = in Ordnung).
     *
     * @return string[]
     */
    public static function validateCodes(mixed $codes): array
    {
        $codes = array_map(ImportanceLevels::code(...), is_array($codes) ? array_values($codes) : []);
        $errors = [];
        if (in_array('', $codes, true)) {
            $errors[] = t('Each importance level needs a code (letters, digits, "-" or "_", e.g. info).');
        }
        if (count($codes) !== count(array_unique($codes))) {
            $errors[] = t('Each code may only be assigned to one importance level.');
        }

        return $errors;
    }
}
