<?php

declare(strict_types=1);

namespace Pressmind\Search;

use DateTimeImmutable;
use DateTimeZone;

final class NaturalLanguageQueryPlanner
{
    /** @var array<string, mixed> */
    private array $options;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function plan(string $query, array $options = []): array
    {
        $options = array_replace($this->options, $options);
        $now = $this->resolveNow($options['now'] ?? null);
        $remaining = $this->normalize($query);
        $request = ['pm-o' => (string) ($options['order'] ?? 'list')];
        $resolved = [];
        $warnings = [];

        $categoryMatches = $this->extractCategoryTerms($remaining, $options['category_terms'] ?? []);
        foreach ($categoryMatches as $match) {
            $field = $match['field'];
            $softCategoryFields = $options['soft_category_fields'] ?? ['reisethema_default' => true];
            $hardFilter = ! (is_array($softCategoryFields) && ! empty($softCategoryFields[$field]));
            if ($hardFilter) {
                if (! isset($request['pm-c'])) {
                    $request['pm-c'] = [];
                }
                if (isset($request['pm-c'][$field]) && $request['pm-c'][$field] !== '') {
                    $request['pm-c'][$field] .= ',' . $match['id'];
                } else {
                    $request['pm-c'][$field] = $match['id'];
                }
                $remaining = $this->removeMatch($remaining, $match['match']);
            }
            $resolved[] = [
                'type' => 'category',
                'field' => $field,
                'id' => $match['id'],
                'name' => $match['name'],
                'matched' => $match['match'],
                'hard_filter' => $hardFilter,
            ];
        }

        $objectType = $this->extractObjectType($remaining, $options['object_type_terms'] ?? []);
        if ($objectType !== null) {
            $request['pm-ot'] = $objectType['id'];
            $remaining = $this->removeMatch($remaining, $objectType['match']);
            $resolved[] = ['type' => 'object_type', 'id' => $objectType['id'], 'name' => $objectType['name']];
        }

        $dateRange = $this->extractDateRange($remaining, $now, $options);
        if ($dateRange !== null) {
            $request['pm-dr'] = $dateRange['from'] . '-' . $dateRange['to'];
            $remaining = $this->removeMatch($remaining, $dateRange['match']);
            $resolved[] = ['type' => 'date_range', 'from' => $dateRange['from'], 'to' => $dateRange['to'], 'matched' => $dateRange['match']];
        }

        $priceRange = $this->extractPriceRange($remaining);
        if ($priceRange !== null) {
            $request['pm-pr'] = $priceRange['from'] . '-' . $priceRange['to'];
            $remaining = $this->removeMatch($remaining, $priceRange['match']);
            $resolved[] = ['type' => 'price_range', 'from' => $priceRange['from'], 'to' => $priceRange['to'], 'matched' => $priceRange['match']];
        }

        $durationRange = $this->extractDurationRange($remaining);
        if ($durationRange !== null) {
            $request['pm-du'] = $durationRange['from'] . '-' . $durationRange['to'];
            $remaining = $this->removeMatch($remaining, $durationRange['match']);
            $resolved[] = ['type' => 'duration_range', 'from' => $durationRange['from'], 'to' => $durationRange['to'], 'matched' => $durationRange['match']];
        }

        $occupancy = $this->extractOccupancy($remaining);
        if ($occupancy !== null) {
            $maxHardOccupancy = isset($options['max_hard_occupancy']) ? max(0, (int) $options['max_hard_occupancy']) : 2;
            $hardOccupancy = (int) $occupancy['value'] <= $maxHardOccupancy;
            $hardChildOccupancy = $hardOccupancy && isset($occupancy['children']) && ! empty($options['enable_child_occupancy_filter']);
            if ($hardOccupancy) {
                $request['pm-ho'] = $occupancy['value'];
            } else {
                $warnings[] = [
                    'type' => 'occupancy_softened',
                    'message' => 'Die Personenanzahl wurde erkannt, aber nicht als harter Filter gesetzt.',
                    'value' => $occupancy['value'],
                ];
            }
            if ($hardChildOccupancy) {
                $request['pm-hoc'] = $occupancy['children'];
            } elseif (isset($occupancy['children'])) {
                $warnings[] = [
                    'type' => 'child_occupancy_softened',
                    'message' => 'Die Kinderanzahl wurde erkannt, aber nicht als harter Filter gesetzt.',
                    'value' => $occupancy['children'],
                ];
            }
            $remaining = $this->removeMatch($remaining, $occupancy['match']);
            $resolvedOccupancy = [
                'type' => 'occupancy',
                'value' => $occupancy['value'],
                'matched' => $occupancy['match'],
                'hard_filter' => $hardOccupancy,
            ];
            if (isset($occupancy['children'])) {
                $resolvedOccupancy['children'] = $occupancy['children'];
                $resolvedOccupancy['children_hard_filter'] = $hardChildOccupancy;
            }
            $resolved[] = $resolvedOccupancy;
        }

        $semanticQuery = $this->cleanResidualQuery($remaining);
        $mode = 'structured';
        if ($semanticQuery !== null) {
            $mode = 'semantic_hybrid';
            $request['pm-t'] = $semanticQuery;
        }

        return [
            'query' => trim($query),
            'mode' => $mode,
            'semantic_query' => $semanticQuery,
            'request' => $request,
            'resolved_filters' => $resolved,
            'warnings' => $warnings,
        ];
    }

