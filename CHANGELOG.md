# Changelog

Alle relevanten Änderungen an MGD JTL SEO & PageSpeed werden hier dokumentiert.

## [1.1.2] - 2026-11-11

### Geändert

* README: Update-Bereich richtig beschrieben. Geprüft wird eine Prüfsumme (SHA-256), keine digitale Signatur
* README: doppelten Abschnitt „Lizenz" entfernt
* Wiki und Überschrift im Bereich **Updates**: „Signatur" durch „Prüfsumme" ersetzt

### Hinweis

Keine Änderung an der Funktion des Plugins. Die Angabe „signierte Update-Prüfung (Ed25519)" bei 1.1.1 bezieht sich auf die vorbereitete, noch nicht aktive Variante.

## [1.1.1] - 2026-11-10

### Hinzugefügt

* MGD Update Client im Backend-Bereich **Updates**: signierte Update-Prüfung (Ed25519), Download nur nach Bestätigung, Prüfung von SHA-256, Größe und ZIP-Inhalt
* Update-Prüfung nur nach Einschalten oder Klick, kein Hintergrund-Job
* Zustand liegt im Shop-Ordner `templates_c/mgd-seo-update/` mit Zugriffssperre
* Smoke-Test `tests/update-smoke.php` (ohne JTL lauffähig) und Schritt in der Qualitätsprüfung

### Geändert

* Die bisherige GitHub-Release-Prüfung wurde durch den Update Client ersetzt
* Die Einstellung „GitHub-Updatehinweise“ entfällt

### Hinweis

Der Update-Kanal arbeitet im einfachen Modus: Eine kleine Textdatei auf theme.michael-gahn.de (`plugin-updates/mgd-jtl-seo.json`) nennt Version und SHA-256-Prüfsumme, das ZIP kommt von GitHub, die Prüfsumme wird beim Download nachgerechnet. Das ist eine Prüfsumme, keine Signatur. Installiert wird weiterhin nur über die JTL-Pluginverwaltung.

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
