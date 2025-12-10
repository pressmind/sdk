# OpenSearch Query Fix für Teilwortsuche

## Problem
Die Volltextsuche findet Teilwörter nicht korrekt:
- ✅ "Tos" findet "Toskana" 
- ❌ "Tosk" findet nichts
- ❌ "Toska" findet nichts
- ✅ "Toskana" findet "Toskana"

Gleiches Problem bei "Adve" vs "Advent".

## Ursache
Die Query im SDK verwendet `operator: 'and'` mit `type: 'best_fields'`, was Teilwörter nicht richtig findet.

## Lösung: SDK-Anpassung

### Datei im SDK ändern:
**`vendor/pressmind/sdk/src/Pressmind/Search/OpenSearch.php`**

### Zeile 185-200: Aktuelle (problematische) Query

```php
'query' => [
    'bool' => [
        'must' => [
            [
                'multi_match' => [
                    'query' => $this->_search_term,
                    'fields' => $this->_getFields(),
                    'type' => 'best_fields',
                    'operator' => 'and',  // ❌ PROBLEM: Erfordert alle Wörter
                    'fuzziness' => 'AUTO'
                ]
            ]
        ],
        'filter' => []
    ]
]
```

### Lösung 1: Operator auf 'or' ändern (einfachste Lösung)

```php
'query' => [
    'bool' => [
        'should' => [  // ✅ Geändert von 'must' zu 'should'
            [
                'multi_match' => [
                    'query' => $this->_search_term,
                    'fields' => $this->_getFields(),
                    'type' => 'best_fields',
                    'operator' => 'or',  // ✅ Geändert von 'and' zu 'or'
                    'fuzziness' => 'AUTO'
                ]
            ]
        ],
        'minimum_should_match' => 1,  // ✅ Neu: Mindestens 1 Match erforderlich
        'filter' => []
    ]
]
```

### Lösung 2: Kombinierte Query mit phrase_prefix (bessere Lösung für Teilwörter)

```php
'query' => [
    'bool' => [
        'should' => [
            // Phrase prefix für Teilwörter (z.B. "Tosk" in "Toskana")
            [
                'multi_match' => [
                    'query' => $this->_search_term,
                    'fields' => $this->_getFields(),
                    'type' => 'phrase_prefix',
                    'operator' => 'or',
                    'boost' => 3.0
                ]
            ],
            // Standard Multi-Match für normale Suche
            [
                'multi_match' => [
                    'query' => $this->_search_term,
                    'fields' => $this->_getFields(),
                    'type' => 'best_fields',
                    'operator' => 'or',
                    'fuzziness' => 'AUTO',
                    'boost' => 2.0
                ]
            ],
            // Exact match boost
            [
                'multi_match' => [
                    'query' => $this->_search_term,
                    'fields' => $this->_getFields(),
                    'type' => 'phrase',
                    'boost' => 5.0
                ]
            ]
        ],
        'minimum_should_match' => 1,
        'filter' => []
    ]
]
```

## Empfohlene Änderung

**Empfehlung: Lösung 2** - Sie bietet die beste Balance zwischen Teilwortsuche und exakter Suche.

### Vollständiger Code für `fetchAllOpenSearchHits()` Methode:

```php
public function fetchAllOpenSearchHits()
{
    $allHits = [];
    $searchAfter = null;
    while (true) {
        $body = [
            '_source' => false,
            'size' => $this->_limit,
            'sort' => [
                ['_score' => 'desc'],
                ['id' => 'asc']
            ],
            'query' => [
                'bool' => [
                    'should' => [
                        // Phrase prefix für Teilwörter (z.B. "Tosk" in "Toskana")
                        [
                            'multi_match' => [
                                'query' => $this->_search_term,
                                'fields' => $this->_getFields(),
                                'type' => 'phrase_prefix',
                                'operator' => 'or',
                                'boost' => 3.0
                            ]
                        ],
                        // Standard Multi-Match für normale Suche
                        [
                            'multi_match' => [
                                'query' => $this->_search_term,
                                'fields' => $this->_getFields(),
                                'type' => 'best_fields',
                                'operator' => 'or',
                                'fuzziness' => 'AUTO',
                                'boost' => 2.0
                            ]
                        ],
                        // Exact match boost
                        [
                            'multi_match' => [
                                'query' => $this->_search_term,
                                'fields' => $this->_getFields(),
                                'type' => 'phrase',
                                'boost' => 5.0
                            ]
                        ]
                    ],
                    'minimum_should_match' => 1,
                    'filter' => []
                ]
            ]
        ];
        if ($searchAfter) {
            $body['search_after'] = $searchAfter;
        }
        $search_params = [
            'index' => $this->_index_name,
            'body' => $body
        ];
        if (!empty($_GET['debug']) || (defined('PM_SDK_DEBUG') && PM_SDK_DEBUG)) {
            echo '<pre>opensearch: ' . json_encode($search_params, JSON_PRETTY_PRINT) . '</pre>';
        }
        $response = $this->_client->search($search_params);
        $hits = $response['hits']['hits'];
        if (empty($hits)) {
            break;
        }
        $allHits = array_merge($allHits, $hits);
        $searchAfter = end($hits)['sort'] ?? null;
    }
    return $allHits;
}
```

## Auswirkungen auf die Config

**Keine Änderungen in `pm-config.php` erforderlich!**

Die Config bleibt unverändert:
- ✅ `search_opensearch.enabled = true`
- ✅ `search_opensearch.enabled_in_mongo_search = true`
- ✅ `search_opensearch.index` mit allen Feldern korrekt konfiguriert

Die Änderung betrifft nur die Query-Logik im SDK, nicht die Config-Struktur.

## Nach der Änderung

1. **SDK-Datei anpassen** (siehe oben)
2. **Index muss NICHT neu erstellt werden** - die Änderung betrifft nur die Query, nicht den Index
3. **Testen** mit:
   - "Tos" → sollte "Toskana" finden
   - "Tosk" → sollte "Toskana" finden ✅
   - "Toska" → sollte "Toskana" finden ✅
   - "Toskana" → sollte "Toskana" finden
   - "Adve" → sollte "Advent" finden ✅
   - "Advent" → sollte "Advent" finden

## Alternative: Config-basierte Lösung (wenn SDK erweitert wird)

Falls das SDK erweitert werden soll, um Query-Parameter über die Config zu steuern, könnte folgende Config-Option hinzugefügt werden:

```php
'search_opensearch' => [
    // ... bestehende Config ...
    'query' => [
        'operator' => 'or',  // 'and' oder 'or'
        'type' => 'best_fields',  // 'best_fields', 'phrase_prefix', etc.
        'use_phrase_prefix' => true,  // Teilwortsuche aktivieren
    ]
]
```

Dann müsste die SDK-Klasse diese Config-Werte auslesen und verwenden. Aktuell ist diese Option **nicht vorhanden**.