    private function resolveNow(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            return new DateTimeImmutable($value, new DateTimeZone('Europe/Berlin'));
        }

        return new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['ß', 'ä', 'ö', 'ü'], ['ss', 'ae', 'oe', 'ue'], $value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /**
     * @param mixed $terms
     * @return array{id: string, name: string, match: string}|null
     */
    private function extractObjectType(string $remaining, mixed $terms): ?array
    {
        if (! is_array($terms)) {
            return null;
        }

        if (preg_match('/\b(?:komfortables?|kleines?|kleinere|kleineres?|modernes?|luxurioeses?|gemuetliches?)\s+schiff\b/u', $remaining) === 1) {
            return null;
        }

        $candidates = [];
        foreach ($terms as $term) {
            if (! is_array($term) || empty($term['id']) || empty($term['name'])) {
                continue;
            }
            $labels = [(string) $term['name']];
            if (! empty($term['terms']) && is_array($term['terms'])) {
                foreach ($term['terms'] as $label) {
                    if (is_string($label) && $label !== '') {
                        $labels[] = $label;
                    }
                }
            }
            if (! empty($term['aliases']) && is_array($term['aliases'])) {
                foreach ($term['aliases'] as $label) {
                    if (is_string($label) && $label !== '') {
                        $labels[] = $label;
                    }
                }
            }
            foreach (array_unique($labels) as $label) {
                $match = $this->normalize($label);
                if ($match === '') {
                    continue;
                }
                $candidates[] = [
                    'id' => (string) $term['id'],
                    'name' => (string) $term['name'],
                    'match' => $match,
                    'length' => mb_strlen($match),
                ];
            }
        }

        usort($candidates, static fn (array $a, array $b): int => $b['length'] <=> $a['length']);

        foreach ($candidates as $candidate) {
            if ($this->containsPhrase($remaining, $candidate['match'])) {
                return ['id' => $candidate['id'], 'name' => $candidate['name'], 'match' => $candidate['match']];
            }
        }

        return null;
    }

    /**
     * @param mixed $terms
     * @return list<array{field: string, id: string, name: string, match: string}>
     */
    private function extractCategoryTerms(string $remaining, mixed $terms): array
    {
        if (! is_array($terms)) {
            return [];
        }

        $candidates = [];
        foreach ($terms as $term) {
            if (! is_array($term) || empty($term['field']) || empty($term['id']) || empty($term['name'])) {
                continue;
            }
            $labels = $this->buildCategoryLabels($term);
            if (! empty($term['aliases']) && is_array($term['aliases'])) {
                foreach ($term['aliases'] as $alias) {
                    if (is_string($alias) && $alias !== '') {
                        $labels[] = $alias;
                    }
                }
            }
            foreach ($labels as $label) {
                $match = $this->normalize($label);
                if ($match === '') {
                    continue;
                }
                $candidates[] = [
                    'field' => (string) $term['field'],
                    'id' => (string) $term['id'],
                    'name' => (string) $term['name'],
                    'match' => $match,
                    'length' => mb_strlen($match),
                ];
            }
        }

        usort($candidates, static fn (array $a, array $b): int => $b['length'] <=> $a['length']);

        $matches = [];
        foreach ($candidates as $candidate) {
            if (! $this->containsPhrase($remaining, $candidate['match'])) {
                continue;
            }
            $key = $candidate['field'] . ':' . $candidate['id'];
            if (isset($matches[$key])) {
                continue;
            }
            $matches[$key] = [
                'field' => $candidate['field'],
                'id' => $candidate['id'],
                'name' => $candidate['name'],
                'match' => $candidate['match'],
            ];
            $remaining = $this->removeMatch($remaining, $candidate['match']);
        }

        return array_values($matches);
    }

