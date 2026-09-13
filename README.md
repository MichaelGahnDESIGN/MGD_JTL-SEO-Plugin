# MGD JTL SEO & PageSpeed

**Technisches SEO und Core-Web-Vitals-Optimierung für JTL-Shop 5.**

> Aktuelle Version: **1.1.0**  
> Mindestversion: **JTL-Shop 5.7.0**  
> Lizenz: **GPL-3.0-or-later**  
> Hersteller: **Michael Gahn DESIGN**

[![Release](https://img.shields.io/github/v/release/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin?display_name=tag)](https://github.com/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin/releases)
[![JTL-Shop](https://img.shields.io/badge/JTL--Shop-%3E%3D%205.7.0-0b80c9)](https://www.jtl-software.de/jtl-shop)
[![License](https://img.shields.io/badge/License-GPL--3.0--or--later-blue)](LICENSE)

## Worum geht es?

MGD JTL SEO & PageSpeed ist ein updatefestes JTL-Shop-5-Plugin für technische Performance- und SEO-Optimierungen. Es greift nicht in JTL-Core-Dateien, das NOVA-Template oder ein Child-Template ein. Alle Änderungen werden zur Laufzeit über offizielle Plugin-Hooks vorgenommen und lassen sich durch Deaktivieren des Plugins wieder abschalten.

Entstanden ist das Projekt aus einem realen Performance-Fall in einem produktiven JTL-Shop. Ziel ist ausdrücklich **kein künstliches Hochdrücken eines Lighthouse-Scores um jeden Preis**, sondern eine sichere Verbesserung von Ladeprioritäten, Core Web Vitals und technischer SEO, ohne Warenkorb, Checkout, Tracking oder andere Shopfunktionen leichtfertig zu gefährden.

## Aktueller Funktionsumfang

### PageSpeed und Core Web Vitals

* automatische Erkennung eines wahrscheinlichen LCP-Hintergrundbildes auf der Startseite
* Preload des LCP-Bildes mit hoher Ladepriorität
* optional feste LCP-Bild-URL für Sonderlayouts
* optionales Inlining sehr kleiner lokaler CSS-Dateien
* `decoding="async"` für Bilder, sofern noch kein Decoding-Hinweis vorhanden ist
* optionales, konservatives Lazy Loading für spätere Inhaltsbilder
* frei konfigurierbare Anzahl von Bildern, die vom zusätzlichen Lazy Loading ausgenommen werden
* optionale `preconnect`-Hinweise für bekannte Drittanbieter-Ursprünge
* experimentelles `defer` nur für ausdrücklich freigegebene JavaScript-URL-Muster

### Administrationsbereich

Das Plugin enthält eigene, verständlich beschriftete Bereiche für:

* **Dashboard** mit Status und sicheren Empfehlungen
* **PageSpeed** mit Erklärungen zu LCP, Bildern, CSS, JavaScript und Caching
* **Updates** mit GitHub-Release-Prüfung
* **Impressum** mit Herstellerinformationen
* **Einstellungen** für alle technischen Optimierungen

### GitHub-Updater

Das Plugin kann das öffentliche GitHub-Repository auf neue stabile Releases prüfen. Die Prüfung wird lokal zwischengespeichert und höchstens alle zwölf Stunden erneut durchgeführt. Es werden nur öffentliche Release-Metadaten abgefragt. Es werden keine Shop-, Kunden- oder Bestelldaten an GitHub übertragen.

Version 1.1.0 zeigt verfügbare Updates und den geprüften Release-Download an. Eine automatische Selbstinstallation ist bewusst noch nicht Bestandteil der ersten öffentlichen Version, da ein produktiver Shop keine ungeprüften Dateien selbst überschreiben sollte. Ein abgesicherter Ein-Klick-Updater mit Prüfsumme, Backup und Rollback ist für eine spätere Version vorgesehen.

## Warum diese Optimierungen?

Bei JTL-Shop 5 können insbesondere OPC-Hintergrundbilder für den Largest Contentful Paint relevant sein. Ein CSS-Hintergrundbild wird vom Browser später entdeckt als ein normales Bild im initialen HTML. Ein gezielter Preload kann die Ressource deutlich früher bekannt machen. Auch kleine renderblockierende Stylesheets, unnötig früh geladene Skripte und nicht priorisierte Bilder können die mobile Lighthouse-Bewertung verschlechtern.

Das Plugin arbeitet deshalb nach dem Prinzip: **messen, gezielt optimieren, erneut messen**.

## Installation

1. Lade die aktuelle ZIP aus dem Bereich **Releases** herunter.
2. Öffne im JTL-Shop-Backend **Plugins → Plugin-Manager → Upload**.
3. Lade die ZIP unverändert hoch.
4. Installiere und aktiviere das Plugin.
5. Öffne die Plugin-Einstellungen.
6. Verwende beim ersten Test die Standardwerte.
7. Leere danach einmal die relevanten JTL-Caches.
8. Öffne die Startseite einmal normal und teste anschließend erneut mit PageSpeed Insights.

### Empfohlene Starteinstellungen

| Einstellung | Empfehlung |
|---|---|
| Plugin aktiv | Ja |
| LCP-Preload Startseite | Ja |
| LCP-Bild-URL | leer, automatische Erkennung |
| Kleine lokale CSS-Dateien inline | Ja |
| Maximale Inline-CSS-Größe | 4096 Bytes |
| Bild-Decoding optimieren | Ja |
| Zusätzliches Lazy Loading | Nein, zunächst messen |
| Anzahl Bilder vor Lazy Loading | 4 |
| Preconnect-Ursprünge | leer, nur gezielt ergänzen |
| JavaScript-Muster für defer | leer, erst nach Funktionstest |
| GitHub-Updatehinweise | Ja |

## Was das Plugin bewusst nicht automatisch macht

Einige PageSpeed-Maßnahmen gehören nicht in ein Shop-Plugin. Dazu zählen unter anderem Brotli/Gzip-Konfiguration, HTTP/2 oder HTTP/3, Server-TTLs für statische Dateien, Reverse-Proxy-Caching, Redis-Konfiguration und Hostingwechsel. Das Plugin soll solche Punkte künftig diagnostizieren und erklären, aber nicht ungefragt die Serverkonfiguration verändern.

Auch jQuery oder andere zentrale JTL-Skripte werden nicht pauschal auf `defer` gesetzt. Eine solche Änderung kann abhängige Plugins, Varianten, Warenkorb, Checkout oder Consent-Management beschädigen.

## Datenschutz

Die Performance-Optimierungen laufen lokal im Shop. Nur wenn die Updateprüfung aktiviert ist, ruft der Server öffentliche Release-Metadaten von GitHub ab. Dabei erhält GitHub technisch die Server-IP, Zeitpunkt und den User-Agent des Requests. Das Plugin überträgt keine Kundendaten, Bestellungen, Formulareingaben oder Zugangsdaten.

## Projektstruktur

```text
plugin/
└── MGD_SEOoverride_Plugin/
    ├── Bootstrap.php
    ├── info.xml
    ├── src/
    │   ├── FrontendOptimizer.php
    │   └── Update/
    └── adminmenu/

wiki/                  ausführliche Anwender- und Entwicklerdokumentation
Dokumentation/         Architektur, Releases und technische Entscheidungen
scripts/               Release-Build
.github/workflows/     Qualitätsprüfung und Release-Automation
```

Die interne Plugin-ID bleibt zunächst `MGD_SEOoverride_Plugin`. Sie stammt aus der ersten produktiven Testversion und wird aus Kompatibilitätsgründen beibehalten. Der sichtbare Produktname lautet **MGD JTL SEO & PageSpeed**.

## Dokumentation

Der Einstieg befindet sich unter [`wiki/Home.md`](wiki/Home.md). Besonders relevant sind:

* [Installation und Updates](wiki/Installation-und-Updates.md)
* [PageSpeed und Core Web Vitals](wiki/PageSpeed-und-Core-Web-Vitals.md)
* [LCP-Optimierung](wiki/LCP-Optimierung.md)
* [Bilder und Lazy Loading](wiki/Bilder-und-Lazy-Loading.md)
* [CSS und JavaScript](wiki/CSS-und-JavaScript.md)
* [Datenschutz und Sicherheit](wiki/Datenschutz-und-Sicherheit.md)
* [Fehlerbehebung](wiki/Fehlerbehebung.md)
* [Für Entwickler](wiki/Fuer-Entwickler.md)

## Entwicklung und Beiträge

Fehlerberichte und nachvollziehbare Verbesserungsvorschläge sind willkommen. Vor Änderungen an produktiven Shops sollten Backups vorhanden sein. Pull Requests sollten möglichst klein, nachvollziehbar und ohne JTL-Core-Patches umgesetzt werden.

Siehe [`CONTRIBUTING.md`](CONTRIBUTING.md) und [`SECURITY.md`](SECURITY.md).

## Hersteller und Impressum

**Michael Gahn DESIGN**  
Michael Gahn  
Dr.-Theodor-Brugsch Str. 12  
08529 Plauen  
Sachsen, Deutschland

Telefon: +49 (0) 176 557 647 48  
E-Mail: Anfrage@Michael-Gahn.de  
USt-IdNr.: DE288143343

Vollständiges Impressum: https://Michael-Gahn.de/impressum/

## Lizenz

Copyright © 2026 Michael Gahn DESIGN.

Dieses Projekt wird unter der **GNU General Public License v3.0 oder später** veröffentlicht. Siehe [`LICENSE`](LICENSE).

## Haftungshinweis

Das Plugin kann technische Optimierungen unterstützen, garantiert aber weder bestimmte PageSpeed-Werte noch Suchmaschinen-Rankings. Ergebnisse hängen unter anderem von Hosting, Template, Plugins, Drittanbietern, Bildern, Inhalten, Tracking und der jeweiligen Messumgebung ab.
