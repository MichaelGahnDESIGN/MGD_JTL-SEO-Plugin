<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Kit\Update\License;

// Free-Plugins: keine Lizenz
final class NullLicenseProvider implements LicenseProvider
{
    public function token(): ?string
    {
        return null;
    }
}
