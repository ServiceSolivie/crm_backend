<?php

namespace Tests\Unit;

use App\Enums\DeviceRestrictionEnum;
use App\Enums\TrustedDeviceStatusEnum;
use App\Models\TrustedDevice;
use App\Services\DeviceTrustService;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\TestCase;

/**
 * Approved machines without database: how a machine is named, what its
 * sessions look like, and the encoding shared with the browser.
 */
class TrustedDeviceTest extends TestCase
{
    public function test_passkey_ids_travel_as_base64url_without_padding(): void
    {
        foreach (["\xfb\xff\xfe", 'a', 'ab', 'abc', random_bytes(32), random_bytes(65)] as $binary) {
            $text = DeviceTrustService::toBase64Url($binary);

            $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $text);
            $this->assertSame($binary, DeviceTrustService::fromBase64Url($text));
        }
    }

    public function test_a_session_opened_through_a_machine_carries_its_id(): void
    {
        $device = new TrustedDevice;
        $device->id = 12;

        $this->assertSame('device:12', $device->tokenName());

        $service = new DeviceTrustService;
        $this->assertTrue($service->openedThroughMachine((new PersonalAccessToken)->forceFill(['name' => 'device:12'])));
        // A plain password login, and the vault extension's own token
        $this->assertFalse($service->openedThroughMachine((new PersonalAccessToken)->forceFill(['name' => 'api'])));
        $this->assertFalse($service->openedThroughMachine((new PersonalAccessToken)->forceFill(['name' => DeviceTrustService::VAULT_TOKEN])));
        $this->assertFalse($service->openedThroughMachine(null));
    }

    public function test_only_an_approved_machine_counts_as_approved(): void
    {
        $this->assertTrue((new TrustedDevice(['status' => 'approved']))->isApproved());
        $this->assertFalse((new TrustedDevice(['status' => 'pending']))->isApproved());
        $this->assertFalse((new TrustedDevice(['status' => 'revoked']))->isApproved());
    }

    public function test_the_list_shows_the_browser_and_system_of_a_machine(): void
    {
        $browser = fn (string $agent) => (new TrustedDevice(['user_agent' => $agent]))->browser;

        $this->assertSame('Chrome · Windows', $browser('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'));
        // Edge also says "Chrome": it must be recognised first
        $this->assertSame('Edge · Windows', $browser('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'));
        $this->assertSame('Firefox · Windows', $browser('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0'));
        $this->assertNull((new TrustedDevice)->browser);
    }

    public function test_the_switch_has_three_positions_and_starts_off(): void
    {
        $this->assertSame(['off', 'prepare', 'on'], DeviceRestrictionEnum::values());
        $this->assertSame(['pending', 'approved', 'revoked'], TrustedDeviceStatusEnum::values());
        // An empty or unknown stored value never turns the restriction on
        $this->assertNull(DeviceRestrictionEnum::tryFrom(''));
    }
}
