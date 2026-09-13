# Beiträge zum Projekt

Danke für dein Interesse an MGD JTL SEO & PageSpeed.

## Grundregeln

Änderungen sollen JTL-Shop updatefest erweitern. Patches an `includes/`, `src/`, `templates/NOVA/`, `admin/` oder anderen Core-Bereichen sind keine akzeptable Plugin-Lösung.

Performance-Optimierungen müssen begründet und messbar sein. Eine höhere Lighthouse-Punktzahl allein ist kein ausreichendes Qualitätsmerkmal, wenn dadurch Shopfunktionen, Barrierefreiheit, Datenschutz oder Wartbarkeit leiden.

## Pull Requests

Bitte beschreibe Ausgangslage, Änderung, erwarteten Effekt und Testschritte. Bei Performance-Änderungen sind Vorher-/Nachher-Werte hilfreich. JavaScript-Änderungen sollten Startseite, Kategorie, Artikeldetailseite, Warenkorb und Checkout berücksichtigen.

## Code-Stil

PHP-Code verwendet `declare(strict_types=1);`, nachvollziehbare Namen und möglichst kleine Verantwortungsbereiche. Externe Abhängigkeiten werden nur aufgenommen, wenn der Nutzen klar ist.
