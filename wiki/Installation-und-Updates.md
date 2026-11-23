# Installation und Updates

## Installation

Die installierbare ZIP enthält als obersten Ordner `MGD_SEOoverride_Plugin`. Lade sie im JTL-Shop-Backend unter **Plugins → Plugin-Manager → Upload** hoch und installiere das Plugin anschließend.

Für produktive Shops wird vor jedem Plugin-Update ein Backup von Datenbank und Dateien empfohlen.

## Updateprüfung

Im Tab **Übersicht** schaltest du die Prüfung selbst ein. Ohne Einschalten oder Klick auf "Jetzt nach Updates suchen" sucht das Plugin nicht nach Updates.

Eine kleine Textdatei auf dem Update-Server nennt die neue Version und ihre Prüfsumme (SHA-256). Beim Download rechnet das Plugin die Prüfsumme nach. Passt sie nicht, wird das Paket abgelehnt.

## Installation melden

Meldet sich höchstens einmal am Tag bei theme.michael-gahn.de (Plugin, Version, Shopsystem, Domain, zufällige ID). Abschaltbar in den Einstellungen.

1. Öffne im Plugin den Punkt **Übersicht**.
2. Dort steht der Haken **Installation bei Michael Gahn DESIGN melden**. Er ist ab Werk gesetzt.
3. Zum Abschalten den Haken rausnehmen und auf **Speichern** klicken.

Das Plugin läuft danach genauso weiter.

## Installation eines Updates

Das Plugin installiert Updates nie selbst. Der Bereich Updates lädt das Paket herunter und prüft es. Du lädst die ZIP danach in der JTL-Pluginverwaltung hoch.

Ein späterer Ein-Klick-Updater soll erst eingeführt werden, wenn Prüfsumme, Backup, atomarer Dateiaustausch und Rollback vollständig umgesetzt sind.
