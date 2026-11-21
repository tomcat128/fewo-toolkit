<?php

namespace FewoTk2\Tour;

/**
 * Daten eines 360°-Rundgangs, wie sie der Szenen-Editor des Blocks speichert, und ihre Übersetzung
 * in die Konfiguration des Pannellum-Viewers.
 *
 *   first:  Name des Startraums
 *   scenes: [{name, fID, title, yaw, pitch, hfov, horizonPitch, horizonRoll, hotSpots: [
 *              {type: "scene", pitch, yaw, text, target, targetYaw?, targetPitch?},   Wegweiser
 *              {type: "info",  pitch, yaw, text}                                      Hinweis
 *           ]}]
 *
 * Der Name eines Raums ist zugleich der Platzhalter {name} für sein Bild und das Ziel von Wegweisern.
 */
final class TourConfig
{
    public const HOTSPOT_SCENE = 'scene';
    public const HOTSPOT_INFO = 'info';

    /** Platzhalter für das Vorschaubild; beginnt mit "_" und kann deshalb kein Raumname sein. */
    public const PREVIEW_PLACEHOLDER = '_preview';

    private const DEFAULT_HFOV = 100.0;
    private const MAX_SCENES = 50;
    private const MAX_HOTSPOTS = 100;

    /**
     * Bereinigt die Daten aus dem Formular: gültige, eindeutige Namen, Winkel im üblichen Bereich,
     * Wegweiser nur zu vorhandenen Räumen.
     *
     * @param mixed $input dekodiertes JSON aus dem Formular
     *
     * @return array{first: string, scenes: array<int, array<string, mixed>>}
     */
    public static function sanitize(mixed $input): array
    {
        $scenes = [];
        $names = [];
        foreach (is_array($input) && is_array($input['scenes'] ?? null) ? $input['scenes'] : [] as $scene) {
            if (!is_array($scene) || count($scenes) >= self::MAX_SCENES) {
                continue;
            }
            $name = self::uniqueName(self::slug((string) ($scene['name'] ?? '')), $names);
            $names[(string) ($scene['name'] ?? '')] ??= $name;
            $scenes[] = [
                'name' => $name,
                'fID' => max(0, (int) ($scene['fID'] ?? 0)),
                'title' => self::text($scene['title'] ?? '', 255),
                'yaw' => self::yaw($scene['yaw'] ?? 0),
                'pitch' => self::pitch($scene['pitch'] ?? 0),
                'hfov' => self::clamp($scene['hfov'] ?? self::DEFAULT_HFOV, 30, 120, self::DEFAULT_HFOV),
                'horizonPitch' => self::clamp($scene['horizonPitch'] ?? 0, -45, 45, 0),
                'horizonRoll' => self::clamp($scene['horizonRoll'] ?? 0, -45, 45, 0),
                'hotSpots' => is_array($scene['hotSpots'] ?? null) ? $scene['hotSpots'] : [],
            ];
        }

        $valid = array_column($scenes, 'name');
        foreach ($scenes as &$scene) {
            $hotSpots = [];
            foreach ($scene['hotSpots'] as $hotSpot) {
                if (!is_array($hotSpot) || count($hotSpots) >= self::MAX_HOTSPOTS) {
                    continue;
                }
                $clean = [
                    'type' => ($hotSpot['type'] ?? '') === self::HOTSPOT_SCENE ? self::HOTSPOT_SCENE : self::HOTSPOT_INFO,
                    'pitch' => self::pitch($hotSpot['pitch'] ?? 0),
                    'yaw' => self::yaw($hotSpot['yaw'] ?? 0),
                    'text' => self::text($hotSpot['text'] ?? '', 500),
                ];
                if ($clean['type'] === self::HOTSPOT_SCENE) {
                    // Ziel wie eingegeben oder – nach einer Umbenennung beim Bereinigen – unter dem neuen Namen
                    $target = (string) ($hotSpot['target'] ?? '');
                    $target = $names[$target] ?? self::slug($target);
                    if (!in_array($target, $valid, true) || $target === $scene['name']) {
                        continue;
                    }
                    $clean['target'] = $target;
                    if (is_numeric($hotSpot['targetYaw'] ?? null)) {
                        $clean['targetYaw'] = self::yaw($hotSpot['targetYaw']);
                        $clean['targetPitch'] = self::pitch($hotSpot['targetPitch'] ?? 0);
                    }
                } elseif ($clean['text'] === '') {
                    continue;
                }
                $hotSpots[] = $clean;
            }
            $scene['hotSpots'] = $hotSpots;
        }
        unset($scene);

        $first = is_array($input) ? (string) ($input['first'] ?? '') : '';
        $first = $names[$first] ?? self::slug($first);

        return [
            'first' => in_array($first, $valid, true) ? $first : ($valid[0] ?? ''),
            'scenes' => $scenes,
        ];
    }

