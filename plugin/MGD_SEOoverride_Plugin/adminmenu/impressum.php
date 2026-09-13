<?php declare(strict_types=1);
require __DIR__ . '/_common.php';

mgdSeoHeader('Impressum', 'Herstellerangaben für MGD JTL SEO & PageSpeed.');
echo '<div class="mgd-grid">';
echo mgdSeoCard('Michael Gahn DESIGN', '<p><strong>Michael Gahn</strong><br>Dr.-Theodor-Brugsch Str. 12<br>08529 Plauen<br>Sachsen<br>Deutschland</p><p>Tel.: +49 (0) 176 557 647 48<br>E-Mail: <a href="mailto:Anfrage@Michael-Gahn.de">Anfrage@Michael-Gahn.de</a></p>', 'info');
echo mgdSeoCard('Steuerangaben', '<p>Steuernummer: 223/222/02451<br>USt-ID: DE288143343</p><p><a class="mgd-btn secondary" href="https://Michael-Gahn.de/impressum/" target="_blank" rel="noopener noreferrer">Vollständiges Impressum</a></p>', 'info');
echo '</div>';
mgdSeoFooter();
