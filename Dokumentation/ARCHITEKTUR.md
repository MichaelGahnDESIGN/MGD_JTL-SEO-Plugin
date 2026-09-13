# Architektur

MGD JTL SEO & PageSpeed ist absichtlich klein und entkoppelt aufgebaut.

Der JTL-Bootstrap liest ausschließlich Plugin-Konfiguration und registriert im Frontend den Smarty-Outputfilter. `FrontendOptimizer` führt reversible DOM-Anpassungen aus. Es werden keine Core- oder Template-Dateien geschrieben.

Der Administrationsbereich besteht aus lesbaren PHP-Seiten innerhalb von `adminmenu/`. Einstellungen werden über JTLs nativen `Settingslink` gepflegt.

Die GitHub-Updateprüfung ist ein eigener Dienst. Sie validiert Repository, Tag, Release-Status und Download-URL. Der Cache liegt im temporären Systemverzeichnis und enthält nur öffentliche Release-Metadaten.

Die Architektur soll später um reine Diagnosemodule erweitert werden, bevor automatische SEO-Korrekturen eingeführt werden.
