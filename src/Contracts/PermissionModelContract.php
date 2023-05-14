<?php

declare(strict_types=1);

namespace Nucleus\Role\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nucleus\Role\Contracts\PermissionModelContract as Permission;
use Nucleus\Role\Exceptions\PermissionDoesNotExist;

/**
 * Interface PermissionModelContract
 * @package Src\Contracts
 */
interface PermissionModelContract
{
    /**
     * Find a permission by its name.
     *
     * @param  string  $name
     * @param  string|null  $guard_name
     *
     * @return Permission
     * @throws PermissionDoesNotExist
     *
     */
    public static function findByName(string $name, $guard_name): self;

    /**
     * Find a permission by its id.
     *
     * @param int $id
     * @param string $guard_name
     *
     * @return Permission
     * @throws PermissionDoesNotExist
     */
    public static function findById(int $id, string $guard_name): self;

    /**
     * Find or Create a permission by its name and guard name.
     *
     * @param  string  $name
     * @param  string|null  $guard_name
     *
     * @return Permission
     */
    public static function findOrCreate(string $name, $guard_name): self;

    /**
     * A permission can be applied to roles.
     *
     * @return BelongsTo
     */
    public function role(): BelongsTo;
}
