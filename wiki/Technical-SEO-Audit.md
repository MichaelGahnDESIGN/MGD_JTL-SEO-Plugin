# Technical SEO Audit

Der Technical SEO Audit prüft eine einzelne URL der eigenen Shop-Domain. Er verändert keine Inhalte.

## Geprüfte Signale

Der Audit erfasst HTTP-Status, Redirects, TTFB, `title`, Meta-Description, Robots-Meta, Canonical, H1-Struktur und das `lang`-Attribut des HTML-Dokuments.

Zusätzlich werden `robots.txt`, der JTL-Sitemap-Index beziehungsweise `sitemap.xml` und vorhandene strukturierte Daten geprüft.

## Bewertung

Die angezeigte Punktzahl ist eine transparente Plugin-Heuristik. Fehler gewichten stärker als Warnungen. Sie ist **kein Google-Rankingwert** und ersetzt weder Search Console noch eine redaktionelle SEO-Bewertung.

Typische Warnungen sind:

* fehlender oder mehrfacher Seitentitel
* fehlende Meta-Description
* fehlendes Canonical auf indexierbaren Seiten
* mehrere Canonicals
* fremde Canonical-Domain
* kein H1 oder mehrere H1
* fehlende Sprachangabe
* ungültiges JSON-LD
* nicht erreichbare robots.txt oder Sitemap

## Canonical richtig interpretieren

Ein Canonical auf eine andere URL ist nicht automatisch ein Fehler. Bei JTL-Kindartikeln kann eine Konsolidierung auf einen Vaterartikel absichtlich erfolgen. Deshalb meldet der Einzel-Audit eine abweichende Canonical-URL zunächst als Hinweis. Die shopweite Strategie wird im Bereich [[Canonical und Varianten|Canonical-und-Varianten]] bewertet.

## robots.txt

Das Plugin ruft `/robots.txt` serverseitig ab und prüft insbesondere:

* HTTP 200
* vorhandene Sitemap-Zeilen
* eine potenziell kritische `Disallow: /`-Regel

Die Datei wird niemals automatisch geändert.

## Sitemap

Zunächst wird `/sitemap_index.xml`, danach `/sitemap.xml` versucht. Sitemap-Indizes werden begrenzt rekursiv gelesen. Es werden ausschließlich URLs der eigenen Shop-Domain berücksichtigt.

Harte Grenzen verhindern, dass eine ungewöhnlich große oder fehlerhafte Sitemap den Adminbereich überlastet.

## Empfohlener Ablauf

Prüfe mindestens:

1. Startseite
2. eine wichtige Kategorie
3. einen normalen Artikel
4. einen Variantenartikel beziehungsweise Kindartikel
5. eine CMS-/OPC-Landingpage

Danach lohnt sich der begrenzte Shop-Crawl für Muster, die auf einzelnen Seiten nicht sichtbar werden.
