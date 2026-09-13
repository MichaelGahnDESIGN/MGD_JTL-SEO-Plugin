<?php declare(strict_types=1);
require __DIR__ . '/_common.php';

mgdSeoHeader('PageSpeed & Core Web Vitals', 'Was die Einstellungen tun, wann sie sinnvoll sind und wann Vorsicht geboten ist.');
echo '<table class="mgd-table"><thead><tr><th>Bereich</th><th>Was das Plugin tun kann</th><th>Empfehlung</th></tr></thead><tbody>';
$rows = [
['LCP', 'Startseitenbild früh erkennen und per Preload priorisieren. Bei echten img-Elementen zusätzlich fetchpriority=high und loading=eager setzen.', 'Aktivieren. Feste Bild-URL nur verwenden, wenn die automatische Erkennung das falsche Motiv wählt.'],
['Bilder', 'decoding=async ergänzen und optional spätere Inhaltsbilder lazy laden.', 'Decoding aktivieren. Zusätzliches Lazy Loading erst nach Messung einschalten. LCP-Bilder niemals lazy laden.'],
['Kleine CSS-Dateien', 'Sehr kleine lokale Stylesheets ohne url(...) oder @import direkt in das HTML übernehmen.', '4096 Bytes ist ein konservativer Startwert. Nach Änderungen Shop und Checkout prüfen.'],
['JavaScript', 'Nur explizit freigegebene Script-URL-Muster mit defer versehen.', 'Leer lassen, solange nicht klar ist, dass das Skript unabhängig geladen werden kann. jQuery nicht blind verzögern.'],
['Preconnect', 'Verbindung zu ausgewählten Drittanbieter-Ursprüngen früher aufbauen.', 'Nur für wenige, früh benötigte externe Hosts einsetzen. Zu viele Preconnects verschwenden Ressourcen.'],
['Server-Cache', 'Nur diagnostizieren und erklären.', 'Objekt-Cache in JTL passend konfigurieren. Redis ist bei geeigneter Serverumgebung für große Cache-Mengen interessant.'],
['Gzip/Brotli & Browser-TTL', 'Nicht zuverlässig aus PHP für statische Dateien erzwingen.', 'Auf Webserver- oder Hosting-Ebene konfigurieren.'],
];
foreach ($rows as $row) { echo '<tr><td><strong>' . mgdSeoEsc($row[0]) . '</strong></td><td>' . mgdSeoEsc($row[1]) . '</td><td>' . mgdSeoEsc($row[2]) . '</td></tr>'; }
echo '</tbody></table>';
echo '<div class="mgd-grid">';
echo mgdSeoCard('LCP zuerst angehen', '<p>Ein sehr hoher Largest Contentful Paint kann den mobilen Lighthouse-Score stark drücken. CSS-Hintergrundbilder werden später entdeckt als Bilder im initialen HTML. Genau hier setzt der LCP-Preload an.</p>', 'ok');
echo mgdSeoCard('Nicht nur auf den Score schauen', '<p>PageSpeed ist ein Labortest. Vergleiche zusätzlich reale Ladezeit, Serverantwort, Conversion-relevante Funktionen und mehrere Testläufe.</p>', 'info');
echo mgdSeoCard('JTL-eigene Funktionen nutzen', '<p>JTL/NOVA kann JavaScript und CSS selbst komprimieren. Das Plugin soll native JTL-Funktionen ergänzen und nicht doppelt nachbauen.</p>', 'info');
echo '</div>';
mgdSeoFooter();
