<?php

namespace Pressmind\Import;

use stdClass;

final class IconNormalizer
{
    /**
     * @param stdClass|array|null $icon
     * @return array|null
     */
    public static function normalize($icon): ?array
    {
        if ($icon === null) {
            return null;
        }
        $icon = is_array($icon) ? (object) $icon : $icon;
        if (!$icon instanceof stdClass) {
            return null;
        }

        return [
            'id' => $icon->id ?? null,
            'name' => $icon->name ?? null,
            'slug' => $icon->slug ?? null,
            'url' => $icon->url ?? null,
            'mime' => $icon->mime ?? null,
            'style' => $icon->style ?? null,
            'variants' => self::normalizeVariants($icon->variants ?? []),
        ];
    }

    private static function normalizeVariants($variants): array
    {
        if (!is_array($variants)) {
            return [];
        }
        $normalized = [];
        foreach ($variants as $variant) {
            $variant = is_array($variant) ? (object) $variant : $variant;
            if (!$variant instanceof stdClass) {
                continue;
            }
            $normalized[] = [
                'style' => $variant->style ?? null,
                'url' => $variant->url ?? null,
                'mime' => $variant->mime ?? null,
            ];
        }
        return $normalized;
    }
}
