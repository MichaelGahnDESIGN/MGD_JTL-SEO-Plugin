<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Kit\Platform\Jtl;

// Admin-Recht + CSRF für alle Kit-Formulare im JTL-Backend. Fehler = kein Zugriff (fail closed)
// geprüft gegen jtl-shop core master 5.8.3: Form::validateToken(), Form::getTokenInput(), AdminAccount::logged()/permission()
final class Guard
{
    public const PERMISSION = 'PLUGIN_ADMIN_VIEW';
    public const TOKEN_FIELD = 'jtl_token';

    /**
     * @param \Closure():bool $canManage angemeldeter Backend-Benutzer mit Plugin-Recht
     * @param \Closure(string):bool $tokenValid prüft das Token aus dem POST
     * @param \Closure():string $tokenInput fertiges Hidden-Feld
     */
    public function __construct(
        private readonly \Closure $canManage,
        private readonly \Closure $tokenValid,
        private readonly \Closure $tokenInput,
    ) {
    }

    /** echte JTL-Anbindung, ohne JTL = alles zu */
    public static function forShop(): self
    {
        return new self(
            static fn (): bool => self::jtlCanManage(),
            static fn (string $t): bool => self::jtlTokenValid($t),
            static fn (): string => self::jtlTokenInput(),
        );
    }

    public function canManage(): bool
    {
        try {
            return ($this->canManage)() === true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** nur POST, Token nur aus dem Formular (nie aus GET) */
    public function postValid(string $method, array $post): bool
    {
        $t = $post[self::TOKEN_FIELD] ?? null;
        if ($method !== 'POST' || !is_string($t) || $t === '' || strlen($t) > 200) {
            return false;
        }
        try {
            return ($this->tokenValid)($t) === true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function tokenInput(): string
    {
        try {
            return (string) ($this->tokenInput)();
        } catch (\Throwable) {
            return '';
        }
    }

    // logged() + Recht, permission() allein prüft die Anmeldung nicht
    private static function jtlCanManage(): bool
    {
        if (!class_exists('\JTL\Shop')) {
            return false;
        }
        $acc = \JTL\Shop::Container()->getAdminAccount();

        return $acc->logged() && $acc->permission(self::PERMISSION);
    }

    private static function jtlTokenValid(string $token): bool
    {
        return class_exists('\JTL\Helpers\Form') && \JTL\Helpers\Form::validateToken($token);
    }

    private static function jtlTokenInput(): string
    {
        return class_exists('\JTL\Helpers\Form') ? (string) \JTL\Helpers\Form::getTokenInput() : '';
    }
}
