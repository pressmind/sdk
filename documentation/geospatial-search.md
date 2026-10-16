# GPX-Import und räumliche Reisesuche (Webcore v2-35)

Das SDK importiert die Übersichtskarte und GPX-Tracks aus `Itinerary/get`. OpenSearch kann Standorte, Etappen und ganze Tracks räumlich durchsuchen und die passenden Reise-IDs an die bestehende MongoDB-Angebotssuche liefern. Preis, Termin, Belegung, Sichtbarkeit, Facetten und Pagination bleiben dort. Alternativ sind native MongoDB-Geofilter nutzbar.

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

Die Reihenfolge für Koordinaten ist **Längengrad, Breitengrad**. Der Radius wird in **Metern** angegeben. Die Bedingungen funktionieren über beide Suchbackends. HTTP-Parameter verwenden dagegen Kilometer. Die Kartenoberfläche ist ein separates Arbeitspaket.

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

Beim nativen MongoDB-Backend verwendet `GeoDistance` `$geoNear`: Auch die Mitte einer Strecke trifft, wenn beide Endpunkte außerhalb des Radius liegen. Die bestehende Sortierung bleibt bestehen, im Beispiel also nach Preis. `GeoBounds` verwendet `$geoIntersects`, sodass ein kreuzender Track nicht vollständig im Ausschnitt liegen muss. Rechtecke werden als sphärische GeoJSON-Polygone ausgewertet; deren Kanten folgen Großkreisen. Weltweite Ausschnitte und Polbereiche werden gesondert behandelt.

Preis-, Datums-, Kategorie- und Sichtbarkeitsfilter laufen in derselben Pipeline. Geo-Parameter und Backend-Auswahl sind Teil des Suchcache-Schlüssels. Geo-Suchen umgehen derzeit den MongoDB-Ergebniscache, damit beim Aktualisieren keine veraltete Kandidatenliste wiederverwendet wird. Für Atlas-Volltext wird die Radiusbedingung über einen korrelierten Lookup angewandt, weil `$search` und `$geoNear` jeweils die erste Pipelineposition benötigen. Diese Kombination kann bei sehr großen Kandidatenmengen teurer sein; die native MongoDB-Geosuche ohne Atlas startet direkt mit `$geoNear`.

