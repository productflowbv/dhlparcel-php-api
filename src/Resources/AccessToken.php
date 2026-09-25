<?php

namespace Mvdnbrk\DhlParcel\Resources;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use SodiumException;

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

    /**
     * Parses the token the same way lcobucci/jwt's unsecured parser did.
     *
     * @throws \InvalidArgumentException When the token structure or a date claim is invalid.
     * @throws \RuntimeException When a token part is not valid base64url or JSON.
     */
    private function parseToken(): void
    {
        $parts = explode('.', $this->token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('The JWT string must have two dots');
        }

        [$encodedHeaders, $encodedClaims] = $parts;

        $headers = $this->decodePart($encodedHeaders);

        if (! is_array($headers)) {
            throw new InvalidArgumentException('headers must be an array');
        }

        if (array_key_exists('enc', $headers)) {
            throw new InvalidArgumentException('Encryption is not supported yet');
        }

        $claims = $this->decodePart($encodedClaims);

        if (! is_array($claims)) {
            throw new InvalidArgumentException('claims must be an array');
        }

        foreach (['iat', 'nbf', 'exp'] as $claim) {
            if (array_key_exists($claim, $claims)) {
                $claims[$claim] = $this->convertDate($claims[$claim]);
            }
        }

        $this->expiresAt = ($claims['exp'] ?? null) ?: new DateTimeImmutable;
        $this->accounts = $claims['accounts'] ?? null;
        $this->roles = $claims['roles'] ?? null;
    }

    /**
     * @return mixed
     *
     * @throws \RuntimeException
     */
    private function decodePart(string $data)
    {
        try {
            return json_decode($this->base64UrlDecode($data), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Error while decoding from JSON', 0, $exception);
        }
    }

    /**
     * @throws \RuntimeException
     */
    private function base64UrlDecode(string $data): string
    {
        if (function_exists('sodium_base642bin')) {
            try {
                return sodium_base642bin($data, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING, '');
            } catch (SodiumException $exception) {
                throw new RuntimeException('Error while decoding from Base64Url, invalid base64 characters detected');
            }
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        if (! is_string($decoded)) {
            throw new RuntimeException('Error while decoding from Base64Url, invalid base64 characters detected');
        }

        return $decoded;
    }

    /**
     * @param  mixed  $timestamp
     *
     * @throws \InvalidArgumentException
     */
    private function convertDate($timestamp): DateTimeImmutable
    {
        if (! is_numeric($timestamp)) {
            throw new InvalidArgumentException('Value is not in the allowed date format: '.$timestamp);
        }

        $normalizedTimestamp = number_format((float) $timestamp, 6, '.', '');

        $date = DateTimeImmutable::createFromFormat('U.u', $normalizedTimestamp);

        if ($date === false) {
            throw new InvalidArgumentException('Value is not in the allowed date format: '.$normalizedTimestamp);
        }

        return $date;
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
