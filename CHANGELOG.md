# Changelog

Alle relevanten Änderungen an MGD JTL SEO & PageSpeed werden hier dokumentiert.

## [Unveröffentlicht]

### Hinzugefügt

* MGD Update Client im Backend-Bereich **Updates**: signierte Update-Prüfung (Ed25519), Download nur nach Bestätigung, Prüfung von SHA-256, Größe und ZIP-Inhalt
* Update-Prüfung nur nach Einschalten oder Klick, kein Hintergrund-Job
* Zustand liegt im Shop-Ordner `templates_c/mgd-seo-update/` mit Zugriffssperre
* Smoke-Test `tests/update-smoke.php` (ohne JTL lauffähig) und Schritt in der Qualitätsprüfung

### Geändert

* Die bisherige GitHub-Release-Prüfung wurde durch den Update Client ersetzt
* Die Einstellung „GitHub-Updatehinweise“ entfällt

### Hinweis

Der Update-Kanal ist noch nicht eingerichtet: Blocker B1 (Schlüsselzeremonie fehlt, `update/root-keys.json` ohne Schlüssel) und B2 (Update-Server kennt das Produkt `mgd-jtl-seo` noch nicht). Bis dahin zeigt der Bereich „Update-Kanal noch nicht eingerichtet“. Installiert wird weiterhin nur über die JTL-Pluginverwaltung.

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
