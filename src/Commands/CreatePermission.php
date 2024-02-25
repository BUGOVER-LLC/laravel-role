<?php

declare(strict_types=1);

namespace Service\Role\Commands;

use Illuminate\Console\Command;
use Service\Role\Contracts\PermissionModelContract;

/**
 * Class CreatePermission
 *
 * @package Src\Console\Commands
 */
class CreatePermission extends Command
{
    /**
     * @var string
     */
    protected $signature = 'permission:CreateComponents-permission
                {name : The name of the permission}
                {guard? : The name of the guard}';

    /**
     * @var string
     */
    protected $description = 'Create a permission';

    /**
     *
     */
    public function handle(): void
    {
        $permissionClass = app(PermissionModelContract::class);

        $permission = $permissionClass::findOrCreate($this->argument('name'), $this->argument('guard'));

        $this->info("Permission `{$permission->name}` created");
    }
}
