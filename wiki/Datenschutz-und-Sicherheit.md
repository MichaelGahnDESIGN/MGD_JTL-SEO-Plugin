# Datenschutz und Sicherheit

Die Frontend-Optimierungen werden lokal im JTL-Shop ausgeführt.

## GitHub-Verbindung

Nur die optionale Updateprüfung baut eine externe Verbindung auf. Angefragt wird ausschließlich der öffentliche GitHub-Endpunkt für das neueste Release dieses Projekts. GitHub erhält technisch Server-IP, Zeitpunkt und User-Agent. Shop-, Kunden-, Bestell- und Formulardaten werden nicht übertragen.

## Externe Dienste

Das Plugin fügt keine Analytics-, Marketing- oder Tracking-Dienste hinzu. Preconnect-Ursprünge werden nur verwendet, wenn der Administrator sie selbst einträgt.

## Update-Sicherheit

Release-URLs werden auf das fest definierte Repository begrenzt. Die erste öffentliche Version nimmt keine automatische Dateiersetzung vor. Für einen späteren Ein-Klick-Updater sind SHA-256-Prüfung, Backup und Rollback Pflicht.
