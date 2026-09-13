# Für Entwickler

## Architektur

Die Frontend-Optimierung hängt am JTL-Hook `HOOK_SMARTY_OUTPUTFILTER`. Dadurch liegt das fertig gerenderte Dokument vor, ohne dass NOVA oder ein Child-Template überschrieben werden muss.

`FrontendOptimizer` bearbeitet nur den finalen DOM-Baum. Sicherheitskritische Optionen sind standardmäßig deaktiviert oder eng begrenzt.

Die Updateprüfung befindet sich getrennt unter `src/Update/` und wird nur im Administrationsbereich verwendet.

## Build

```bash
bash scripts/build-release.sh 1.1.0
```

Das Skript prüft die PHP-Syntax, erzeugt eine JTL-installierbare ZIP und eine SHA-256-Datei.

## Kompatibilität

Die Plugin-ID lautet aus historischen Kompatibilitätsgründen `MGD_SEOoverride_Plugin`. Eine Änderung der Plugin-ID würde JTL als anderes Plugin behandeln und bestehende Installationen nicht sauber aktualisieren.

## Pull Requests

Bitte keine JTL-Core-Patches einreichen. Neue Optimierungen benötigen eine nachvollziehbare Wirkung, konservative Defaults und einen Abschaltpfad.
