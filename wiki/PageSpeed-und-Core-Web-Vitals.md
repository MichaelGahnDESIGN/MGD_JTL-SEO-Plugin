# PageSpeed und Core Web Vitals

PageSpeed Insights kombiniert Lighthouse-Labortests mit realen Chrome-Nutzerdaten, sofern für die getestete URL ausreichend Felddaten vorhanden sind. Labormessungen können zwischen Läufen schwanken. Deshalb sollten Änderungen immer mit mehreren Läufen verglichen werden.

## Wichtige Kennzahlen

**LCP** misst, wann das größte relevante sichtbare Inhaltselement erscheint. Bei JTL-/OPC-Startseiten kann dies ein Kachel- oder Hero-Hintergrundbild sein.

**CLS** bewertet unerwartete Layoutverschiebungen. Fehlende Bildabmessungen, spät erscheinende Inhalte oder Fonts können eine Rolle spielen.

**INP** beschreibt die Reaktionsfähigkeit auf Benutzerinteraktionen. Große JavaScript-Aufgaben und umfangreiche Drittanbieter-Skripte sind typische Ursachen.

**FCP** beschreibt, wann erstmals sichtbarer Inhalt gerendert wird.

## Teststrategie

Teste immer Mobile und Desktop. Notiere LCP, FCP, CLS und die wichtigsten Lighthouse-Diagnosen. Aktiviere eine Optimierung nach der anderen. Bei JavaScript-Änderungen müssen Warenkorb und Checkout mitgetestet werden.

## Server und Frontend unterscheiden

Ein Shop kann serverseitig schnell antworten und trotzdem einen schlechten LCP haben. Umgekehrt kann perfektes Frontend-Markup einen langsamen TTFB nicht vollständig ausgleichen. Das Plugin konzentriert sich auf sichere Frontend-Maßnahmen und soll Serverprobleme künftig diagnostizieren, nicht verschleiern.
