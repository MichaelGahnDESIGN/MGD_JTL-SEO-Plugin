<?php declare(strict_types=1);
require __DIR__ . '/_common.php';

$config = $oPlugin->getConfig();
$enabled = mgdSeoYes($config->getValue('mgd_enabled'));
$lcp = mgdSeoYes($config->getValue('mgd_lcp_preload'));
$inlineCss = mgdSeoYes($config->getValue('mgd_inline_small_css'));
$lazy = mgdSeoYes($config->getValue('mgd_lazy_images'));

mgdSeoHeader('MGD JTL SEO & PageSpeed', 'Sichere, nachvollziehbare Optimierungen für Core Web Vitals und technisches SEO in JTL-Shop 5.');
echo '<div class="mgd-grid">';
echo mgdSeoCard('Plugin-Status', '<span class="mgd-pill">Version ' . mgdSeoEsc($oPlugin->getMeta()->getVersion()) . '</span><p>' . ($enabled ? 'Die Frontend-Optimierungen sind aktiv.' : 'Die Frontend-Optimierungen sind derzeit deaktiviert.') . '</p>', $enabled ? 'ok' : 'warn');
echo mgdSeoCard('LCP-Optimierung', '<p>' . ($lcp ? 'Der Startseiten-LCP wird automatisch priorisiert.' : 'Der LCP-Preload ist deaktiviert.') . '</p><p class="mgd-small">Besonders sinnvoll bei OPC-Containern mit CSS-Hintergrundbildern.</p>', $lcp ? 'ok' : 'warn');
echo mgdSeoCard('Render-Blocking CSS', '<p>' . ($inlineCss ? 'Sehr kleine lokale Stylesheets dürfen sicher inline eingebettet werden.' : 'Inline-CSS-Optimierung ist deaktiviert.') . '</p>', $inlineCss ? 'ok' : 'info');
echo mgdSeoCard('Lazy Loading', '<p>' . ($lazy ? 'Zusätzliches Lazy Loading ist aktiviert. Nach Änderungen LCP und Produktdarstellung testen.' : 'Zusätzliches Lazy Loading bleibt konservativ deaktiviert.') . '</p>', $lazy ? 'warn' : 'ok');
echo '</div>';
echo '<div class="mgd-note"><strong>Empfohlener Ablauf:</strong> Einstellungen ändern, JTL-Cache leeren, Startseite einmal aufrufen und danach mindestens zwei bis drei PageSpeed-Mobile-Läufe vergleichen. Ein einzelner Lighthouse-Lauf kann deutlich schwanken.</div>';
echo '<div class="mgd-grid">';
echo mgdSeoCard('PageSpeed verstehen', '<p>Im Bereich <strong>PageSpeed</strong> erklärt das Plugin die wichtigsten Stellschrauben und welche Maßnahmen in JTL sinnvoll oder riskant sind.</p>', 'info');
echo mgdSeoCard('Updates', '<p>Der Updater prüft das öffentliche GitHub-Repository auf stabile Releases. Automatisches Überschreiben von PHP-Dateien findet nicht statt.</p>', 'info');
echo '</div>';
mgdSeoFooter();
