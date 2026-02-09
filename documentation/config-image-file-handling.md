# Configuration: Image & File Handling

[← Back to Overview](configuration.md)

---

## Overview

The SDK supports flexible image and file management with multiple storage backends (local filesystem and Amazon S3), image processing with derivatives (thumbnails, teasers, etc.), WebP conversion, and image filters (watermarks, grayscale, etc.).

---

## Image Handling (`image_handling`)

```json
"image_handling": {
  "processor": {
    "adapter": "ImageMagick",
    "webp_support": false,
    "derivatives": { ... }
  },
  "storage": {
    "provider": "filesystem",
    "bucket": "WEBSERVER_DOCUMENT_ROOT/assets/images",
    "credentials": {}
  },
  "http_src": "WEBSERVER_HTTP/assets/images"
}
```

---

### `image_handling.processor.adapter`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"ImageMagick"` |
| **Required** | Yes |
| **Used in** | `Image\Processor\Config.php`, `CLI\ImageProcessorCommand.php` |

#### Description

The image processing adapter to use. Determines which image manipulation library is used for creating derivatives.

#### Valid Values

| Value | Description |
|---|---|
| `"ImageMagick"` | Uses ImageMagick (requires the `imagick` PHP extension) |

#### Example

```json
"adapter": "ImageMagick"
```

