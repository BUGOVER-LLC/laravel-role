<?php

declare(strict_types=1);

namespace Nucleus\Role\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Nucleus\Role\Contracts\PermissionModelContract;
use Nucleus\Role\Contracts\RoleModelContract;

/**
 * Class CreateRole
 * @package Src\Console\Commands
 */
class CreateRole extends Command
{
    /**
     * @var string
     */
    protected $signature = 'permission:CreateComponents-role
        {name : The name of the role}
        {guard? : The name of the guard}
        {permissions? : A list of permissions to assign to the role, separated by | }';
    /**
     * @var string
     */
    protected $description = 'Create a role';

    /**
     *
     */
    public function handle(): void
    {
        $roleClass = app(RoleModelContract::class);

        $role = $roleClass::findOrCreate($this->argument('name'), $this->argument('guard'));

        $role->givePermissionTo($this->makePermissions($this->argument('permissions')));

        $this->info("WorkerRole `{$role->name}` created");
    }

    /**
     * @param  null  $string
     * @return Collection|void
     */
    protected function makePermissions($string = null)
    {
        if (empty($string)) {
            return;
        }

        $permissionClass = app(PermissionModelContract::class);

        $permissions = explode('|', $string);

        $models = [];

        foreach ($permissions as $permission) {
            $models[] = $permissionClass::findOrCreate(trim($permission), $this->argument('guard'));
        }

        return collect($models);
    }
}
