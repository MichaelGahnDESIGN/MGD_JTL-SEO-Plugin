<?php declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Plugin\PluginInterface;
use JTL\Shop;

if (!defined('PFAD_ROOT') || !isset($oPlugin) || !$oPlugin instanceof PluginInterface) {
    http_response_code(403);
    echo 'Dieser Bereich ist nur innerhalb der JTL-Shop-Administration verfügbar.';
    return;
}

if (!function_exists('mgdSeoEsc')) {
    function mgdSeoEsc(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    function mgdSeoYes(mixed $value): bool
    {
        return (string)$value === 'Y';
    }

    function mgdSeoShopUrl(): string
    {
        return rtrim(Shop::getURL(), '/') . '/';
    }

    function mgdSeoToken(): string
    {
        $token = $_SESSION['jtl_token'] ?? '';
        return is_string($token) ? $token : '';
    }

    function mgdSeoAssertPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new RuntimeException('Diese Aktion ist nur per POST zulässig.');
        }
        $token = $_POST['jtl_token'] ?? '';
        if (!is_string($token) || $token === '' || !Form::validateToken($token)) {
            throw new RuntimeException('Die JTL-Sicherheitsprüfung ist fehlgeschlagen.');
        }
    }

    function mgdSeoCard(string $title, string $body, string $tone = ''): string
    {
        return '<section class="mgd-card ' . mgdSeoEsc($tone) . '"><h3>' . mgdSeoEsc($title) . '</h3><div>' . $body . '</div></section>';
    }

    function mgdSeoPill(string $text, string $tone = ''): string
    {
        return '<span class="mgd-pill ' . mgdSeoEsc($tone) . '">' . mgdSeoEsc($text) . '</span>';
    }

    function mgdSeoScore(float|int|null $score): string
    {
        if ($score === null) {
            return '<span class="mgd-score neutral">–</span>';
        }
        $rounded = (int)round((float)$score);
        $tone = $rounded >= 90 ? 'good' : ($rounded >= 50 ? 'mid' : 'bad');
        return '<span class="mgd-score ' . $tone . '">' . $rounded . '</span>';
    }

    function mgdSeoAlert(string $message, string $tone = 'info'): string
    {
        return '<div class="mgd-alert ' . mgdSeoEsc($tone) . '">' . $message . '</div>';
    }

    function mgdSeoHeader(string $title, string $lead = ''): void
    {
        echo '<style>
        .mgd-wrap{max-width:1240px;margin:0 auto 40px;color:#263238}.mgd-hero{padding:28px 30px;border-radius:14px;background:linear-gradient(135deg,#102a24,#246b47);color:#fff;margin-bottom:22px}.mgd-hero h2{margin:0 0 8px;font-size:28px}.mgd-hero p{margin:0;opacity:.92;max-width:920px}.mgd-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin:18px 0}.mgd-card{background:#fff;border:1px solid #dfe5e2;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.035)}.mgd-card h3{font-size:17px;margin:0 0 10px}.mgd-card.ok{border-left:5px solid #2e9d58}.mgd-card.warn{border-left:5px solid #e6a127}.mgd-card.bad{border-left:5px solid #c54b4b}.mgd-card.info{border-left:5px solid #3584c7}.mgd-pill{display:inline-block;padding:4px 9px;border-radius:999px;background:#edf5f0;font-size:12px;font-weight:700}.mgd-pill.good{background:#e2f5e8;color:#176536}.mgd-pill.warn{background:#fff2d2;color:#805d00}.mgd-pill.bad{background:#fde7e7;color:#8b2525}.mgd-pill.info{background:#e4f0fb;color:#245b87}.mgd-table-wrap{overflow:auto;border-radius:10px;border:1px solid #e1e7e4;background:#fff}.mgd-table{width:100%;border-collapse:collapse;min-width:700px}.mgd-table th,.mgd-table td{padding:11px 12px;border-bottom:1px solid #e7ece9;text-align:left;vertical-align:top}.mgd-table th{background:#f5f8f6;position:sticky;top:0;z-index:1}.mgd-table tr:last-child td{border-bottom:0}.mgd-btn{display:inline-block;padding:10px 14px;border:0;border-radius:8px;background:#267447;color:#fff!important;text-decoration:none!important;font-weight:700;cursor:pointer}.mgd-btn.secondary{background:#455a64}.mgd-btn.danger{background:#a83d3d}.mgd-btn:disabled{opacity:.55;cursor:not-allowed}.mgd-note{padding:14px 16px;border-radius:9px;background:#f4f7f5;margin:14px 0}.mgd-alert{padding:13px 15px;border-radius:9px;margin:12px 0;border-left:4px solid #3584c7;background:#eef6fc}.mgd-alert.ok{border-color:#2e9d58;background:#edf8f0}.mgd-alert.warn{border-color:#e6a127;background:#fff8e6}.mgd-alert.bad{border-color:#c54b4b;background:#fff0f0}.mgd-small{font-size:12px;color:#66736d}.mgd-kpi{display:flex;align-items:center;gap:12px}.mgd-score{display:inline-flex;width:52px;height:52px;border-radius:50%;align-items:center;justify-content:center;font-size:18px;font-weight:800;background:#eef1f0}.mgd-score.good{background:#e0f4e6;color:#176536}.mgd-score.mid{background:#fff0c7;color:#745400}.mgd-score.bad{background:#f9dede;color:#8b2525}.mgd-score.neutral{color:#67736e}.mgd-section{margin-top:26px}.mgd-section>h3{margin:0 0 12px}.mgd-form-row{display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin:14px 0}.mgd-field{display:flex;flex-direction:column;gap:5px;min-width:190px;flex:1}.mgd-field label{font-weight:700}.mgd-field input,.mgd-field select,.mgd-field textarea{padding:9px 10px;border:1px solid #cbd5d0;border-radius:7px;background:#fff;max-width:100%}.mgd-muted{color:#6b7772}.mgd-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:#f4f6f5;border-radius:5px;padding:2px 5px;font-size:.92em;word-break:break-all}.mgd-progress{height:8px;background:#edf0ee;border-radius:999px;overflow:hidden}.mgd-progress>span{display:block;height:100%;background:#2e8155}.mgd-list{margin:8px 0;padding-left:20px}.mgd-list li{margin:5px 0}
        </style><div class="mgd-wrap"><header class="mgd-hero"><h2>' . mgdSeoEsc($title) . '</h2><p>' . mgdSeoEsc($lead) . '</p></header>';
    }

    function mgdSeoFooter(): void
    {
        echo '<p class="mgd-small">MGD JTL SEO &amp; PageSpeed · Michael Gahn DESIGN · GPL-3.0-or-later</p></div>';
        echo <<<'HTML'
<script>
(function () {
    if (window.__mgdSeoAdminFormRouting) {
        return;
    }
    window.__mgdSeoAdminFormRouting = true;

    function prepareForm(form) {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        var method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'post' || !form.closest('.mgd-wrap')) {
            return;
        }

        var pane = form.closest('[id^="plugin-tab-"]');
        if (!pane) {
            return;
        }
        var match = pane.id.match(/^plugin-tab-(\d+)$/);
        if (!match) {
            return;
        }

        var menuID = match[1];
        var menuField = form.querySelector('input[name="kPluginAdminMenu"]');
        if (!menuField) {
            menuField = document.createElement('input');
            menuField.type = 'hidden';
            menuField.name = 'kPluginAdminMenu';
            form.appendChild(menuField);
        }
        menuField.value = menuID;

        var baseURL = window.location.href.split('#')[0];
        form.setAttribute('action', baseURL + '#plugin-tab-' + menuID);
    }

    function bindAll() {
        document.querySelectorAll('.mgd-wrap form').forEach(prepareForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindAll, {once: true});
    } else {
        bindAll();
    }

    document.addEventListener('submit', function (event) {
        prepareForm(event.target);
    }, true);
})();
</script>
HTML;
    }

    function mgdSeoRenderIssues(array $issues): string
    {
        if ($issues === []) {
            return mgdSeoAlert('Keine Auffälligkeiten in diesem Prüfbereich erkannt.', 'ok');
        }
        $html = '<div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>Priorität</th><th>Prüfung</th><th>Hinweis</th></tr></thead><tbody>';
        foreach ($issues as $issue) {
            $severity = (string)($issue['severity'] ?? 'info');
            $label = match ($severity) {
                'error' => 'Fehler',
                'warning' => 'Warnung',
                'ok' => 'OK',
                default => 'Hinweis',
            };
            $tone = match ($severity) {
                'error' => 'bad',
                'warning' => 'warn',
                'ok' => 'good',
                default => 'info',
            };
            $html .= '<tr><td>' . mgdSeoPill($label, $tone) . '</td><td>' . mgdSeoEsc($issue['title'] ?? '') . '</td><td>' . mgdSeoEsc($issue['message'] ?? '') . '</td></tr>';
        }
        return $html . '</tbody></table></div>';
    }
}
