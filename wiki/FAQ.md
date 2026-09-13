# FAQ

## Garantiert das Plugin PageSpeed 90 oder 100?

Nein. Ein seriöses Plugin kann keinen festen Lighthouse-Score garantieren. Hosting, Bilder, Tracking, externe Dienste, Template, Produktdaten und Messschwankungen beeinflussen das Ergebnis.

## Ersetzt das Plugin ein schnelles Hosting?

Nein. Es kann Frontend-Probleme reduzieren, aber keinen dauerhaft langsamen Server kompensieren.

## Sollte ich zusätzliches Lazy Loading sofort aktivieren?

Nein. Zuerst mit den Standardwerten messen. LCP-nahe Bilder dürfen nicht versehentlich lazy geladen werden.

## Kann ich jQuery mit defer eintragen?

Davon wird ohne umfassenden Funktionstest abgeraten. Viele JTL- und Drittanbieter-Skripte können davon abhängen.

## Funktioniert das Plugin mit Child-Templates?

Die Optimierungen arbeiten am final gerenderten HTML und sind nicht auf ein bestimmtes Child-Template angewiesen. Sonderlayouts können jedoch eine manuelle LCP-URL erfordern.