MongoDB-Dokumentation: [Geo-Abfragen](https://www.mongodb.com/docs/manual/geospatial-queries/), [Entfernung zu Geometrien mit $geoNear](https://www.mongodb.com/docs/manual/reference/operator/aggregation/geonear/).

## OpenSearch aktivieren

Nach Mapping-Migration und vollständiger Neuindexierung:

```php
// Innerhalb data.search_opensearch:
'enabled' => true,
'enabled_in_mongo_search' => true,
'query' => [
    // Andere vorhandene query-Einstellungen beibehalten.
    'geo' => ['enabled' => true],
],
```

Ohne `query.geo.enabled` bleibt die räumliche Filterung in MongoDB. Es gibt keinen stillen Rückfall bei OpenSearch-Fehlern. Die Aktivierung verändert weder Indexnamen noch bestehende Volltext-Konfiguration. Direkte `Search\OpenSearch`-Aufrufer können `setGeoFilter(new Search\Geo\Filter(...))` verwenden.

| Parameter | Bedeutung | Beispiel |
|---|---|---|
| `pm-geo=lng,lat,radius_km` | Abstand zum nächsten Standort, Etappenpunkt oder GPX-Abschnitt | `pm-geo=13.405,52.52,10` |
| `pm-bounds=west,south,east,north` | Standort/Punkt oder kreuzender Track im sichtbaren Rechteck | `pm-bounds=13.2,52.4,13.6,52.6` |
| `pm-loc[feld]=lng,lat,radius_km` | Nur Punkte dieses konfigurierten Standortfelds; Radius optional, Standard 100 km | `pm-loc[hotelstandort]=-3.7,40.4,20` |

Die Parameter sind mit `pm-t` und den bisherigen Reisefiltern kombinierbar und funktionieren ohne Suchtext. Mehrere Geo-Bedingungen werden mit UND verbunden; sie dürfen unterschiedliche Geometrien derselben Reise treffen. Pro Anfrage ist ein `pm-loc`-Feld erlaubt. Negative Koordinaten und Null sind gültig. Ungültige Geo-Parameter lösen `InvalidArgumentException` aus, statt den räumlichen Filter unbemerkt wegzulassen. `Query::rebuild()` und Query-Strings behalten die validierten Parameter bei.

### Index und Geometriesemantik

- `geo`: `geo_shape`, gemeinsame GeometryCollection für alle Punkte/Tracks.
- `location_points.<feld>`: `geo_point`, ausschließlich die Punkte des jeweiligen Standortfelds.
- `geo_radius_cover`: `geo_shape`, konservative Hüllrechtecke für den Radius-Vorfilter. Jedes vollständige sphärische Segment liegt in einer Kugelkappe um seinen Anfangspunkt mit der Segmentlänge als Radius. Deren Koordinatenhülle wird mit den übrigen Segmenthüllen vereinigt. Deshalb werden auch gekrümmte Segmentmitten berücksichtigt.
- `id`: immer gesetzt und eindeutig, auch bei Objekten ohne konfigurierte Volltextfelder. Der Neuaufbau berücksichtigt zusätzlich Typen mit Location-Konfiguration und gespeicherte Itinerary-Karten.

Für Umkreise prüft das SDK nach der OpenSearch-Vorauswahl den kürzesten Abstand zu jedem Punkt und jedem vollständigen Großkreisabschnitt. Berechnung auf einer Kugel mit mittlerem Erdradius **6.371.008,8 m**, ohne Höhenunterschiede. Das ist eine sphärische Entfernung, keine ellipsoidische Vermessung. Die endgültige Prüfung verwendet nur 0,000001 m numerische Toleranz; die weiter gefassten Kandidatenhüllen verändern den fachlichen Radius nicht. Antipodale Segmentendpunkte haben keinen eindeutig kürzesten Großkreisabschnitt und werden bei der Indexierung mit einer Fehlermeldung abgelehnt; ein Zwischenpunkt ist erforderlich.

Kartenausschnitte verwenden OpenSearch `geo_shape`/`intersects` mit Koordinatenrechtecken. Datumsgrenzen werden in getrennte Rechtecke und Trackabschnitte zerlegt. OpenSearch verwendet die GeoJSON-Koordinatengeometrie; MongoDBs native Polygonabfragen verwenden sphärische Kanten. An langen Kanten kann die Randsemantik daher zwischen den Backends abweichen. Radiusprüfungen berücksichtigen immer die sphärischen Segmente.

Exakte Radiusprüfungen erfolgen vor MongoDB-Trefferzahlen, Facetten und Pagination. Auch zusätzliche TermResolver-Treffer, Autocomplete sowie Vektor-/Hybridsuche werden eingeschränkt. Vektorsuche bewertet räumliche Kandidaten mit `knn_score`, prüft den Radius und wendet erst danach das bestehende k-Limit an. Suchobjekte mit Geo-Filtern können wiederverwendet werden, ohne ihre ursprünglichen Bedingungen zu verlieren.

OpenSearch liest Kandidaten vollständig mit `search_after`; 10.000 ist die maximale Seitengröße, keine Gesamttreffergrenze. Große Tracks oder breite Suchgebiete benötigen entsprechend Speicher und Rechenzeit. Weit auseinanderliegende/datumsgrenzenüberschreitende Punkte können breite Hüllen erzeugen. Vor Kundenaktivierung mit repräsentativen Daten messen. MongoDBs BSON-/Pipelinegrenzen gelten weiterhin; Überschreitungen werden als Fehler gemeldet, nicht durch gekürzte Trefferlisten kaschiert.

Geo-Abfragen umgehen den MongoDB-Ergebniscache bewusst. Der optionale direkte OpenSearch-ID-Cache speichert Geo-Parameter, Sprache und Autocomplete-Modus auch für `updateCache()`. Beim Import gilt weiterhin der vorhandene Cache-Invalidierungsworkflow.

## Update eines bestehenden Systems

1. Vor der Aktivierung Datenbank sichern und statische SDK-Modelle mit dem vorhandenen Schema-Integrity-Workflow aktualisieren, im Travelshop etwa `php cli/integrity_check.php --static-only`. Die Erweiterungen betreffen `Map`, `Step` und `Geopoint`. Der dynamische MediaType-SchemaMigrator ersetzt diesen Schritt nicht. Weitere gemeldete Unterschiede separat prüfen.
2. Standard-Webcore-Version ist jetzt `v2-35`. Explizit konfigurierte `rest.client.api_endpoint`-Werte separat prüfen; sie haben Vorrang vor dem Standard.
3. Betroffene Medienobjekte vollständig erneut importieren. Ein bloßer MongoDB-Neuaufbau kann bislang nicht importierte GPX-Daten nicht nachträglich beschaffen. Ein eventuell hashbedingt übersprungener Import muss über den bestehenden projektspezifischen Reimportweg erneut ausgeführt werden.
4. MongoDB-Indizes mit `php cli/index_mongo.php indexes` ergänzen und vorhandene Suchdokumente mit `php cli/index_mongo.php all` neu aufbauen. Einzelobjekt-Updates erstellen den Geo-Index ebenfalls und erneuern das Feld `geo`.
5. OpenSearch-Mappings mit `php cli/index_opensearch.php create_index_templates` ergänzen. Vorhandene Indizes erhalten additive `putMapping`-Updates; inkompatible Feldtypen führen zum Abbruch. Der Befehl löscht keine alten Indizes. Bei Konflikten einen neuen Index mit geeignetem `index_prefix` aufbauen und den alten für den Rückbau behalten.
6. OpenSearch mit `php cli/index_opensearch.php all` vollständig neu aufbauen. Vor Aktivierung Dokumentzahlen und Stichproben kontrollieren, insbesondere Objekte ohne Textfelder, geänderte/gelöschte Tracks und alle Sprachen. Indexierungsfehler werden nach den konfigurierten Wiederholungen weitergegeben.
7. Erst jetzt `data.search_opensearch.query.geo.enabled` aktivieren. Suchcaches über den vorhandenen Projektworkflow leeren. Eine normale Suche, Umkreis, Kartenausschnitt sowie ein Objekt mit entferntem Track prüfen.

Dies sind Ausrollschritte; die SDK-Änderung allein führt keinen Import oder Datenbankumbau auf Kundensystemen aus. Für einen Rückbau der OpenSearch-Geosuche `query.geo.enabled` deaktivieren und den aktuellen MongoDB-Geoindex verwenden. Ein Rollback auf das vorherige SDK kann die zusätzlichen nullable Spalten/Tabelle zunächst bestehen lassen. Die zusätzlichen Geo-Felder im MongoDB-Index werden vom vorherigen SDK nicht verwendet; Suchcaches beim Rollback leeren.

Die aufgezeichneten API-Snapshots der ImportIntegration-Suite müssen für `v2-35` neu aufgezeichnet werden. Die neuen Geo-Tests verwenden synthetische Daten und benötigen keine Kundenzugänge. Sie prüfen beide Reiseverlaufsarten, JSON-Erhalt, MySQL-Schemaerweiterung, große Tracks, Reimport/Löschung sowie reale MongoDB-Abfragen. Der Atlas-Lookup wird lokal getestet; ein echter Atlas-`$search`-Dienst und Live-Webcore-Objekte sind damit nicht verifiziert.

## Verifikation

Automatisierte Tests decken den MySQL-Import samt Schema und großen Tracks, beide Indexer, Radius gegen Trackmitten, Standorte mit mehreren Punkten, kreuzende Tracks, Datumsgrenze, Pole, Nullradius, reine Geo-Suche, Textkombination, Reise-/Sichtbarkeitsfilter, Pagination, TermResolver, Vektor/Hybrid, Cache-Aktualisierung und über 10.000 OpenSearch-Kandidaten ab. OpenSearch-/MongoDB-Tests verwenden isolierte synthetische Reisedaten. Cache-Integration zusätzlich über `GEO_REDIS_PORT`. Produktive Datenmengen, die Kartenoberfläche und ein Kundenrollout sind damit nicht abgenommen.

Teststand 16.10.2026: Geo-Integration erfolgreich auf OpenSearch 2.11.0 und 2.19.1 mit MongoDB 8; Cache-Refresh mit Redis 7; Import/Schema mit MySQL 8. In der breiteren Suite sind drei bekannte, mit unveränderten Ausgangsklassen reproduzierte Fehler unabhängig von Geo offen: `MongoDBTest::testBuildQueryValidityStageUsesCurrentDate` (Minutenrundung) und die beiden `testDeleteAllIndexesThatNotMatchConfigHash*`-Integrationstests (Indexpräfixe). Diese Tests wurden nicht zur Beschönigung verändert.
