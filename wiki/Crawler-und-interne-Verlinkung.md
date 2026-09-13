# Crawler und interne Verlinkung

Der integrierte Crawler ist ein bewusst begrenztes Diagnosewerkzeug für die eigene JTL-Shop-Domain. Er soll typische technische SEO-Probleme schnell sichtbar machen, ohne den Shop mit einem unkontrollierten Vollcrawl zu belasten.

## Sicherheitsgrenzen

Standardmäßig werden 50 Seiten bis Tiefe 4 geprüft. Im Plugin sind harte Grenzen von 250 Seiten und Tiefe 8 eingebaut.

Der Crawler folgt nur HTTP-/HTTPS-Links derselben Shop-Domain. Admin-, Plugin-, Checkout- und typische technische Pfade sowie statische Dateien werden nicht gecrawlt.

## Was wird pro Seite erfasst?

* HTTP-Status
* finale URL
* Redirectanzahl
* Crawl-Tiefe
* Anzahl erkannter interner Inlinks
* Seitentitel
* Meta-Description
* Canonical
* Robots-Meta
* H1-Anzahl
* TTFB

## Broken Links

URLs mit HTTP 4xx/5xx oder nicht erreichbare Ziele werden als Broken Links aufgeführt. Soweit möglich, zeigt das Plugin zusätzlich interne Seiten, von denen auf das fehlerhafte Ziel verlinkt wurde.

## Redirectketten

Jeder Redirect wird stufenweise verfolgt. Mehrere aufeinanderfolgende Weiterleitungen verursachen zusätzliche Roundtrips und erschweren Wartung. Eine typische Bereinigung ersetzt interne Links auf eine alte URL direkt durch das finale Ziel.

## Crawl-Tiefe

Seiten ab Tiefe 4 werden separat markiert. Eine hohe Tiefe ist nicht automatisch schlecht, kann aber bei wichtigen Kategorien oder Produkten auf eine schwache interne Verlinkung hinweisen.

## Sitemap-Orphans

Nach dem Crawl wird die JTL-Sitemap mit den intern entdeckten URLs verglichen. Eine Sitemap-URL, die im Linkcrawl nicht gefunden wurde, erscheint als Orphan-Kandidat.

Wichtig: Bei einem begrenzten Crawl sind Fehlalarme möglich. Wenn der Crawl an der Seiten- oder Tiefengrenze abgeschnitten wurde, bedeutet „nicht gefunden“ nicht zwingend „wirklich verwaist“.

## Doppelte Titles

Der Crawler gruppiert identische, nicht leere Seitentitel. Das hilft, generische oder versehentlich wiederverwendete Metadaten zu finden.

## Externe Origins

Externe Link- und Ressourcenhosts werden gesammelt. Das ist hilfreich für Performance- und Datenschutzanalysen, aber kein automatisches Fehlerkriterium.

## Wann ein externer Crawler sinnvoll bleibt

Für Shops mit zehntausenden URLs, JavaScript-Rendering, komplexen Facetten oder vollständiger Logfile-Analyse sind spezialisierte Werkzeuge wie Screaming Frog, Sitebulb oder professionelle Crawler weiterhin sinnvoll. Das Plugin konzentriert sich auf eine schnell verfügbare JTL-interne Diagnose.
