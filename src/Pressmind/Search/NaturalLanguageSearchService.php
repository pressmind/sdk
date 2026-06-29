<?php

declare(strict_types=1);

namespace Pressmind\Search;

use Pressmind\Search\Query\Filter;

final class NaturalLanguageSearchService
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
        if (! array_key_exists('category_terms', $options)) {
            $options['category_terms'] = $this->loadCategoryTerms($options);
        }

        return (new NaturalLanguageQueryPlanner($options))->plan($query);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{plan: array<string, mixed>, result: array<string, mixed>}
     */
    public function search(string $query, array $options = []): array
    {
        $options = array_replace($this->options, $options);
        $plan = $this->plan($query, $options);

        $filter = new Filter();
        $filter->request = $plan['request'];
        $filter->page_size = isset($options['page_size']) ? max(1, min(100, (int) $options['page_size'])) : 12;
        $filter->getFilters = ! empty($options['get_filters']);
        $filter->returnFiltersOnly = ! empty($options['return_filters_only']);
        if (isset($options['ttl_filter'])) {
            $filter->ttl_filter = $options['ttl_filter'];
        }
        if (isset($options['ttl_search'])) {
            $filter->ttl_search = $options['ttl_search'];
        }
        if (isset($options['output'])) {
            $filter->output = $options['output'];
        }

        return [
            'plan' => $plan,
            'result' => Query::getResult($filter),
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return list<array{field: string, id: string, name: string}>
     */
    private function loadCategoryTerms(array $options): array
    {
        $language = isset($options['language']) && is_string($options['language']) ? $options['language'] : null;
        $origin = isset($options['origin']) ? (int) $options['origin'] : 0;
        $fields = [];
        if (! empty($options['category_fields']) && is_array($options['category_fields'])) {
            foreach ($options['category_fields'] as $field) {
                if (is_string($field) && $field !== '') {
                    $fields[$field] = true;
                }
            }
        }

        $dictionary = TermResolver::all($language, $origin);
        $terms = [];
        foreach ($dictionary as $entry) {
            if ($fields !== [] && empty($fields[$entry['field']])) {
                continue;
            }
            $terms[] = [
                'field' => $entry['field'],
                'id' => $entry['id'],
                'name' => $entry['name'],
            ];
        }

        return $terms;
    }
}
