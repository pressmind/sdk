# GPX-Import und räumliche Reisesuche (Webcore v2-35)

Das SDK importiert die Übersichtskarte und GPX-Tracks aus `Itinerary/get`. MongoDB ergänzt den bestehenden Preisindex um räumliche Filter. Es ist keine zusätzliche Suchdatenbank erforderlich; OpenSearch bleibt für Volltext nutzbar. Die gespeicherten Geometrien sind GeoJSON und können später auch für einen OpenSearch-Geo-Index verwendet werden.

## Daten und Speicherung

| Quelle | Speicherung | Inhalt |
|---|---|---|
| `result.map` | `pmt2core_itinerary_maps.geojson` | Vollständige Übersichtskarte einschließlich Verbindungen und Darstellungsmetadaten |
| `steps[].gpx_tracks` bzw. `variants[].steps[].gpx_tracks` | `pmt2core_itinerary_steps.gpx_tracks` | FeatureCollection einschließlich Höhen, Namen, Farben und Stationen |
| `geopoints[].pointType` | `pmt2core_itinerary_step_geopoints.pointType` | Punktart, etwa `accommodation` oder `attraction` |
| Abgeleitete Suchgeometrie | `pmt2core_itinerary_maps.search_geometry` | Alle Etappenpunkte, Hafenkoordinaten und GPX-Segmente, unabhängig von der Auswahl der Übersichtskarte |

Die Kartenzeile verwendet die Medienobjekt-ID als Primärschlüssel. Sie liegt in einer eigenen Tabelle, weil der Reiseverlauf vor dem Medienobjekt importiert wird. Die JSON-Spalten werden als `LONGTEXT` angelegt, damit längere Tracks nicht an der 64-KiB-Grenze von `TEXT` scheitern. Das bestehende JSON-ORM liefert PHP-Arrays zurück.

```php
$map = $mediaObject->getItineraryMap(); // FeatureCollection oder null
foreach ($mediaObject->getItinerarySteps() as $step) {
    $tracks = $step->gpx_tracks;
}
// Bei terminabhängigen Verläufen: getItineraryVariants(), dann $variant->steps.
```

Erneute Importe ersetzen die Daten. Entfernte Tracks, ein Wechsel der Reiseverlaufsart und ein erfolgreicher leerer API-Response hinterlassen keine alten Suchgeometrien. Ein API-Fehler lässt den bisherigen Kartenstand unverändert.

## MongoDB-Index

Jedes Suchdokument erhält `geo` als zweidimensionale GeoJSON-`GeometryCollection` und einen `2dsphere`-Index namens `geo_2dsphere`. Die Originaldaten behalten ihre Höhenkoordinaten; die räumliche Suche berechnet Entfernungen auf der Erdoberfläche.

Enthalten sind:

- GPX-Strecken einschließlich ihrer Segmente, Etappenpunkte und Hafenkoordinaten;
- konfigurierte Location-Felder aus `data.search_mongodb.search.locations`, beispielsweise Hotelstandorte.

Bestehende `locations.<feld>`-Daten bleiben erhalten. Hotelstandorte müssen wie bisher für ihren Medienobjekttyp konfiguriert sein:

```php
// Innerhalb data.search_mongodb.search:
'locations' => [
    123 => ['hotelstandort' => null], // Beispiel: tatsächliche Typ-ID und Feldname einsetzen
],
```

Gezeichnete Übersichtskarten-Verbindungen zählen nicht als besuchte Strecke. Ungültige Koordinaten werden nicht indexiert; ein ungültiger Trackabschnitt wird nicht durch eine erfundene Verbindung überbrückt. Doppelte Geometrien werden zusammengeführt. Fehlende Geodaten setzen `geo` ausdrücklich auf `null`, damit ein `$set`-Update alte Treffer entfernt. Solche Reisen bleiben ohne Geo-Filter weiterhin suchbar.

## SDK-Suchbedingungen

Die Reihenfolge für Koordinaten ist **Längengrad, Breitengrad**. Der Radius wird in **Metern** angegeben. Die neuen Bedingungen sind direkt in der SDK-Suche verfügbar; neue HTTP-/`pm-*`-Parameter und die Kartenoberfläche sind noch nicht Bestandteil dieser Erweiterung.

