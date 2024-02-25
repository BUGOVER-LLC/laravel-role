<?php

declare(strict_types=1);

namespace Service\Role\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use ReflectionException;
use RuntimeException;
use Service\Models\Entity\ServiceAuthenticable;
use Service\Role\Contracts\RoleModelContract;
use Service\Role\RoleRegister;
use Src\Models\Role\Permission;
use Src\Models\Role\Role;

use function get_class;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function strlen;

/**
 * Trait HasRoles
 *
 * @package Src\Traits
 */
trait HasRoles
{
    use HasPermissions;

    /**
     * @var
     */
    private $roleClass;

    /**
     *
     */
    public static function bootHasRoles(): void
    {
        static::deleting(
            static function ($model) {
                if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                    return;
                }

                $model->roles()->detach();
            }
        );
    }

    /**
     * A model may have multiple roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'worker_role',
            'system_worker_id',
            'role_id'
        )->withPivot('permission_ids');
    }

    /**
     * Scope the model query to certain roles only.
     *
     * @param Builder $query
     * @param string|array|Role|Collection $roles
     * @param string $guard
     *
     * @return Builder
     * @throws ReflectionException
     */
    public function scopeRole(Builder $query, $roles, $guard = null): Builder
    {
        if ($roles instanceof Collection) {
            $roles = $roles->all();
        }

        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $roles = array_map(
            function ($role) use ($guard) {
                if ($role instanceof Role) {
                    return $role;
                }

                $method = is_numeric($role) ? 'findById' : 'findByName';
                $guard = $guard ?: $this->getDefaultGuardName();

                return $this->getRoleClass()->{$method}($role, $guard);
            },
            $roles
        );

        return $query->whereHas(
            'roles',
            function ($query) use ($roles) {
                $query->where(
                    static function ($query) use ($roles) {
                        foreach ($roles as $role) {
                            $query->orWhere(config('permission.table_names.roles') . '.id', $role->id);
                        }
                    }
                );
            }
        );
    }

    /**
     * @return Role
     */
    public function getRoleClass(): Role
    {
        if (!isset($this->roleClass)) {
            $this->roleClass = app(RoleRegister::class)->getRoleClass();
        }

        return $this->roleClass;
    }

    /**
     * @param $role
     * @return $this
     * @throws ReflectionException
     */
    public function removeRole($role): self
    {
        $this->roles()->detach($this->getStoredRole($role));

        $this->load('roles');

        return $this;
    }

    /**
     * @param $role
     * @return RoleModelContract
     * @throws ReflectionException
     */
    protected function getStoredRole($role): RoleModelContract
    {
        $roleClass = $this->getRoleClass();

        if (is_numeric($role)) {
            return $roleClass->findById($role, $this->getDefaultGuardName());
        }

        if (is_string($role)) {
            return $roleClass->findByName($role, $this->getDefaultGuardName());
        }

        return $role;
    }

    /**
     * Remove all current roles and set the given ones.
     *
     * @param array|RoleModelContract|string ...$roles
     *
     * @return $this
     */
    public function syncRoles(...$roles): self
    {
        $this->roles()->detach();

        return $this->assignRole($roles);
    }

    /**
     * Assign the given role to the model.
     *
     * @param array|string|RoleModelContract ...$roles
     *
     * @return Permission|ServiceAuthenticable|HasRoles
     * @throws ReflectionException
     */
    public function assignRole(...$roles): self
    {
        if (!$this->franchiseHasRole($roles)) {
            throw new RuntimeException('This Franchise has not assigned roles');
        }

        $roles = collect($roles)
            ->flatten()
            ->map(
                function ($role) {
                    if (empty($role)) {
                        return false;
                    }

                    return $this->getStoredRole($role);
                }
            )
            ->filter(
                static function ($role) {
                    return $role instanceof RoleModelContract;
                }
            )
            ->each(
                function ($role) {
                    $this->ensureModelSharesGuard($role);
                }
            )
            ->map->role_id
            ->all();

        $model = $this->getModel();

        if ($model->exists) {
            $this->roles()->sync($roles, false); //@todo
            $model->load('roles');
        } else {
            $class = get_class($model);

            $class::saved(
                static function ($object) use ($roles, $model) {
                    static $modelLastFiredOn;
                    if (null !== $modelLastFiredOn && $modelLastFiredOn === $model) {
                        return;
                    }
                    $object->roles()->sync($roles, false); //@todo
                    $object->load('roles');
                    $modelLastFiredOn = $object;
                }
            );
        }

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Grant the given permission(s) to a role.
     *
     * @param mixed ...$permissions
     *
     * @return Permission|ServiceAuthenticable|HasRoles
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
            $this->permissions()->sync($permissions, false); //@todo
            $model->load('permissions');
        } else {
            $class = get_class($model);

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
     * @TODO DETERMINATE ROLE
     * Determine if the model has any of the given role(s).
     *
     * @param string|array|Role|Collection $roles
     *
     * @return bool
     */
    public function hasAnyRole($roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Determine if the model has (one of) the given role(s).
     *
     * @param string|int|array|Role|Collection $roles
     *
     * @return bool
     */
    public function hasRole($roles): bool
    {
        if (is_string($roles) && str_contains($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $this->roles->contains('name', $roles);
        }

        if (is_int($roles)) {
            return $this->roles->contains('role_id', $roles);
        }

        if ($roles instanceof Role) {
            return $this->roles->contains('role_id', $roles->role_id);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role)) {
                    return true;
                }
            }

            return false;
        }

        return $roles->intersect($this->roles)->isNotEmpty();
    }

    /**
     * @param string $pipeString
     * @return string|string[]
     */
    protected function convertPipeToArray(string $pipeString): array|string
    {
        $pipeString = trim($pipeString);

        if (strlen($pipeString) <= 2) {
            return $pipeString;
        }

        $quoteCharacter = $pipeString[0];
        $endCharacter = substr($quoteCharacter, -1, 1);

        if ($quoteCharacter !== $endCharacter) {
            return explode('|', $pipeString);
        }

        if (!in_array($quoteCharacter, ["'", '"'])) {
            return explode('|', $pipeString);
        }

        return explode('|', trim($pipeString, $quoteCharacter));
    }

    /**
     * Determine if the model has all of the given role(s).
     *
     * @param string|Role|Collection $roles
     *
     * @return bool
     */
    public function hasAllRoles($roles): bool
    {
        if (is_string($roles) && str_contains($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $this->roles->contains('name', $roles);
        }

        if ($roles instanceof Role) {
            return $this->roles->contains('id', $roles->id);
        }

        $roles = collect()->make($roles)->map(
            function ($role) {
                return $role instanceof Role ? $role->name : $role;
            }
        );

        return $roles->intersect($this->getRoleNames()) == $roles;
    }

    /**
     * @return Collection
     */
    public function getRoleNames(): Collection
    {
        return $this->roles->pluck('name');
    }

    /**
     * Return all permissions directly coupled to the model.
     */
    public function getDirectPermissions(): Collection
    {
        return $this->permissions;
    }
}
