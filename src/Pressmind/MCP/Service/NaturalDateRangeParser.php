<?php

declare(strict_types=1);

namespace Pressmind\MCP\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Parses natural-language date ranges (DE/EN) into YYYYMMDD-YYYYMMDD for Query::pm-dr.
 */
final class NaturalDateRangeParser
{
    /**
     * @return array{0: string, 1: string}|null Two YYYYMMDD strings [from, to] or null if not recognized
     */
    public static function parse(string $input, ?DateTimeZone $tz = null): ?array
    {
        $tz = $tz ?? new DateTimeZone('Europe/Berlin');
        $s = mb_strtolower(trim($input));
        if ($s === '') {
            return null;
        }

        $now = new DateTimeImmutable('now', $tz);

        try {
            // Next / this month
            if (preg_match('/\b(next month|nächsten monat|naechsten monat)\b/u', $s)) {
                $first = $now->modify('first day of next month')->setTime(0, 0);
                $last = $first->modify('last day of this month')->setTime(0, 0);

                return [self::ymd($first), self::ymd($last)];
            }
            if (preg_match('/\b(this month|diesen monat)\b/u', $s)) {
                $first = $now->modify('first day of this month')->setTime(0, 0);
                $last = $now->modify('last day of this month')->setTime(0, 0);

                return [self::ymd($first), self::ymd($last)];
            }

            // Next week (ISO week: next Monday .. Sunday)
            if (preg_match('/\b(next week|naechste woche|nächste woche)\b/u', $s)) {
                $dow = (int) $now->format('N'); // 1=Mon .. 7=Sun
                $daysToNextMonday = $dow === 7 ? 1 : (8 - $dow);
                $mon = $now->add(new DateInterval('P' . $daysToNextMonday . 'D'))->setTime(0, 0);
                $sun = $mon->add(new DateInterval('P6D'));

                return [self::ymd($mon), self::ymd($sun)];
            }

            // Summer (Jun 1 – Aug 31)
            if (preg_match('/\b(this summer|diesen sommer)\b/u', $s)) {
                $y = (int) $now->format('Y');
                $m = (int) $now->format('n');
                if ($m > 8) {
                    $y++;
                }
                $from = new DateTimeImmutable(sprintf('%04d-06-01', $y), $tz);
                $to = new DateTimeImmutable(sprintf('%04d-08-31', $y), $tz);

                return [self::ymd($from), self::ymd($to)];
            }

            // Winter (Dec 1 – last day Feb): current season if we are in Dec–Feb, else upcoming
            if (preg_match('/\b(this winter|diesen winter)\b/u', $s)) {
                $m = (int) $now->format('n');
                $y = (int) $now->format('Y');
                if ($m === 12) {
                    $from = new DateTimeImmutable(sprintf('%04d-12-01', $y), $tz);
                    $to = (new DateTimeImmutable(sprintf('%04d-02-01', $y + 1), $tz))->modify('last day of this month')->setTime(0, 0);
                } elseif ($m <= 2) {
                    $from = new DateTimeImmutable(sprintf('%04d-12-01', $y - 1), $tz);
                    $to = (new DateTimeImmutable(sprintf('%04d-02-01', $y), $tz))->modify('last day of this month')->setTime(0, 0);
                } else {
                    $from = new DateTimeImmutable(sprintf('%04d-12-01', $y), $tz);
                    $to = (new DateTimeImmutable(sprintf('%04d-02-01', $y + 1), $tz))->modify('last day of this month')->setTime(0, 0);
                }

                return [self::ymd($from), self::ymd($to)];
            }

            // Christmas period
            if (preg_match('/\b(weihnachten|christmas)\b/u', $s)) {
                $y = (int) $now->format('Y');
                if ((int) $now->format('md') > '1231') {
                    $y++;
                }
                $from = new DateTimeImmutable(sprintf('%04d-12-20', $y), $tz);
                $to = new DateTimeImmutable(sprintf('%04d-12-31', $y), $tz);

                return [self::ymd($from), self::ymd($to)];
            }

            // Silvester / New Year bridge
            if (preg_match('/\b(silvester|new year\'?s?)\b/u', $s)) {
                $y = (int) $now->format('Y');
                if ((int) $now->format('n') === 1 && (int) $now->format('j') <= 3) {
                    $from = new DateTimeImmutable(sprintf('%04d-12-28', $y - 1), $tz);
                    $to = new DateTimeImmutable(sprintf('%04d-01-02', $y), $tz);
                } else {
                    $from = new DateTimeImmutable(sprintf('%04d-12-28', $y), $tz);
                    $to = new DateTimeImmutable(sprintf('%04d-01-02', $y + 1), $tz);
                }

                return [self::ymd($from), self::ymd($to)];
            }

            // Easter (Good Friday .. Easter Monday)
            if (preg_match('/\b(ostern|easter)\b/u', $s)) {
                $y = (int) $now->format('Y');
                $easterTs = easter_date($y);
                $easterSunday = (new DateTimeImmutable('@' . $easterTs))->setTimezone($tz)->setTime(0, 0);
                $easterMonday = $easterSunday->add(new DateInterval('P1D'));
                if ($now > $easterMonday) {
                    $y++;
                    $easterTs = easter_date($y);
                    $easterSunday = (new DateTimeImmutable('@' . $easterTs))->setTimezone($tz)->setTime(0, 0);
                }
                $from = $easterSunday->sub(new DateInterval('P2D'));
                $to = $easterSunday->add(new DateInterval('P1D'));

                return [self::ymd($from), self::ymd($to)];
            }

            // Pfingsten (Whitsun weekend: Sat–Mon around Pentecost)
            if (preg_match('/\b(pfingsten|whitsun|pentecost)\b/u', $s)) {
                $y = (int) $now->format('Y');
                $easterTs = easter_date($y);
                $easter = (new DateTimeImmutable('@' . $easterTs))->setTimezone($tz)->setTime(0, 0);
                $pentecost = $easter->add(new DateInterval('P49D'));
                if ($now > $pentecost->add(new DateInterval('P1D'))) {
                    $y++;
                    $easterTs = easter_date($y);
                    $easter = (new DateTimeImmutable('@' . $easterTs))->setTimezone($tz)->setTime(0, 0);
                    $pentecost = $easter->add(new DateInterval('P49D'));
                }
                $from = $pentecost->sub(new DateInterval('P1D'));
                $to = $pentecost->add(new DateInterval('P1D'));

                return [self::ymd($from), self::ymd($to)];
            }

            // "im Juli" / "in July"
            if (preg_match('/\b(im|in)\s+(januar|februar|märz|maerz|april|mai|juni|juli|august|september|oktober|november|dezember|january|february|march|april|may|june|july|august|september|october|november|december)\b/u', $s, $m)) {
                $monthMap = [
                    'januar' => 1, 'january' => 1,
                    'februar' => 2, 'february' => 2,
                    'märz' => 3, 'maerz' => 3, 'march' => 3,
                    'april' => 4,
                    'mai' => 5, 'may' => 5,
                    'juni' => 6, 'june' => 6,
                    'juli' => 7, 'july' => 7,
                    'august' => 8,
                    'september' => 9,
                    'oktober' => 10, 'october' => 10,
                    'november' => 11,
                    'dezember' => 12, 'december' => 12,
                ];
                $monName = mb_strtolower($m[2]);
                $month = $monthMap[$monName] ?? null;
                if ($month !== null) {
                    $y = (int) $now->format('Y');
                    $curMonth = (int) $now->format('n');
                    if ($month < $curMonth) {
                        $y++;
                    }
                    $from = new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $month), $tz);
                    $to = $from->modify('last day of this month')->setTime(0, 0);

                    return [self::ymd($from), self::ymd($to)];
                }
            }
        } catch (Exception $e) {
            return null;
        }

        return null;
    }

    private static function ymd(DateTimeImmutable $d): string
    {
        return $d->format('Ymd');
    }
}
