# Configuration: Sections, Languages & Miscellaneous

[← Back to Overview](configuration.md) | [→ Configuration Examples & Best Practices](config-examples.md)

---

## Sections (`data.sections`)

Sections define content variations within media objects (e.g., different versions of the same content for different websites or brands).

> **Production Insight:** All ~40 checked production installations use `"allowed": ["Default"]` as the single section. ~87% are German-only (`["de"]`). Multi-language setups typically use a regex replacement to map PIM section names to `default`. See [Configuration Examples](config-examples.md#multi-language-setup).

```json
"sections": {
  "allowed": ["Default"],
  "default": "Default",
  "fallback": "Default",
  "fallback_on_empty_values": true,
  "replace": {
    "regular_expression": null,
    "replacement": null
  }
}
```

---

### `data.sections.allowed`

| Property | Value |
|---|---|
| **Type** | `array` of strings |
| **Default** | `["Default"]` |
| **Required** | Yes |

#### Description

List of allowed section names. Only sections in this list are processed during import.

#### Example

```json
// Single section (most common)
"allowed": ["Default"]

// Multiple sections for multi-brand setup
"allowed": ["Default", "BrandA", "BrandB"]
```

---

### `data.sections.default`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"Default"` |
| **Required** | Yes |

#### Description

The default section name used when no specific section is requested. This is the section used in most single-brand setups.

#### Usage in Code

The default section name is stored in the Registry and used when accessing data type content:

```php
// Accessing section data
$this->_section_name = Registry::getInstance()->get('defaultSectionName');
```

---

### `data.sections.fallback`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"Default"` |
| **Required** | No |

#### Description

Fallback section name used when the requested section does not contain data.

#### Example

```json
// Fallback to Default section
"fallback": "Default"
```

---

### `data.sections.fallback_on_empty_values`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `true` |
| **Required** | No |

#### Description

When `true`, if a value is empty in the requested section, the system falls back to the `fallback` section to retrieve the value.

#### Example

```json
// Enable fallback on empty values
"fallback_on_empty_values": true
```

---

### `data.sections.replace`

Enables regex-based section name transformation during import. Useful for normalizing section names from the Pressmind API.

```json
"replace": {
  "regular_expression": null,
  "replacement": null
}
```

### `data.sections.replace.regular_expression`

| Property | Value |
|---|---|
| **Type** | `string` or `null` |
| **Default** | `null` |
| **Used in** | `Import\MediaObjectData.php`, `System\SchemaMigrator.php`, `ObjectTypeScaffolder.php`, `ObjectIntegrityCheck.php` |

#### Description

A regular expression pattern used to transform section names during import. When set, the pattern is applied via `preg_replace()`.

#### Usage in Code

```php
// src/Pressmind/Import/MediaObjectData.php:120-121
if (!empty($conf['data']['sections']['replace']['regular_expression'])) {
    $section_name = preg_replace(
        $conf['data']['sections']['replace']['regular_expression'], 
        $conf['data']['sections']['replace']['replacement'], 
        $section_name
    );
}
```

### `data.sections.replace.replacement`

| Property | Value |
|---|---|
| **Type** | `string` or `null` |
| **Default** | `null` |

Replacement string for the regex pattern.

#### Example

```json
// Remove numeric prefixes from section names
"replace": {
  "regular_expression": "/^\\d+_/",
  "replacement": ""
}
// "01_Default" becomes "Default"

// Replace spaces with underscores
"replace": {
  "regular_expression": "/\\s+/",
  "replacement": "_"
}
// "My Section" becomes "My_Section"
```

---

## Languages (`data.languages`)

```json
"languages": {
  "allowed": ["de"],
  "default": "de",
  "gettext": {
    "active": false,
    "dir": "/path/to/languagefiles"
  }
}
```

---

### `data.languages.allowed`

| Property | Value |
|---|---|
| **Type** | `array` of strings |
| **Default** | `["de"]` |
| **Required** | Yes |
| **Used in** | `Import\MediaObjectData.php`, `Import\CategoryTree.php`, `Search\MongoDB.php`, `Search\OpenSearch.php` |

#### Description

List of language codes that are allowed/processed. Only data in these languages is imported and indexed.

#### Usage in Code

```php
// src/Pressmind/Import/MediaObjectData.php:118
if (!in_array($var_name, $this->_var_names_to_be_ignored) 
    && in_array($language, $conf['data']['languages']['allowed'])) {
    // Process this language
}
```

#### Examples

```json
// German only (default)
"allowed": ["de"]

// German and English
"allowed": ["de", "en"]

// Multi-language setup
"allowed": ["de", "en", "fr", "it", "es"]
```

---

### `data.languages.default`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"de"` |
| **Required** | Yes |
| **Used in** | `Import\MediaObjectData.php` and many others |

#### Description

Default language code. Used as fallback when no language is specified in the data.

#### Usage in Code

```php
// src/Pressmind/Import/MediaObjectData.php:75,115
$default_language = $conf['data']['languages']['default'];
$section->language = empty($section->language) ? $default_language : $section->language;
```

#### Examples

```json
// German (default)
"default": "de"

// English
"default": "en"
```

---

### `data.languages.gettext`

Configuration for GNU gettext translation file generation.

#### `data.languages.gettext.active`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Used in** | `Import\CategoryTree.php` |

Enables automatic gettext `.mo` file generation during category tree import.

#### `data.languages.gettext.dir`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"/path/to/languagefiles"` |
| **Used in** | `Import\CategoryTree.php` |

Directory path for gettext translation files.

#### Usage in Code

```php
// src/Pressmind/Import/CategoryTree.php:251-262
$active = !empty($conf['data']['languages']['gettext']['active']);
if ($active) {
    if (empty($conf['data']['languages']['gettext']['dir'])) {
        $this->_errors[] = 'Error: No directory for gettext files defined in config';
    }
    $dir = rtrim($conf['data']['languages']['gettext']['dir'], '/');
    // Generate .mo files
}
```

#### Example

```json
"gettext": {
  "active": true,
  "dir": "/var/www/my-site/languages"
}
```

---

## Price Format (`price_format`)

Configures how prices are formatted for display, organized by locale.

```json
"price_format": {
  "de": {
    "decimals": 2,
    "decimal_separator": ",",
    "thousands_separator": ".",
    "position": "LEFT",
    "currency": "€"
  }
}
```

---

### `price_format.{locale}.decimals`

| Property | Value |
|---|---|
| **Type** | `integer` |
| **Default** | `2` |
| **Used in** | `Tools\PriceHandler.php` |

Number of decimal places.

---

### `price_format.{locale}.decimal_separator`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `","` |
| **Used in** | `Tools\PriceHandler.php` |

Character used as decimal separator.

---

### `price_format.{locale}.thousands_separator`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"."` |
| **Used in** | `Tools\PriceHandler.php` |

Character used as thousands separator.

---

### `price_format.{locale}.position`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"LEFT"` |
| **Valid values** | `"LEFT"`, `"RIGHT"` |
| **Used in** | `Tools\PriceHandler.php` |

#### Description

Position of the currency symbol relative to the amount.

#### Usage in Code

```php
// src/Pressmind/Tools/PriceHandler.php:69,72
if ($position == 'LEFT') {
    return $currency . '&nbsp;' . $price;
}
return $price . '&nbsp;' . $currency;
```

---

### `price_format.{locale}.currency`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"€"` |
| **Used in** | `Tools\PriceHandler.php` |

Currency symbol.

---

### Complete Price Format Examples

```json
// German (Euro)
"price_format": {
  "de": {
    "decimals": 2,
    "decimal_separator": ",",
    "thousands_separator": ".",
    "position": "LEFT",
    "currency": "€"
  }
}
// Result: € 1.299,00

// English (US Dollar)
"price_format": {
  "en": {
    "decimals": 2,
    "decimal_separator": ".",
    "thousands_separator": ",",
    "position": "LEFT",
    "currency": "$"
  }
}
// Result: $ 1,299.00

// Swiss Francs
"price_format": {
  "ch": {
    "decimals": 2,
    "decimal_separator": ".",
    "thousands_separator": "'",
    "position": "LEFT",
    "currency": "CHF"
  }
}
// Result: CHF 1'299.00

// British Pounds (currency after amount)
"price_format": {
  "en_gb": {
    "decimals": 2,
    "decimal_separator": ".",
    "thousands_separator": ",",
    "position": "RIGHT",
    "currency": "£"
  }
}
// Result: 1,299.00 £

// Multiple locales
"price_format": {
  "de": {
    "decimals": 2,
    "decimal_separator": ",",
    "thousands_separator": ".",
    "position": "LEFT",
    "currency": "€"
  },
  "en": {
    "decimals": 2,
    "decimal_separator": ".",
    "thousands_separator": ",",
    "position": "LEFT",
    "currency": "€"
  }
}
```

---

## View Scripts (`view_scripts`)

```json
"view_scripts": {
  "base_path": "APPLICATION_PATH/Custom/Views"
}
```

---

### `view_scripts.base_path`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"APPLICATION_PATH/Custom/Views"` |
| **Required** | Yes |
| **Used in** | `ORM\Object\MediaObject.php`, `ORM\Object\AbstractObject.php`, `ObjectTypeScaffolder.php` |

#### Description

Base path for view script templates. The SDK looks for view scripts at this location when rendering media objects.

#### Usage in Code

```php
// src/Pressmind/ORM/Object/MediaObject.php:661
$script_path = $config['view_scripts']['base_path'] 
    . DIRECTORY_SEPARATOR . ucfirst($media_type_name) . '_' . ucfirst($template);
```

#### Template Naming Convention

View scripts follow the naming pattern: `{MediaTypeName}_{TemplateName}.php`

Example: For a media type named "Reise" and a template "teaser":
`Custom/Views/Reise_Teaser.php`

#### Example

```json
// Default
"base_path": "APPLICATION_PATH/Custom/Views"

// Custom path
"base_path": "APPLICATION_PATH/templates/pressmind"
```

---

## Scaffolder Templates (`scaffolder_templates`)

```json
"scaffolder_templates": {
  "overwrite_existing_templates": false,
  "base_path": "APPLICATION_PATH/ObjectTypeScaffolderTemplates"
}
```

---

### `scaffolder_templates.overwrite_existing_templates`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Required** | No |
| **Used in** | `ObjectTypeScaffolder.php` |

#### Description

Controls whether the scaffolder overwrites existing template files. When `false`, existing templates are preserved (preventing loss of customizations).

#### Usage in Code

```php
// src/Pressmind/ObjectTypeScaffolder.php:320
if (!file_exists($file_path) 
    || $config['scaffolder_templates']['overwrite_existing_templates'] == true) {
    // Write template file
}
```

#### Example

```json
// Preserve existing templates (default, recommended)
"overwrite_existing_templates": false

// Force regeneration of all templates
"overwrite_existing_templates": true
```

> **Warning:** Setting this to `true` will overwrite any manual customizations to generated template files!

---

### `scaffolder_templates.base_path`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"APPLICATION_PATH/ObjectTypeScaffolderTemplates"` |
| **Required** | No |
| **Used in** | `ObjectTypeScaffolder.php` |

#### Description

Base path for scaffolder template files. The scaffolder reads template files from this directory to generate object type classes and views.

#### Usage in Code

```php
// src/Pressmind/ObjectTypeScaffolder.php:314-315
foreach (new \DirectoryIterator(
    str_replace('APPLICATION_PATH', APPLICATION_PATH, $config['scaffolder_templates']['base_path'])
) as $file) {
    // Process template files
}
```

#### Example

```json
// Default
"base_path": "APPLICATION_PATH/ObjectTypeScaffolderTemplates"

// Custom path
"base_path": "APPLICATION_PATH/custom_templates"
```

---

## Documentation Directory (`docs_dir`)

```json
"docs_dir": "WEBSERVER_DOCUMENT_ROOT/docs"
```

### `docs_dir`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"WEBSERVER_DOCUMENT_ROOT/docs"` |
| **Required** | No |
| **Used in** | `ObjectTypeScaffolder.php` |

#### Description

Base directory for generated documentation files. The scaffolder generates HTML documentation for each object type and stores it here.

#### Usage in Code

```php
// src/Pressmind/ObjectTypeScaffolder.php:274
$docs_dir = HelperFunctions::replaceConstantsFromConfig($config['docs_dir']) 
    . DIRECTORY_SEPARATOR . 'objecttypes' . DIRECTORY_SEPARATOR;
file_put_contents(
    $docs_dir . HelperFunctions::human_to_machine($this->_object_definition->name) . '.html', 
    ...
);
```

#### Example

```json
// Default
"docs_dir": "WEBSERVER_DOCUMENT_ROOT/docs"

// Custom path
"docs_dir": "APPLICATION_PATH/documentation/generated"
```

---

## Pretty URLs (`data.media_types_pretty_url`)

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Used in** | `ORM\Object\MediaObject.php` |

#### Description

Configures SEO-friendly URL generation per object type. Supports both a legacy format and a newer format with language support.

### Legacy Format

```json
"media_types_pretty_url": {
  "123": {
    "fields": ["name"],
    "separator": "-",
    "strategy": "unique",
    "prefix": "trip",
    "suffix": ""
  }
}
```

### New Format (with language support)

```json
"media_types_pretty_url": [
  {
    "id_object_type": 123,
    "language": "de",
    "field": "name",
    "separator": "-",
    "strategy": "unique",
    "prefix": "reise",
    "suffix": ""
  },
  {
    "id_object_type": 123,
    "language": "en",
    "field": "name",
    "separator": "-",
    "strategy": "unique",
    "prefix": "trip",
    "suffix": ""
  }
]
```

### Properties

| Property | Type | Default | Description |
|---|---|---|---|
| `fields` / `field` | `array` / `string` | `["name"]` | Fields used for URL generation |
| `separator` | `string` | `"-"` | Separator between words |
| `strategy` | `string` | `"unique"` | `"unique"` (appends ID if needed) or `"slug"` |
| `prefix` | `string` | `""` | URL prefix |
| `suffix` | `string` | `""` | URL suffix |

### Usage in Code

```php
// src/Pressmind/ORM/Object/MediaObject.php:1206-1228
$is_legacy = !isset($config['data']['media_types_pretty_url'][array_key_first($config['data']['media_types_pretty_url'])]['id_object_type']);

if ($is_legacy) {
    $fields = $config['data']['media_types_pretty_url'][$this->id_object_type]['fields'] ?? ['name'];
    $separator = $config['data']['media_types_pretty_url'][$this->id_object_type]['separator'] ?? $separator;
    $strategy = $config['data']['media_types_pretty_url'][$this->id_object_type]['strategy'] ?? $strategy;
    $prefix = $config['data']['media_types_pretty_url'][$this->id_object_type]['prefix'] ?? $prefix;
    $suffix = $config['data']['media_types_pretty_url'][$this->id_object_type]['suffix'] ?? $suffix;
}
```

### Example URLs

With the configuration:
```json
{"prefix": "trip", "separator": "-", "fields": ["name"]}
```

A media object with `name: "Beautiful Mallorca Holiday"` generates:
`/trip/beautiful-mallorca-holiday`

---

## Complete Example Configuration

```json
{
  "development": {
    "data": {
      "sections": {
        "allowed": ["Default", "PremiumBrand"],
        "default": "Default",
        "fallback": "Default",
        "fallback_on_empty_values": true,
        "replace": {
          "regular_expression": null,
          "replacement": null
        }
      },
      "languages": {
        "allowed": ["de", "en"],
        "default": "de",
        "gettext": {
          "active": true,
          "dir": "/var/www/my-site/languages"
        }
      },
      "media_types_pretty_url": {
        "123": {
          "fields": ["name"],
          "separator": "-",
          "strategy": "unique",
          "prefix": "trip",
          "suffix": ""
        },
        "456": {
          "fields": ["name", "city"],
          "separator": "-",
          "strategy": "unique",
          "prefix": "hotel",
          "suffix": ""
        }
      },
      "schema_migration": {
        "mode": "auto",
        "log_changes": true
      }
    },
    "price_format": {
      "de": {
        "decimals": 2,
        "decimal_separator": ",",
        "thousands_separator": ".",
        "position": "LEFT",
        "currency": "€"
      },
      "en": {
        "decimals": 2,
        "decimal_separator": ".",
        "thousands_separator": ",",
        "position": "LEFT",
        "currency": "€"
      }
    },
    "view_scripts": {
      "base_path": "APPLICATION_PATH/Custom/Views"
    },
    "scaffolder_templates": {
      "overwrite_existing_templates": false,
      "base_path": "APPLICATION_PATH/ObjectTypeScaffolderTemplates"
    },
    "docs_dir": "WEBSERVER_DOCUMENT_ROOT/docs"
  }
}
```

---

[← Back to Overview](configuration.md) | [Back to: Image & File Handling ←](config-image-file-handling.md)
