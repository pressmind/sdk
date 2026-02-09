# Touristic: Option Description

[← Back to Option](../Option.md) | [← Back to Documentation](../../documentation.md)

**Namespace:** `Pressmind\ORM\Object\Touristic\Option\Description`

---

## Description

Supplementary description for option types `extra`, `sightseeing`, and `ticket`. Provides additional text and optionally links to another MediaObject for extended content.

> **Note:** This entity is rarely used in production. Most implementations use the `Option.description_long` field or render descriptions from linked MediaObjects directly.

---

## Properties

| Property | Type | Required | Description |
|---|---|---|---|
| `id_booking_package` | string | yes | Reference to the parent Booking Package |
| `id_media_object` | integer | yes | Reference to a linked MediaObject for extended content |
| `type` | string | yes | Option type: `extra`, `sightseeing`, or `ticket` |
| `name` | string | no | Display name |
| `text` | string | no | Description text |

### Deprecated Properties

| Property | Note |
|---|---|
| `necessary` | No longer used |

---

## Related Documentation

- [Option](../Option.md) – Parent option entity
- [Booking Package](../Booking/Package.md) – Booking package context
