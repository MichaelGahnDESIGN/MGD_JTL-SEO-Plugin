# CSS und JavaScript

## Kleine CSS-Dateien inline

Lighthouse kann kleine Stylesheets als renderblockierende Netzwerkrequests melden. Das Plugin darf sehr kleine lokale CSS-Dateien direkt in den HTML-Kopf übernehmen, wenn sie keine `url(...)`-Abhängigkeiten und kein `@import` enthalten. Dateien mit Integritäts- oder Cross-Origin-Attributen werden nicht verändert.

Der Standardgrenzwert beträgt 4096 Bytes.

## JTL/NOVA-Komprimierung

NOVA besitzt eine eigene Option zur Komprimierung von JavaScript- und CSS-Dateien. Diese sollte genutzt werden, bevor ein zusätzliches Plugin versucht, dieselbe Aufgabe doppelt zu lösen.

## JavaScript und `defer`

Das Plugin setzt niemals pauschal alle Skripte auf `defer`. Nur ausdrücklich hinterlegte URL-Muster werden berücksichtigt. Bereits asynchrone, deferred oder Module-Skripte bleiben unangetastet.

jQuery, Consent-Manager, Zahlungsanbieter und Checkout-Skripte sollten nicht ohne vollständigen Funktionstest verzögert werden.
