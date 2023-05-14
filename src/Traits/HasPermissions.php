<?php

declare(strict_types=1);

namespace Nucleus\Role\Traits;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use ReflectionException;
use Nucleus\Role\Contracts\PermissionModelContract;
use Nucleus\Role\RoleRegister;
use Nucleus\Role\Common\Guard;
use Nucleus\Role\Exceptions\GuardDoesNotMatch;
use Nucleus\Role\Exceptions\PermissionDoesNotExist;
use Nucleus\Models\Role\Permission;
use Nucleus\Models\Role\Role;

/**
 * Trait HasPermissions
 * @package Src\Traits
 */
trait HasPermissions
{
    /**
     * @var
     */
    private $permissionClass;

    /**
     *
     */
    public static function bootHasPermissions(): void
    {
        static::deleting(
            static function ($model) {
                if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                    return;
                }

                $model->permissions()->detach();
            }
        );
    }

    /**
     * Scope the model query to certain permissions only.
     *
     * @param Builder $query
     * @param string|array|Permission|Collection $permissions
     *
     * @return Builder
     */
    public function scopePermission(Builder $query, $permissions): Builder
    {
        $permissions = $this->convertToPermissionModels($permissions);

        $rolesWithPermissions = array_unique(
            array_reduce(
                $permissions,
                function ($result, $permission) {
                    return array_merge($result, $permission->roles->all());
                },
                []
            )
        );

        return $query->where(
            static function ($query) use ($permissions, $rolesWithPermissions) {
                $query->whereHas(
                    'permissions',
                    static function ($query) use ($permissions) {
                        $query->where(
                            static function ($query) use ($permissions) {
                                foreach ($permissions as $permission) {
                                    $query->orWhere(config('permission.table_names.permissions') . '.id', $permission->id);
                                }
                            }
                        );
                    }
                );
                if (count($rolesWithPermissions) > 0) {
                    $query->orWhereHas(
                        'roles',
                        static function ($query) use ($rolesWithPermissions) {
                            $query->where(
                                static function ($query) use ($rolesWithPermissions) {
                                    foreach ($rolesWithPermissions as $role) {
                                        $query->orWhere(config('permission.table_names.roles') . '.id', $role->id);
                                    }
                                }
                            );
                        }
                    );
                }
            }
        );
    }

    /**
     * @param string|array|Permission|Collection $permissions
     *
     * @return array
     */
    protected function convertToPermissionModels($permissions): array
    {
        if ($permissions instanceof Collection) {
            $permissions = $permissions->all();
        }

        $permissions = \is_array($permissions) ? $permissions : [$permissions];

        return array_map(
            function ($permission) {
                if ($permission instanceof Permission) {
                    return $permission;
                }

                return $this->getPermissionClass()->findByName($permission, $this->getDefaultGuardName());
            },
            $permissions
        );
    }

    /**
     * @return Permission
     */
    public function getPermissionClass(): Permission
    {
        if (!isset($this->permissionClass)) {
            $this->permissionClass = app(RoleRegister::class)->getPermissionClass();
        }

        return $this->permissionClass;
    }

    /**
     * @return string
     * @throws ReflectionException
     */
    protected function getDefaultGuardName(): string
    {
        return Guard::getDefaultName($this);
    }

    /**
     * @deprecated since 2.35.0
     * @alias of hasPermissionTo()
     */
    public function hasUncachedPermissionTo($permission, $guardName = null): bool
    {
        return $this->hasPermissionTo($permission, $guardName);
    }

    /**
     * Determine if the model may perform the given permission.
     *
     * @param string|int|Permission $permission
     * @param string|null $guardName
     *
     * @return bool
     * @throws PermissionDoesNotExist
     * @throws ReflectionException
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $permission_class = $this->getPermissionClass();

        if (\is_string($permission)) {
            $permission = $permission_class->findByName(
                $permission,
                $guardName ?? $this->getDefaultGuardName()
            );
        }

        if (\is_int($permission)) {
            $permission = $permission_class->findById(
                $permission,
                $guardName ?? $this->getDefaultGuardName()
            );
        }

        if (!$permission instanceof Permission) {
            throw new PermissionDoesNotExist();
        }

        return $this->hasDirectPermission($permission) || $this->hasPermissionViaRole($permission);
    }

    /**
     * Determine if the model has the given permission.
     *
     * @param string|int|Permission $permission
     *
     * @return bool
     * @throws ReflectionException
     */
    public function hasDirectPermission($permission): bool
    {
        $permissionClass = $this->getPermissionClass();

        if (\is_string($permission)) {
            $permission = $permissionClass->findByName($permission, $this->getDefaultGuardName());
            if (!$permission) {
                return false;
            }
        }

        if (\is_int($permission)) {
            $permission = $permissionClass->findById($permission, $this->getDefaultGuardName());
            if (!$permission) {
                return false;
            }
        }

        if (!$permission instanceof Permission) {
            return false;
        }

        return $this->permissions->contains('permission_id', $permission->permission_id);
    }

    /**
     * Determine if the model has, via roles, the given permission.
     *
     * @param Permission $permission
     *
     * @return bool
     */
    protected function hasPermissionViaRole(Permission $permission): bool
    {
        return $this->hasRole($permission->roles);
    }

    /**
     * Determine if the model has any of the given permissions.
     *
     * @param array ...$permissions
     *
     * @return bool
     * @throws Exception
     */
    public function hasAnyPermission(...$permissions): bool
    {
        if (\is_array($permissions[0])) {
            $permissions = $permissions[0];
        }

        foreach ($permissions as $permission) {
            if ($this->checkPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * An alias to hasPermissionTo(), but avoids throwing an exception.
     *
     * @param string|int|Permission $permission
     * @param string|null $guardName
     *
     * @return bool
     * @throws ReflectionException
     */
    public function checkPermissionTo($permission, $guardName = null): bool
    {
        try {
            return $this->hasPermissionTo($permission, $guardName);
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    /**
     * Determine if the model has all of the given permissions.
     *
     * @param array ...$permissions
     *
     * @return bool
     * @throws Exception
     */
    public function hasAllPermissions(...$permissions): bool
    {
        if (\is_array($permissions[0])) {
            $permissions = $permissions[0];
        }

        foreach ($permissions as $permission) {
            if (!$this->hasPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return all the permissions the model has, both directly and via roles.
     *
     * @throws Exception
     */
    public function getAllPermissions(): Collection
    {
        $permissions = $this->permissions;

        if ($this->roles) {
            $permissions = $permissions->merge($this->getPermissionsViaRoles());
        }

        return $permissions->sort()->values();
    }

    /**
     * Return all the permissions the model has via roles.
     */
    public function getPermissionsViaRoles(): Collection
    {
        return $this->load('roles', 'roles.permissions')->roles->flatMap(fn($role) => $role->permissions)->sort()->values();
    }

    /**
     * Remove all current permissions and set the given ones.
     *
     * @param mixed ...$permissions
     *
     * @return Role|HasPermissions|HasRoles
     * @throws ReflectionException
     */
    public function syncPermissions(...$permissions): self
    {
        $this->permissions()->detach();

        return $this->givePermissionTo($permissions);
    }

    /**
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'worker_permission', 'system_worker_id', 'permission_id');
    }

    /**
     * @TODO ADD PERMISSION TO ROLE
     *
     * Grant the given permission(s) to a role.
     *
     * @param mixed ...$permissions
     *
     * @return Role|HasPermissions|HasRoles
     * @throws ReflectionException
     */
    public function givePermissionTo(...$permissions): self
    {
        $permissions = collect($permissions)
            ->flatten()
            ->map(
                function ($permission) {
                    if (empty($permission)) {
                        return false;
                    }

                    return $this->getStoredPermission($permission);
                }
            )
            ->filter(
                static function ($permission) {
                    return $permission instanceof Permission;
                }
            )
            ->each(
                function ($permission) {
                    $this->ensureModelSharesGuard($permission);
                }
            )
            ->map->permission_id
            ->all();

        $model = $this->getModel();

        if ($model->exists) {
            $this->getPermissionClass()
                ->whereIn('permission_id', $permissions)
                ->update(['role_id' => $model->{$model->getKeyName()}]);

            $model->load('permissions');
        } else {
            $class = \get_class($model);

            $class::saved(
                static function ($object) use ($permissions, $model) {
                    static $modelLastFiredOn;
                    if (null !== $modelLastFiredOn && $modelLastFiredOn === $model) {
                        return;
                    }
                    $object->permissions()->sync($permissions, false); //@todo
                    $object->load('permissions');
                    $modelLastFiredOn = $object;
                }
            );
        }

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * @param $permissions
     * @return PermissionModelContract
     * @throws ReflectionException
     */
    protected function getStoredPermission($permissions): PermissionModelContract
    {
        $permissionClass = $this->getPermissionClass();

        if (is_numeric($permissions)) {
            return $permissionClass->findById($permissions, $this->getDefaultGuardName());
        }

        if (\is_string($permissions)) {
            return $permissionClass->findByName($permissions, $this->getDefaultGuardName());
        }

        if (\is_array($permissions)) {
            return $permissionClass
                ->whereIn('name', $permissions)
                ->whereIn('guard_name', $this->getGuardNames())
                ->get();
        }

        return $permissions;
    }

    /**
     * @return Collection
     * @throws ReflectionException
     */
    protected function getGuardNames(): Collection
    {
        return Guard::getNames($this);
    }

    /**
     * @param Permission|Role $roleOrPermission
     *
     * @throws GuardDoesNotMatch
     * @throws ReflectionException
     */
    protected function ensureModelSharesGuard($roleOrPermission): void
    {
        if (!$this->getGuardNames()->contains($roleOrPermission->guard_name)) {
            throw GuardDoesNotMatch::create($roleOrPermission->guard_name, $this->getGuardNames());
        }
    }

    /**
     * Forget the cached permissions.
     */
    public function forgetCachedPermissions(): void
    {
        app(RoleRegister::class)->forgetCachedPermissions();
    }

    /**
     * @param $permission
     * @return $this
     * @throws ReflectionException
     */
    public function revokePermissionTo($permission): self
    {
        $this->permissions()->detach($this->getStoredPermission($permission));

        $this->forgetCachedPermissions();

        $this->load('permissions');

        return $this;
    }

    /**
     * @return Collection
     */
    public function getPermissionNames(): Collection
    {
        return $this->permissions->pluck('name');
    }
}
