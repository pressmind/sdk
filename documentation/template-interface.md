# Template Interface & PHP Integration

[← Back to Image Processor](image-processor.md) | [→ Real-World Examples](real-world-examples.md) | [→ Back to Architecture](architecture.md)

---

## Table of Contents

- [Overview](#overview)
- [How Rendering Works](#how-rendering-works)
- [The Scaffolder](#the-scaffolder)
  - [What the Scaffolder Generates](#what-the-scaffolder-generates)
  - [ORM Class Generation](#orm-class-generation)
  - [Database Table Generation](#database-table-generation)
  - [View Template Generation](#view-template-generation)
  - [Object Information File](#object-information-file)
- [MediaObject::render()](#mediaobjectrender)
  - [File Naming Convention](#file-naming-convention)
  - [Data Available in Templates](#data-available-in-templates)
  - [Render Caching](#render-caching)
- [Writing View Templates](#writing-view-templates)
  - [Basic Template Structure](#basic-template-structure)
  - [Accessing Content Fields](#accessing-content-fields)
  - [Working with Images](#working-with-images)
  - [Working with Prices](#working-with-prices)
  - [Working with Categories](#working-with-categories)
  - [Working with Relations (Object Links)](#working-with-relations-object-links)
  - [Working with Locations](#working-with-locations)
  - [Working with Booking Packages](#working-with-booking-packages)
- [Custom Scaffolder Templates](#custom-scaffolder-templates)
  - [Template Placeholders](#template-placeholders)
  - [Overwrite Behavior](#overwrite-behavior)
- [The MVC View System](#the-mvc-view-system)
- [Snippet System](#snippet-system)
- [API Output Templates](#api-output-templates)
- [Configuration Reference](#configuration-reference)

---

## Overview

The SDK uses a **template-based rendering system** that bridges the gap between ORM objects and HTML output. The workflow is:

```
┌───────────────────┐     ┌───────────────────┐     ┌───────────────────┐
│  pressmind PIM    │     │  Scaffolder        │     │  Developer        │
│                   │     │                    │     │                   │
│  Object Type      │────▶│  Generates:        │────▶│  Customizes:      │
│  Definitions      │     │  • ORM Class       │     │  • View Templates │
│  (fields,sections)│     │  • DB Table        │     │  • CSS / Layout   │
│                   │     │  • Example View    │     │  • Business Logic │
│                   │     │  • Object Info     │     │                   │
└───────────────────┘     └───────────────────┘     └───────────────────┘

At runtime:

┌───────────────────┐     ┌───────────────────┐     ┌───────────────────┐
│  Application      │     │  MediaObject       │     │  View Template    │
│                   │     │                    │     │  (.php file)      │
│  $mediaObject     │────▶│  ->render('Teaser')│────▶│  Receives $data:  │
│                   │     │                    │     │  media_object     │
│                   │     │  File resolution:  │     │  custom_data      │
│                   │     │  {Type}_{Tpl}.php  │     │  language          │
│                   │     │                    │     │                   │
│                   │◀────│  Returns HTML      │◀────│  Output buffered  │
│                   │     │  (cached if enabled)│     │                   │
└───────────────────┘     └───────────────────┘     └───────────────────┘
```

**Key Principle:** Every media object type in the pressmind PIM gets its own PHP ORM class with strongly typed properties. The developer writes PHP view templates that access these properties directly.

---

## How Rendering Works

1. The pressmind PIM defines **Object Types** (e.g. "Reise", "Hotel", "Ausflug") with fields and sections
2. The **Scaffolder** generates an ORM class for each object type (`Custom\MediaType\Reise`)
3. The developer creates **view templates** for each media type (e.g. `Reise_Teaser.php`, `Reise_Detail.php`)
4. At runtime, `$mediaObject->render('Teaser')` resolves and renders the correct template
5. The template receives the typed media type object and can access all fields directly

---

## The Scaffolder

**Class:** `Pressmind\ObjectTypeScaffolder`

The scaffolder is the code generator that converts pressmind PIM object type definitions into PHP classes, database tables, and example templates.

### What the Scaffolder Generates

For each object type (e.g. ID 123, Name "Reise"):

| Output | Path | Description |
|---|---|---|
| ORM Class | `Custom/MediaType/Reise.php` | PHP class with typed properties |
| DB Table | `objectdata_reise` | MySQL table matching the properties |
| Example View | `Custom/Views/Reise_Example.php` | Template showing all available properties |
| Object Info | `docs/objecttypes/reise.html` | HTML reference of all fields and sections |

### ORM Class Generation

The scaffolder creates a class extending `AbstractMediaType` with all fields from the PIM:

```php
<?php

namespace Custom\MediaType;

use Pressmind\ORM\Object\MediaType\AbstractMediaType;
use Pressmind\ORM\Object\MediaObject\DataType;

/**
 * Class Reise
 * @property integer $id
 * @property integer $id_media_object
 * @property string $language
 * @property string $headline_default
 * @property string $subline_default
 * @property string $beschreibung_default
 * @property DataType\Picture[] $bilder_default
 * @property DataType\Categorytree[] $zielgebiet_default
 * @property DataType\Location[] $standort_default
 * @property DataType\Objectlink[] $hotel_default
 */
class Reise extends AbstractMediaType {
    protected $_definitions = [
        'class' => ['name' => 'Reise'],
        'database' => [
            'table_name' => 'objectdata_reise',
            'primary_key' => 'id',
            'relation_key' => 'id_media_object'
        ],
        'properties' => [
            'id' => ['name' => 'id', 'type' => 'integer', 'required' => true, ...],
            'id_media_object' => ['name' => 'id_media_object', 'type' => 'integer', ...],
            'language' => ['name' => 'language', 'type' => 'string', ...],
            'headline_default' => ['name' => 'headline_default', 'type' => 'string', ...],
            'bilder_default' => [
                'name' => 'bilder_default',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'class' => '\Pressmind\ORM\Object\MediaObject\DataType\Picture',
                    'related_id' => 'id_media_object',
                    'filters' => ['var_name' => 'bilder_default', 'section_name' => 'IS NULL']
                ]
            ],
            // ... more properties
        ]
    ];
}
```

**Field Naming Convention:**
Fields are named `{var_name}_{section_name}` where:
- `var_name` = the variable name from the PIM (e.g. `headline`, `bilder`)
- `section_name` = the section name, converted to machine-readable format (e.g. `default`, `sidebar`)

The `human_to_machine()` helper converts PIM names: `"Meine Überschrift"` → `meine_ueberschrift`

### Database Table Generation

The scaffolder creates the MySQL table using `DB\Scaffolder\Mysql`:

```sql
CREATE TABLE IF NOT EXISTS objectdata_reise (
    `id` INT NOT NULL AUTO_INCREMENT,
    `id_media_object` INT NOT NULL,
    `language` TEXT NULL,
    `headline_default` TEXT NULL,
    `subline_default` TEXT NULL,
    `beschreibung_default` LONGTEXT NULL,
    -- relation fields are NOT in the table (handled via ORM relations)
    PRIMARY KEY (id)
) ENGINE=innodb DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

Relation fields (Picture, Categorytree, Location, etc.) are stored in their own tables and connected via `id_media_object` + `var_name` filters.

### View Template Generation

The scaffolder generates an example template that demonstrates how to access every property:

```php
<?php
/**
 * @var array $data
 */

/**
 * @var Custom\MediaType\Reise $reise
 */
$reise = $data['data'];

/**
 * @var Pressmind\ORM\Object\Touristic\Booking\Package[] $booking_packages
 */
$booking_packages = $data['booking_packages'];

/**
 * @var Pressmind\ORM\Object\MediaObject $media_object
 */
$media_object = $data['media_object'];

$cheapest_price = $media_object->getCheapestPrice();
?>

<h1>This is the Example View for Media Object Type "Reise"</h1>

<?php if(!is_null($cheapest_price)) {?>
    <h4>Cheapest Price</h4>
    <pre><?php print_r($cheapest_price->toStdClass());?></pre>
<?php }?>

<h4>Properties for Media Type</h4>
<dl>
    <dt>headline_default</dt>
    <dd><?php echo $reise->headline_default;?></dd>

    <dt>bilder_default</dt>
    <dd>
        <?php foreach($reise->bilder_default as $bilder_default_item) {?>
            <img src="<?php echo $bilder_default_item->getUri('thumbnail');?>"
                 title="<?php echo $bilder_default_item->copyright;?>"
                 alt="<?php echo $bilder_default_item->alt;?>">
        <?php }?>
    </dd>
</dl>
```

### Object Information File

An HTML reference file documenting all fields:

| Section | Field Name | Variable Name | Variable Type | Property Name | Tags |
|---|---|---|---|---|---|
| Default | Headline | headline | text | headline_default | seo, search |
| Default | Images | bilder | picture | bilder_default | gallery |

---

## MediaObject::render()

The central method for rendering a media object with a template:

```php
$html = $mediaObject->render('Teaser');
$html = $mediaObject->render('Detail', 'de');
$html = $mediaObject->render('Card', null, ['show_price' => true]);
```

**Signature:**

```php
public function render(
    string $template,        // Template name (e.g. 'Teaser', 'Detail', 'Card')
    string $language = null, // Optional language code
    object $custom_data = null // Optional custom data
): string
```

### File Naming Convention

The render method resolves the template file path:

```
{view_scripts.base_path}/{MediaTypeName}_{TemplateName}.php
```

**Examples:**

| Object Type | Template | File Path |
|---|---|---|
| "Reise" (ID 123) | `Teaser` | `Custom/Views/Reise_Teaser.php` |
| "Reise" (ID 123) | `Detail` | `Custom/Views/Reise_Detail.php` |
| "Hotel" (ID 456) | `Card` | `Custom/Views/Hotel_Card.php` |
| "Ausflug" (ID 789) | `List` | `Custom/Views/Ausflug_List.php` |

The media type name is derived from `config.data.media_types[id_object_type]` and converted via `human_to_machine()`.

### Data Available in Templates

The template receives a `$data` array with these keys:

```php
$data = [
    'media_object' => $mediaObject,   // Pressmind\ORM\Object\MediaObject
    'custom_data'  => $customData,     // Any custom data passed by the caller
    'language'     => $language        // Language code (e.g. 'de', 'en')
];
```

### Render Caching

If caching is enabled with `RENDERER` type, rendered HTML is cached in Redis:

```
Cache Key: pmt2core_media_objects_{id}_{template}
```

The cache is checked before rendering. If a cached version exists, it's returned immediately without executing the template. The cache is updated when the media object is reimported.

**Enable render caching:**

```json
{
  "cache": {
    "enabled": true,
    "types": ["OBJECT", "RENDERER"]
  }
}
```

---

## Writing View Templates

### Basic Template Structure

```php
<?php
/**
 * @var array $data
 */

/** @var Pressmind\ORM\Object\MediaObject $media_object */
$media_object = $data['media_object'];

/** @var string $language */
$language = $data['language'];

// Load the typed media type object
$reise = $media_object->dataForLanguage($language);
?>

<article class="product-card">
    <h2><?php echo $reise->headline_default; ?></h2>
    <p><?php echo $reise->subline_default; ?></p>
</article>
```

### Accessing Content Fields

All content fields are accessible as typed properties on the media type object:

```php
// Text fields
echo $reise->headline_default;       // String
echo $reise->beschreibung_default;   // String (HTML)

// Date fields
if(!is_null($reise->reisedatum_default)) {
    echo $reise->reisedatum_default->format('d.m.Y');
}

// Integer fields
echo $reise->sortierung_default;
```

### Working with Images

Images are accessed through Picture relation properties:

```php
<?php foreach($reise->bilder_default as $picture) { ?>
    <!-- Get derivative URI -->
    <img src="<?php echo $picture->getUri('teaser'); ?>"
         alt="<?php echo $picture->alt; ?>"
         title="<?php echo $picture->copyright; ?>">

    <!-- Original image (no derivative) -->
    <img src="<?php echo $picture->getUri(); ?>">

    <!-- WebP version (if configured) -->
    <picture>
        <source srcset="<?php echo $picture->getUri('teaser', true); ?>" type="image/webp">
        <img src="<?php echo $picture->getUri('teaser'); ?>" alt="<?php echo $picture->alt; ?>">
    </picture>

    <!-- Image sections (e.g. banner crop) -->
    <img src="<?php echo $picture->getUri('hero', false, 'banner'); ?>">

    <!-- All picture properties -->
    <?php echo $picture->caption; ?>
    <?php echo $picture->copyright; ?>
    <?php echo $picture->alt; ?>
    <?php echo $picture->title; ?>
<?php } ?>
```

**The `getUri()` method:**

```php
$picture->getUri(
    $derivativeName,  // 'thumbnail', 'teaser', etc. (null = original)
    $force_webp,      // true = return .webp version
    $sectionName,     // Section name for image sections
    $debug            // Enable debug logging
);
```

If the image hasn't been downloaded yet (`download_successful == false`), `getUri()` returns a temporary CDN URL instead.

### Working with Prices

```php
// Cheapest price for this media object
$cheapest = $media_object->getCheapestPrice();

if(!is_null($cheapest)) {
    echo number_format($cheapest->price_total, 2, ',', '.') . ' €';
    echo $cheapest->date_departure->format('d.m.Y');
    echo $cheapest->duration . ' Tage';
    echo $cheapest->transport_type;
}

// Cheapest prices with filters
$filter = new \Pressmind\Search\CheapestPrice();
$filter->occupancy = 2;
$prices = $media_object->getCheapestPrices($filter, ['price_total' => 'ASC'], [0, 10]);

foreach($prices as $price) {
    echo $price->price_total;
    echo $price->date_departure->format('d.m.Y');
}
```

### Working with Categories

```php
// Category tree fields
foreach($reise->zielgebiet_default as $category) {
    echo $category->item->name;              // Category item name
    echo $category->item->id_item;           // Category item ID (MD5/GUID)

    // Access parent tree path
    foreach($category->item->tree_path as $parent) {
        echo $parent->name . ' > ';
    }
}
```

### Working with Relations (Object Links)

```php
// Linked objects (e.g. hotels linked to a trip)
foreach($reise->hotel_default as $objectlink) {
    $linked_object = $objectlink->getLinkedMediaObject();

    // Render the linked object
    echo $linked_object->render('Card');

    // Or access properties directly
    $hotel_data = $linked_object->dataForLanguage($language);
    echo $hotel_data->headline_default;
}
```

### Working with Locations

```php
// Location fields
foreach($reise->standort_default as $location) {
    echo $location->lat;   // Latitude
    echo $location->lng;   // Longitude
    echo $location->city;
    echo $location->country;
    echo $location->address;
    echo $location->zip;
}
```

### Working with Booking Packages

```php
// Touristic booking packages
foreach($media_object->booking_packages as $package) {
    echo $package->name;

    foreach($package->dates as $date) {
        echo $date->departure->format('d.m.Y');
        echo $date->arrival->format('d.m.Y');
        echo $date->duration . ' nights';
    }

    foreach($package->housing_packages as $housing) {
        echo $housing->name;
        foreach($housing->options as $option) {
            echo $option->name;
            echo $option->price;
            echo $option->board_type;
        }
    }
}
```

---

## Custom Scaffolder Templates

Instead of using the default example template, you can provide your own scaffolder templates:

### Configuration

```json
{
  "scaffolder_templates": {
    "overwrite_existing_templates": false,
    "base_path": "APPLICATION_PATH/ObjectTypeScaffolderTemplates"
  }
}
```

Place custom template files in the `base_path` directory. The scaffolder processes **all files** in this directory and generates one view template per file.

### Template Placeholders

| Placeholder | Replaced With | Example |
|---|---|---|
| `###CLASSNAME###` | Media type class name | `Reise` |
| `###VARIABLENAME###` | Lowercase variable name | `reise` |
| `###OBJECTNAME###` | Original PIM object name | `Reise` |
| `###VIEWFILEPATH###` | Full path to the generated view file | `Custom/Views/Reise_Example.php` |
| `###PROPERTYLIST###` | Auto-generated property listing | `<dt>headline_default</dt>...` |

### File Naming

Template source files become template name suffixes:

| Source File | Generated View File |
|---|---|
| `Example.txt` | `Reise_Example.php` |
| `Teaser.txt` | `Reise_Teaser.php` |
| `Detail.txt` | `Reise_Detail.php` |
| `Card.txt` | `Reise_Card.php` |

### Overwrite Behavior

| `overwrite_existing_templates` | Behavior |
|---|---|
| `false` (default) | Only generate if file doesn't exist |
| `true` | Overwrite existing view files on every scaffolding run |

**Important:** Set to `false` in production to protect manually customized templates.

---

## The MVC View System

**Class:** `Pressmind\MVC\View`

The View class is a simple PHP template renderer using output buffering:

```php
$view = new View($pathToViewScript);
$html = $view->render(['key' => 'value']);
```

**How it works:**

1. Sets the data array as `$data`
2. Starts output buffering (`ob_start()`)
3. Includes the PHP template via `require()`
4. Captures output buffer content (`ob_get_contents()`)
5. Returns the rendered HTML string

The template receives a `$data` variable and can echo/output any HTML. There is no template engine – templates are plain PHP files.

---

## Snippet System

**Class:** `Pressmind\MVC\Snippet`

For reusable template fragments within the MVC layer:

```php
$html = Snippet::render('ProductCard', ['product' => $mediaObject]);
```

Snippets are resolved from: `APPLICATION_PATH/{module}/Snippet/{SnippetName}.php`

---

## API Output Templates

**Method:** `AbstractObject::renderApiOutputTemplate()`

For rendering API response objects through templates:

```php
$output = $mediaObject->renderApiOutputTemplate('ProductApiResponse');
```

This loads a template class from `view_scripts.base_path`, instantiates it with the object, and calls its `render()` method. Used for structured API responses.

---

## Configuration Reference

| Config Path | Description |
|---|---|
| `view_scripts.base_path` | Directory for view template files (e.g. `APPLICATION_PATH/Custom/Views`) |
| `scaffolder_templates.base_path` | Directory for scaffolder template sources |
| `scaffolder_templates.overwrite_existing_templates` | Whether to overwrite existing templates on re-scaffolding |
| `docs_dir` | Directory for generated object type documentation |
| `data.media_types` | Object type ID → name mapping |
| `data.media_types_fulltext_index_fields` | Auto-populated by scaffolder with field names for search |
| `cache.types` | Include `RENDERER` to enable render caching |
