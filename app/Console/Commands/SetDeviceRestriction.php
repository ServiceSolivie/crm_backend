<?php

namespace App\Console\Commands;

use App\Enums\DeviceRestrictionEnum;
use App\Services\DeviceTrustService;
use Illuminate\Console\Command;

/**
 * Emergency exit for the approved-machines restriction: moves the switch
 * from the server when nobody can reach the "Appareils" page any more.
 */
class SetDeviceRestriction extends Command
{
    protected $signature = 'devices:restriction {mode? : off, prepare or on}';

    protected $description = 'Show or change the approved-machines restriction (off / prepare / on)';

    public function handle(DeviceTrustService $devices): int
    {
        $mode = $this->argument('mode');

        if ($mode === null) {
            $this->info('Approved machines: '.$devices->mode()->value);

            return self::SUCCESS;
        }

        $target = DeviceRestrictionEnum::tryFrom(strtolower($mode));

        if ($target === null) {
            $this->error('Unknown mode. Use off, prepare or on.');

            return self::FAILURE;
        }

        $closed = $devices->setMode($target);

        $this->info('Approved machines: '.$target->value.($closed ? " ({$closed} sessions closed)" : ''));

        return self::SUCCESS;
    }
}
