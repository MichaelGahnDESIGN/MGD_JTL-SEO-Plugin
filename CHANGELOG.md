# Changelog

Alle relevanten Änderungen an MGD JTL SEO & PageSpeed werden hier dokumentiert.

## [2.0.0] - 2026-09-13

### Technical SEO

* neuer URL-basierter Technical SEO Audit im JTL-Backend
* Prüfung von HTTP-Status, Redirects, TTFB, Title, Meta-Description, Robots, Canonical, H1 und HTML-Sprache
* Prüfung von `robots.txt`
* sichere Same-Origin-Auswertung von `sitemap_index.xml` beziehungsweise `sitemap.xml`
* lokaler technischer SEO-Score als transparente Heuristik

### Structured Data

* JSON-LD-Syntaxprüfung
* Erkennung vorhandener Microdata-`itemtype`-Angaben
* Basis-Audit für `Product`, `Offer`, `BreadcrumbList` und `Organization`
* bewusste Audit-only-Strategie, damit JTL-/Template-Daten nicht automatisch dupliziert werden

### Bilder, LCP und CLS

* Bildinventar aus gerendertem HTML
* Erkennung fehlender `width`-/`height`-Attribute
* Alt-Text-Prüfung
* Prüfung frühen Lazy Loadings
* Erkennung klassischer JPG/JPEG/PNG-Formate als mögliche WebP-/AVIF-Kandidaten
* HEAD-Stichproben für Bildgröße und Cache-Header
* Erkennung von CSS-Hintergrundbildern
* heuristische LCP-Kandidatenermittlung

### Server und Hosting

* Messung von TTFB und Gesamtantwortzeit
* Anzeige des verwendeten HTTP-Protokolls
* Erkennung von Content-Encoding
* Cache-Control-Stichproben lokaler Assets
* Drittanbieter-Origin-Übersicht
* JTL-Cacheklasse beziehungsweise verfügbarer Cache-Hinweis
* PHP-Version, OPcache und Memory-Limit

### PageSpeed Insights

* Google PageSpeed Insights direkt im JTL-Backend
* Mobile- und Desktop-Läufe
* Performance- und SEO-Score
* FCP, LCP, TBT, CLS, Speed Index und TTI soweit geliefert
* wichtige Lighthouse-Opportunities
* Anzeige realer Nutzerdaten soweit die PageSpeed API diese liefert
* optionaler eigener Google PageSpeed API-Key
* begrenzte PageSpeed-Historie zur Verlaufsauswertung

### Crawler und interne Verlinkung

* begrenzter Same-Origin-BFS-Crawler mit konfigurierbarer Seitenzahl und Tiefe
* Crawl-Tiefe und Inlink-Zählung
* Broken Links
* Redirectketten
* doppelte Seitentitel
* Seiten ohne erkannte interne Inlinks
* Sitemap-URLs, die im begrenzten Crawl nicht gefunden wurden
* externe Origins
* harte Sicherheits- und Lastgrenzen

### Canonical und JTL-Varianten

* Anzahl von Kindartikeln über `tartikel.kVaterArtikel`
* Anzahl von Varianten-Elterngruppen
* Gruppierung gecrawlter Seiten nach abweichendem Canonical-Ziel
* Hinweise auf große Canonical-Konsolidierungen
* Hinweise auf indexierbare Seiten ohne Canonical
* Hinweise auf Kombinationen aus `noindex` und Canonical
* Anzeige des JTL-Funktionsattributs `varkombi_canonicalurl`

### Redirects und 404

* optionaler datensparsamer Live-404-Monitor
* Speicherung nur von Pfad, optional internem Referrer-Pfad, Trefferzahl und Zeitpunkten
* keine Speicherung von IP, User-Agent oder Query-Parametern
* Backend-Ansicht mit sicherer Löschfunktion
* Redirect- und Broken-Link-Auswertung aus letztem Crawl

### JavaScript und INP

* Inventar externer Skripte
* Erkennung von async/defer/module
* Kennzeichnung potenziell synchron geladener Skripte
* First-Party-/Third-Party-Auswertung
* lokale Größenstichproben
* Risikomuster für JTL, jQuery, Consent, Checkout und Zahlungsskripte
* ausschließlich Vorschläge für defer-Testkandidaten, keine unkontrollierte Automatik

### Updates und Sicherheit

* GitHub-Releaseprüfung erweitert um Release-Manifest
* SHA256-Prüfung vor jeder Updatefreigabe
* optionaler Abgleich mit GitHub Asset-Digest
* Plugin-ID-, Versions- und Mindest-Shopversionsprüfung
* ZIP-Path-Traversal-Schutz
* Symlink-Abweisung
* Begrenzung von ZIP-Dateianzahl und entpackter Größe
* sicherer JTL-Lifecycle-Gate für Versionen mit `info.xml`, Einstellungen oder Migrationen
* atomarer Self-Update-Pfad ausschließlich für explizit freigegebene Datei-Hotfixes ohne JTL-Lifecycle-Änderung
* Backup und Rollback-Versuch bei Aktivierungsfehlern

### Datenbank

* neue Migration für begrenzte Audit-/PageSpeed-Historie
* neue Migrationstabelle für datensparsamen 404-Monitor

### UI und Dokumentation

* erweitertes Dashboard
* neue Bereiche SEO Audit, Performance & Core Web Vitals, Crawl & Links sowie Redirects & 404
* vollständig überarbeitete README
* erweitertes Wiki und Release-Dokumentation
* CI-Matrix für PHP 8.1 bis 8.4
* reproduzierbarer Release-Build mit ZIP, SHA256 und JSON-Manifest

### Updatehinweis

Version 2.0.0 verändert `info.xml`, Einstellungen, Adminbereiche und Datenbankmigrationen. Das Update von 1.1.x auf 2.0.0 muss deshalb einmal über den nativen JTL-Plugin-Manager durchgeführt werden. Die interne Plugin-ID `MGD_SEOoverride_Plugin` bleibt aus Kompatibilitätsgründen unverändert.

## [1.1.0] - 2026-09-13

### Hinzugefügt

* erste öffentliche Release-Version des eigenständigen Projekts
* benutzerfreundliches Dashboard im JTL-Backend
* eigener PageSpeed-Hilfebereich
* GitHub-Release-Prüfung mit 12-Stunden-Cache
* direkter Link zum geprüften Release und Release-ZIP
* eigener Impressum-Tab
* LCP-Preload für Startseiten-Hintergrundbilder
* Unterstützung für `fetchpriority="high"` bei erkannten echten LCP-Bildern
* HTTP-Link-Preload, sofern Header noch nicht gesendet wurden
* sicheres Inlining sehr kleiner lokaler CSS-Dateien
* `decoding="async"` für Bilder
* optionales konservatives Lazy Loading
* optionale Preconnect-Hinweise
* explizit konfigurierbares `defer` für freigegebene JavaScript-Muster
* ausführliche Projektdokumentation
* GitHub-Actions-Prüfung und reproduzierbarer Release-Build

### Kompatibilität

Die interne Plugin-ID `MGD_SEOoverride_Plugin` bleibt erhalten, damit die zuvor produktiv getestete Version 1.0.0 in JTL-Shop aktualisiert werden kann.

## [1.0.0] - 2026-09-13

Interne produktive Testversion, entstanden im Rahmen der Optimierung eines JTL-Shop-5-Projekts.
