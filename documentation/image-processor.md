# Image Processor

[← Back to Import Process](import-process.md) | [→ Template Interface](template-interface.md)

---

## Table of Contents

- [Overview](#overview)
- [Processing Pipeline](#processing-pipeline)
- [Configuration](#configuration)
  - [Processor Adapter](#processor-adapter)
  - [Derivatives](#derivatives)
  - [Storage](#storage)
- [Processor Adapters](#processor-adapters)
  - [ImageMagick (Recommended)](#imagemagick-recommended)
  - [ImageMagickCLI](#imagemagickcli)
  - [GD](#gd)
  - [WebPicture (WebP Conversion)](#webpicture-webp-conversion)
- [Derivatives in Detail](#derivatives-in-detail)
  - [Resize Modes](#resize-modes)
  - [Crop Options](#crop-options)
  - [WebP Support](#webp-support)
- [Filter System](#filter-system)
  - [Filter Chain](#filter-chain)
  - [WatermarkFilter](#watermarkfilter)
  - [InstaFilter](#instafilter)
  - [GrayscaleFilter](#grayscalefilter)
  - [Creating Custom Filters](#creating-custom-filters)
- [Storage Providers](#storage-providers)
- [Image Downloader](#image-downloader)
- [ORM Integration (Derivative Records)](#orm-integration-derivative-records)
- [How Images Are Triggered](#how-images-are-triggered)

---

## Overview

The Image Processor is responsible for downloading original images from the pressmind CDN, generating size-optimized **derivatives** (thumbnail, teaser, hero, etc.), optionally applying **filters** (watermarks, color effects), and storing the results via configurable **storage providers** (filesystem or S3).

```
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│  pressmind CDN   │     │  Image Processor │     │  Storage         │
│  (Original)      │     │                  │     │                  │
│                  │     │  ┌────────────┐  │     │  Filesystem      │
│  image.jpg       │────▶│  │ Download   │  │────▶│  /assets/images/ │
│  (3000×2000)     │     │  └────────────┘  │     │                  │
│                  │     │        │         │     │  – or –          │
│                  │     │        ▼         │     │                  │
│                  │     │  ┌────────────┐  │     │  Amazon S3       │
│                  │     │  │ Adapter    │  │     │  s3://bucket/    │
│                  │     │  │ (ImageMag.)│  │     │                  │
│                  │     │  └────────────┘  │     └──────────────────┘
│                  │     │        │         │
│                  │     │   Derivatives:   │     Output Files:
│                  │     │   thumbnail ─────│───▶ image_thumbnail.jpg (125×83)
│                  │     │   teaser ────────│───▶ image_teaser.jpg (500×333)
│                  │     │   hero ──────────│───▶ image_hero.jpg (1200×800)
│                  │     │        │         │
│                  │     │  ┌────────────┐  │
│                  │     │  │ Filters    │  │     Optional:
│                  │     │  │ Watermark  │  │───▶ image_hero.webp
│                  │     │  │ InstaFilter│  │
│                  │     │  │ Grayscale  │  │
│                  │     │  └────────────┘  │
│                  │     │        │         │
│                  │     │  ┌────────────┐  │
│                  │     │  │ WebP       │  │
│                  │     │  │ Conversion │  │
│                  │     │  └────────────┘  │
│                  │     └──────────────────┘
```

---

## Processing Pipeline

For each image, the processor executes these steps:

```
1. Download original from pressmind CDN (via cURL)
   └─ Lock file mechanism prevents duplicate downloads

2. For each configured derivative:
   ├─ Check if derivative already exists → skip if yes
   ├─ Resize/crop according to config
   ├─ Apply filter chain (if configured)
   │   ├─ WatermarkFilter
   │   ├─ InstaFilter
   │   └─ GrayscaleFilter
   ├─ Convert to JPEG (quality 85)
   ├─ Save derivative to storage
   └─ Optionally create WebP version

3. Store derivative metadata in database
   └─ pmt2core_media_object_image_derivatives
```

---

## Configuration

All image handling is configured under `image_handling` in `config.json`:

```json
{
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
}
```

### Processor Adapter

| Property | Type | Description |
|---|---|---|
| `adapter` | String | Processor class name: `ImageMagick`, `ImageMagickCLI`, or `GD` |
| `webp_support` | Boolean | Global WebP support flag |

### Derivatives

Each derivative defines a named size variant of the original image:

```json
{
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
      "webp_create": false,
      "webp_quality": 80
    }
  }
}
```

| Property | Type | Description |
|---|---|---|
| `max_width` | Integer | Maximum width in pixels |
| `max_height` | Integer | Maximum height in pixels |
| `preserve_aspect_ratio` | Boolean | Maintain original aspect ratio |
| `crop` | Boolean | Crop to exact dimensions (vs. fit within) |
| `horizontal_crop` | String | Horizontal crop anchor: `center`, `left`, `right` |
| `vertical_crop` | String | Vertical crop anchor: `center`, `top`, `bottom` |
| `webp_create` | Boolean | Also generate a WebP version |
| `webp_quality` | Integer | WebP compression quality (0-100) |
| `filters` | Array | Optional filter chain (see [Filter System](#filter-system)) |

### Storage

| Property | Type | Description |
|---|---|---|
| `provider` | String | `filesystem` or `s3` |
| `bucket` | String | Storage path/bucket. Supports `WEBSERVER_DOCUMENT_ROOT` placeholder |
| `credentials` | Object | S3 credentials (`key`, `secret`, `region`, `endpoint`) |

The `http_src` property defines the public URL base for serving images.

---

## Processor Adapters

The SDK provides four adapter implementations. All implement `Pressmind\Image\Processor\AdapterInterface`:

```php
interface AdapterInterface {
    public function process($config, $file, $derivativeName);
    public function isImageCorrupted($file);
}
```

### ImageMagick (Recommended)

**Class:** `Pressmind\Image\Processor\Adapter\ImageMagick`

Uses the PHP Imagick extension. Supports crop, resize, aspect ratio preservation, filter chains, and JPEG conversion.

**Features:**
- Crop with `cropThumbnailImage()` for exact dimensions
- Resize with `thumbnailImage()` for proportional fit
- Automatic JPEG conversion (quality 85)
- Full filter chain support (Watermark, InstaFilter, Grayscale)
- Corruption detection via histogram analysis

**Corruption Detection:**
The `isImageCorrupted()` method analyzes the image histogram – if more than 70% of pixels share a single color, the image is flagged as corrupted.

### ImageMagickCLI

**Class:** `Pressmind\Image\Processor\Adapter\ImageMagickCLI`

Uses the command-line `convert` tool via `proc_open()`. Useful when the PHP Imagick extension is not available but ImageMagick is installed on the system.

**Note:** Does not support the filter system. Filter chains are only available with the PHP ImageMagick adapter.

### GD

**Class:** `Pressmind\Image\Processor\Adapter\GD`

Uses PHP's built-in GD library. Fallback adapter with limited features.

**Limitations:**
- No crop mode (only resize)
- No filter support
- No automatic JPEG conversion (keeps original format)
- No corruption detection

### WebPicture (WebP Conversion)

**Class:** `Pressmind\Image\Processor\Adapter\WebPicture`

Not a standalone adapter – used additionally when `webp_create: true` is set on a derivative. Creates a WebP version of the already-processed derivative using PHP GD's `imagewebp()`.

---

## Derivatives in Detail

### Resize Modes

The processor has two distinct resize behaviors:

**Crop Mode** (`crop: true`):
```
Original: 3000×2000
Config:   max_width=500, max_height=333

Result: Exactly 500×333 pixels
Method: cropThumbnailImage() – scales and crops to fill the exact dimensions
```

**Fit Mode** (`crop: false`, `preserve_aspect_ratio: true`):
```
Original: 3000×2000
Config:   max_width=800, max_height=600

Result: 800×533 pixels (fits within 800×600, preserving ratio)
Method: thumbnailImage() – scales to fit within the bounding box
```

**Stretch Mode** (`crop: false`, `preserve_aspect_ratio: false`):
```
Original: 3000×2000
Config:   max_width=800, max_height=600

Result: 800×600 pixels (stretched/squished to exact dimensions)
```

### Crop Options

When `crop: true`, you control the crop anchor point:

| `horizontal_crop` | `vertical_crop` | Anchor |
|---|---|---|
| `center` | `center` | Center of the image (default) |
| `left` | `top` | Top-left corner |
| `right` | `bottom` | Bottom-right corner |

### WebP Support

For each derivative with `webp_create: true`, an additional `.webp` file is generated:

```
image_teaser.jpg     → JPEG derivative (always created)
image_teaser.webp    → WebP derivative (if webp_create is true)
```

The WebP quality is controlled by `webp_quality` (0-100, default 80).

---

## Filter System

Filters are applied **after** resize/crop and **before** JPEG conversion. They are configured per derivative and executed as a chain.

### Filter Chain

Filters are processed in order. The output of one filter becomes the input of the next:

```json
{
  "hero_watermarked": {
    "max_width": "1200",
    "max_height": "800",
    "crop": true,
    "filters": [
      {
        "class": "\\Pressmind\\Image\\Filter\\InstaFilter",
        "params": { "preset": "warm", "intensity": 0.3 }
      },
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
    ]
  }
}
```

The `FilterChain` class manages filter instantiation and execution:

```php
$filterChain = FilterChain::createFromConfig($config->filters);
$image = $filterChain->process($image); // Imagick object
```

### WatermarkFilter

**Class:** `Pressmind\Image\Filter\WatermarkFilter`

Overlays a watermark image (e.g. logo) onto the derivative.

| Parameter | Type | Default | Description |
|---|---|---|---|
| `image` | String | (required) | Path to watermark image file |
| `position` | String | `bottom-right` | Position: `top-left`, `top-right`, `bottom-left`, `bottom-right`, `center` |
| `size` | Integer | `10` | Watermark width as percentage of image width |
| `margin_x` | Integer | `10` | Horizontal margin in pixels |
| `margin_y` | Integer | `10` | Vertical margin in pixels |
| `opacity` | Float | `1.0` | Opacity (0.0 = transparent, 1.0 = opaque) |

The watermark is automatically scaled proportionally to the target image size.

### InstaFilter

**Class:** `Pressmind\Image\Filter\InstaFilter`

Instagram-style color grading presets.

| Parameter | Type | Default | Description |
|---|---|---|---|
| `preset` | String | `vintage` | Filter preset name |
| `intensity` | Float | `1.0` | Effect intensity (0.0 = none, 1.0 = full) |

**Available Presets:**

| Preset | Effect |
|---|---|
| `vintage` | Warm sepia tones, slightly desaturated, soft vignette |
| `vivid` | Increased saturation and contrast, punchy colors |
| `warm` | Golden toning, warmer colors (sunset look) |
| `cool` | Blueish toning, cooler colors (nordic look) |
| `fade` | Washed out colors, reduced contrast (retro film look) |

Each preset internally adjusts: saturation, brightness, contrast, gamma, color tint, and optionally applies a vignette effect. The `intensity` parameter blends all values towards their neutral state.

### GrayscaleFilter

**Class:** `Pressmind\Image\Filter\GrayscaleFilter`

Converts the image to grayscale. No parameters.

```json
{
  "class": "\\Pressmind\\Image\\Filter\\GrayscaleFilter",
  "params": {}
}
```

### Creating Custom Filters

To create a custom filter:

1. Implement `Pressmind\Image\Filter\FilterInterface`
2. Optionally extend `AbstractFilter` for helper methods

```php
<?php

namespace Custom\Image\Filter;

use Imagick;
use Pressmind\Image\Filter\AbstractFilter;

class BlurFilter extends AbstractFilter
{
    public function apply(Imagick $image, array $params): Imagick
    {
        $params = $this->mergeParams($params, [
            'radius' => 5,
            'sigma' => 3.0
        ]);

        $image->gaussianBlurImage($params['radius'], $params['sigma']);

        return $image;
    }

    public function getName(): string
    {
        return 'blur';
    }
}
```

Use in config:

```json
{
  "class": "\\Custom\\Image\\Filter\\BlurFilter",
  "params": { "radius": 8, "sigma": 4.0 }
}
```

The `AbstractFilter` base class provides helper methods:
- `mergeParams($params, $defaults)` – Merge with defaults
- `clamp($value, $min, $max)` – Clamp numeric value
- `percentageOf($dimension, $percentage)` – Calculate pixel value from percentage

---

## Storage Providers

Images are stored through the SDK's storage abstraction:

**Filesystem:**
```json
{
  "provider": "filesystem",
  "bucket": "WEBSERVER_DOCUMENT_ROOT/assets/images"
}
```

**Amazon S3:**
```json
{
  "provider": "s3",
  "bucket": "my-travel-images",
  "credentials": {
    "key": "AKIAIOSFODNN7EXAMPLE",
    "secret": "wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY",
    "region": "eu-central-1",
    "endpoint": null
  }
}
```

The `http_src` config defines the public URL prefix for accessing stored images:
```json
"http_src": "WEBSERVER_HTTP/assets/images"
```

In templates, images are accessed via: `$picture->getUri('teaser')` which returns the full HTTP path to the derivative.

---

## Image Downloader

**Class:** `Pressmind\Image\Downloader`

Handles downloading original images from the pressmind CDN.

**Features:**
- cURL-based download with timeout (20 seconds)
- HTTP 429 (rate limit) detection with abort
- Lock file mechanism to prevent concurrent downloads of the same image
- Skip existing files (unless `forceOverwrite` is set)

**Lock Files:**
A `.lock` file is created for each image during download. This prevents parallel import processes from downloading the same image simultaneously.

---

## ORM Integration (Derivative Records)

Each generated derivative is tracked in the database:

**Table:** `pmt2core_media_object_image_derivatives`

| Column | Type | Description |
|---|---|---|
| `id` | Integer | Primary key |
| `id_media_object` | Integer | Media object reference |
| `id_image` | Integer | Source image reference |
| `id_image_section` | Integer | Image section reference |
| `name` | String | Derivative name (e.g. `thumbnail`, `teaser`) |
| `file_name` | String | Generated file name |
| `width` | Integer | Width in pixels |
| `height` | Integer | Height in pixels |
| `download_successful` | Boolean | Whether download/generation succeeded |

Access in PHP:

```php
$picture = $mediaObject->bilder_default[0];

// Get URI for specific derivative
$teaser_url = $picture->getUri('teaser');
$thumb_url = $picture->getUri('thumbnail');

// Access metadata
echo $picture->copyright;
echo $picture->alt;
echo $picture->caption;
```

---

## How Images Are Triggered

Images are processed in two scenarios:

**1. During Import (Post-Import Phase)**

The `Import::postImport()` method spawns the image processor as a background CLI process:

```php
$cmd = 'nohup php /cli/image_processor.php mediaobject 123,456 > /dev/null 2>&1 &';
exec($cmd);
```

This ensures the import pipeline doesn't block waiting for image processing.

**2. On-Demand / Manual**

The image processor can be run manually:

```bash
php cli/image_processor.php                    # Process all pending images
php cli/image_processor.php mediaobject 123    # Process images for specific object
```

The processor checks if each derivative already exists before generating it, so running it multiple times is safe (idempotent).