    /**
     * Konfiguration für den Pannellum-Viewer. Bilder stehen als Platzhalter {name} darin; welche Adresse
     * eingesetzt wird (groß oder für Mobilgeräte), entscheidet der Browser.
     *
     * @param array{first: string, scenes: array<int, array<string, mixed>>} $tour bereinigte Daten
     * @param string[] $available Namen der Räume, deren Bild vorhanden ist; die übrigen werden ausgelassen
     * @param array{autoLoad?: bool, compass?: bool, author?: string, preview?: bool} $options
     *
     * @return array<string, mixed>|null null, wenn kein Raum ein Bild hat
     */
    public static function toPannellum(array $tour, array $available, array $options = []): ?array
    {
        $scenes = [];
        foreach ($tour['scenes'] as $scene) {
            if (!in_array($scene['name'], $available, true)) {
                continue;
            }
            $config = [
                'title' => $scene['title'],
                'type' => 'equirectangular',
                'panorama' => '{' . $scene['name'] . '}',
                'yaw' => $scene['yaw'],
                'pitch' => $scene['pitch'],
                'hfov' => $scene['hfov'],
            ];
            foreach (['horizonPitch', 'horizonRoll'] as $key) {
                if ((float) $scene[$key] !== 0.0) {
                    $config[$key] = $scene[$key];
                }
            }
            $config['hotSpots'] = [];
            foreach ($scene['hotSpots'] as $hotSpot) {
                $entry = ['type' => $hotSpot['type'], 'pitch' => $hotSpot['pitch'], 'yaw' => $hotSpot['yaw']];
                if ($hotSpot['text'] !== '') {
                    $entry['text'] = $hotSpot['text'];
                }
                if ($hotSpot['type'] === self::HOTSPOT_SCENE) {
                    if (!in_array($hotSpot['target'], $available, true)) {
                        continue;
                    }
                    $entry['sceneId'] = $hotSpot['target'];
                    // Ohne eigene Angabe kommt man in der Startposition des Zielraums an
                    if (isset($hotSpot['targetYaw'])) {
                        $entry['targetYaw'] = $hotSpot['targetYaw'];
                        $entry['targetPitch'] = $hotSpot['targetPitch'];
                    }
                }
                $config['hotSpots'][] = $entry;
            }
            $scenes[$scene['name']] = $config;
        }
        if ($scenes === []) {
            return null;
        }

        $default = [
            'firstScene' => isset($scenes[$tour['first']]) ? $tour['first'] : array_key_first($scenes),
            'sceneFadeDuration' => 500,
            'autoLoad' => (bool) ($options['autoLoad'] ?? true),
        ];
        if (!empty($options['compass'])) {
            $default['compass'] = true;
        }
        if (trim((string) ($options['author'] ?? '')) !== '') {
            $default['author'] = trim((string) $options['author']);
        }
        if (!empty($options['preview'])) {
            $default['preview'] = '{' . self::PREVIEW_PLACEHOLDER . '}';
        }

        return ['default' => $default, 'scenes' => $scenes];
    }

    /**
     * Name eines Raums: Kleinbuchstaben, Ziffern, "-" und "_", beginnt mit einem Buchstaben.
     */
    public static function slug(string $value): string
    {
        $value = strtr(mb_strtolower(trim($value)), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $value = trim((string) preg_replace(['/[^a-z0-9_-]+/', '/-{2,}/'], '-', $value), '-_');
        if ($value === '') {
            return 'raum';
        }

        return mb_substr(preg_match('/^[a-z]/', $value) ? $value : 'raum-' . $value, 0, 40);
    }

    /**
     * @param array<string, string> $names bisher vergebene Namen (ursprünglicher Name => bereinigter Name)
     */
    private static function uniqueName(string $name, array $names): string
    {
        $candidate = $name;
        for ($i = 2; in_array($candidate, $names, true); $i++) {
            $candidate = $name . '-' . $i;
        }

        return $candidate;
    }

    private static function text(mixed $value, int $length): string
    {
        return is_scalar($value) ? mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''), 0, $length) : '';
    }

    /** Blickrichtung waagerecht, -180 … 180 Grad. */
    private static function yaw(mixed $value): float
    {
        $value = is_numeric($value) ? fmod((float) $value, 360) : 0.0;
        if ($value > 180) {
            $value -= 360;
        } elseif ($value <= -180) {
            $value += 360;
        }

        return round($value, 2);
    }

    /** Blickrichtung senkrecht, -90 … 90 Grad (alte Rundgänge schreiben -4° auch als 356°). */
    private static function pitch(mixed $value): float
    {
        return max(-90.0, min(90.0, self::yaw($value)));
    }

    private static function clamp(mixed $value, float $min, float $max, float $default): float
    {
        return is_numeric($value) ? round(max($min, min($max, (float) $value)), 2) : $default;
    }
}
