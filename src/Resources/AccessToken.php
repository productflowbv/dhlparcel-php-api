<?php

namespace Mvdnbrk\DhlParcel\Resources;

use DateTimeImmutable;

class AccessToken
{
    /** @var array */
    public $accounts;

    /** @var \DateTimeImmutable */
    public $expiresAt;

    /** @var array */
    public $roles;

    /** @var string */
    public $token;

    public function __construct(string $token)
    {
        $this->token = $token;

        $this->parseToken();
    }

    private function parseToken(): void
    {
        $payload = explode('.', $this->token)[1] ?? '';
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true) ?: [];

        $this->expiresAt = isset($claims['exp']) ? new DateTimeImmutable('@'.$claims['exp']) : new DateTimeImmutable;
        $this->accounts = $claims['accounts'] ?? null;
        $this->roles = $claims['roles'] ?? null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new DateTimeImmutable;
    }

    public function getAccountId(): string
    {
        return collect($this->accounts)->first();
    }

    public function setAccountId(?string $value): void
    {
        if (collect($this->accounts)->contains($value)) {
            $this->accounts = [$value];
        }
    }
}
