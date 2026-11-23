# Changelog

Alle relevanten Änderungen an MGD JTL SEO & PageSpeed werden hier dokumentiert.

## [1.6.0] - 2026-11-24

### Neu

* Brückenversion mit dem gemeinsamen Plugin-Baukasten: Der Tab **Updates** heißt jetzt **Übersicht** und zeigt Info, Updates, Weitere Plugins und Installation melden auf einer Seite
* Neuer Tab **Weitere Plugins** mit Karten anderer MGD-Plugins für JTL-Shop. Es wird nichts installiert, nur angesehen
* Beim Deinstallieren meldet sich das Plugin bei theme.michael-gahn.de ab
* Die bisherige Installations-ID und der Schalter bleiben erhalten (Daten werden einmal in den neuen Ordner übernommen)

### Geändert

* Der alte Update-Code liegt noch im Plugin, wird aber nicht mehr angemeldet. Er wird in der Endversion entfernt

## [1.5.0] - 2026-11-18

### Neu

* Installation melden (Free-Lizenz): Meldet sich höchstens einmal am Tag bei theme.michael-gahn.de (Plugin, Version, Shopsystem, Domain, zufällige ID). Abschaltbar in den Einstellungen.
* Die Meldung läuft beim Installieren, Aktivieren und Updaten und danach höchstens einmal am Tag im JTL-Backend, nie im Shop-Frontend. Sie läuft auch, wenn die Update-Prüfung aus ist
* Neuer Schalter **Installation bei Michael Gahn DESIGN melden** im Bereich **Updates**, ab Werk an. Beim Abschalten wird der Eintrag auf der Plattform gelöscht
* Das Plugin funktioniert immer voll weiter, auch ohne Plattform

## [1.4.0] - 2026-11-14

### Geändert

* Gemeinsame Versionsnummer: Alle Themes und Plugins von Michael Gahn DESIGN tragen jetzt die Version 1.4.0. Am Plugin selbst ändert sich dadurch nichts
* Update-Prüfung über theme.michael-gahn.de unverändert (Prüfsumme, kostenlose Variante)

## [1.1.4] - 2026-11-12

### Behoben

* Falsche Telefonnummer im Impressum korrigiert: im Backend-Bereich **Impressum**, in der README und im Wiki steht jetzt +49 (0) 151 59156639

## [1.1.3] - 2026-11-12

### Behoben

* Plugin ließ sich in JTL-Shop 5.7 nicht installieren: JTL erlaubt im Plugin-Namen kein „&“ (Fehlercode 6, ungültiger Name). Der Name in der `info.xml` lautet jetzt **MGD JTL SEO und PageSpeed**. Die interne Plugin-ID bleibt gleich
* Bereich **Updates**: Nach dem Download stand „geprüft (Signatur, …)“, obwohl im einfachen Modus nur die Prüfsumme geprüft wird. Die Meldung stimmt jetzt
* Keine Hinweise „veraltet“ mehr unter PHP 8.5 (`curl_close`)

### Geändert

* Update Client neu erzeugt (aktueller Stand aus MichaThemeV5-Pro)
* Release-ZIP enthält feste Dateirechte: Ordner 755, Dateien 644. Vorher konnten beim Entpacken per SSH Rechte 700 entstehen

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