    /**
     * @param array<string, mixed> $term
     * @return list<string>
     */
    private function buildCategoryLabels(array $term): array
    {
        $labels = [(string) $term['name']];
        $field = (string) ($term['field'] ?? '');
        $normalizedName = $this->normalize((string) $term['name']);

        if (($field === 'reiseart_default' || $field === 'reisethema_default') && preg_match('/reisen$/u', $normalizedName) === 1) {
            $labels[] = mb_substr($normalizedName, 0, -1);
        }

        if (($field === 'reiseart_default' || $field === 'reisethema_default') && preg_match('/reise$/u', $normalizedName) === 1) {
            $labels[] = $normalizedName . 'n';
        }

        if ($field === 'reiseart_default' && preg_match('/\\bfluss(?:reise|reisen)\\b/u', $normalizedName) === 1) {
            $labels[] = 'fluss';
            $labels[] = 'auf dem fluss';
            $labels[] = 'flusskreuzfahrt';
            $labels[] = 'flusskreuzfahrten';
        }

        if ($field === 'reisethema_default' && preg_match('/\\bfamilien(?:reise|reisen)?\\b/u', $normalizedName) === 1) {
            $labels[] = 'familienurlaub';
            $labels[] = 'familienreise';
            $labels[] = 'familienreisen';
        }

        return array_values(array_unique(array_filter($labels, static fn (string $label): bool => $label !== '')));
    }

    /**
     * @return array{from: string, to: string, match: string}|null
     */
    private function extractDateRange(string $remaining, DateTimeImmutable $now, array $options = []): ?array
    {
        $monthMap = [
            'januar' => 1, 'jan' => 1, 'january' => 1,
            'februar' => 2, 'february' => 2,
            'maerz' => 3, 'maer' => 3, 'märz' => 3, 'march' => 3,
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
        $monthPattern = implode('|', array_map(
            static fn (string $month): string => preg_quote($month, '/'),
            array_keys($monthMap)
        ));

        if (preg_match('/\b(?:im|in)\s+(' . $monthPattern . ')(?:\s+([0-9]{4}))?\b/u', $remaining, $match)) {
            $month = $monthMap[$match[1]] ?? null;
            if ($month !== null) {
                $year = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : (int) $now->format('Y');
                if (! isset($match[2]) || $match[2] === '') {
                    $currentMonth = (int) $now->format('n');
                    if ($month < $currentMonth) {
                        $year++;
                    }
                }
                $from = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $now->getTimezone());
                $to = $from->modify('last day of this month');

                return ['from' => $from->format('Ymd'), 'to' => $to->format('Ymd'), 'match' => $match[0]];
            }
        }

        if (preg_match('/\bab(?:\s+(?:dem|der|den))?\s+(' . $monthPattern . ')(?:\s+([0-9]{4}))?\b/u', $remaining, $match)) {
            $month = $monthMap[$match[1]] ?? null;
            if ($month !== null) {
                $year = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : (int) $now->format('Y');
                if (! isset($match[2]) || $match[2] === '') {
                    $currentMonth = (int) $now->format('n');
                    if ($month < $currentMonth) {
                        $year++;
                    }
                }
                $from = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $now->getTimezone());
                $to = $this->resolveOpenDateRangeEnd($from, $options);

                return ['from' => $from->format('Ymd'), 'to' => $to->format('Ymd'), 'match' => $match[0]];
            }
        }

        if ($this->containsPhrase($remaining, 'weihnachten')) {
            $year = (int) $now->format('Y');
            if ((int) $now->format('md') > 1231) {
                $year++;
            }

            return ['from' => $year . '1220', 'to' => $year . '1231', 'match' => 'weihnachten'];
        }

        if ($this->containsPhrase($remaining, 'silvester')) {
            $year = (int) $now->format('Y');

            return ['from' => $year . '1228', 'to' => ($year + 1) . '0102', 'match' => 'silvester'];
        }

