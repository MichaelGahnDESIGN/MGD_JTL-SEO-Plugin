<?php declare(strict_types=1);

use JTL\Plugin\PluginInterface;

if (!defined('PFAD_ROOT') || !isset($oPlugin) || !$oPlugin instanceof PluginInterface) {
    http_response_code(403);
    echo 'Dieser Bereich ist nur innerhalb der JTL-Shop-Administration verfügbar.';
    return;
}

if (!function_exists('mgdSeoEsc')) {
    function mgdSeoEsc(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    function mgdSeoYes(mixed $value): bool { return (string)$value === 'Y'; }
    function mgdSeoCard(string $title, string $body, string $tone = ''): string {
        return '<section class="mgd-card ' . mgdSeoEsc($tone) . '"><h3>' . mgdSeoEsc($title) . '</h3><div>' . $body . '</div></section>';
    }
    function mgdSeoHeader(string $title, string $lead = ''): void {
        echo '<style>
        .mgd-wrap{max-width:1180px;margin:0 auto 40px;color:#263238}.mgd-hero{padding:28px 30px;border-radius:14px;background:linear-gradient(135deg,#102a24,#246b47);color:#fff;margin-bottom:22px}.mgd-hero h2{margin:0 0 8px;font-size:28px}.mgd-hero p{margin:0;opacity:.9;max-width:850px}.mgd-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin:18px 0}.mgd-card{background:#fff;border:1px solid #dfe5e2;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.035)}.mgd-card h3{font-size:17px;margin:0 0 10px}.mgd-card.ok{border-left:5px solid #2e9d58}.mgd-card.warn{border-left:5px solid #e6a127}.mgd-card.info{border-left:5px solid #3584c7}.mgd-pill{display:inline-block;padding:4px 9px;border-radius:999px;background:#edf5f0;font-size:12px;font-weight:600}.mgd-table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden}.mgd-table th,.mgd-table td{padding:12px;border-bottom:1px solid #e7ece9;text-align:left;vertical-align:top}.mgd-table th{background:#f5f8f6}.mgd-btn{display:inline-block;padding:10px 14px;border-radius:8px;background:#267447;color:#fff!important;text-decoration:none!important;font-weight:600}.mgd-btn.secondary{background:#455a64}.mgd-note{padding:14px 16px;border-radius:9px;background:#f4f7f5;margin:14px 0}.mgd-small{font-size:12px;color:#66736d}
        </style><div class="mgd-wrap"><header class="mgd-hero"><h2>' . mgdSeoEsc($title) . '</h2><p>' . mgdSeoEsc($lead) . '</p></header>';
    }
    function mgdSeoFooter(): void { echo '<p class="mgd-small">MGD JTL SEO &amp; PageSpeed · Michael Gahn DESIGN · GPL-3.0-or-later</p></div>'; }
}
