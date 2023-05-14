<?php

declare(strict_types=1);

namespace Nucleus\Role\Commands;

use Illuminate\Console\Command;
use Nucleus\Role\RoleRegister;

/**
 * Class CacheReset
 * @package Src\Console\Commands
 */
class CacheReset extends Command
{
    /**
     * @var string
     */
    protected $signature = 'permission:cache-reset';
    /**
     * @var string
     */
    protected $description = 'Reset the permission cache';

    /**
     *
     */
    public function handle(): void
    {
        app(RoleRegister::class)->forgetCachedPermissions();

        $this->info('Permission cache flushed.');
    }
}
