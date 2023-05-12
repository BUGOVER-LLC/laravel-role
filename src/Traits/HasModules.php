<?php

declare(strict_types=1);

namespace Service\Role\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Trait HasModules
 * @package Src\Traits
 */
trait HasModules
{
    /**
     * Determinate user has this module
     *
     * @param $module_names
     * @return bool
     */
    public function hasModule($module_names): bool
    {
        $result = false;

        foreach ((array)$module_names as $module_name) {
            $result = $this->getModuleNames()->contains($module_name);
        }

        return $result;
    }

    /**
     * @return Collection
     */
    public function getModuleNames(): Collection
    {
        $user_data = static::getModel()
            ->where(static::getModel()->getKeyName(), static::getModel()->{static::getModel()->getKeyName()})
            ->with([
                'roles' => static function (BelongsToMany $role_query) {
                    $role_query->with('module:module_id,name');
                    $role_query->select(['roles.role_id', 'module_id']);
                },
            ])
            ->first();

        $user_module_names = $user_data->roles->map(fn($key) => $key->module);

        $modules = collect();

        foreach ($user_module_names as $user_module_name) {
            $modules[] = $user_module_name['name'];
        }

        return $modules;
    }
}
