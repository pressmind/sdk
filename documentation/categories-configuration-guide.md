# MongoDB Categories Index Configuration – Complete Guide

[← Back to MongoDB Index Configuration](search-mongodb-index-configuration.md) | [← Back to Config Examples](config-examples.md)

---

## Table of Contents

- [Mission](#mission)
- [Context](#context)
- [Overview: All Five Configuration Types](#overview-all-five-configuration-types)
- [Typ 1: Direct Category Tree](#typ-1-direct-category-tree-null)
- [Typ 2: Categories from Linked Objects](#typ-2-categories-from-linked-objects-from)
- [Typ 3: Plaintext from Linked Objects (Virtual Categories)](#typ-3-plaintext-from-linked-objects-virtual-categories)
- [Typ 4: Aggregation Methods](#typ-4-aggregation-methods-custom-logic)
- [Typ 5: Virtual Trees via TreeBuilder](#typ-5-virtual-trees-via-treebuilder-post-import-hook)
- [Custom Filter & Aggregation Classes](#custom-filter--aggregation-classes)
- [MongoDB Document Structure](#mongodb-document-structure)
- [FAQ: When to Use Which Type?](#faq-when-to-use-which-type)
- [Decision Tree](#decision-tree)
- [Troubleshooting](#troubleshooting)

---

## Mission

For performant search, complex structures are "flattened" in the search index. Category trees, linked objects, and multi-level relations (e.g. Reise → Schiff → Kabine) are resolved at index time and stored as flat, searchable category entries in each MongoDB document. The search can then work without JOINs, subqueries, or extra lookups — a single document access is enough.

Different configuration options exist depending on the complexity of the requirement:

```
  Data structure complexity              Configuration option
  ─────────────────────────             ────────────────────────────────

  Simple:
  Category is directly on the object  ──>  Type 1: Direct Tree (null)

  Medium:
  Category is on the linked object    ──>  Type 2: from ObjectLink
  (one level deep)                          Type 3: plaintext_from_objectlink

  Complex:
  Category is several levels deep      ──>  Type 4: Aggregation Method
  (e.g. Reise → Schiff → Kabine)           Type 5: Virtual Tree (TreeBuilder)
```

This guide describes all five options in detail with examples, ASCII diagrams, and a complete Filter class.

---

## Context

The `categories` configuration lives under `data.search_mongodb.search.categories` in `pm-config.php` (or `config.json`). It controls which category trees are indexed into the MongoDB search documents and thus are available as facet filters (`pm-c[field]`).

Implementation is in the SDK: [src/Pressmind/Search/MongoDB/Indexer.php](src/Pressmind/Search/MongoDB/Indexer.php), method `_mapCategories()` (around line 877).

---

## Overview: All Five Configuration Types

| Type | Use case | Config key pattern |
|------|----------|---------------------|
| 1 | Category tree directly on the MediaObject | `'field_default' => null` |
| 2 | Category tree on a linked object (one level) | `'key' => ['from' => 'objectlink_field']` |
| 3 | Plaintext from linked object as virtual category | `'type' => 'plaintext_from_objectlink'`, `virtual_id_tree` |
| 4 | Category several levels deep (e.g. Reise → Schiff → Kabine) | `'aggregation' => ['method' => '...']` |
| 5 | Persistent virtual tree built at import time | TreeBuilder hook + `'field_virtual' => null` |

---

## Typ 1: Direct Category Tree (null)

- **Frequency:** ~95% of projects; default pattern.
- **Syntax:** `'field_name' => null`
- **Implementation:** `_mapCategories()` (Indexer, ~line 913–934)

When the value is `null`, the category tree is read directly from the MediaObject’s content.

```
  ┌─────────────────────────┐
  │  MediaObject (ID: 100)  │
  │                         │
  │  destination_default:   │
  │    ├── Europe           │
  │    │   └── Spain        │
  │    │       └── Mallorca │  ◄── Category tree on the object
  │    └── Asia             │
  │        └── Thailand     │
  └────────────┬────────────┘
               │  null (= read directly)
               ▼
  ┌────────────────────────────────────────┐
  │  MongoDB Document                      │
  │  categories: [                         │
  │    { field_name: "destination_default",│
  │      name: "Mallorca", level: 2,       │
  │      path_str: ["Europe","Spain",      │
  │                  "Mallorca"] },        │
  │    { field_name: "destination_default",│
  │      name: "Spain", level: 1,          │
  │      path_str: ["Europe","Spain"] }    │
  │  ]                                     │
  └────────────────────────────────────────┘
```

**Example config:**

```php
'categories' => [
    100 => [
        'destination_default' => null,
        'travel_type_default' => null,
        'season_default'      => null,
    ],
],
```

---

## Typ 2: Categories from Linked Objects (from)

- **Frequency:** ~15% (e.g. projects with linked ships, hotels).
- **Syntax:** `'key' => ['from' => 'objectlink_field']`
- Optional: `field` for renaming, `id_tree` for an explicit tree ID override.
- **Implementation:** `_mapCategoriesFromObjectLinks()` (Indexer, ~line 1046).

Scenario: Reise links to Schiff; Schiff has category `ausstattung_default`.

```
  ┌───────────────────────┐       Object Link         ┌───────────────────────────┐
  │ MediaObject (Reise)   │  ─── schiff_default ───>  │ MediaObject (Schiff)      │
  │ id_object_type: 100   │                           │ id_object_type: 200       │
  │                       │                           │                           │
  │ (has NO               │                           │ ausstattung_default:      │
  │  ausstattung_default) │                           │   ├── Pool                │
  └───────────┬───────────┘                           │   ├── Wellness            │
              │                                       │   └── Fitness             │
              │  'from' => 'schiff_default'           └──────────┬────────────────┘
              │                                                  │
              ▼                                                  │
  ┌──────────────────────────────────────────┐                   │
  │  Indexer follows ObjectLink,             │ ◄─────────────────┘
  │  reads ausstattung_default from Schiff   │
  │                                          │
  │  MongoDB Document (Reise):               │
  │  categories: [                           │
  │    { field_name: "ausstattung_default",  │
  │      name: "Pool", level: 0 },           │
  │    { field_name: "ausstattung_default",  │
  │      name: "Wellness", level: 0 }        │
  │  ]                                       │
  └──────────────────────────────────────────┘

  Variant: Reise → Schiff → category with rename and id_tree override

  ┌──────────────┐    schiff_    ┌──────────────┐
  │ Reise        │ ── default ─> │ Schiff       │
  │              │               │              │
  │              │               │ kategorie_   │
  │              │               │ default:     │
  │              │               │  └── Luxus   │
  └──────┬───────┘               └──────────────┘
         │
         │  Config:
         │  'schiff_kategorie_ol' => [
         │      'from'    => 'schiff_default',
         │      'field'   => 'kategorie_default',
         │      'id_tree' => 99997,
         │  ]
         ▼
  ┌─────────────────────────────────────────┐
  │  MongoDB Document (Reise):              │
  │  categories: [                          │
  │    { field_name: "schiff_kategorie_ol", │  ◄── Renamed
  │      name: "Luxus",                     │
  │      id_tree: 99997 }                   │  ◄── Overridden tree ID
  │  ]                                      │
  └─────────────────────────────────────────┘
```

**Example config:**

```php
// Simple: category from linked ship
'ausstattung_default' => [
    'from' => 'schiff_default',
],

// With field rename and explicit id_tree
'schiff_kategorie_ol' => [
    'from'    => 'schiff_default',
    'field'   => 'kategorie_default',
    'id_tree' => 99997,
],
```

---

## Typ 3: Plaintext from Linked Objects (Virtual Categories)

- **Frequency:** ~5%
- **Syntax:** Requires `type`, `from`, `field`, `virtual_id_tree`. Optional: `filter`.
- **Implementation:** `_mapPlaintextFromObjectLinks()` (Indexer, ~line 994).
- `id_item` is generated as MD5: `md5(id_link + varName + field + name + virtual_id_tree)`.

Scenario: Reise links to Schiff; the ship name is a plain text field, not a category tree. You still want "MS Europa" as a filter option.

```
  ┌───────────────────────┐       Object Link         ┌──────────────────────────┐
  │ MediaObject (Reise)   │  ─── schiff_default ──>  │ MediaObject (Schiff)     │
  │ id_object_type: 100   │                            │                          │
  │                        │                            │ name: "MS Europa"        │ ◄── Plaintext
  └───────────┬───────────┘                            │ (no category tree,        │     No tree,
              │                                        │  just object name)        │     just a name
              │                                        └──────────┬───────────────┘
              │  type: 'plaintext_from_objectlink'                │
              │  from: 'schiff_default'                           │
              │  field: 'schiff_name'                             │
              │  virtual_id_tree: 99999                           │
              ▼                                                   │
  ┌──────────────────────────────────────────────┐                │
  │  Indexer creates VIRTUAL category:           │ ◄──────────────┘
  │                                              │
  │  id_item: md5("456-schiff_default            │  ◄── Generated hash
  │           -name-MS Europa-99999")            │
  │  id_tree: 99999                              │  ◄── Virtual tree (arbitrary)
  │  name: "MS Europa"                           │
  │  level: 0 (always flat, no tree)            │
  │  field_name: "schiff_name"                   │
  │                                              │
  │  Optional: filter transforms name            │
  │  e.g. "MS Europa" -> "MS Europa (Luxus)"     │
  └──────────────────────────────────────────────┘

  Result in frontend filter:

  ┌─────────────────────────┐
  │  Filter: Schiff         │
  │  ☐ MS Europa            │
  │  ☐ MS Fantasia          │
  │  ☐ MS Amadea            │
  └─────────────────────────┘
  Even though there is no "Schiffe" category tree,
  all ship names appear as filter options.
```

**Example config:**

```php
'schiff_name_ol' => [
    'from'            => 'schiff_default',
    'field'           => 'schiff_name',
    'type'            => 'plaintext_from_objectlink',
    'filter'          => '\\Custom\\Filter::schiffNameFilter',
    'virtual_id_tree' => 99999,
],
```

---

## Typ 4: Aggregation Methods (Custom Logic)

- **Frequency:** ~2%
- **Syntax:** `aggregation.method` + `aggregation.params`
- The method must return an array with the same structure as standard categories.
- **Implementation:** `_callMethod()` (Indexer, ~line 886–891).
- **Use case:** Category is more than one ObjectLink level deep (e.g. Reise → Schiff → Kabine → Kabinenkategorie).

Scenario: Reise → Schiff → Kabine → kabinen_kategorie_default. The category is three levels deep; none of the built-in types (null, from, plaintext) can express this. Use aggregation.

```
  ┌──────────────┐   schiff_    ┌─────────────────────┐   kabinen_    ┌──────────────┐
  │ Reise        │── default ──>│ Schiff              │── default ──> │ Kabine       │
  │ (OT: 100)    │              │ (OT: 200)           │               │ (OT: 300)    │
  │              │              │                     │               │              │
  │              │              │ kabinen_default     │               │ kategorie_   │
  │              │              │ :                   │ default:      │
  └──────┬───────┘              └─────────────────────┘               │  ├── Aussen  │
         │                                                            │  ├── Innen   │
         │                                                            │  └── Suite   │
         │                                                            └──────┬───────┘
         │  aggregation.method:                                              │
         │  '\\Custom\\Filter::getKabinenKategorien'                         │
         ▼                                                                   │
  ┌──────────────────────────────────────────────────────────────────────────┤
  │  Custom PHP method traverses the full chain:                             │
  │                                                                          │
  │  foreach ($reise->schiff_default as $schiffLink) {                       │
  │      $schiff = new MediaObject($schiffLink->id_link);                    │
  │      foreach ($schiff->kabinen_default as $kabinenLink) {                │
  │          $kabine = new MediaObject($kabinenLink->id_link);               │
  │          foreach ($kabine->kategorie_default as $treeitem) {             │
  │              $categories[] = [                                           │
  │                  'id_item'    => $treeitem->item->id,                    │
  │                  'name'       => $treeitem->item->name,                  │  ◄── "Suite"
  │                  'id_tree'    => $treeitem->item->id_tree,               │
  │                  'field_name' => 'kabinen_kategorie_ol',                 │
  │                  'level'      => 0,                                      │
  │                  'path_str'   => [$treeitem->item->name],                │
  │                  'path_ids'   => [$treeitem->item->id],                  │
  │              ];                                                          │
  │          }                                                               │
  │      }                                                                   │
  │  }                                                                       │
  │  return $categories;                                                     │
  └──────────────────────────────────────────────────────────────────────────┘

  Result in MongoDB document (Reise):

  categories: [
    { field_name: "kabinen_kategorie_ol", name: "Aussen", ... },
    { field_name: "kabinen_kategorie_ol", name: "Innen", ... },
    { field_name: "kabinen_kategorie_ol", name: "Suite", ... },
  ]
```

**Example config:**

```php
'kabinen_kategorie_ol' => [
    'aggregation' => [
        'method' => '\\Custom\\Filter::getKabinenKategorien',
        'params' => []
    ]
],
```

---

## Typ 5: Virtual Trees via TreeBuilder (Post-Import Hook)

- **Frequency:** ~5%
- **Pattern:** `media_type_custom_post_import_hooks` creates CategoryTree entries in MySQL during import; they are then indexed as normal Direct Trees (Type 1, `null`). Fields with suffix `_virtual` are typical.
- **Use case:** You want a real, persistent category tree built from ObjectLinks (e.g. "Schiffe" as a tree from linked ship objects).

Scenario: Each Reise links to a Schiff. You want a category tree `schiffe_virtual` whose items are all ships. Unlike Type 3, real category tree items are written to MySQL and are persistent.

```
  PHASE 1: IMPORT (Post-Import Hook)
  ====================================

  ┌──────────────┐   schiff_    ┌──────────────┐
  │ Reise A      │── default ──>│ Schiff       │
  │ (ID: 1001)   │              │ "MS Europa"  │
  └──────┬───────┘              │ (ID: 5001)   │
         │                      └──────────────┘
  ┌──────┴───────┐   schiff_    ┌──────────────┐
  │ Reise B      │── default ──>│ Schiff       │
  │ (ID: 1002)   │              │ "MS Amadu"   │
  └──────┬───────┘              │ (ID: 5002)   │
         │                      └──────────────┘
         │
         │  TreeBuilder (Post-Import Hook) reads
         │  all schiff_default ObjectLinks and
         │  writes REAL category tree entries:
         ▼
  ┌─────────────────────────────────────────┐
  │  MySQL: pmt2core_category_tree_item     │
  │  ┌───────────────────────────────────┐  │
  │  │ id: md5("schiff-MS Europa-5001")  │  │
  │  │ id_tree: 9999                     │  │
  │  │ name: "MS Europa"                 │  │
  │  ├───────────────────────────────────┤  │
  │  │ id: md5("schiff-MS Amadu-5002")   │  │
  │  │ id_tree: 9999                     │  │
  │  │ name: "MS Amadea"                 │  │
  │  └───────────────────────────────────┘  │
  │                                         │
  │  MySQL: pmt2core_media_object_          │
  │         categorytree                    │
  │  ┌───────────────────────────────────┐  │
  │  │ id_media_object: 1001             │  │
  │  │ var_name: "schiffe_virtual"       │  │
  │  │ id_item: md5("..MS Europa..")     │  │
  │  ├───────────────────────────────────┤  │
  │  │ id_media_object: 1002             │  │
  │  │ var_name: "schiffe_virtual"       │  │
  │  │ id_item: md5("..MS Amadu..")      │  │
  │  └───────────────────────────────────┘  │
  └────────────────┬────────────────────────┘

  PHASE 2: INDEX (normal Type 1)
  ================================
                   │
                   │  A real category tree with var_name
                   │  "schiffe_virtual" now exists.
                   ▼
  ┌──────────────────────────────────────────┐
  │  pm-config.php:                          │
  │  'schiffe_virtual' => null               │  ◄── Type 1
  │                                          │
  │  The indexer sees a normal category      │
  │  tree and indexes it like any other      │
  │  Direct Tree.                            │
  └──────────────────────────────────────────┘

  Advantages over Type 3 (Plaintext):
  - Real tree items with id_parent (hierarchy possible)
  - Reusable across object types
  - Visible in the pressmind UI
```

**Example config:**

```php
// 1. Register Post-Import Hook
'media_type_custom_post_import_hooks' => [
    100 => ['Custom\\TreeBuilder'],
],

// 2. Index virtual tree as normal Direct Tree
'categories' => [
    100 => [
        'zielgebiet_default' => null,
        'schiffe_virtual'    => null,   // built by TreeBuilder
    ],
],
```

---

## Custom Filter & Aggregation Classes

The indexer calls filter and aggregation methods via `_callMethod()`. It instantiates the configured class, sets `mediaObject` and `agency`, then invokes the method with the first parameter (e.g. plaintext value) and any named `params` matched by reflection.

```
  pm-config.php                         Indexer._callMethod()
  ┌──────────────────────┐              ┌────────────────────────────────────────┐
  │ 'filter' =>          │              │ 1. $p = explode('::', $method)         │
  │   '\\Custom\\Filter  │  ────────>   │    -> ['Custom\\Filter', 'schiffName'] │
  │    ::schiffName'     │              │                                        │
  │                      │              │ 2. $Filter = new Custom\Filter()       │
  │ 'params' => [        │              │    $Filter->mediaObject = <current>    │
  │   'mediaObject' =>   │              │    $Filter->agency = <current>         │
  │     <linkedObject>   │              │                                        │
  │ ]                    │              │ 3. Reflection: match config 'params'   │
  └──────────────────────┘              │    to method parameters                │
                                        │                                        │
                                        │ 4. call_user_func_array(               │
                                        │      [$Filter, 'schiffName'],          │
                                        │      [$name, $linkedMediaObject]       │
                                        │    )                                   │
                                        └────────────────────────────────────────┘

  Important:
  - $this->mediaObject is always the object currently being indexed (the Reise)
  - The first parameter ($name) is the plaintext value BEFORE the filter
  - Additional parameters come from 'params' via reflection
```

### Complete example Filter class

```php
<?php
namespace Custom;

use Pressmind\ORM\Object\MediaObject;

/**
 * Filter class for MongoDB category indexing.
 *
 * The Indexer automatically sets these properties before calling any method:
 * - $this->mediaObject: The MediaObject currently being indexed (e.g. the "Reise")
 * - $this->agency: The current agency (if agency-based indexing is active)
 */
class Filter
{
    /**
     * @var MediaObject Automatically set by the Indexer
     */
    public $mediaObject;

    /**
     * @var string|null Automatically set by the Indexer
     */
    public $agency;


    // =========================================================================
    // PLAINTEXT FILTER (Type 3: plaintext_from_objectlink)
    // =========================================================================

    /**
     * Transforms the ship name for the search filter.
     * Appends the ship category in parentheses, e.g. "MS Europa" -> "MS Europa (Luxus)"
     *
     * Config:
     *   'schiff_name_ol' => [
     *       'from'            => 'schiff_default',
     *       'field'           => 'schiff_name',
     *       'type'            => 'plaintext_from_objectlink',
     *       'filter'          => '\\Custom\\Filter::schiffNameFilter',
     *       'virtual_id_tree' => 99999,
     *   ]
     *
     * @param string $name        The raw plaintext value (ship name)
     * @param MediaObject $mediaObject  The LINKED MediaObject (the ship, NOT the trip)
     * @return string  The transformed name for the filter
     */
    public function schiffNameFilter($name, $mediaObject)
    {
        $schiff = $mediaObject->getDataForLanguage();
        $suffix = '';
        if (!empty($schiff->kategorie_default[0]->item->name)) {
            $suffix = $schiff->kategorie_default[0]->item->name;
        }
        if (!empty($suffix)) {
            return $name . ' (' . $suffix . ')';
        }
        return $name;
    }


    // =========================================================================
    // AGGREGATION METHOD (Type 4: aggregation)
    // =========================================================================

    /**
     * Collects cabin categories from linked ships.
     * Traverses: Reise -> schiff_default -> kabinen_default -> kategorie_default
     *
     * Config:
     *   'kabinen_kategorie_ol' => [
     *       'aggregation' => [
     *           'method' => '\\Custom\\Filter::getKabinenKategorien',
     *           'params' => []
     *       ]
     *   ]
     *
     * @return array  Array of category items (same structure as standard categories)
     */
    public function getKabinenKategorien()
    {
        $data = $this->mediaObject->getDataForLanguage();
        $categories = [];
        $seen = [];

        if (empty($data->schiff_default) || !is_array($data->schiff_default)) {
            return $categories;
        }

        foreach ($data->schiff_default as $schiffLink) {
            $schiff = new MediaObject($schiffLink->id_media_object_link);
            $schiffData = $schiff->getDataForLanguage();

            if (empty($schiffData->kabinen_default) || !is_array($schiffData->kabinen_default)) {
                continue;
            }

            foreach ($schiffData->kabinen_default as $kabinenLink) {
                $kabine = new MediaObject($kabinenLink->id_media_object_link);
                $kabinenData = $kabine->getDataForLanguage();

                if (empty($kabinenData->kategorie_default) || !is_array($kabinenData->kategorie_default)) {
                    continue;
                }

                foreach ($kabinenData->kategorie_default as $treeitem) {
                    if (empty($treeitem->item->id) || isset($seen[$treeitem->item->id])) {
                        continue;
                    }
                    $seen[$treeitem->item->id] = true;

                    $categories[] = [
                        'id_item'    => $treeitem->item->id,
                        'name'       => $treeitem->item->name,
                        'id_tree'    => $treeitem->item->id_tree,
                        'id_parent'  => $treeitem->item->id_parent,
                        'field_name' => 'kabinen_kategorie_ol',
                        'level'      => 0,
                        'path_str'   => [$treeitem->item->name],
                        'path_ids'   => [$treeitem->item->id],
                    ];
                }
            }
        }

        return $categories;
    }


    // =========================================================================
    // DESCRIPTION FILTERS (for 'descriptions' config, not 'categories')
    // Same Filter class is used for both.
    // =========================================================================

    /**
     * Strips HTML tags from a text field.
     *
     * @param string $value  Raw HTML content
     * @return string  Plain text
     */
    public static function strip($value)
    {
        return strip_tags($value);
    }

    /**
     * Extracts the first image URL with a specific derivative.
     *
     * Config:
     *   'image' => [
     *       'field'  => 'bilder_default',
     *       'filter' => '\\Custom\\Filter::firstPicture',
     *       'params' => ['derivative' => 'teaser'],
     *   ]
     *
     * @param mixed $value       Raw image field data (array of image objects)
     * @param string $derivative  Image derivative name from params
     * @return object|null  Image data with URI
     */
    public static function firstPicture($value, $derivative = 'teaser')
    {
        if (empty($value) || !is_array($value)) {
            return null;
        }
        $first = $value[0];
        if (!empty($first->derivatives[$derivative])) {
            return (object)[
                'uri'    => $first->derivatives[$derivative]->uri,
                'width'  => $first->derivatives[$derivative]->width,
                'height' => $first->derivatives[$derivative]->height,
                'alt'    => $first->alt ?? '',
            ];
        }
        return null;
    }

    /**
     * Extracts the deepest level of a category tree as a readable string.
     * E.g. Tree [Europa > Spanien > Mallorca] -> "Mallorca"
     *
     * @param mixed $value  Category tree field data
     * @return string|null  Name of the deepest tree item
     */
    public static function lastTreeItemAsString($value)
    {
        if (empty($value) || !is_array($value)) {
            return null;
        }
        $maxLevel = -1;
        $result = null;
        foreach ($value as $treeitem) {
            if (!empty($treeitem->item->name)) {
                $level = 0;
                $parent = $treeitem->item->id_parent;
                foreach ($value as $check) {
                    if (!empty($check->item->id) && $check->item->id == $parent) {
                        $level++;
                        break;
                    }
                }
                if ($level >= $maxLevel) {
                    $maxLevel = $level;
                    $result = $treeitem->item->name;
                }
            }
        }
        return $result;
    }
}
```

### Corresponding pm-config.php (where each method is referenced)

```php
'categories' => [
    100 => [
        // Type 1: Direct (no filter)
        'zielgebiet_default' => null,
        'reiseart_default'   => null,

        // Type 3: Plaintext with filter
        'schiff_name_ol' => [
            'from'            => 'schiff_default',
            'field'           => 'schiff_name',
            'type'            => 'plaintext_from_objectlink',
            'filter'          => '\\Custom\\Filter::schiffNameFilter',
            'virtual_id_tree' => 99999,
        ],

        // Type 4: Aggregation
        'kabinen_kategorie_ol' => [
            'aggregation' => [
                'method' => '\\Custom\\Filter::getKabinenKategorien',
                'params' => []
            ]
        ],
    ],
],
'descriptions' => [
    100 => [
        'headline' => [
            'field'  => 'headline_default',
            'filter' => '\\Custom\\Filter::strip',
        ],
        'image' => [
            'field'  => 'bilder_default',
            'filter' => '\\Custom\\Filter::firstPicture',
            'params' => ['derivative' => 'teaser'],
        ],
        'destination' => [
            'field'  => 'zielgebiet_default',
            'filter' => '\\Custom\\Filter::lastTreeItemAsString',
        ],
    ],
],
```

---

## MongoDB Document Structure

Each indexed category item in the document’s `categories` array has this shape:

```json
{
  "id_item": "5d41402abc4b2a76b9719d911017c592",
  "name": "Mallorca",
  "id_tree": "abc123",
  "id_parent": "def456",
  "code": "MAL",
  "sort": 5,
  "field_name": "zielgebiet_default",
  "level": 1,
  "path_str": ["Spain", "Balearic Islands", "Mallorca"],
  "path_ids": ["aaa111", "bbb222", "5d41402abc4b2a76b9719d911017c592"]
}
```

`path_str` and `path_ids` give the full path from root to leaf for breadcrumbs and hierarchical filtering without extra queries.

---

## FAQ: When to Use Which Type?

**Q: My product has a field "zielgebiet_default" with a category tree. How do I index it?**  
A: Type 1 (Direct Tree). Simply: `'zielgebiet_default' => null`

**Q: The hotel has stars (kategorie_default), but the Reise does not. The Reise links to the hotel.**  
A: Type 2 (from ObjectLink):  
`'sterne_ol' => ['from' => 'hotel_default', 'field' => 'kategorie_default']`

**Q: Each Reise links to a Schiff. I want the ship name as a filter, but there is no "Schiffe" category tree in the PIM.**  
A: Type 3 (Plaintext Virtual):  
`'schiff_name_ol' => ['type' => 'plaintext_from_objectlink', 'from' => 'schiff_default', 'field' => 'schiff_name', 'virtual_id_tree' => 99999]`

**Q: I need cabin categories as a filter. They are three levels deep: Reise → Schiff → Kabine → kategorie_default.**  
A: Type 4 (Aggregation). Only custom code can traverse multiple levels:  
`'kabinen_kategorie_ol' => ['aggregation' => ['method' => '\\Custom\\Filter::getKabinenKategorien']]`

**Q: I want ship names as a real, reusable category tree in MySQL, not just a flat virtual category in the index.**  
A: Type 5 (TreeBuilder). Implement a Post-Import Hook that creates real CategoryTree items, then index with `null` (Type 1).

**Q: Type 3 or Type 5 — what’s the difference?**  
A:

|                         | Type 3 (Plaintext) | Type 5 (TreeBuilder) |
|-------------------------|--------------------|------------------------|
| Persisted in MySQL      | No                 | Yes                    |
| Hierarchy possible      | No (flat)          | Yes (id_parent)        |
| Visible in PIM          | No                 | Yes                    |
| Reusable                | No                 | Yes                    |
| Effort                  | Config only        | PHP code required      |
| Performance             | Good               | Good                   |

**Q: Type 2 or Type 3 — the linked object has both a tree and plaintext?**  
A: If the field on the linked object is a category tree → Type 2. If it’s a text field (e.g. object name) → Type 3. Type 2 reads real trees; Type 3 builds virtual ones from text.

**Q: Can I use filter functions with all types?**  
A: Type 1 (null): No. Type 2 (from): No. Type 3 (plaintext): Yes, `filter` transforms the name. Type 4 (aggregation): The whole method is the “filter”. Type 5 (TreeBuilder): Yes, transform in the PHP hook.

**Q: I want the filter to append the ship category to the name, e.g. "MS Europa" → "MS Europa (Luxus)".**  
A: Type 3 with filter: `'filter' => '\\Custom\\Filter::schiffNameFilter'`. The method receives the name and the MediaObject and can transform it.

---

## Decision Tree

```
  Where does the category live?
  │
  ├── Directly on the MediaObject? ──────────────────> Type 1: Direct Tree (null)
  │
  ├── On the linked object (one level deep)?
  │   │
  │   ├── Linked field is a category tree? ──────────> Type 2: from ObjectLink
  │   │
  │   └── Linked data is plaintext? ─────────────────> Type 3: plaintext_from_objectlink
  │
  ├── More than one level deep?
  │   (e.g. Reise → Schiff → Kabine)
  │   │
  │   └── Custom PHP logic needed ───────────────────> Type 4: Aggregation Method
  │
  └── Should a persistent tree in MySQL be built
      (reusable, hierarchical)?
      │
      └── TreeBuilder as Post-Import Hook ───────────> Type 5: Virtual Tree
```

---

## Troubleshooting

- **Categories missing in search results:** Ensure the field is listed under `data.search_mongodb.search.categories` for the correct `id_object_type`. Rebuild the index after config changes.
- **"virtual_id_tree is required for plaintext_from_objectlink":** Always set `virtual_id_tree` (e.g. `99999`) when using Type 3.
- **"field is required for plaintext_from_objectlink":** Set `field` to the target `field_name` in the document (e.g. `schiff_name`).
- **Aggregation method returns nothing:** Check that `$this->mediaObject` and the object link field names match your data model. Ensure the method returns an array of items with `id_item`, `name`, `id_tree`, `field_name`, `level`, `path_str`, `path_ids` (and optionally `id_parent`, `code`, `sort`).
- **Filter not applied (Type 3):** Verify the callable string (e.g. `'\\Custom\\Filter::schiffNameFilter'`) and that the method accepts `($name, $mediaObject)`. The indexer passes the linked MediaObject via `params['mediaObject']` if the parameter name matches.

---

[← Back to MongoDB Index Configuration](search-mongodb-index-configuration.md) | [← Back to Config Examples](config-examples.md)
