<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update\License;

// Lizenz-Adapter. Liefert das Lizenz-Token nur für den Authorization-Header. Nie loggen, nie in URLs
interface LicenseProvider
{
    public function token(): ?string;
}
