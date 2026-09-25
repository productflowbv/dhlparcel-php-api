<?php

namespace Mvdnbrk\DhlParcel\Tests\Unit\Resources;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Mvdnbrk\DhlParcel\Resources\AccessToken;
use Mvdnbrk\DhlParcel\Tests\TestCase;
use RuntimeException;

class AccessTokenTest extends TestCase
{
    protected function encode($data): string
    {
        return rtrim(strtr(base64_encode(is_string($data) ? $data : json_encode($data)), '+/', '-_'), '=');
    }

    protected function makeTokenWithClaims($claims): string
    {
        return $this->encode(['typ' => 'JWT', 'alg' => 'none']).'.'.$this->encode($claims).'.';
    }

    protected function makeToken(DateTimeImmutable $expires, array $accounts = []): string
    {
        return $this->makeTokenWithClaims(['exp' => $expires->getTimestamp(), 'accounts' => $accounts, 'roles' => []]);
    }

    /** @test */
    public function create_a_new_access_token()
    {
        $expires = (new DateTimeImmutable('1970-01-01 00:00:00', new DateTimeZone('UTC')))->add(new DateInterval('PT9S'));
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

    /** @test */
    public function it_parses_base64url_encoded_claims()
    {
        // "~~~" and "???" encode to base64 containing "+" and "/", which base64url turns into "-" and "_".
        $token = $this->makeTokenWithClaims(['exp' => 9, 'accounts' => ['~~~???'], 'roles' => []]);

        $this->assertMatchesRegularExpression('/[-_]/', explode('.', $token)[1]);

        $accessToken = new AccessToken($token);

        $this->assertSame('~~~???', $accessToken->getAccountId());
    }

    /** @test */
    public function it_parses_a_float_expiration()
    {
        $accessToken = new AccessToken($this->makeTokenWithClaims(['exp' => 9.5]));

        $this->assertSame('1970-01-01 00:00:09.500000', $accessToken->expiresAt->format('Y-m-d H:i:s.u'));
    }

    /** @test */
    public function it_parses_a_numeric_string_expiration()
    {
        $accessToken = new AccessToken($this->makeTokenWithClaims(['exp' => '9']));

        $this->assertSame(9, $accessToken->expiresAt->getTimestamp());
    }

    /** @test */
    public function it_is_expired_when_the_token_has_no_expiration()
    {
        $accessToken = new AccessToken($this->makeTokenWithClaims(['accounts' => ['1111']]));

        $this->assertTrue($accessToken->isExpired());
        $this->assertSame('1111', $accessToken->getAccountId());
    }

    /** @test */
    public function it_throws_when_the_expiration_is_not_numeric()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value is not in the allowed date format: tomorrow');

        new AccessToken($this->makeTokenWithClaims(['exp' => 'tomorrow']));
    }

    /** @test */
    public function it_throws_when_another_date_claim_is_not_numeric()
    {
        $this->expectException(InvalidArgumentException::class);

        new AccessToken($this->makeTokenWithClaims(['exp' => 9, 'iat' => 'yesterday']));
    }

    /**
     * @test
     * @dataProvider invalidStructures
     */
    public function it_throws_when_the_token_does_not_have_three_parts(string $token)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The JWT string must have two dots');

        new AccessToken($token);
    }

    public static function invalidStructures(): array
    {
        return [
            'empty' => [''],
            'one part' => ['abc'],
            'two parts' => ['abc.def'],
            'four parts' => ['a.b.c.d'],
        ];
    }

    /** @test */
    public function it_throws_when_a_part_is_not_valid_base64url()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error while decoding from Base64Url, invalid base64 characters detected');

        new AccessToken($this->encode(['alg' => 'none']).'.not*base64.');
    }

    /** @test */
    public function it_throws_when_a_part_is_not_valid_json()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error while decoding from JSON');

        new AccessToken($this->encode(['alg' => 'none']).'.'.$this->encode('not json').'.');
    }

    /** @test */
    public function it_throws_when_the_headers_are_not_an_array()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('headers must be an array');

        new AccessToken($this->encode('"none"').'.'.$this->encode(['exp' => 9]).'.');
    }

    /** @test */
    public function it_throws_when_the_claims_are_not_an_array()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('claims must be an array');

        new AccessToken($this->makeTokenWithClaims('123'));
    }

    /** @test */
    public function it_throws_when_the_token_is_encrypted()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Encryption is not supported yet');

        new AccessToken($this->encode(['alg' => 'none', 'enc' => 'A256GCM']).'.'.$this->encode(['exp' => 9]).'.');
    }
}
