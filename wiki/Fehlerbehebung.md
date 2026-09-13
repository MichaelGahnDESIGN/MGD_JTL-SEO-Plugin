# Fehlerbehebung

## PageSpeed wird nach Aktivierung schlechter

Ein einzelner Lighthouse-Lauf kann schwanken. Führe mehrere Tests durch. Prüfe insbesondere, ob das richtige LCP-Bild erkannt wurde und ob Cache gerade frisch geleert wurde.

## Falsches Bild wird priorisiert

Trage testweise die vollständige URL des tatsächlichen LCP-Bildes in `LCP-Bild-URL` ein. Wenn sich das Layout regelmäßig ändert, sollte die Ursache anschließend besser automatisch erkennbar gemacht werden.

## Darstellung ist nach CSS-Inlining verändert

Deaktiviere `Kleine lokale CSS-Dateien inline einbetten`, leere den Cache und teste erneut. Melde die betroffene Stylesheet-URL als Issue.

## JavaScript-Funktion funktioniert nicht mehr

Entferne alle Einträge aus `JavaScript-Muster für defer`. Diese Funktion ist experimentell und sollte nur für nachweislich unabhängige Skripte verwendet werden.

## GitHub-Updateprüfung schlägt fehl

Der Shopbetrieb wird dadurch nicht beeinflusst. Häufige Ursachen sind ausgehende Firewall-Regeln, fehlendes cURL, TLS-Probleme oder ein kurzfristiges GitHub-Limit.
