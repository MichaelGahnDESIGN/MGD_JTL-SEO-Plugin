<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Kit\Platform\Jtl;

use Plugin\MGD_SEOoverride_Plugin\Kit\Catalog\{CatalogItems, MediaCache, Recommendations};
use Plugin\MGD_SEOoverride_Plugin\Kit\Support\Text;

// Weitere Plugins im WordPress-Design (Klassen aus Catalog/assets/kit-catalog.css), ohne Smarty
// nur Admin, Bilder nur lokal (mediafiles), nix wird installiert
final class RecommendationsView
{
    public const CSS = __DIR__ . '/../../Catalog/assets/kit-catalog.css';
    public const JS = __DIR__ . '/../../Catalog/assets/kit-catalog.js';
    // lokale Bildadresse: nur Pfad auf dem eigenen Shop, nie fremder Host
    public const LOCAL_RE = '~^/(?!/)[A-Za-z0-9._/-]*/[a-f0-9]{32}\.webp$~D';

    private static int $n = 0;
    private static bool $assets = false;

    public static function render(?Recommendations $r, Guard $guard, ?MediaCache $media = null): string
    {
        try {
            if ($r === null || !$guard->canManage()) {
                return '';
            }
            $res = $r->load();
            $media?->syncItems(is_array($res['items']) ? $res['items'] : [], $res['source'] === 'fresh');
            // CSS/JS nur 1x pro Seite, JTL zeigt Übersicht + Tab zusammen
            $first = !self::$assets;
            self::$assets = true;

            return self::html($res, [
                'uid' => 'mgd-kit-cat-jtl-' . (++self::$n),
                'assets' => $first,
                'media' => $media !== null ? static fn (string $url): string => $media->url($media->local($url)) : null,
            ]);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * reines HTML, testbar ohne JTL
     * @param array{items?:mixed,source?:mixed,checkedAt?:mixed} $result von Recommendations::load()
     * @param array{uid?:string,assets?:bool,media?:?\Closure} $ctx media: Katalog-URL > lokale Adresse
     */
    public static function html(array $result, array $ctx = []): string
    {
        $media = ($ctx['media'] ?? null) instanceof \Closure ? $ctx['media'] : null;
        // nur eigene Pfade durchlassen
        $local = $media === null ? null : static function (string $url) use ($media): string {
            $l = (string) $media($url);

            return preg_match(self::LOCAL_RE, $l) === 1 && !str_contains($l, '..') ? $l : '';
        };
        $items = CatalogItems::prepare($result['items'] ?? null, $local);
        $source = is_string($result['source'] ?? null) ? $result['source'] : '';
        $uid = is_string($ctx['uid'] ?? null) && preg_match('/^[a-z0-9-]{1,60}$/D', $ctx['uid']) === 1 ? $ctx['uid'] : 'mgd-kit-cat-jtl';
        $checked = is_int($result['checkedAt'] ?? null) && $result['checkedAt'] > 0 ? gmdate('d.m.Y · H:i', $result['checkedAt']) : '';
        $assets = ($ctx['assets'] ?? true) !== false;

        return ($assets ? self::style() : '') . '<div class="mgd-kit-catalog" data-mgd-kit-catalog>'
            . self::hero($items)
            . '<div class="mgd-kit-catalog__collection-bar"><p><strong>' . count($items) . ' Erweiterungen</strong><span>Installation nur von Hand</span></p></div>'
            . ($source === 'stale' || $source === 'fallback' ? '<p class="mgd-kit-catalog__stale">Die Liste konnte gerade nicht aktualisiert werden. Du siehst den letzten bekannten Stand.</p>' : '')
            . self::browser($items, $uid)
            . self::footer($source, $checked)
            . '<dialog class="mgd-kit-catalog__dialog" aria-labelledby="' . $uid . '-dialog-title"><div class="mgd-kit-catalog__dialog-header"><p>MGD PLUGIN-KOLLEKTION</p><button type="button" data-close aria-label="Details schließen">×</button></div>'
            . '<h2 id="' . $uid . '-dialog-title">Plugin-Details</h2><div data-dialog-content></div></dialog></div>'
            . ($assets ? self::script() : '');
    }

    private static function hero(array $items): string
    {
        $art = '';
        foreach (array_slice($items, 0, 3) as $i => $it) {
            $art .= self::icon($it, 'mgd-kit-catalog__hero-icon mgd-kit-catalog__hero-icon--' . $i, 94);
        }

        return '<header class="mgd-kit-catalog__hero"><div class="mgd-kit-catalog__hero-copy"><p class="mgd-kit-catalog__eyebrow"><span class="mgd-kit-catalog__brand">MGD</span> JTL-SHOP · PLUGIN-KOLLEKTION</p>'
            . '<h3>Mehr aus deinem<br><em>Shop machen.</em></h3><p>Durchdachte Erweiterungen von Michael Gahn DESIGN.<br>Entdecke die passenden Werkzeuge für deinen JTL-Shop.</p></div>'
            . '<div class="mgd-kit-catalog__hero-art" aria-hidden="true"><span class="mgd-kit-catalog__orbit"></span>' . $art . '<span class="mgd-kit-catalog__art-caption">GEMACHT FÜR DEINEN JTL-SHOP</span></div></header>';
    }

    // Status-Filter gibts erst mit sicherer Erkennung (Feed-Feld fehlt noch)
    private static function browser(array $items, string $uid): string
    {
        $cats = '';
        foreach (array_values(array_unique(array_column($items, 'category'))) as $c) {
            $cats .= '<button type="button" data-category="' . Text::e($c) . '" aria-pressed="false">' . Text::e($c) . '</button>';
        }
        // Tag-Chips (mehrere wählbar), nur wenn der Feed Tags liefert
        $all = [];
        foreach ($items as $it) {
            foreach ((array) ($it['tags'] ?? []) as $t) {
                $all[mb_strtolower((string) $t)] = (string) $t;
            }
        }
        $chips = '';
        foreach ($all as $key => $label) {
            $chips .= '<button type="button" data-tag="' . Text::e($key) . '" aria-pressed="false">' . Text::e($label) . '</button>';
        }
        $tagBar = $chips === '' ? '' : '<div class="mgd-kit-catalog__tags" role="group" aria-label="Nach Tag filtern" data-filters hidden><span class="mgd-kit-catalog__tags-title">Tags</span>' . $chips
            . '<button type="button" class="mgd-kit-catalog__tags-reset" data-tag-reset hidden>Alle Tags zurücksetzen</button></div>';
        $cards = '';
        foreach ($items as $it) {
            $cards .= CatalogCard::html($it, $uid);
        }
        $n = count($items);

        return '<section class="mgd-kit-catalog__browser" aria-label="Plugins entdecken">'
            . '<div class="mgd-kit-catalog__toolbar" data-filters hidden>'
            . '<div class="mgd-kit-catalog__search"><label for="' . $uid . '-search"><span aria-hidden="true">⌕</span><span class="mgd-kit-catalog__sr">Plugins durchsuchen</span></label><input id="' . $uid . '-search" type="search" placeholder="Was möchtest du verbessern?" autocomplete="off" data-search-input></div>'
            . '<label class="mgd-kit-catalog__select">Preis<select data-price><option value="all">Alle Preise</option><option value="free">Kostenlos</option><option value="paid">Kostenpflichtig</option></select></label></div>'
            . '<div class="mgd-kit-catalog__browse-heading"><div class="mgd-kit-catalog__categories" role="group" aria-label="Kategorie filtern" data-filters hidden><button type="button" data-category="all" aria-pressed="true">Alle entdecken</button>' . $cats . '</div>'
            . '<p class="mgd-kit-catalog__count" aria-live="polite" aria-atomic="true" data-count>' . $n . ' ' . ($n === 1 ? 'Plugin' : 'Plugins') . '</p></div>'
            . $tagBar
            . '<div class="mgd-kit-catalog__grid">' . $cards . '</div>'
            . '<div class="mgd-kit-catalog__empty" data-empty' . ($n > 0 ? ' hidden' : '') . '><span aria-hidden="true">⌕</span>'
            . '<h4>' . ($n > 0 ? 'Noch nicht das Richtige dabei?' : 'Hier ist gerade nichts gelistet.') . '</h4>'
            . '<p>' . ($n > 0 ? 'Versuche einen anderen Suchbegriff oder setze die Filter zurück.' : 'Bitte schau später noch einmal vorbei.') . '</p>'
            . '<button class="mgd-kit-catalog__button" type="button" data-reset hidden>Filter zurücksetzen</button></div></section>';
    }

    private static function footer(string $source, string $checked): string
    {
        $central = $source === 'fresh' || $source === 'cache' || $source === 'stale';

        return '<footer class="mgd-kit-catalog__footer"><p><strong>Michael Gahn DESIGN</strong><span>Werkzeuge mit einem klaren Zweck.</span></p><p>'
            . ($central ? 'Katalog von theme.michael-gahn.de' : 'Ersatzkatalog aktiv · Zentraler Feed derzeit nicht erreichbar')
            . ($checked !== '' ? '<br><span>Geprüft: ' . Text::e($checked) . ' · Zwischenspeicherung bis zu 15 Minuten</span>' : '') . '</p></footer>';
    }

    /** Icon lokal oder mitgeliefertes Motiv als SVG (keine Datei, keine Adresse) */
    public static function icon(array $it, string $class, int $size): string
    {
        if (($it['icon'] ?? '') !== '') {
            return '<img class="' . Text::e($class) . '" src="' . Text::e((string) $it['icon']) . '" width="' . $size . '" height="' . $size . '" alt="" loading="lazy" decoding="async">';
        }

        return '<svg class="' . Text::e($class) . '" viewBox="0 0 128 128" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false"><rect width="128" height="128" rx="27" fill="#263b37"/>'
            . '<g fill="none" stroke="#e5c58e" stroke-width="5"><rect x="28" y="28" width="27" height="27" rx="5"/><rect x="28" y="73" width="27" height="27" rx="5"/><rect x="73" y="73" width="27" height="27" rx="5"/></g>'
            . '<path d="M86 27v28M72 41h28" stroke="#e6eee7" stroke-width="5" stroke-linecap="round"/></svg>';
    }

    private static function style(): string
    {
        $css = is_file(self::CSS) ? (string) file_get_contents(self::CSS) : '';

        // eigene Datei, trotzdem kein </style> durchlassen
        return $css === '' ? '' : '<style>' . str_replace('</', '', $css) . '</style>';
    }

    // JS inline (JTL hat kein enqueue), ohne JS gehen Details-Links auf die Plugin-Seite
    private static function script(): string
    {
        $js = is_file(self::JS) ? (string) file_get_contents(self::JS) : '';

        return $js === '' ? '' : '<script>' . str_replace('</', '<\/', $js) . '</script>';
    }
}
