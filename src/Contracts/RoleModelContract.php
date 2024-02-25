<?php

declare(strict_types=1);

namespace Service\Role\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Exceptions\Role\RoleDoesNotExist;
use Src\Models\Role\Permission;
use Src\Models\Role\Role;

/**
 * Interface RoleModelContract
 *
 * @package Src\Contracts
 */
interface RoleModelContract
{
    /**
     * Find a role by its name and guard name.
     *
     * @param string $name
     * @param string|null $guard_name
     *
     * @return RoleModelContract
     *
     * @throws RoleDoesNotExist
     */
    public static function findByName(string $name, $guard_name): self;

    /**
     * Find a role by its id and guard name.
     *
     * @param int $id
     * @param string|null $guard_name
     *
     * @return RoleModelContract
     *
     * @throws RoleDoesNotExist
     */
    public static function findById(int $id, $guard_name): self;

    /**
     * Find or CreateComponents a role by its name and guard name.
     *
     * @param string $name
     * @param string|null $guard_name
     *
     * @return Role
     */
    public static function findOrCreate(string $name, $guard_name): self;

    /**
     * A role may be given various permissions.
     *
     * @return HasMany
     */
    public function role_permissions(): HasMany;

    /**
     * Determine if the user may perform the given permission.
     *
     * @param string|Permission $permission
     *
     * @return bool
     */
    public function hasPermissionTo($permission): bool;
}
