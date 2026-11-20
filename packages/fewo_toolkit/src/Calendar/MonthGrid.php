<?php

namespace FewoToolkit\Calendar;

use DateTimeImmutable;

/**
 * Baut die Monatsansichten für den Belegungsplan.
 */
final class MonthGrid
{
    /**
     * @return array<int, array{
     *     month: DateTimeImmutable,
     *     leading: int,
     *     days: array<int, array{date: DateTimeImmutable, am: string, pm: string, past: bool, today: bool}>
     * }>
     */
    public static function build(Occupancy $occupancy, DateTimeImmutable $today, int $months): array
    {
        $today = $today->setTime(0, 0);
        $first = $today->modify('first day of this month');
        $result = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $first->modify("+{$i} months");
            $daysInMonth = (int) $month->format('t');
            $days = [];
            for ($d = 0; $d < $daysInMonth; $d++) {
                $date = $month->modify("+{$d} days");
                $state = $occupancy->day($date);
                $days[] = [
                    'date' => $date,
                    'am' => $state['am'],
                    'pm' => $state['pm'],
                    'past' => $date < $today,
                    'today' => $date == $today,
                ];
            }
            $result[] = [
                'month' => $month,
                // ISO-Wochentag: Montag = 1 … Sonntag = 7
                'leading' => (int) $month->format('N') - 1,
                'days' => $days,
            ];
        }

        return $result;
    }
}
