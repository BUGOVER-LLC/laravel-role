<?php

declare(strict_types=1);

namespace Service\Role\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Src\Models\Franchise\Franchise;
use Src\Models\Franchise\FranchiseModule;
use Src\Models\Role\Role;

use function count;

/**
 * Class HasFranchise
 *
 * @package Src\Traits
 */
trait HasFranchise
{
    use HasModules;

    /**
     * @param $module_name
     * @return bool|null
     */
    public function franchiseHasModules($module_name): ?bool
    {
        $franchise_id = user()->franchise_id;

        if (!$franchise_id) {
            return false;
        }

        $current_guard = get_guard();

        if ('system_workers_web' === $current_guard || 'system_workers_api' === $current_guard) {
            $franchise = (new Franchise())
                ::where('franchise_id', $franchise_id)
                ->with([
                    'modules' => fn(BelongsToMany $module_query) => $module_query->whereIn(
                        'name',
                        $module_name
                    )->select(['modules.module_id', 'name']),
                ])
                ->first(['franchise_id']);

            return !(!$franchise || (int) $franchise->modules->count() !== (int) count($module_name));
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function franchiseModuleHasRoles(): mixed
    {
        $current_guard = get_guard();
        $franchise_id = user()->franchise_id;
        $result = false;

        if ('system_workers_web' === $current_guard || 'system_workers_api' === $current_guard) {
            $user_module = (new Role())
                ::with('module:module_id')
                ->whereIn('name', user()->getRoleNames())
                ->get(['role_id', 'module_id']);

            $user_module_ids = $user_module->pluck('module')
                ->map(fn($value) => $value->module_id)
                ->all();

            $user_role_ids = $user_module->pluck('role_id')->all();

            $franchise = (new Franchise())
                ::where('franchise_id', $franchise_id)
                ->with(
                    [
                        'modules' => fn(BelongsToMany $module_query) => $module_query
                            ->whereIn('modules.module_id', $user_module_ids)
                            ->select('modules.module_id'),
                    ]
                )
                ->first('franchise_id');

            if ($franchise->modules->count() < 1) {
                return false;
            }

            $franchise_roles = $franchise->modules->pluck('pivot')->flatMap(fn($value) => json_decode(
                $value->role_ids,
                true,
                512,
                JSON_THROW_ON_ERROR
            )->id);

            foreach ($user_role_ids as $ids) {
                if (collect($franchise_roles)->contains($ids)) {
                    $result = true;
                } else {
                    $result = false;
                }
            }

            return $result;
        }

        return false;
    }

    /**
     * Check Franchise has this assigned roles
     *
     * @param mixed ...$roles
     * @return bool
     */
    public function franchiseHasRole(...$roles): bool
    {
        $role_ids = (new Role())
            ->whereIn('name', $roles[0])
            ->get(['role_id', 'name'])
            ->pluck('role_id')
            ->flatten()
            ->all();

        $franchisee = (new FranchiseModule())
            ->where('franchise_id', $this->getModel()->franchise_id)
            ->get(['franchise_id', 'role_ids'])
            ->pluck('role_ids->id')
            ->flatten()
            ->all();

        foreach ($role_ids as $id) {
            if (collect($franchisee)->contains($id)) {
                $result = true;
            } else {
                $result = false;
            }
        }

        return $result ?: false;
    }
}