```php
use Pressmind\Search\Condition\MongoDB\GeoBounds;
use Pressmind\Search\Condition\MongoDB\GeoDistance;
use Pressmind\Search\Condition\MongoDB\PriceRange;
use Pressmind\Search\MongoDB;
use Pressmind\Search\Paginator;

// Standort oder irgendein Abschnitt eines GPX-Tracks innerhalb von 10 km:
$search = new MongoDB([
    new GeoDistance(13.405, 52.52, 10000),
    new PriceRange(100, 1500),
], ['price_total' => 'asc'], 'de', 0);
$search->setPaginator(Paginator::create(20, 1));
$result = $search->getResult();
// $result->documents enthalten geo_distance_meters.

// Sichtbarer Kartenausschnitt: West, Süd, Ost, Nord.
$viewport = new GeoBounds(13.2, 52.4, 13.6, 52.6);
// West > Ost bedeutet einen Ausschnitt über die Datumsgrenze:
$dateline = new GeoBounds(170, -20, -170, 20);
```

`GeoDistance` verwendet `$geoNear`: Auch die Mitte einer Strecke trifft, wenn beide Endpunkte außerhalb des Radius liegen. Die bestehende Sortierung bleibt bestehen, im Beispiel also nach Preis. `GeoBounds` verwendet `$geoIntersects`, sodass ein kreuzender Track nicht vollständig im Ausschnitt liegen muss. Rechtecke werden als sphärische GeoJSON-Polygone ausgewertet; deren Kanten folgen Großkreisen. Weltweite Ausschnitte und Polbereiche werden gesondert behandelt.

Preis-, Datums-, Kategorie- und Sichtbarkeitsfilter laufen in derselben Pipeline. Geo-Parameter sind Teil des bestehenden Suchcache-Schlüssels. Für Atlas-Volltext wird die Radiusbedingung über einen korrelierten Lookup angewandt, weil `$search` und `$geoNear` jeweils die erste Pipelineposition benötigen. Diese Kombination kann bei sehr großen Kandidatenmengen teurer sein; die normale MongoDB-/OpenSearch-Kombination startet direkt mit `$geoNear`.

MongoDB-Dokumentation: [Geo-Abfragen](https://www.mongodb.com/docs/manual/geospatial-queries/), [Entfernung zu Geometrien mit $geoNear](https://www.mongodb.com/docs/manual/reference/operator/aggregation/geonear/).

## Update eines bestehenden Systems

1. Vor der Aktivierung Datenbank sichern und statische SDK-Modelle mit dem vorhandenen Schema-Integrity-Workflow aktualisieren, im Travelshop etwa `php cli/integrity_check.php --static-only`. Die Erweiterungen betreffen `Map`, `Step` und `Geopoint`. Der dynamische MediaType-SchemaMigrator ersetzt diesen Schritt nicht. Weitere gemeldete Unterschiede separat prüfen.
2. Standard-Webcore-Version ist jetzt `v2-35`. Explizit konfigurierte `rest.client.api_endpoint`-Werte separat prüfen; sie haben Vorrang vor dem Standard.
3. Betroffene Medienobjekte vollständig erneut importieren. Ein bloßer MongoDB-Neuaufbau kann bislang nicht importierte GPX-Daten nicht nachträglich beschaffen. Ein eventuell hashbedingt übersprungener Import muss über den bestehenden projektspezifischen Reimportweg erneut ausgeführt werden.
4. MongoDB-Indizes mit `php cli/index_mongo.php indexes` ergänzen und vorhandene Suchdokumente mit `php cli/index_mongo.php all` neu aufbauen. Einzelobjekt-Updates erstellen den Geo-Index ebenfalls und erneuern das Feld `geo`.
5. Suchcaches über den vorhandenen Projektworkflow leeren. Eine normale Suche, Umkreis, Kartenausschnitt sowie ein Objekt mit entferntem Track prüfen.

Dies sind Ausrollschritte; die SDK-Änderung allein führt keinen Import oder Datenbankumbau auf Kundensystemen aus. Ein Rollback auf das vorherige SDK kann die zusätzlichen nullable Spalten/Tabelle zunächst bestehen lassen. Die zusätzlichen Geo-Felder im MongoDB-Index werden vom vorherigen SDK nicht verwendet; Suchcaches beim Rollback leeren.

Die aufgezeichneten API-Snapshots der ImportIntegration-Suite müssen für `v2-35` neu aufgezeichnet werden. Die neuen Geo-Tests verwenden synthetische Daten und benötigen keine Kundenzugänge. Sie prüfen beide Reiseverlaufsarten, JSON-Erhalt, MySQL-Schemaerweiterung, große Tracks, Reimport/Löschung sowie reale MongoDB-Abfragen. Der Atlas-Lookup wird lokal getestet; ein echter Atlas-`$search`-Dienst und Live-Webcore-Objekte sind damit nicht verifiziert.
