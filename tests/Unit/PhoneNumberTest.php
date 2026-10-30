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
            'country code without +' => ['33612345678', '+33612345678'],
            'overseas code without +' => ['596696777862', '+596696777862'],
            'reunion code without +' => ['262692242751', '+262692242751'],
        ];
    }

    public static function storedNumbers(): array
    {
        return [
            'national, dialled as is' => ['0692737813', '0692737813'],
            'national with spaces' => ['06 12 34 56 78', '0612345678'],
            'country code without +' => ['596696777862', '+596696777862'],
            'france code without +' => ['33612345678', '+33612345678'],
            'already international' => ['+33 6 92 12 34 56', '+33692123456'],
            '00 prefix' => ['00262692242751', '+262692242751'],
            'unusual length is not refused' => ['69038123', '+69038123'],
        ];
    }

    #[DataProvider('storedNumbers')]
    public function test_it_dials_numbers_as_stored(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::forDialing($input));
    }

    public function test_it_has_nothing_to_dial_without_digits(): void
    {
        $this->assertNull(PhoneNumber::forDialing(null));
        $this->assertNull(PhoneNumber::forDialing('  '));
        $this->assertNull(PhoneNumber::forDialing('n/a'));
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
