# Touristic: Startingpoint

[← Back to Documentation](../documentation.md) | [→ Startingpoint Option](Startingpoint/Option.md) | [→ Transport](Transport.md)

**Namespace:** `Pressmind\ORM\Object\Touristic\Startingpoint`

---

## Description

A Startingpoint represents a **group of boarding points** (Zustiege) for bus travel. The group itself has a name (e.g. "Route South") that is typically not shown to the end user. The individual boarding points are stored in the `options` relation.

Startingpoints are assigned to Transports. A bus Transport references a Startingpoint group, which in turn contains the individual boarding locations with their prices, addresses, and departure times.

---

## Properties

| Property | Type | Required | Description |
|---|---|---|---|
| `id` | string | yes | Primary key |
| `code` | string | no | External code |
| `name` | string | yes | Group name, e.g. "Route South". Usually not displayed in frontend |
| `text` | string | no | Description |

### Relations

| Relation | Type | Description |
|---|---|---|
| `options` | Startingpoint\Option[] | Individual boarding points within this group |

### Deprecated Properties

| Property | Note |
|---|---|
| `logic` | No longer used |

---

## Related Documentation

- [Startingpoint Option](Startingpoint/Option.md) – Individual boarding points with prices and addresses
- [Transport](Transport.md) – Transports referencing startingpoint groups
- [Configuration: generate_offer_for_each_startingpoint_option](../config-touristic-data.md) – Whether each boarding point gets a separate price entry
