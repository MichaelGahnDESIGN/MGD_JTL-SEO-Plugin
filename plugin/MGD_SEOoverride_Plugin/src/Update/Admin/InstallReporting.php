<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update\Admin;

// Brücke zum Install-Client (eigener Namensraum, darum Closures). Anmeldung läuft auch bei ausgeschalteter Update-Prüfung
final class InstallReporting
{
    /**
     * @param \Closure():void $ping Aktiv-Meldung (max 1x am Tag, macht der Install-Client selbst)
     * @param \Closure():bool $isEnabled
     * @param \Closure(bool):void $setEnabled
     * @param \Closure():string $status
     */
    public function __construct(
        public readonly \Closure $ping,
        public readonly \Closure $isEnabled,
        public readonly \Closure $setEnabled,
        public readonly \Closure $status,
        public readonly string $hint,
    ) {
    }
}
