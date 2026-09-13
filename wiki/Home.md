# MGD JTL SEO & PageSpeed Wiki

Willkommen im Wiki von **MGD JTL SEO & PageSpeed** für JTL-Shop 5.

Das Plugin kombiniert vorsichtige Frontend-Optimierungen mit technischen Audits. Ziel ist nicht, wahllos jeden PageSpeed-Hinweis automatisch umzusetzen. Ein Onlineshop muss schnell sein, aber gleichzeitig Varianten, Suche, Warenkorb, Checkout, Consent, Tracking und Zahlungsarten zuverlässig ausführen.

## Schnellstart

1. Die aktuelle Release-ZIP im JTL-Plugin-Manager installieren beziehungsweise aktualisieren.
2. Beim Versionsupdate den JTL-Update-Schritt vollständig ausführen.
3. Zunächst die empfohlenen Standardwerte verwenden.
4. Einmal die relevanten JTL-Caches leeren.
5. **SEO Audit** für Startseite, Kategorie oder Artikel testen.
6. Unter **Performance & Core Web Vitals** eine lokale Diagnose ausführen.
7. Optional PageSpeed Mobile und Desktop messen.
8. Einen kleinen Crawl mit 25 bis 50 Seiten starten.
9. Erst auf Basis der Ergebnisse weitere Performance-Schalter aktivieren.

## Funktionsbereiche

### Technical SEO Audit

Prüft HTTP-Status, Redirects, Title, Description, Canonical, Robots, H1, Sprache, robots.txt, Sitemap und strukturierte Daten.

[[Technical SEO Audit|Technical-SEO-Audit]]

### Performance & Core Web Vitals

Analysiert LCP, Bilder, CLS-Risiken, Serverantwort, Caching, Drittanbieter und JavaScript. Optional kann Google PageSpeed Insights direkt aus dem JTL-Backend aufgerufen werden.

[[PageSpeed und Core Web Vitals|PageSpeed-und-Core-Web-Vitals]]

### Crawl & interne Verlinkung

Ein begrenzter Same-Origin-Crawler prüft Linkstruktur, Crawl-Tiefe, Broken Links, Redirectketten, doppelte Titles und Sitemap-Orphans.

[[Crawler und interne Verlinkung|Crawler-und-interne-Verlinkung]]

### Canonical & Varianten

Spezielle Hinweise für JTL-Kindartikel, Varkombinationen, Canonical-Gruppen und `noindex`-Strategien.

[[Canonical und Varianten|Canonical-und-Varianten]]

### Structured Data

Prüft vorhandene JSON-LD- und Microdata-Ausgaben, ohne JTLs vorhandene Markups automatisch zu duplizieren.

[[Structured Data|Structured-Data]]

### Redirects & 404

Datensparsamer Live-404-Monitor plus Weiterleitungs- und Broken-Link-Auswertung aus dem Crawl.

[[Redirects und 404|Redirects-und-404]]

### Update-Sicherheit

Release-Prüfung, SHA256, Manifest, ZIP-Sicherheitsprüfung und klare Trennung zwischen JTL-Lifecycle-Update und zulässigem Datei-Hotfix.

[[Updater und Sicherheit|Updater-und-Sicherheit]]

## Weitere Dokumentation

* [[Installation und Updates|Installation-und-Updates]]
* [[LCP-Optimierung|LCP-Optimierung]]
* [[Bilder und Lazy Loading|Bilder-und-Lazy-Loading]]
* [[CSS und JavaScript|CSS-und-JavaScript]]
* [[Server und Hosting|Server-und-Hosting]]
* [[Einstellungen|Einstellungen]]
* [[Datenschutz und Sicherheit|Datenschutz-und-Sicherheit]]
* [[Fehlerbehebung|Fehlerbehebung]]
* [[FAQ|FAQ]]
* [[Für Entwickler|Fuer-Entwickler]]
* [[SEO Roadmap|SEO-Roadmap]]
* [[Impressum|Impressum]]

## Leitprinzipien

**Diagnose vor Automatik.** Kritische SEO-Entscheidungen werden angezeigt und erklärt, nicht ungefragt umgeschrieben.

**JTL-Lifecycle respektieren.** Core-Dateien, NOVA und Child-Templates werden nicht durch das Plugin gepatcht. Versionsupdates mit Migrationen bleiben im Plugin-Manager.

**Datensparsamkeit.** Der 404-Monitor speichert keine IP-Adressen und keine Query-Parameter.

**Messwerte richtig einordnen.** Ein einzelner Lighthouse-Lauf kann schwanken. Mehrere Messungen und reale Felddaten sind aussagekräftiger als ein einzelner Score.

**Server bleibt Server.** Brotli, Gzip, Reverse Proxy, Redis und Webserver-TTLs werden diagnostiziert, aber nicht ungefragt verändert.
