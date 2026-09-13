# Changelog

Alle relevanten Änderungen an MGD JTL SEO & PageSpeed werden hier dokumentiert.

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
* ausführliches Wiki und Projektdokumentation
* GitHub-Actions-Prüfung und reproduzierbarer Release-Build

### Kompatibilität

Die interne Plugin-ID `MGD_SEOoverride_Plugin` bleibt erhalten, damit die zuvor produktiv getestete Version 1.0.0 in JTL-Shop aktualisiert werden kann.

## [1.0.0] - 2026-09-13

Interne produktive Testversion, entstanden im Rahmen der Optimierung eines JTL-Shop-5-Projekts.
