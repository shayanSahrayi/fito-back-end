<?php

namespace App\Console\Commands;

use App\Domain\Identity\IdentityException;
use App\Domain\Identity\PlatformAdminGranter;
use Illuminate\Console\Command;

class GrantPlatformAdminCommand extends Command
{
    protected $signature = 'identity:grant-platform-admin {phone}';

    protected $description = 'Give an active user the platform admin role';

    public function handle(PlatformAdminGranter $granter): int
    {
        try {
            $result = $granter->grant((string) $this->argument('phone'));
        } catch (IdentityException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result['created'] ? 'نقش مدیر داده شد.' : 'این کاربر از قبل مدیر است.');

        return self::SUCCESS;
    }
}
