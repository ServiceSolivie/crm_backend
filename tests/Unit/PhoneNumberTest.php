<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    public static function frenchNumbers(): array
    {
        return [
            'national' => ['06 12 34 56 78', '+33612345678'],
            'national with dots' => ['06.12.34.56.78', '+33612345678'],
            'international with spaces' => ['+33 6 12 34 56 78', '+33612345678'],
            '00 prefix' => ['0033612345678', '+33612345678'],
            'landline' => ['01 84 80 00 00', '+33184800000'],
            'foreign number keeps its country' => ['+212 6 61 23 45 67', '+212661234567'],
        ];
    }

    #[DataProvider('frenchNumbers')]
    public function test_it_normalises_to_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toE164($input));
    }

    public function test_it_returns_null_for_garbage(): void
    {
        $this->assertNull(PhoneNumber::toE164(null));
        $this->assertNull(PhoneNumber::toE164('   '));
        $this->assertNull(PhoneNumber::toE164('not a phone'));
        $this->assertNull(PhoneNumber::toE164('123'));
    }

    public function test_it_converts_from_and_to_ringover_format(): void
    {
        $this->assertSame('+33612345678', PhoneNumber::fromRingover(33612345678));
        $this->assertSame('+33612345678', PhoneNumber::fromRingover('33612345678'));
        $this->assertNull(PhoneNumber::fromRingover(null));
        $this->assertSame('33612345678', PhoneNumber::toRingover('+33612345678'));
    }
}
