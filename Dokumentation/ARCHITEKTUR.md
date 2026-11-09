# Architektur

MGD JTL SEO & PageSpeed ist absichtlich klein und entkoppelt aufgebaut.

Der JTL-Bootstrap liest ausschließlich Plugin-Konfiguration und registriert im Frontend den Smarty-Outputfilter. `FrontendOptimizer` führt reversible DOM-Anpassungen aus. Es werden keine Core- oder Template-Dateien geschrieben.

Der Administrationsbereich besteht aus lesbaren PHP-Seiten innerhalb von `adminmenu/`. Einstellungen werden über JTLs nativen `Settingslink` gepflegt.

Die Updateprüfung ist der generierte MGD Update Client (`src/Update/`, Namensraum `Plugin\MGD_SEOoverride_Plugin\Update`). Er prüft Signaturen, SHA-256 und ZIP-Inhalt. Die Verbindung zu JTL liegt in `src/UpdateBridge.php`, der Backend-Bereich in `adminmenu/updates.php`. Der Zustand liegt in `templates_c/mgd-seo-update/`. Der Client darf nicht von Hand geändert werden, er wird neu generiert.

Die Architektur soll später um reine Diagnosemodule erweitert werden, bevor automatische SEO-Korrekturen eingeführt werden.
