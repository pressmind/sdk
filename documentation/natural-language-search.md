# Natural Language Search

[← Back to Documentation Index](documentation.md) | [→ OpenSearch Configuration](search-opensearch.md)

---

## Overview

The natural language search converts a user sentence into normal SDK search parameters (`pm-*`). It does not replace MongoDB or OpenSearch. It prepares structured filters first and keeps only the meaningful residual text as `pm-t`.

Example:

```text
Ich möchte gerne im Sommer mit meiner Familie eine Flusskreuzfahrt machen, schlage mir was vor
```

Resulting request:

```php
[
    'pm-o' => 'list',
    'pm-c' => [
        'reiseart_default' => '{flussreise-category-id}',
    ],
    'pm-dr' => '20260601-20260831',
    'pm-t' => 'familie',
]
```

The `pm-t` value then runs through the normal fulltext/OpenSearch/vector search path if that stack is enabled.

---

## SDK Classes

| Class | Purpose |
|---|---|
| `Pressmind\Search\NaturalLanguageQueryPlanner` | Deterministically converts a natural language query into a search plan. |
| `Pressmind\Search\NaturalLanguageSearchService` | Loads category dictionaries through `TermResolver`, creates the plan, and can run the resulting search. |

The SDK classes are customer-neutral. They do not contain hardcoded object type IDs such as "tour", "ship", "hotel", or "daytrip". These IDs are project data and must be configured by the consuming theme or application.

---

## Recommended Theme Configuration

The WordPress travelshop theme should define which category fields and object types may be used by natural language search.

```php
define('TS_NATURAL_LANGUAGE_CATEGORY_FIELDS', [
    'zielgebiet_land_default',
    'zielgebiet_fluss_default',
    'schiff_default',
    'reiseart_default',
    'reisethema_default',
]);

define('TS_NATURAL_LANGUAGE_OBJECT_TYPES', [
    [
        'id' => '2741',
        'name' => 'Schiff',
        'terms' => ['schiffssuche', 'schiffe', 'schiff'],
    ],
    [
        'id' => (string) TS_TOUR_PRODUCTS,
        'name' => 'Reise',
        'terms' => ['reise', 'reisen'],
    ],
]);
```

### Category Fields

`TS_NATURAL_LANGUAGE_CATEGORY_FIELDS` limits which category fields are loaded from the term dictionary. Keep this list project-specific and only include fields that are useful as user-facing search filters.

Typical fields:

| Field | Use case |
|---|---|
| `zielgebiet_land_default` | Countries such as France, Belgium, Switzerland |
| `zielgebiet_fluss_default` | Rivers such as Rhine, Danube, Seine |
| `reiseart_default` | Trip types such as river cruise |
| `reisethema_default` | Themes such as culture or family trips |
| `schiff_default` | Named ships |

### Object Types

`TS_NATURAL_LANGUAGE_OBJECT_TYPES` maps free text words to `pm-ot`.

Rules:

- `id` is the MediaObject object type ID from the customer/project setup.
- `name` is only used for debugging and `resolved_filters`.
- `terms` contains lowercase user-facing words and synonyms.
- Do not add generic domain words that should stay semantic. For example, "komfortables kleines Schiff" should usually remain `pm-t=komfortables kleines schiff`, not force `pm-ot=Schiff`.
- Do not put object type IDs into SDK code. They belong to theme/application configuration.

### Legacy Constant Names

Older theme integrations may still use:

```php
TS_AI_SEARCH_CATEGORY_FIELDS
TS_AI_SEARCH_OBJECT_TYPE_TERMS
```

New integrations should use:

```php
TS_NATURAL_LANGUAGE_CATEGORY_FIELDS
TS_NATURAL_LANGUAGE_OBJECT_TYPES
```

Theme endpoints may keep the old names as fallback while migrating existing projects.

---

## Passing Options to the Service

Example endpoint integration:

```php
$options = [
    'language' => defined('TS_LANGUAGE_CODE') ? TS_LANGUAGE_CODE : null,
    'origin' => defined('TS_TOURISTIC_ORIGIN') ? TS_TOURISTIC_ORIGIN : 0,
];

if (defined('TS_NATURAL_LANGUAGE_CATEGORY_FIELDS') && is_array(TS_NATURAL_LANGUAGE_CATEGORY_FIELDS)) {
    $options['category_fields'] = TS_NATURAL_LANGUAGE_CATEGORY_FIELDS;
}

if (defined('TS_NATURAL_LANGUAGE_OBJECT_TYPES') && is_array(TS_NATURAL_LANGUAGE_OBJECT_TYPES)) {
    $options['object_type_terms'] = TS_NATURAL_LANGUAGE_OBJECT_TYPES;
}

$service = new \Pressmind\Search\NaturalLanguageSearchService($options);
$plan = $service->plan($query);
```

---

## Parser Behavior

The planner extracts stable filters before creating the residual semantic query.

| User phrase | Search parameter |
|---|---|
| `im Sommer`, `Sommerurlaub`, `Sommerferien` | `pm-dr=YYYY0601-YYYY0831` |
| `im Winter`, `Winterurlaub`, `Winterferien` | `pm-dr=YYYY1201-(YYYY+1)0228/0229` |
| `ab August` | open date window, default 24 months |
| `bis 2000 Euro` | `pm-pr=1-2000` |
| `ab 3000 Euro` | `pm-pr=3000-9999999` |
| `7 Tage` | `pm-du=7-7` |
| `für 2 Personen` | `pm-ho=2` |
| `mit Frau und 2 Kindern` | `pm-ho=2`, child count stays soft by default |

Soft defaults are intentional:

- `reisethema_default` is not a hard filter by default. Theme/category data can be sparse, and hard filtering can hide good results.
- Child occupancy is not a hard filter by default unless `enable_child_occupancy_filter` is explicitly enabled.
- Occupancy above `max_hard_occupancy` defaults to a warning instead of a hard filter.

---

## Rollout Checklist

1. Enable MongoDB search and term dictionary generation.
2. Enable OpenSearch/vector search if semantic residual text should use vector matching.
3. Add `TS_NATURAL_LANGUAGE_CATEGORY_FIELDS` in the theme.
4. Add `TS_NATURAL_LANGUAGE_OBJECT_TYPES` in the theme with customer-specific object type IDs and synonyms.
5. Wire the frontend module or shortcode to an endpoint that calls `NaturalLanguageSearchService`.
6. Test representative queries and inspect the returned `plan`, especially `request`, `semantic_query`, `resolved_filters`, and `warnings`.
