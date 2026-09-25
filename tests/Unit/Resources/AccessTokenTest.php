<?php

namespace Mvdnbrk\DhlParcel\Tests\Unit\Resources;

use DateInterval;
use DateTimeImmutable;
use Mvdnbrk\DhlParcel\Resources\AccessToken;
use Mvdnbrk\DhlParcel\Tests\TestCase;

class AccessTokenTest extends TestCase
{
    protected function makeToken(DateTimeImmutable $expires, array $accounts = []): string
    {
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $encode(['typ' => 'JWT', 'alg' => 'none']).'.'.$encode(['exp' => $expires->getTimestamp(), 'accounts' => $accounts, 'roles' => []]).'.';
    }

    /** @test */
    public function create_a_new_access_token()
    {
        $expires = (new DateTimeImmutable('1970-01-01 00:00:00'))->add(new DateInterval('PT9S'));
        $accessToken = new AccessToken(
            $this->makeToken($expires)
        );

        $this->assertSame('eyJ0eXAiOiJKV1QiLCJhbGciOiJub25lIn0.eyJleHAiOjksImFjY291bnRzIjpbXSwicm9sZXMiOltdfQ.', $accessToken->token);
        $this->assertEquals('1970-01-01 00:00:09', $accessToken->expiresAt->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_can_determine_if_the_access_token_has_expired()
    {
        $expires = (new DateTimeImmutable())->sub(new DateInterval('PT1S'));
        $accessToken = new AccessToken(
            $this->makeToken($expires)
        );

        $this->assertTrue($accessToken->isExpired());

        $expires = (new DateTimeImmutable())->add(new DateInterval('PT9S'));
        $accessToken = new AccessToken(
            $this->makeToken($expires)
        );

        $this->assertFalse($accessToken->isExpired());
    }

    /** @test */
    public function it_can_retrieve_the_account_id_from_the_token()
    {
        $expires = new DateTimeImmutable();
        $accessToken = new AccessToken(
            $this->makeToken($expires, ['123456'])
        );

        $this->assertEquals('123456', $accessToken->getAccountId());
    }

    /** @test */
    public function it_can_set_the_account_id()
    {
        $expires = new DateTimeImmutable();
        $accessToken = new AccessToken(
            $this->makeToken($expires, ['1111', '2222'])
        );

        $accessToken->setAccountId('does-not-exist');

        $this->assertEquals('1111', $accessToken->getAccountId());

        $accessToken->setAccountId('2222');

        $this->assertEquals('2222', $accessToken->getAccountId());
    }
}
