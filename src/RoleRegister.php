<?php

declare(strict_types=1);

namespace Nucleus\Role;

use DateInterval;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Nucleus\Role\Contracts\PermissionModelContract;
use Nucleus\Role\Contracts\RoleModelContract as Role;
use Nucleus\Role\Exceptions\PermissionDoesNotExist;

/**
 * Class RoleRegister
 * @package Src\Roles
 */
class RoleRegister
{
    /**
     * @var
     */
    public static $cacheExpirationTime;

    /**
     * @var
     */
    public static $cacheKey;
    /**
     * @var
     */
    public static $cacheModelKey;
    /**
     * @var
     */
    protected $cache;
    /**
     * @var \Illuminate\Config\Repository|Application|mixed
     */
    protected $permissionClass;
    /**
     * @var \Illuminate\Config\Repository|Application|mixed
     */
    protected $roleClass;
    /**
     * @var
     */
    protected $permissions;

    /**
     * RoleRegister constructor.
     *
     * @param Gate $gate
     * @param CacheManager $cacheManager
     */
    public function __construct(protected Gate $gate, protected CacheManager $cacheManager)
    {
        $this->permissionClass = config('permission.models.permission');
        $this->roleClass = config('permission.models.role');

        $this->initializeCache();
    }

    /**
     *
     */
    protected function initializeCache(): void
    {
        static::$cacheExpirationTime = config('permission.cache.expiration_time');

        if (static::$cacheExpirationTime instanceof DateInterval) {
            $interval = static::$cacheExpirationTime;
            static::$cacheExpirationTime = $interval->m * 30 * 60 * 24 + $interval->d * 60 * 24 + $interval->h * 60 + $interval->i;
        }

        static::$cacheKey = config('permission.cache.key');
        static::$cacheModelKey = config('permission.cache.model_key');

        $this->cache = $this->getCacheStoreFromConfig();
    }

    /**
     * @return Repository
     */
    protected function getCacheStoreFromConfig(): Repository
    {
        // the 'default' fallback here is from the permission.php config file, where 'default' means to use config(cache.default)
        $cache_driver = config('permission.cache.store', 'default');

        // when 'default' is specified, no action is required since we already have the default instance
        if ('default' === $cache_driver) {
            return $this->cacheManager->store();
        }

        // if an undefined cache store is specified, fallback to 'array' which is Laravel's closest equiv to 'none'
        if (!\array_key_exists($cache_driver, config('cache.stores'))) {
            $cache_driver = 'array';
        }

        return $this->cacheManager->store($cache_driver);
    }

    /**
     * Register the permission check method on the gate.
     *
     * @return bool
     */
    public function registerPermissions(): bool
    {
        $this->gate->before(
            static function (Authorizable $user, string $ability) {
                try {
                    if (method_exists($user, 'hasPermissionTo')) {
                        return $user->hasPermissionTo($ability) ?: null;
                    }
                } catch (PermissionDoesNotExist $exception) {
                    throw new $exception();
                }
            }
        );

        return true;
    }

    /**
     * Flush the cache.
     */
    public function forgetCachedPermissions(): void
    {
        $this->permissions = null;
        $this->cache->forget(static::$cacheKey);
    }

    /**
     * Get the permissions based on the passed params.
     *
     * @param array $params
     *
     * @return Collection
     */
    public function getPermissions(array $params = []): Collection
    {
        if (null === $this->permissions) {
            $this->permissions = $this->cache->remember(
                static::$cacheKey,
                static::$cacheExpirationTime,
                fn() => $this->getPermissionClass()->with('role')->get()
            );
        }

        $perms = clone $this->permissions;

        foreach ($params as $attr => $value) {
            $perms = $perms->where($attr, $value);
        }

        return $perms;
    }

    /**
     * Get an instance of the permission class.
     *
     * @return PermissionModelContract
     */
    public function getPermissionClass(): PermissionModelContract
    {
        return app($this->permissionClass);
    }

    /**
     * Get an instance of the role class.
     *
     * @return Role
     */
    public function getRoleClass(): Role
    {
        return app($this->roleClass);
    }

    /**
     * Get the instance of the Cache Store.
     *
     * @return Store
     */
    public function getCacheStore(): Store
    {
        return $this->cache->getStore();
    }
}