        if (preg_match('/\bsommer(?:urlaub|ferien|reise|reisen)?\b/u', $remaining, $match) === 1) {
            $year = (int) $now->format('Y');
            if ((int) $now->format('n') > 8) {
                $year++;
            }

            return ['from' => $year . '0601', 'to' => $year . '0831', 'match' => $match[0]];
        }

        if (preg_match('/\bwinter(?:urlaub|ferien|reise|reisen)?\b/u', $remaining, $match) === 1) {
            $year = (int) $now->format('Y');
            $month = (int) $now->format('n');
            $startYear = $month <= 2 ? $year - 1 : $year;
            $endYear = $startYear + 1;
            $to = new DateTimeImmutable(sprintf('%04d-02-01', $endYear), $now->getTimezone());

            return ['from' => $startYear . '1201', 'to' => $to->modify('last day of this month')->format('Ymd'), 'match' => $match[0]];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveOpenDateRangeEnd(DateTimeImmutable $from, array $options): DateTimeImmutable
    {
        if (! empty($options['open_date_range_to']) && is_string($options['open_date_range_to'])) {
            $configuredTo = DateTimeImmutable::createFromFormat('Ymd', $options['open_date_range_to'], $from->getTimezone())
                ?: DateTimeImmutable::createFromFormat('Y-m-d', $options['open_date_range_to'], $from->getTimezone());
            if ($configuredTo instanceof DateTimeImmutable && $configuredTo >= $from) {
                return $configuredTo;
            }
        }

        $months = isset($options['open_date_range_months']) ? max(1, (int) $options['open_date_range_months']) : 24;

        return $from->modify('+' . $months . ' months')->modify('-1 day');
    }

    /**
     * @return array{from: string, to: string, match: string}|null
     */
    private function extractPriceRange(string $remaining): ?array
    {
        if (preg_match('/\b([0-9][0-9\.\s]*)\s*(?:bis|-)\s*([0-9][0-9\.\s]*)\s*(?:euro|eur)?\b/u', $remaining, $match)) {
            return ['from' => (string) $this->toInt($match[1]), 'to' => (string) $this->toInt($match[2]), 'match' => $match[0]];
        }
        if (preg_match('/\b(?:bis|maximal|max|unter)\s+([0-9][0-9\.\s]*)\s*(?:euro|eur)?\b/u', $remaining, $match)) {
            return ['from' => '1', 'to' => (string) $this->toInt($match[1]), 'match' => $match[0]];
        }
        if (preg_match('/\b(?:ab|mindestens)\s+([0-9][0-9\.\s]*)\s*(?:euro|eur|chf|franken|fr)\b/u', $remaining, $match)) {
            return ['from' => (string) $this->toInt($match[1]), 'to' => '9999999', 'match' => $match[0]];
        }

        return null;
    }

    /**
     * @return array{from: string, to: string, match: string}|null
     */
    private function extractDurationRange(string $remaining): ?array
    {
        if (preg_match('/\b([0-9]+)\s*(?:bis|-)\s*([0-9]+)\s*(?:tage|tagen|tag|naechte|nacht|wochen|woche)\b/u', $remaining, $match)) {
            return ['from' => $match[1], 'to' => $match[2], 'match' => $match[0]];
        }
        if (preg_match('/\b(?:bis|maximal|max|unter)\s+([0-9]+)\s*(?:tage|tagen|tag|naechte|nacht)\b/u', $remaining, $match)) {
            return ['from' => '1', 'to' => $match[1], 'match' => $match[0]];
        }
        if (preg_match('/\b([0-9]+)\s*(?:tage|tagen|tag|naechte|nacht)\b/u', $remaining, $match)) {
            return ['from' => $match[1], 'to' => $match[1], 'match' => $match[0]];
        }
        if (preg_match('/\b(?:eine|1)\s+woche\b/u', $remaining, $match)) {
            return ['from' => '7', 'to' => '7', 'match' => $match[0]];
        }
        if (preg_match('/\b(?:zwei|2)\s+wochen\b/u', $remaining, $match)) {
            return ['from' => '14', 'to' => '14', 'match' => $match[0]];
        }

        return null;
    }

    /**
     * @return array{value: string, match: string, children?: string}|null
     */
    private function extractOccupancy(string $remaining): ?array
    {
        if ($this->containsPhrase($remaining, 'allein')) {
            return ['value' => '1', 'match' => 'allein'];
        }

        if (preg_match('/\b(?:mit\s+)?(?:meiner\s+|meinem\s+)?(?:frau|mann|partnerin|partner|ehefrau|ehemann)\s+(?:und\s+)?([0-9]+)\s*(?:kinder|kindern|kind)\b/u', $remaining, $match) === 1) {
            return ['value' => '2', 'children' => $match[1], 'match' => trim($match[0])];
        }

        if (preg_match('/\b(?:familie|familienurlaub|familienreise)\s+(?:mit\s+)?([0-9]+)\s*(?:kinder|kindern|kind)\b/u', $remaining, $match) === 1) {
            return ['value' => '2', 'children' => $match[1], 'match' => trim($match[0])];
        }

        if (preg_match('/\b([0-9]+)\s*(?:erwachsene|erwachsener)\s+(?:und\s+)?([0-9]+)\s*(?:kinder|kindern|kind)\b/u', $remaining, $match) === 1) {
            return ['value' => $match[1], 'children' => $match[2], 'match' => trim($match[0])];
        }

        if (preg_match('/\b(?:fuer|für)?\s*([0-9]+)\s*(?:personen|person|erwachsene|erwachsener)\b/u', $remaining, $match)) {
            return ['value' => $match[1], 'match' => trim($match[0])];
        }

        return null;
    }

    private function cleanResidualQuery(string $remaining): ?string
    {
        $words = preg_split('/\s+/u', trim($remaining), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false || $words === []) {
            return null;
        }

        $stopwords = [
            'ab' => true, 'am' => true, 'an' => true, 'auf' => true, 'bis' => true, 'der' => true, 'die' => true,
            'das' => true, 'den' => true, 'dem' => true, 'des' => true, 'eine' => true, 'einer' => true,
            'einen' => true, 'fuer' => true, 'für' => true, 'im' => true, 'in' => true, 'mit' => true,
            'nach' => true, 'reise' => true, 'reisen' => true, 'suche' => true, 'und' => true, 'von' => true,
            'zu' => true, 'fluss' => true, 'urlaub' => true, 'ferien' => true, 'frau' => true, 'mann' => true,
            'partner' => true, 'partnerin' => true, 'kind' => true, 'kinder' => true, 'kindern' => true,
            'ich' => true, 'mir' => true, 'mich' => true, 'mein' => true, 'meine' => true, 'meiner' => true,
            'meinem' => true, 'meinen' => true, 'moechte' => true, 'moechten' => true, 'will' => true,
            'wollen' => true, 'gerne' => true, 'gern' => true, 'bitte' => true, 'mach' => true,
            'machen' => true, 'schlag' => true, 'schlage' => true, 'schlagen' => true, 'schlaegt' => true,
            'vorschlag' => true, 'vorschlaege' => true, 'vorschlagen' => true, 'empfiehl' => true,
            'empfehle' => true, 'empfehlen' => true, 'zeige' => true, 'zeigen' => true, 'finde' => true,
            'finden' => true, 'was' => true, 'etwas' => true, 'vor' => true, 'welche' => true,
            'welcher' => true, 'welches' => true, 'welchen' => true, 'welchem' => true, 'gibt' => true,
            'es' => true, 'da' => true,
        ];

        $semantic = [];
        foreach ($words as $word) {
            if (isset($stopwords[$word]) || preg_match('/^[0-9]+$/', $word)) {
                continue;
            }
            $semantic[] = $word;
        }

        if ($semantic === []) {
            return null;
        }

        return implode(' ', $semantic);
    }

    private function containsPhrase(string $haystack, string $needle): bool
    {
        return preg_match('/(?<![\pL\pN])' . preg_quote($needle, '/') . '(?![\pL\pN])/u', $haystack) === 1;
    }

    private function removeMatch(string $remaining, string $match): string
    {
        $remaining = preg_replace('/(?<![\pL\pN])' . preg_quote($match, '/') . '(?![\pL\pN])/u', ' ', $remaining) ?? $remaining;

        return trim(preg_replace('/\s+/u', ' ', $remaining) ?? $remaining);
    }

    private function toInt(string $value): int
    {
        return (int) preg_replace('/[^0-9]/', '', $value);
    }
}
