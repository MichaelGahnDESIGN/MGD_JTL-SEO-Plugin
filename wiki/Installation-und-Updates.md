# Installation und Updates

## Installation

Die installierbare ZIP enthält als obersten Ordner `MGD_SEOoverride_Plugin`. Lade sie im JTL-Shop-Backend unter **Plugins → Plugin-Manager → Upload** hoch und installiere das Plugin anschließend.

Für produktive Shops wird vor jedem Plugin-Update ein Backup von Datenbank und Dateien empfohlen.

## Updateprüfung

Wenn `GitHub-Updatehinweise` aktiviert ist, prüft das Plugin öffentliche Release-Metadaten des Repositories. Positive wie negative Prüfergebnisse werden bis zu zwölf Stunden lokal zwischengespeichert. Dadurch entsteht nicht bei jedem Admin-Aufruf eine Anfrage an GitHub.

Das Plugin akzeptiert nur stabile Releases mit Tags im Format `vX.Y.Z` und URLs, die exakt zum fest hinterlegten Repository gehören.

## Installation eines Updates

Version 1.1.0 lädt und installiert Updates nicht selbst. Der Updatebereich führt zur offiziellen Release-ZIP. Diese wird anschließend über den JTL-Plugin-Manager eingespielt.

Ein späterer Ein-Klick-Updater soll erst eingeführt werden, wenn Prüfsumme, Backup, atomarer Dateiaustausch und Rollback vollständig umgesetzt sind.