> **Note:** `ImageMagick` (PHP extension) is used by ~97% of production installations. A rare alternative is `ImageMagickCLI` (command-line fallback) for environments without the PHP extension.
>
> See [Configuration Examples](config-examples.md#image-derivatives) for the most common derivative size patterns from ~40 production installations.

---

### `image_handling.processor.webp_support`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Required** | No |
| **Used in** | `ORM\Object\MediaObject\DataType\Picture.php` |

#### Description

Global switch for WebP image support. Must be `true` for any derivative to generate WebP versions. Works in conjunction with the per-derivative `webp_create` setting.

#### Usage in Code

```php
// src/Pressmind/ORM/Object/MediaObject/DataType/Picture.php:372,399
if ($config['image_handling']['processor']['webp_support'] == true 
    && $config['image_handling']['processor']['derivatives'][$derivativeName]['webp_create'] == true) {
    // Generate WebP version
}
```

#### Behavior

Both conditions must be true for WebP generation:
1. `webp_support` must be `true` (global switch)
2. The specific derivative's `webp_create` must be `true`

#### Example

```json
// Enable WebP globally
"webp_support": true
```

---

### `image_handling.processor.derivatives`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | Yes |
| **Used in** | Multiple image processing classes |

#### Description

Defines named image derivatives (variations) that are automatically created from uploaded images. Each derivative is a named configuration object.

#### Structure

```json
"derivatives": {
  "{derivative_name}": {
    "max_width": "500",
    "max_height": "333",
    "preserve_aspect_ratio": false,
    "crop": true,
    "horizontal_crop": "center",
    "vertical_crop": "center",
    "webp_create": false,
    "webp_quality": 80,
    "filters": []
  }
}
```

#### Properties of Each Derivative

| Property | Type | Default | Description |
|---|---|---|---|
| `max_width` | `string`/`integer` | – | Maximum width in pixels |
| `max_height` | `string`/`integer` | – | Maximum height in pixels |
| `preserve_aspect_ratio` | `boolean` | `false` | If `true`, maintains the original aspect ratio |
| `crop` | `boolean` | `true` | If `true`, crops the image to fit exact dimensions |
| `horizontal_crop` | `string` | `"center"` | Horizontal crop anchor: `"left"`, `"center"`, `"right"` |
| `vertical_crop` | `string` | `"center"` | Vertical crop anchor: `"top"`, `"center"`, `"bottom"` |
| `webp_create` | `boolean` | `false` | If `true`, creates a WebP version (requires `webp_support: true`) |
| `webp_quality` | `integer` | `80` | WebP compression quality (0-100, higher = better quality) |
| `filters` | `array` | `[]` | Array of image filter configurations |

#### Crop Behavior

When `crop: true` and `preserve_aspect_ratio: false`:
- The image is resized to exactly `max_width` x `max_height`
- Excess content is cropped according to `horizontal_crop` and `vertical_crop`

When `crop: false` and `preserve_aspect_ratio: true`:
- The image is resized to fit within `max_width` x `max_height`
- The original aspect ratio is maintained
- The resulting image may be smaller than the specified dimensions

#### Standard Example

```json
"derivatives": {
  "thumbnail": {
    "max_width": "125",
    "max_height": "83",
    "preserve_aspect_ratio": false,
    "crop": true,
    "horizontal_crop": "center",
    "vertical_crop": "center",
    "webp_create": false,
    "webp_quality": 80
  },
  "teaser": {
    "max_width": "500",
    "max_height": "333",
    "preserve_aspect_ratio": false,
    "crop": true,
    "horizontal_crop": "center",
    "vertical_crop": "center",
    "webp_create": true,
    "webp_quality": 85
  },
  "detail": {
    "max_width": "1200",
    "max_height": "800",
    "preserve_aspect_ratio": true,
    "crop": false,
    "webp_create": true,
    "webp_quality": 90
  }
}
```

---

### Image Filters (`derivatives.{name}.filters`)

Filters are applied to derivatives during image processing. Multiple filters can be chained.

#### Structure

```json
"filters": [
  {
    "class": "\\Pressmind\\Image\\Filter\\{FilterName}",
    "params": { ... }
  }
]
```

#### Available Filters

##### WatermarkFilter

Adds a watermark image overlay.

```json
{
  "class": "\\Pressmind\\Image\\Filter\\WatermarkFilter",
  "params": {
    "image": "APPLICATION_PATH/assets/watermark.png",
    "position": "bottom-right",
    "size": 15,
    "margin_x": 20,
    "margin_y": 20,
    "opacity": 0.7
  }
}
```

| Parameter | Type | Description |
|---|---|---|
| `image` | `string` | Path to the watermark image (supports placeholder constants) |
| `position` | `string` | Position: `"top-left"`, `"top-right"`, `"bottom-left"`, `"bottom-right"`, `"center"` |
| `size` | `integer` | Watermark size as percentage of the image |
| `margin_x` | `integer` | Horizontal margin in pixels |
| `margin_y` | `integer` | Vertical margin in pixels |
| `opacity` | `float` | Opacity (0.0 = fully transparent, 1.0 = fully opaque) |

##### InstaFilter

Applies Instagram-style photo filters.

```json
{
  "class": "\\Pressmind\\Image\\Filter\\InstaFilter",
  "params": {
    "preset": "vintage",
    "intensity": 0.5
  }
}
```

| Parameter | Type | Description |
|---|---|---|
| `preset` | `string` | Filter preset name (e.g., `"vintage"`) |
| `intensity` | `float` | Filter intensity (0.0 - 1.0) |

##### GrayscaleFilter

Converts the image to grayscale.

```json
{
  "class": "\\Pressmind\\Image\\Filter\\GrayscaleFilter",
  "params": {}
}
```

#### Complete Derivative with Filters Example

```json
"branded_teaser": {
  "max_width": "800",
  "max_height": "600",
  "preserve_aspect_ratio": true,
  "crop": false,
  "webp_create": true,
  "webp_quality": 80,
  "filters": [
    {
      "class": "\\Pressmind\\Image\\Filter\\WatermarkFilter",
      "params": {
        "image": "APPLICATION_PATH/assets/logo.png",
        "position": "bottom-right",
        "size": 10,
        "margin_x": 15,
        "margin_y": 15,
        "opacity": 0.8
      }
    }
  ]
}
```

---

### `image_handling.storage`

The storage section defines where processed images are stored.

#### Filesystem Storage (Default)

```json
"storage": {
  "provider": "filesystem",
  "bucket": "WEBSERVER_DOCUMENT_ROOT/assets/images",
  "credentials": {}
}
```

#### S3 Storage

```json
"storage": {
  "provider": "S3",
  "bucket": "pressmind",
  "region": "eu-west-1",
  "version": "latest",
  "endpoint": "",
  "credentials": {
    "key": "AKIAIOSFODNN7EXAMPLE",
    "secret": "wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
  }
}
```

### `image_handling.storage.provider`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"filesystem"` |
| **Required** | Yes |
| **Used in** | `Storage\Provider\Factory.php`, `Storage\Bucket.php` |

#### Description

Storage provider for images.

#### Valid Values

| Value | Class | Description |
|---|---|---|
| `"filesystem"` | `Pressmind\Storage\Provider\Filesystem` | Local filesystem (default) |
| `"S3"` | `Pressmind\Storage\Provider\S3` | Amazon S3 or S3-compatible storage |

#### Usage in Code

```php
// src/Pressmind/Storage/Provider/Factory.php:16
$class_name = 'Pressmind\Storage\Provider\\' . ucfirst($storage['provider']);
```

---

### `image_handling.storage.bucket`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"WEBSERVER_DOCUMENT_ROOT/assets/images"` |
| **Required** | Yes |
| **Used in** | `Storage\Bucket.php`, `Storage\Provider\Filesystem.php` |

#### Description

- **Filesystem:** Directory path for image storage (supports placeholder constants)
- **S3:** S3 bucket name

#### Examples

```json
// Filesystem
"bucket": "WEBSERVER_DOCUMENT_ROOT/assets/images"

// S3
"bucket": "my-pressmind-images"
```

---

### `image_handling.storage.credentials`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | Only for S3 |
| **Used in** | `Storage\Provider\S3.php` |

#### Description

Credentials for S3 storage.

| Property | Type | Description |
|---|---|---|
| `key` | `string` | AWS Access Key ID |
| `secret` | `string` | AWS Secret Access Key |

---

### `image_handling.storage.region` (S3 only)

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"eu-west-1"` |

AWS region for the S3 bucket.

---

### `image_handling.storage.version` (S3 only)

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"latest"` |

AWS SDK version.

---

### `image_handling.storage.endpoint` (S3 only)

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `""` |
| **Used in** | `Storage\Provider\S3.php` |

#### Description

Custom S3-compatible endpoint URL. Used for S3-compatible services like MinIO, DigitalOcean Spaces, or Wasabi.

#### Usage in Code

```php
// src/Pressmind/Storage/Provider/S3.php:37-40
if (!empty($storage['endpoint'])) {
    $clientSetup['endpoint'] = $storage['endpoint'];
    $clientSetup['use_path_style_endpoint'] = true;
}
```

#### Examples

```json
// MinIO
"endpoint": "http://minio:9000"

// DigitalOcean Spaces
"endpoint": "https://fra1.digitaloceanspaces.com"

// Wasabi
"endpoint": "https://s3.eu-central-1.wasabisys.com"
```

---

### `image_handling.http_src`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"WEBSERVER_HTTP/assets/images"` |
| **Required** | Yes |
| **Used in** | `ORM\Object\MediaObject\DataType\Picture.php` |

#### Description

Base HTTP URL for accessing images in the browser. Supports placeholder constants.

#### Usage in Code

```php
// src/Pressmind/ORM/Object/MediaObject/DataType/Picture.php:324,369
return HelperFunctions::replaceConstantsFromConfig($config['image_handling']['http_src']) 
    . '/' . $this->file_name;
```

#### Examples

```json
// Local filesystem
"http_src": "WEBSERVER_HTTP/assets/images"

// CDN
"http_src": "https://cdn.my-travel-site.com/images"

// S3 bucket URL
"http_src": "https://pressmind.s3.eu-west-1.amazonaws.com"
```

---

## File Handling (`file_handling`)

The file handling section configures storage and access for non-image files (PDFs, documents, attachments).

```json
"file_handling": {
  "processor": {},
  "storage": {
    "provider": "filesystem",
    "bucket": "WEBSERVER_DOCUMENT_ROOT/assets/files",
    "credentials": {}
  },
  "http_src": "WEBSERVER_HTTP/assets/files"
}
```

---

### `file_handling.processor`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | No |
| **Used in** | – (reserved for future use) |

#### Description

Reserved for future file processing functionality. Currently not actively used.

---

### `file_handling.storage.provider`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"filesystem"` |
| **Required** | Yes |
| **Used in** | `Storage\Provider\Factory.php` |

#### Description

Same as `image_handling.storage.provider`. Supports `"filesystem"` and `"S3"`.

---

### `file_handling.storage.bucket`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"WEBSERVER_DOCUMENT_ROOT/assets/files"` |
| **Required** | Yes |
| **Used in** | `ORM\Object\Attachment.php`, `ORM\Object\MediaObject\DataType\File.php` |

#### Description

Storage path/bucket for files. Supports placeholder constants for filesystem storage.

---

### `file_handling.storage.credentials`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | Only for S3 |

#### Description

Same structure as `image_handling.storage.credentials`.

---

### `file_handling.http_src`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"WEBSERVER_HTTP/assets/files"` |
| **Required** | Yes |
| **Used in** | `ORM\Object\Attachment.php`, `ORM\Object\MediaObject\DataType\File.php`, `Import\MediaObjectData.php` |

#### Description

Base HTTP URL for accessing files in the browser. Supports placeholder constants.

#### Usage in Code

```php
// src/Pressmind/ORM/Object/Attachment.php:190
return HelperFunctions::replaceConstantsFromConfig($config['file_handling']['http_src']) 
    . '/attachments' . $this->path . $this->name;
```

#### Examples

```json
// Local filesystem
"http_src": "WEBSERVER_HTTP/assets/files"

// CDN
"http_src": "https://cdn.my-travel-site.com/files"

// S3 bucket URL
"http_src": "https://pressmind-files.s3.eu-west-1.amazonaws.com"
```

---

## Complete Example: Filesystem Storage

```json
{
  "image_handling": {
    "processor": {
      "adapter": "ImageMagick",
      "webp_support": true,
      "derivatives": {
        "thumbnail": {
          "max_width": "150",
          "max_height": "100",
          "preserve_aspect_ratio": false,
          "crop": true,
          "horizontal_crop": "center",
          "vertical_crop": "center",
          "webp_create": true,
          "webp_quality": 75
        },
        "teaser": {
          "max_width": "600",
          "max_height": "400",
          "preserve_aspect_ratio": false,
          "crop": true,
          "horizontal_crop": "center",
          "vertical_crop": "center",
          "webp_create": true,
          "webp_quality": 85
        },
        "detail": {
          "max_width": "1600",
          "max_height": "1200",
          "preserve_aspect_ratio": true,
          "crop": false,
          "webp_create": true,
          "webp_quality": 90
        }
      }
    },
    "storage": {
      "provider": "filesystem",
      "bucket": "WEBSERVER_DOCUMENT_ROOT/assets/images",
      "credentials": {}
    },
    "http_src": "WEBSERVER_HTTP/assets/images"
  },
  "file_handling": {
    "processor": {},
    "storage": {
      "provider": "filesystem",
      "bucket": "WEBSERVER_DOCUMENT_ROOT/assets/files",
      "credentials": {}
    },
    "http_src": "WEBSERVER_HTTP/assets/files"
  }
}
```

## Complete Example: S3 Storage

```json
{
  "image_handling": {
    "processor": {
      "adapter": "ImageMagick",
      "webp_support": true,
      "derivatives": {
        "thumbnail": {
          "max_width": "150",
          "max_height": "100",
          "preserve_aspect_ratio": false,
          "crop": true,
          "horizontal_crop": "center",
          "vertical_crop": "center",
          "webp_create": true,
          "webp_quality": 80
        }
      }
    },
    "storage": {
      "provider": "S3",
      "bucket": "my-pressmind-images",
      "region": "eu-central-1",
      "version": "latest",
      "endpoint": "",
      "credentials": {
        "key": "AKIAIOSFODNN7EXAMPLE",
        "secret": "wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
      }
    },
    "http_src": "https://my-pressmind-images.s3.eu-central-1.amazonaws.com"
  },
  "file_handling": {
    "processor": {},
    "storage": {
      "provider": "S3",
      "bucket": "my-pressmind-files",
      "region": "eu-central-1",
      "version": "latest",
      "endpoint": "",
      "credentials": {
        "key": "AKIAIOSFODNN7EXAMPLE",
        "secret": "wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
      }
    },
    "http_src": "https://my-pressmind-files.s3.eu-central-1.amazonaws.com"
  }
}
```

---

[← Back to Overview](configuration.md) | [Next: Sections, Languages & Misc →](config-sections-languages-misc.md)
