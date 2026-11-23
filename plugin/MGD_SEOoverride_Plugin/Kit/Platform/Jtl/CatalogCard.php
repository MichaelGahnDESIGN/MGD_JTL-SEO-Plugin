<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Kit\Platform\Jtl;

use Plugin\MGD_SEOoverride_Plugin\Kit\Support\Text;

// eine Karte Weitere Plugins JTL + Inhalt fürn Details-Dialog (template, kein Skript)
// statt Installieren: Details + Ansehen (neuer Tab)
final class CatalogCard
{
    /** @param array<string,mixed> $it von CatalogItems::prepare() */
    public static function html(array $it, string $uid): string
    {
        $slug = (string) $it['slug'];
        $name = (string) $it['name'];
        $url = (string) $it['url'];
        $did = $uid . '-d-' . $slug;
        $features = array_slice((array) $it['features'], 0, 3);
        $search = $name . ' ' . $it['description'] . ' ' . $it['category'] . ' ' . implode(' ', $features);
        $accent = is_string($it['accent']) ? ' style="--mgd-kit-cat-accent:' . Text::e($it['accent']) . '"' : '';
        $li = '';
        foreach ($features as $f) {
            $li .= '<li>' . Text::e((string) $f) . '</li>';
        }
        $bg = '';
        if ($it['image'] !== '') {
            $alt = (string) $it['imageAlt'];
            $bg = '<img class="mgd-kit-catalog__card-background" src="' . Text::e((string) $it['image']) . '" width="1080" height="360" alt="' . Text::e($alt) . '"'
                . ($alt === '' ? ' aria-hidden="true"' : '') . ' loading="lazy" decoding="async">';
        }
        $free = $it['tier'] === 'free';

        return '<article class="mgd-kit-catalog__card" data-card' . $accent . ' data-category="' . Text::e((string) $it['category']) . '" data-state="' . Text::e((string) $it['state'])
            . '" data-tier="' . Text::e((string) $it['tier']) . '" data-search="' . Text::e($search) . '">'
            . '<div class="mgd-kit-catalog__card-art">' . $bg
            . '<span class="mgd-kit-catalog__category">' . Text::e((string) $it['category']) . '</span>'
            . RecommendationsView::icon($it, 'mgd-kit-catalog__card-icon', 78)
            . ($it['version'] !== '' ? '<span class="mgd-kit-catalog__version">v' . Text::e((string) $it['version']) . '</span>' : '')
            . '</div><div class="mgd-kit-catalog__card-body"><div class="mgd-kit-catalog__card-heading"><h4>' . Text::e($name) . '</h4>'
            . '<span class="mgd-kit-catalog__price">' . ($free ? 'Kostenlos' : 'Kostenpflichtig') . '</span></div>'
            . '<p class="mgd-kit-catalog__description">' . Text::e((string) $it['description']) . '</p>'
            . ($li !== '' ? '<ul class="mgd-kit-catalog__features">' . $li . '</ul>' : '')
            . self::status($it)
            . '<div class="mgd-kit-catalog__actions"><a class="mgd-kit-catalog__details" href="' . Text::e($url) . '" target="_blank" rel="noopener noreferrer" data-details="' . Text::e($did) . '" data-title="' . Text::e($name)
            . '" aria-label="' . Text::e('Details zu ' . $name) . '">Details <span aria-hidden="true">↗</span></a>'
            . '<a class="mgd-kit-catalog__button" href="' . Text::e($url) . '" target="_blank" rel="noopener noreferrer" aria-label="' . Text::e($name . ' ansehen (neuer Tab)') . '">Ansehen</a></div></div>'
            . '<template id="' . Text::e($did) . '"><p>' . Text::e((string) $it['description']) . '</p>' . ($li !== '' ? '<ul>' . $li . '</ul>' : '')
            . '<p>' . ($free ? 'Kostenlos erhältlich.' : 'Kostenpflichtige Erweiterung. Informationen und Konditionen findest du beim Anbieter.') . '</p>'
            . '<a class="mgd-kit-catalog__button" href="' . Text::e($url) . '" target="_blank" rel="noopener noreferrer">Mehr erfahren <span class="mgd-kit-catalog__sr">(neuer Tab)</span></a></template>'
            . '</article>';
    }

    // Aktiv / inaktiv nur wenn sicher erkannt, sonst "Für deinen Shop"
    private static function status(array $it): string
    {
        $label = $it['active'] === true ? 'Aktiv' : ($it['installed'] === true ? 'Installiert · inaktiv' : 'Für deinen Shop');

        return '<div class="mgd-kit-catalog__card-status"><span class="mgd-kit-catalog__status mgd-kit-catalog__status--' . Text::e((string) $it['state']) . '">' . $label . '</span></div>';
    }
}
