# LCP-Optimierung

## Warum CSS-Hintergrundbilder schwierig sind

Ein normales `<img src="…">` ist bereits beim Parsen des HTML sichtbar. Ein Hintergrundbild kann dagegen erst entdeckt werden, nachdem der Browser die relevante CSS- oder Style-Regel verarbeitet hat. Dadurch kann der Bilddownload später beginnen.

## Automatische Erkennung

Auf der Startseite sucht das Plugin zuerst nach OPC-Containern beziehungsweise Elementen mit inline gesetztem `background-image`. Wird ein plausibles Bild gefunden, ergänzt das Plugin einen `<link rel="preload" as="image" fetchpriority="high">` im Dokumentkopf und versucht zusätzlich einen HTTP-Link-Preload zu senden.

Falls die Seite statt eines Hintergrundbilds ein echtes Inhaltsbild verwendet, setzt das Plugin auf dem erkannten Bild `fetchpriority="high"` und `loading="eager"`.

## Feste LCP-Bild-URL

Das Feld sollte normalerweise leer bleiben. Es ist für Layouts gedacht, bei denen die automatische Erkennung ein falsches Bild priorisiert. Nach einer festen URL muss bei Layout- oder Bildwechsel geprüft werden, ob sie noch stimmt.

## Was nicht passieren darf

Ein LCP-Bild sollte nicht lazy geladen werden. Ebenso sollte man nicht mehrere große Bilder gleichzeitig mit hoher Priorität preloaden, weil sie dann um dieselbe Bandbreite konkurrieren.
