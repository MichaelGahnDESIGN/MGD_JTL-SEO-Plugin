<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update\Admin;

use Plugin\MGD_SEOoverride_Plugin\Update\Store\StateStore;
use Plugin\MGD_SEOoverride_Plugin\Update\Support\UpdateException;
use Plugin\MGD_SEOoverride_Plugin\Update\{UpdateClient, UpdateResult};

// Backend-Bereich "Updates" (einheitlich für alle MGD-JTL-Plugins). Schreibende Aktionen nur per POST mit
// JTL-CSRF-Token, Rechteprüfung und ausdrücklicher Bestätigung. Es wird nie installiert, nur geprüft und zur JTL-Pluginverwaltung geführt
final class UpdatesPage
{
    public const AUTO_CHECK_SECONDS = 43200;

    /** @var callable():bool */
    private $csrfValid;
    /** @var callable():bool */
    private $canManage;
    /** @var callable():int */
    private $clock;

    /**
     * @param callable():bool $csrfValid prüft das JTL-CSRF-Token der aktuellen Anfrage (z. B. Form::validateToken)
     * @param callable():bool $canManage Rechteprüfung des Backend-Benutzers
     * @param string $csrfField fertiges Hidden-Feld mit dem JTL-Token (z. B. Form::getTokenInput())
     */
    public function __construct(
        private readonly UpdateClient $client,
        private readonly StateStore $state,
        callable $csrfValid,
        callable $canManage,
        private readonly string $csrfField,
        private readonly string $pluginName,
        private readonly string $installedVersion,
        ?callable $clock = null,
    ) {
        $this->csrfValid = $csrfValid;
        $this->canManage = $canManage;
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * @param array<string,mixed> $post Inhalt von $_POST (leer bei GET)
     * @return array{html:string,deliver:?string}
     */
    public function handle(string $method, array $post): array
    {
        $notice = '';
        $deliver = null;
        if (!($this->canManage)()) {
            return ['html' => $this->wrap('<p class="alert alert-danger">Dafür fehlt die Berechtigung.</p>'), 'deliver' => null];
        }
        $action = $method === 'POST' && is_string($post['mgd_update_action'] ?? null) ? $post['mgd_update_action'] : '';
        if ($action !== '') {
            if (!($this->csrfValid)()) {
                return ['html' => $this->wrap('<p class="alert alert-danger">Sicherheits-Token ungültig. Bitte Seite neu laden.</p>'), 'deliver' => null];
            }
            [$notice, $deliver] = $this->runAction($action, $post);
        }
        $st = $this->state->load();
        $enabled = ($st['enabled'] ?? false) === true;
        $last = is_array($st['last_result'] ?? null) ? $st['last_result'] : null;
        if ($action === '' && $enabled && ((int) ($st['last_check'] ?? 0)) < ($this->clock)() - self::AUTO_CHECK_SECONDS) {
            $this->checkAndStore();
            $last = $this->state->load()['last_result'] ?? null;
        }

        return ['html' => $this->wrap($this->render($notice, $enabled, is_array($last) ? $last : null)), 'deliver' => $deliver];
    }

    /** @param array<string,mixed> $post @return array{0:string,1:?string} */
    private function runAction(string $action, array $post): array
    {
        if ($action === 'save_settings') {
            $st = $this->state->load();
            $this->state->save(['enabled' => ($post['checks_enabled'] ?? '') === '1'] + $st);

            return ['<p class="alert alert-success">Einstellung gespeichert.</p>', null];
        }
        if ($action === 'check_now') {
            // Klick = Zustimmung für genau diesen einen Abruf
            $r = $this->checkAndStore();

            return ['<p class="alert alert-info">' . $this->e($r->message) . '</p>', null];
        }
        if ($action === 'download' || $action === 'deliver') {
            if (($post['confirm'] ?? '') !== '1') {
                return ['<p class="alert alert-warning">Bitte die Bestätigung ankreuzen.</p>', null];
            }
            try {
                $r = $this->checkAndStore();
                $file = $this->client->downloadVerified($r);
            } catch (UpdateException $e) {
                return ['<p class="alert alert-danger">' . $this->e($e->getMessage()) . '</p>', null];
            }
            if ($action === 'deliver') {
                return ['', $file];
            }
            $this->state->save(['verified_file' => basename($file), 'verified_version' => (string) ($r->manifest['version'] ?? '')] + $this->state->load());

            return ['<p class="alert alert-success">Paket heruntergeladen und geprüft (Signatur, SHA-256, Inhalt). Es wurde noch nichts installiert.</p>', null];
        }

        return ['', null];
    }

    private function checkAndStore(): UpdateResult
    {
        $r = $this->client->check();
        $summary = ['status' => $r->status, 'message' => $r->message, 'version' => $r->manifest['version'] ?? null,
            'changelog' => $r->manifest['changelog_short'] ?? null, 'security' => $r->manifest['security'] ?? null];
        $this->state->save(['last_check' => ($this->clock)(), 'last_result' => $summary] + $this->state->load());

        return $r;
    }

    /** @param array<string,mixed>|null $last */
    private function render(string $notice, bool $enabled, ?array $last): string
    {
        $h = $notice;
        $h .= '<p>Installiert: <strong>' . $this->e($this->installedVersion) . '</strong></p>';
        $h .= '<form method="post">' . $this->csrfField . '<input type="hidden" name="mgd_update_action" value="save_settings">'
            . '<label><input type="checkbox" name="checks_enabled" value="1"' . ($enabled ? ' checked' : '') . '> Update-Prüfung einschalten</label>'
            . '<p class="small text-muted">Beim Prüfen sieht der Update-Server von Michael Gahn DESIGN die IP-Adresse dieses Servers. '
            . 'Es werden keine Besucher-, Kunden- oder Bestelldaten gesendet. Es wird nichts automatisch installiert.</p>'
            . '<button type="submit" class="btn btn-primary">Speichern</button></form><hr>';
        $h .= '<form method="post">' . $this->csrfField . '<input type="hidden" name="mgd_update_action" value="check_now">'
            . '<button type="submit" class="btn btn-default">Jetzt nach Updates suchen</button></form>';
        if ($last !== null) {
            $h .= '<p class="mt-3">Letztes Ergebnis: ' . $this->e((string) ($last['message'] ?? '')) . '</p>';
            if (in_array($last['status'] ?? '', ['update_available', 'security_update_only'], true)) {
                $h .= $this->renderUpdate($last);
            }
        }

        return $h;
    }

    /** @param array<string,mixed> $last */
    private function renderUpdate(array $last): string
    {
        $h = '<div class="alert alert-info"><strong>Version ' . $this->e((string) ($last['version'] ?? '')) . '</strong>'
            . (($last['security'] ?? false) === true ? ' (Sicherheitsupdate)' : '')
            . '<br>' . $this->e((string) ($last['changelog'] ?? '')) . '</div>';
        $h .= '<form method="post">' . $this->csrfField . '<input type="hidden" name="mgd_update_action" value="download">'
            . '<label><input type="checkbox" name="confirm" value="1"> Ja, Paket herunterladen und prüfen (es wird noch nichts installiert)</label><br>'
            . '<button type="submit" class="btn btn-primary">Paket herunterladen und prüfen</button></form>';
        $h .= '<form method="post" class="mt-2">' . $this->csrfField . '<input type="hidden" name="mgd_update_action" value="deliver">'
            . '<input type="hidden" name="confirm" value="1">'
            . '<button type="submit" class="btn btn-default">Geprüftes Paket als ZIP speichern</button></form>';
        $h .= '<ol class="mt-3"><li>Datensicherung des Shops machen.</li>'
            . '<li>Die ZIP-Datei in der JTL-Pluginverwaltung hochladen (Backend, Plugins, Pluginverwaltung).</li>'
            . '<li>JTL führt Update und Migrationen aus. Danach das Plugin prüfen.</li></ol>';

        return $h;
    }

    private function wrap(string $inner): string
    {
        return '<div class="mgd-updates"><h3>Updates: ' . $this->e($this->pluginName) . '</h3>' . $inner . '</div>';
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
