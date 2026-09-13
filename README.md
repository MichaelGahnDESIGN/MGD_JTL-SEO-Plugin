# MGD JTL SEO & PageSpeed

**Technisches SEO, Core Web Vitals und Performance-Diagnose für JTL-Shop 5.**

> Aktuelle Entwicklung: **2.0.0**  
> Mindestversion: **JTL-Shop 5.7.0**  
> Lizenz: **GPL-3.0-or-later**  
> Hersteller: **Michael Gahn DESIGN**

[![Release](https://img.shields.io/github/v/release/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin?display_name=tag)](https://github.com/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin/releases)
[![Quality](https://github.com/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin/actions/workflows/quality.yml/badge.svg)](https://github.com/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin/actions/workflows/quality.yml)
[![JTL-Shop](https://img.shields.io/badge/JTL--Shop-%3E%3D%205.7.0-0b80c9)](https://www.jtl-software.de/jtl-shop)
[![License](https://img.shields.io/badge/License-GPL--3.0--or--later-blue)](LICENSE)

## Worum geht es?

MGD JTL SEO & PageSpeed ist ein quelloffenes JTL-Shop-5-Plugin für technische SEO-Prüfungen, Core-Web-Vitals-Analyse und vorsichtige Performance-Optimierungen. Es ist aus einem realen JTL-Shop-Performancefall entstanden und verfolgt bewusst einen konservativen Ansatz: erst messen, dann gezielt optimieren.

Das Plugin verändert keine JTL-Core-Dateien und überschreibt weder NOVA noch Child-Templates. Frontend-Optimierungen laufen über JTL-Hooks und lassen sich durch Deaktivieren des Plugins zurücknehmen. Diagnosemodule arbeiten primär lesend und verändern weder Artikel noch Kategorien, Canonicals, Redirects oder strukturierte Daten automatisch.

Ein hoher PageSpeed-Wert ist kein Selbstzweck. Ziel ist ein schneller, crawlbarer und stabiler Shop, ohne Warenkorb, Checkout, Varianten, Consent-Management oder Drittanbieterintegrationen leichtfertig zu gefährden.

## Highlights in Version 2.0

Version 2.0 erweitert das ursprüngliche PageSpeed-Hilfsplugin zu einer technischen SEO-Suite für JTL-Shop 5.

### Technical SEO Audit

Eine einzelne Shop-URL kann direkt aus dem JTL-Backend geprüft werden. Der Audit untersucht unter anderem:

* HTTP-Status und Weiterleitungen
* Time to First Byte
* Seitentitel und Anzahl der `title`-Elemente
* Meta-Description
* `robots`-Meta-Tag
* Canonical URL
* H1-Struktur
* `lang`-Attribut des HTML-Dokuments
* `robots.txt`
* JTL-Sitemap beziehungsweise Sitemap-Index
* syntaktische JSON-LD-Probleme

Die angezeigte SEO-Punktzahl ist bewusst eine lokale technische Heuristik und kein Google-Rankingwert.

### Structured Data Audit

Vorhandene strukturierte Daten werden analysiert, aber nicht ungefragt ersetzt oder dupliziert. Das Plugin prüft JSON-LD und erkennt zusätzlich Microdata-`itemtype`-Angaben.

Für typische E-Commerce-Entitäten werden grundlegende Plausibilitätschecks ausgeführt:

* `Product`
* `Offer`
* `BreadcrumbList`
* `Organization`

Bei Produkten werden beispielsweise `name`, `image` und `offers` geprüft. Bei Angeboten unter anderem Preis, Währung, Verfügbarkeit und URL. Ungültiges JSON-LD wird separat gemeldet.

### Bilder, LCP und CLS

Der Performance-Bereich untersucht normale Bilder und CSS-Hintergrundbilder. Er erkennt unter anderem:

* fehlende intrinsische `width`- und `height`-Angaben als mögliches CLS-Risiko
* leere oder fehlende Alt-Texte
* frühe Bilder mit `loading="lazy"`
* JPG-, JPEG- und PNG-Dateien als Kandidaten für WebP beziehungsweise AVIF
* große Bilddateien anhand echter HTTP-Metadaten
* Cache-Header lokaler Bildressourcen
* CSS-Hintergrundbilder
* einen wahrscheinlichen LCP-Kandidaten

Das Plugin ersetzt Bilddateien nicht automatisch. Bildqualität, Zuschnitt und passende responsive Größen bleiben eine redaktionelle beziehungsweise gestalterische Entscheidung.

### Server- und Hosting-Diagnose

Die Diagnose misst beziehungsweise erkennt:

* TTFB
* HTTP-Protokoll
* `Content-Encoding` wie Brotli oder Gzip
* HTML-`Cache-Control`
* Server-Header
* PHP-Version
* JTL-Shop-Version
* JTL-Cacheklasse beziehungsweise verfügbaren Cache-Hinweis
* OPcache-Status
* PHP-Memory-Limit
* Cachezeiten ausgewählter statischer Assets
* Drittanbieter-Origins

Das Plugin verändert keine `.htaccess`, keinen nginx-vHost, keine Redis-Konfiguration und keine Hostingparameter automatisch. Solche Einstellungen gehören in die Serveradministration und werden nur diagnostiziert.

### Google PageSpeed Insights im JTL-Backend

Mobile- und Desktop-Messungen können direkt im Plugin gestartet werden. Unterstützt werden:

* Lighthouse Performance Score
* Lighthouse SEO Score
* FCP
* LCP
* TBT
* CLS
* Speed Index
* Time to Interactive, soweit von Lighthouse geliefert
* zentrale PageSpeed-Opportunities
* reale Nutzerdaten aus der API, soweit Google sie für die URL noch liefert

Ein optionaler eigener Google PageSpeed API-Key kann in der JTL-Plugin-Konfiguration hinterlegt werden. Messungen werden begrenzt als Verlauf gespeichert, damit Veränderungen nach Optimierungen vergleichbar werden.

### Crawl, interne Verlinkung und Crawl-Tiefe

Der integrierte Same-Origin-Crawler bleibt vollständig auf der eigenen Shop-Domain und besitzt harte Limits. Er analysiert unter anderem:

* interne Links
* Crawl-Tiefe
* Inlinks
* HTTP-Status
* Redirectketten
* Broken Links
* Sitemap-URLs, die im begrenzten Linkcrawl nicht gefunden wurden
* Seiten ohne erkannte interne Inlinks
* wiederholte Seitentitel
* Canonical-Gruppen
* Drittanbieter-Origins

Typische Admin-, Plugin-, Checkout- und statische Dateipfade werden nicht gecrawlt.

Der Crawler ist als Shopdiagnose gedacht. Bei sehr großen Shops ersetzt er keinen spezialisierten Desktop- oder Enterprise-Crawler.

### Canonical- und JTL-Varianten-Audit

JTL-Shop kann Kindartikel beziehungsweise Varkombinationen SEO-technisch unterschiedlich behandeln. Das Plugin versucht deshalb nicht, eine pauschale Strategie zu erzwingen.

Der Audit:

* zählt vorhandene JTL-Kindartikel über `tartikel.kVaterArtikel`
* gruppiert gecrawlte URLs nach abweichendem Canonical-Ziel
* erkennt größere Canonical-Konsolidierungen
* weist auf indexierbare Seiten ohne Canonical hin
* zeigt Kombinationen aus `noindex` und Canonical zur manuellen Prüfung
* nennt das JTL-Funktionsattribut `varkombi_canonicalurl` beziehungsweise die zur Laufzeit vorhandene Konstante

So lässt sich erkennen, ob die aktuelle Variantenstrategie zum tatsächlichen Sortiment passt, ohne automatisiert Canonicals oder Indexierungsregeln umzuschreiben.

### Redirects und datensparsamer 404-Monitor

Der optionale 404-Monitor speichert ausschließlich:

* den URL-Pfad ohne Query-Parameter
* optional den Pfad eines internen Referrers
* Trefferzahl
* ersten und letzten Zeitpunkt

Nicht gespeichert werden IP-Adressen, User-Agents, Kundendaten oder Query-Parameter.

Zusätzlich zeigt der Bereich Weiterleitungsketten und Broken Links aus dem letzten begrenzten Shop-Crawl.

### JavaScript- und INP-Diagnose

Das Plugin setzt JavaScript nicht pauschal auf `defer`. Stattdessen werden zunächst Skripte analysiert:

* First-Party oder Drittanbieter
* `async`, `defer` oder Modul
* potenziell synchron/blockierend
* bekannte lokale Dateigröße
* Drittanbieter-Domains
* Cache-Header
* Risikobegriffe wie jQuery, JTL, Checkout, Consent oder Zahlungsanbieter

Nur vergleichsweise unauffällige lokale Skripte werden als mögliche Testkandidaten angezeigt. Auch diese müssen anschließend funktional geprüft werden.

### Verifizierte GitHub-Updates

Der Updatebereich prüft ausschließlich das öffentliche Repository dieses Projekts. Releases werden lokal zwischengespeichert und höchstens alle zwölf Stunden automatisch erneut abgefragt.

Die Updatepipeline kann folgende Artefakte validieren:

* offizielle Release-ZIP
* separate SHA256-Datei
* Release-Manifest
* GitHub-Asset-Digest, sofern GitHub einen liefert
* Plugin-ID
* Releaseversion
* JTL-Mindestversion
* ZIP-Pfade gegen Path Traversal
* symbolische Links im ZIP
* entpackte Gesamtgröße und Dateianzahl

Normale JTL-Versionsupdates mit Änderungen an `info.xml`, Einstellungen oder Datenbankmigrationen werden bewusst durch JTLs eigenen Plugin-Update-Lifecycle abgeschlossen. Das ist wichtig, damit JTL die neue Version, Einstellungen und Migrationen korrekt registriert.

Ein atomarer Self-Update-Pfad existiert nur für ausdrücklich im Release-Manifest als sicher markierte Datei-Hotfixes ohne JTL-Lifecycle-Änderung. Dabei wird die bestehende Installation vor dem Austausch umbenannt und als Backup erhalten. Bei einem Aktivierungsfehler wird ein Rollback versucht.

## Bereits seit Version 1.1 enthaltene Frontend-Optimierungen

Die ursprünglichen Optimierungen bleiben erhalten:

* automatische Erkennung eines wahrscheinlichen Startseiten-LCP-Hintergrundbilds
* `<link rel="preload" as="image">` für den LCP-Kandidaten
* `fetchpriority="high"` und `loading="eager"` bei einem erkannten echten LCP-`img`
* HTTP-`Link`-Preload, solange Header noch gesendet werden können
* sicheres Inlining sehr kleiner lokaler Stylesheets
* `decoding="async"` für Bilder ohne vorhandene Vorgabe
* optionales konservatives Lazy Loading für spätere Inhaltsbilder
* optionale `preconnect`-Hinweise
* explizit freizugebende JavaScript-Muster für `defer`

## Installation

1. Aktuelle Release-ZIP aus [GitHub Releases](https://github.com/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin/releases) herunterladen.
2. Im JTL-Backend **Plugins → Plugin-Manager → Upload** öffnen.
3. Die Plugin-ZIP unverändert hochladen.
4. Plugin installieren beziehungsweise aktualisieren.
5. Plugin aktivieren.
6. Beim Versionsupdate den von JTL angebotenen Update-Schritt vollständig durchführen.
7. Relevante JTL-Caches einmal leeren.
8. Shopfrontend, Suche, Varianten, Warenkorb, Login, Consent und Checkout testen.
9. Erst danach Performance-Optionen weiter verschärfen.

### Update von 1.1.x auf 2.0.0

Version 2.0 fügt Adminbereiche, Einstellungen und Datenbankmigrationen hinzu. Dieses Update muss daher über den normalen JTL-Plugin-Manager eingespielt werden. Die interne Plugin-ID bleibt `MGD_SEOoverride_Plugin`, damit JTL das Paket als Update derselben Erweiterung erkennt.

## Empfohlene Starteinstellungen

| Einstellung | Empfehlung |
|---|---|
| Plugin aktiv | Ja |
| LCP-Preload Startseite | Ja |
| LCP-Bild-URL | leer, automatische Erkennung |
| Kleine lokale CSS-Dateien inline | Ja |
| Maximale Inline-CSS-Größe | 4096 Bytes |
| Bild-Decoding optimieren | Ja |
| Zusätzliches Lazy Loading | zunächst Nein |
| Bilder vor Lazy Loading überspringen | 4 |
| Preconnect-Ursprünge | zunächst leer |
| JavaScript-Muster für defer | zunächst leer |
| GitHub-Updatehinweise | Ja |
| Verifizierte Datei-Hotfixes | Ja |
| PageSpeed API-Key | optional, für regelmäßige Nutzung empfohlen |
| Crawl-Limit | 50 Seiten |
| Crawl-Tiefe | 4 |
| 404-Monitor | Ja |

## Datenschutz

Die eigentlichen SEO- und Performance-Audits laufen im Shop beziehungsweise durch serverseitige HTTP-Anfragen an die eigene Shop-Domain.

Der 404-Monitor speichert keine IP-Adressen, keine User-Agents und keine Query-Parameter.

Externe Verbindungen entstehen nur bei bewusst verwendeten Funktionen:

* GitHub-Updateprüfung: öffentliche Release-Metadaten und Release-Artefakte
* PageSpeed Insights: die vom Administrator angeforderte Shop-URL wird an Googles PageSpeed API übergeben

Dabei erhalten die jeweiligen Dienste technisch die Server-IP, Zeitpunkt und HTTP-Metadaten. Shopkunden-, Bestell- und Formulardaten werden vom Plugin nicht an GitHub oder Google übertragen.

## Was das Plugin bewusst nicht automatisch verändert

Nicht automatisch überschrieben werden:

* Canonical-Strategien
* `noindex`-Entscheidungen
* Redirects
* strukturierte Produktdaten
* Produkt- oder Kategorietexte
* Alt-Texte
* Bilddateien
* zentrale JTL-/jQuery-Skripte
* Webserverkonfiguration
* Redis oder andere Objekt-Caches
* Consent- oder Trackingkonfiguration

Das Plugin liefert dafür Diagnosen und konkrete Ansatzpunkte. Die tatsächliche Änderung bleibt nachvollziehbar und kontrollierbar.

## Projektstruktur

```text
plugin/
└── MGD_SEOoverride_Plugin/
    ├── Bootstrap.php
    ├── info.xml
    ├── Migrations/
    ├── adminmenu/
    │   ├── dashboard.php
    │   ├── seo-audit.php
    │   ├── performance.php
    │   ├── crawler.php
    │   ├── redirects.php
    │   ├── updates.php
    │   └── impressum.php
    └── src/
        ├── Audit/
        ├── Http/
        ├── Monitor/
        ├── PageSpeed/
        ├── Storage/
        └── Update/

wiki/                  Dokumentation, auch als Quelle für das GitHub-Wiki
Dokumentation/         Architektur, Release- und Sicherheitsnotizen
scripts/               reproduzierbarer Release-Build
.github/workflows/     PHP-Lint, Paket- und Release-Validierung
```

## Entwicklung

Der Branch `main` soll nur nachvollziehbar geprüfte Stände enthalten. Umfangreiche Änderungen werden in Feature-Branches vorbereitet und über Pull Requests zusammengeführt.

Die CI prüft derzeit:

* PHP-Syntax unter mehreren PHP-Versionen
* gültige `info.xml`
* Plugin-ID und SemVer
* reproduzierbaren Installations-ZIP-Build
* ZIP-Struktur
* Release-Manifest

Weitere automatisierte Tests sollen mit wachsendem Funktionsumfang ergänzt werden.

## Dokumentation und Wiki

Die versionierten Wiki-Quellen befinden sich unter [`wiki/`](wiki/). Dadurch bleibt die Dokumentation gemeinsam mit dem Quellcode versioniert und reviewbar.

Wichtige Einstiege:

* [`wiki/Home.md`](wiki/Home.md)
* [`wiki/Installation-und-Updates.md`](wiki/Installation-und-Updates.md)
* [`wiki/PageSpeed-und-Core-Web-Vitals.md`](wiki/PageSpeed-und-Core-Web-Vitals.md)
* [`wiki/Technical-SEO-Audit.md`](wiki/Technical-SEO-Audit.md)
* [`wiki/Crawler-und-interne-Verlinkung.md`](wiki/Crawler-und-interne-Verlinkung.md)
* [`wiki/Canonical-und-Varianten.md`](wiki/Canonical-und-Varianten.md)
* [`wiki/Structured-Data.md`](wiki/Structured-Data.md)
* [`wiki/Redirects-und-404.md`](wiki/Redirects-und-404.md)
* [`wiki/Updater-und-Sicherheit.md`](wiki/Updater-und-Sicherheit.md)
* [`wiki/Fehlerbehebung.md`](wiki/Fehlerbehebung.md)

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

## Haftung und Erwartungen

Das Plugin garantiert weder einen bestimmten PageSpeed-Score noch bessere Suchmaschinenpositionen. Lighthouse-Werte können zwischen Messungen schwanken. SEO-Ergebnisse hängen unter anderem von Hosting, Template, Produkten, Inhalten, Suchintention, Konkurrenz, externen Diensten, Tracking, Indexierungsstatus und der tatsächlichen Nutzererfahrung ab.

Vor Änderungen an produktiven Shops sollten vollständige Backups vorhanden sein. Neue Releases zuerst auf einer Entwicklungs- oder Staging-Installation zu testen bleibt die sicherste Vorgehensweise.
