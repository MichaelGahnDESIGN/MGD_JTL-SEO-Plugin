# Roadmap: MGD JTL SEO & PageSpeed

Diese Roadmap priorisiert Funktionen nach Nutzen, technischer Sicherheit und JTL-Kompatibilität. Native JTL-Funktionen sollen nicht unnötig dupliziert werden.

## 1.2 – Technical SEO Audit

Priorität: hoch

* Live-Audit ausgewählter Shop-URLs auf Title, Meta Description, Canonical, Robots, H1 und Statuscode
* Warnungen bei fehlenden, mehrfachen oder auffälligen Meta-Angaben
* Sitemap-Index prüfen und offensichtliche Konflikte zwischen Sitemap und `noindex` melden
* robots.txt prüfen
* Canonical-Diagnose für Vater-/Kindartikel, ohne die Shopstrategie ungefragt zu verändern
* Prüfung auf fehlende Open-Graph-Basisdaten

Warum zuerst: JTL bietet bereits umfangreiche Meta- und Sitemap-Funktionen. Ein Plugin kann den größten Mehrwert als zentraler Diagnose-Layer liefern und vorhandene Konfiguration sichtbar machen.

## 1.3 – Bilder & CLS

Priorität: sehr hoch

* Audit für überdimensionierte Bilder
* Erkennung von PNG/JPEG-Dateien, bei denen WebP/AVIF sinnvoll wäre
* Bilder ohne `width`/`height` erkennen
* optional sichere intrinsische Abmessungen für lokale Bilder ergänzen
* Alt-Text-Audit
* LCP-Kandidaten pro Seitentyp analysieren
* Lazy-Loading-Konflikte erkennen

## 1.4 – Structured Data Audit

Priorität: hoch

* bestehendes JSON-LD analysieren
* Product, Offer, BreadcrumbList und Organization auf fehlende Pflicht-/Empfehlungsfelder prüfen
* doppelte Schema-Blöcke erkennen
* keine parallelen Product-Schemata erzeugen, wenn JTL oder ein anderes Plugin bereits gültiges Markup liefert

## 1.5 – Performance Diagnose

Priorität: hoch

* TTFB und Response-Header des eigenen Shops prüfen
* Content-Encoding wie Brotli/Gzip anzeigen
* Cache-Control für statische Ressourcen beurteilen
* HTTP/2-/HTTP/3-Hinweise
* Drittanbieter-Ursprünge und frühe Requests analysieren
* JTL/NOVA-Komprimierungs- und Cache-Checkliste im Dashboard
* Warnung bei zu vielen Preconnects oder Preloads

## 1.6 – PageSpeed Insights Integration

Priorität: mittel

* optionale Google-PageSpeed-Insights-API-Anbindung
* Mobile/Desktop-Berichte im Plugin
* Verlauf früherer Messungen
* Fokus auf LCP, CLS, INP, FCP, TBT und konkrete Opportunities
* API-Schlüssel niemals öffentlich oder im Repository speichern

## 1.7 – Redirects & Crawl

Priorität: mittel

* Redirect-Ketten und -Schleifen erkennen
* 404-Bericht
* begrenzter interner Crawl für Crawl-Tiefe und verwaiste Seiten
* interne Links auf nicht indexierbare Ziele erkennen

## 2.0 – Sicherer Ein-Klick-Updater und Auto-Fixes

Priorität: erst nach breiter Praxiserprobung

* SHA-256-Prüfung gegen Release-Artefakt
* Versions- und JTL-Kompatibilitätsprüfung
* Plugin-Backup vor Update
* atomarer Austausch der Dateien
* automatisches Rollback bei Fehler
* explizite Bestätigung vor Installation
* ausgewählte SEO-Korrekturen nur mit Vorschau und Undo

## Grundprinzipien

1. Diagnose vor automatischer Änderung.
2. Keine JTL-Core-Patches.
3. Keine doppelten Funktionen, wenn JTL sie bereits zuverlässig anbietet.
4. Jede Performance-Optimierung muss deaktivierbar sein.
5. Warenkorb und Checkout haben Vorrang vor einem Lighthouse-Score.
6. Keine externen Tracking- oder Marketingdienste im Plugin.
