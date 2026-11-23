<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Kit\Platform\Jtl;

use Plugin\MGD_SEOoverride_Plugin\Kit\License\LicenseClient;
use Plugin\MGD_SEOoverride_Plugin\Kit\Support\Text;

// Lizenz-Karte für JTL, gleiche Texte wie WordPress. Schlüssel nie zurück ins HTML (nur …abcd)
final class LicenseView
{
    public static function field(string $product): string
    {
        return 'mgd_kit_lic_' . str_replace('-', '_', $product);
    }

    public static function html(LicenseClient $l, string $product, Guard $guard): string
    {
        if (!$l->enabled() || !$guard->canManage()) {
            return '';
        }
        $f = Text::e(self::field($product));
        $out = '<form method="post" class="mgd-kit-license" autocomplete="off">' . $guard->tokenInput()
            . '<p><label for="' . $f . '_token">Lizenzschlüssel</label></p>'
            . '<p><input type="password" class="form-control" id="' . $f . '_token" name="' . $f . '_token" value="" autocomplete="off" spellcheck="false"></p>';
        if ($l->hasToken()) {
            $out .= '<p class="text-muted">Eingetragen ist ein Schlüssel, der auf ' . Text::e($l->tokenHint()) . ' endet.</p>';
        }
        $out .= '<p role="status">' . Text::e($l->status()) . '</p>'
            . '<p><button type="submit" class="btn btn-primary mgd-kit-btn" name="' . $f . '_do" value="save">Speichern</button>';
        if ($l->hasToken()) {
            $out .= ' <button type="submit" class="btn btn-default mgd-kit-btn" name="' . $f . '_do" value="remove">Entfernen</button>';
        }

        return $out . '</p><p class="text-muted">Zur Prüfung gehen nur der Lizenzschlüssel und die Domain deiner Seite an theme.michael-gahn.de.</p></form>';
    }

    /**
     * POST auswerten. null = Formular nicht gemeint
     * @param array<string,mixed> $post
     */
    public static function handle(LicenseClient $l, string $product, string $method, array $post, Guard $guard): ?string
    {
        $f = self::field($product);
        $do = $post[$f . '_do'] ?? '';
        if (!$l->enabled() || !in_array($do, ['save', 'remove'], true)) {
            return null;
        }
        if (!$guard->canManage() || !$guard->postValid($method, $post)) {
            return 'Das hat nicht geklappt. Bitte lade die Seite neu und versuche es noch einmal.';
        }
        if ($do === 'remove') {
            $l->forget();

            return 'Der Lizenzschlüssel ist entfernt.';
        }

        return $l->setToken(is_string($post[$f . '_token'] ?? null) ? $post[$f . '_token'] : '')
            ? 'Der Lizenzschlüssel ist gespeichert.'
            : 'Der Lizenzschlüssel sieht nicht richtig aus. Bitte kopiere ihn vollständig aus deinem Kundenkonto.';
    }
}
