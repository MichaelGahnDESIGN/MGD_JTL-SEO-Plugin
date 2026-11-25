<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Kit\Platform\Jtl;

use Plugin\MGD_SEOoverride_Plugin\Kit\Ui\Colors;

// MGD Farbwähler im JTL-Backend: inline auf der Plugin-Seite (auch Tab Einstellungen, gleiche Seite)
// Meine Farben = Datei templates_c/mgd-kit-colors.json für alle MGD-Plugins, Zuletzt benutzt = im Browser
final class ColorPicker
{
    public const FILE = 'mgd-kit-colors.json';
    public const FIELD = 'mgd_color_save';
    // JTL-Einstellungen vom Typ colorpicker
    public const SELECTOR = 'input.colorpicker, input[data-color-picker]';

    /** gemeinsame Datei neben den Datenordnern der Plugins */
    public static function file(string $pluginDataDir): string
    {
        return dirname(rtrim($pluginDataDir, '/\\')) . '/' . self::FILE;
    }

    /** @return list<string> */
    public static function mine(string $file): array
    {
        if (!is_file($file) || is_link($file) || filesize($file) > 8192) {
            return [];
        }
        $j = json_decode((string) file_get_contents($file), true, 4);

        return Colors::clean(is_array($j) ? ($j['mine'] ?? []) : [], Colors::MAX_MINE);
    }

    /**
     * POST "Meine Farben speichern" (fetch vom Farbwähler). true = war gemeint (egal ob gespeichert)
     * @param array<string,mixed> $post
     */
    public static function handle(string $method, array $post, Guard $guard, string $file): bool
    {
        if (!isset($post[self::FIELD])) {
            return false;
        }
        if (!$guard->postValid($method, $post)) {
            return true;
        }
        $j = json_decode((string) $post[self::FIELD], true, 4);
        if (!is_array($j)) {
            return true;
        }
        $data = (string) json_encode(['mine' => Colors::clean($j['mine'] ?? [], Colors::MAX_MINE)]);
        if (@file_put_contents($file, $data, LOCK_EX) !== false) {
            @chmod($file, 0600);
        }

        return true;
    }

    /** Farbwähler + Speicher-Adapter als inline <style>/<script> */
    public static function html(string $file): string
    {
        $cfg = (string) json_encode(['mine' => self::mine($file), 'field' => self::FIELD, 'sel' => self::SELECTOR], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
        $adapter = '(function(c){var M=window.MGDColor;if(!M)return;M.addSelector(c.sel);'
            . 'var rec=function(){try{return JSON.parse(localStorage.getItem("mgd-color-recent")||"[]")}catch(e){return[]}};'
            . 'M.setStore({load:function(){return Promise.resolve({mine:c.mine,recent:rec()})},save:function(d){'
            . 'try{localStorage.setItem("mgd-color-recent",JSON.stringify(d.recent||[]))}catch(e){}'
            . 'if(JSON.stringify(d.mine)===JSON.stringify(c.mine))return Promise.resolve();c.mine=d.mine;'
            . 'var t=document.querySelector("input[name=jtl_token]");var f=new FormData();if(t)f.append("jtl_token",t.value);'
            . 'f.append(c.field,JSON.stringify({mine:d.mine}));return fetch(location.href,{method:"POST",credentials:"same-origin",body:f}).catch(function(){})}});'
            . 'M.init();})(' . $cfg . ');';

        return Colors::inline($adapter);
    }
}
