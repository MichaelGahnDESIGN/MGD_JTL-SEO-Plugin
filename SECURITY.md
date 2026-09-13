# Sicherheitsrichtlinie

## Unterstützte Versionen

Sicherheitskorrekturen werden für die jeweils aktuelle stabile Release-Linie bereitgestellt.

## Sicherheitsproblem melden

Bitte veröffentliche vermutete Sicherheitslücken nicht sofort als öffentliches Issue. Kontaktiere stattdessen Michael Gahn DESIGN über `Anfrage@Michael-Gahn.de` mit einer nachvollziehbaren Beschreibung, betroffener Version und reproduzierbaren Schritten.

## Sicherheitsprinzipien

Das Plugin verändert keine JTL-Core-Dateien, keine NOVA-Dateien und keine Child-Template-Dateien. Netzwerkzugriffe sind auf die öffentliche GitHub-Release-Prüfung beschränkt und können deaktiviert werden. Update-Metadaten werden streng auf erwartete GitHub-URLs und semantische Versionsnummern geprüft.

Version 1.1.0 überschreibt bei Updates bewusst keine Plugin-Dateien automatisch. Ein späterer Ein-Klick-Updater wird nur mit Prüfsummenprüfung, Backup, atomarem Austausch und Rollback-Funktion freigegeben.

Technische Optimierungen wie JavaScript-`defer` sind opt-in, weil sie in einem Shop funktionale Auswirkungen haben können.
