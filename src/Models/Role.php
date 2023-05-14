<?php

declare(strict_types=1);

namespace Nucleus\Role\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use ReflectionException;
use Service\Models\Entity\Highlight;
use Service\Models\Entity\ServiceModel;
use Service\Role\Contracts\RoleModelContract;
use Service\Role\Traits\HasPermissions;
use Src\Core\Additional\Guard;
use Src\Core\Traits\RefreshRoleCache;
use Src\Exceptions\Role\GuardDoesNotMatch;
use Src\Exceptions\Role\RoleAlreadyExists;
use Src\Exceptions\Role\RoleDoesNotExist;
use Src\Models\Franchise\Franchise;
use Src\Models\Role\FranchiseRole;
use Src\Models\Role\Module;
use Src\Models\Role\Permission;
use Src\Models\SystemUsers\SystemWorker;
use Src\Models\Views\Menu;
use Src\Models\Views\Route;

use function Src\Models\Role\app;
use function Src\Models\Role\config;
use function Src\Models\Role\is_not_lumen;

/**
 * Class Role
 *
 * @package Src\Models\Role
 * @property int $role_id
 * @property int|null $module_id
 * @property int|null $homepage_route_id
 * @property string $name
 * @property string|null $alias
 * @property string|null $description
 * @property string $guard_name
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Route|null $homepage_route
 * @property-read int|null $menus_count
 * @property-read Module|null $module
 * @property-read Collection|Permission[] $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection|Route[] $routes
 * @property-read int|null $routes_count
 * @property-read Collection|SystemWorker[] $system_workers
 * @property-read int|null $system_workers_count
 * @method static Builder|ServiceModel except($values = [])
 * @method static Builder|Role newModelQuery()
 * @method static Builder|Role newQuery()
 * @method static \Illuminate\Database\Query\Builder|Role onlyTrashed()
 * @method static Builder|Role permission($permissions)
 * @method static Builder|Role query()
 * @method static Builder|Role whereAlias($value)
 * @method static Builder|Role whereCreatedAt($value)
 * @method static Builder|Role whereDeletedAt($value)
 * @method static Builder|Role whereDescription($value)
 * @method static Builder|Role whereGuardName($value)
 * @method static Builder|Role whereHomepageRouteId($value)
 * @method static Builder|Role whereModuleId($value)
 * @method static Builder|Role whereName($value)
 * @method static Builder|Role whereRoleId($value)
 * @method static Builder|Role whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|Role withTrashed()
 * @method static \Illuminate\Database\Query\Builder|Role withoutTrashed()
 * @mixin Eloquent
 * @method static Builder|ServiceModel distance($latitude, $longitude)
 * @method static Builder|ServiceModel distanceCord($latitude, $longitude, $distance = 1)
 * @method static Builder|ServiceModel geofence($latitude, $longitude, $inner_radius, $outer_radius)
 * @property string $text
 * @method static Builder|ServiceModel disatnceCordsss($latitude, $longitude, $distance)
 * @method static Builder|Role whereText($value)
 * @property-read Collection|FranchiseRole[] $franchise_roles
 * @property-read int|null $franchise_roles_count
 * @property-read Collection|Franchise[] $franchises
 * @property-read int|null $franchises_count
 * @property-read Collection|Permission[] $role_permissions
 * @property-read int|null $role_permissions_count
 * @method static Builder|ServiceModel cordDistance($latitude, $longitude)
 * @property-read Collection|Menu[] $menus
 * @method static Builder|ServiceModel distanceCordsACOS($latitude, $longitude, $distance)
 * @property Highlight|null $highlight
 */
class Role extends ServiceModel implements RoleModelContract
{
    use HasPermissions;
    use RefreshRoleCache;
    use SoftDeletes;

    /**
     *
     */
    public const ADMIN_SUPER_WEB = 'admin_super_web';
    /**
     *
     */
    public const ADMIN_SUPER_API = 'admin_super_api';
    /**
     *
     */
    public const ADMIN_FRANCHISE_WEB = 'admin_franchise_web';
    /**
     *
     */
    public const ADMIN_FRANCHISE_API = 'admin_franchise_api';
    /**
     *
     */
    public const ADMIN_CORPORATE_WEB = 'admin_corporate_web';
    /**
     *
     */
    public const ADMIN_CORPORATE_API = 'admin_corporate_api';
    /**
     *
     */
    public const PARK_MANAGER_WEB = 'park_manager_web';
    /**
     *
     */
    public const PARK_MANAGER_API = 'park_manager_api';
    /**
     *
     */
    public const TRAFFIC_SAFETY_WEB = 'traffic_safety_web';
    /**
     *
     */
    public const TRAFFIC_SAFETY_API = 'traffic_safety_api';
    /**
     *
     */
    public const DRIVER_WEB = 'driver_web';
    /**
     *
     */
    public const DRIVER_API = 'driver_api';
    /**
     *
     */
    public const CLIENT_WEB = 'client_web';
    /**
     *
     */
    public const CLIENT_API = 'client_api';
    /**
     *
     */
    public const DISPATCHER_WEB = 'dispatcher_web';
    /**
     *
     */
    public const DISPATCHER_API = 'dispatcher_api';
    /**
     *
     */
    public const OPERATOR_WEB = 'operator_web';
    /**
     *
     */
    public const OPERATOR_API = 'operator_api';
    /**
     *
     */
    public const HEAD_CALL_CENTER_WEB = 'head_call_center_web';
    /**
     *
     */
    public const HEAD_CALL_CENTER_API = 'head_call_center_api';
    /**
     *
     */
    public const ACCOUNTANT_WEB = 'accountant_web';
    /**
     *
     */
    public const ACCOUNTANT_API = 'accountant_api';
    /**
     *
     */
    public const SALES_DEPARTMENT_WEB = 'sales_department_web';
    /**
     *
     */
    public const SALES_DEPARTMENT_API = 'sales_department_api';
    /**
     *
     */
    public const MECHANIC_WEB = 'mechanic_web';
    /**
     *
     */
    public const MECHANIC_API = 'mechanic_api';
    /**
     *
     */
    public const HEAD_PERSONAL_DEPARTMENT_WEB = 'head_personal_department_web';
    /**
     *
     */
    public const HEAD_PERSONAL_DEPARTMENT_API = 'head_personal_department_api';
    /**
     *
     */
    public const WORKER_PERSONAL_DEPARTMENT_WEB = 'worker_personal_department_web';
    /**
     *
     */
    public const WORKER_PERSONAL_DEPARTMENT_API = 'worker_personal_department_api';
    /**
     *
     */
    public const TUTOR_WEB = 'tutor_web';
    /**
     *
     */
    public const TUTOR_API = 'tutor_api';
    /**
     *
     */
    public const CALL_CENTER_HEAD_WEB = 'call_center_head_web';
    /**
     *
     */
    public const CALL_CENTER_HEAD_API = 'call_center_head_api';

    /**
     * @var string
     */
    protected $table = 'roles';
    /**
     * @var string
     */
    protected $primaryKey = 'role_id';
    /**
     * @var array
     */
    protected $fillable = ['name', 'alias', 'guard_name', 'description'];
    /**
     * @var array
     */
    protected $casts = ['permission_ids' => 'json'];
    /**
     * @var array
     */
    protected $guarded = ['role_id'];

    /**
     * WorkerRole constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->setTable($this->roles);
    }

    /**
     * Find or CreateComponents role by its name (and optionally guardName).
     *
     * @param string $name
     * @param string|null $guard_name
     *
     * @return RoleModelContract
     * @throws ReflectionException
     */
    public static function findOrCreate(string $name, $guard_name = null): RoleModelContract
    {
        $guard_name = $guard_name ?? Guard::getDefaultName(static::class);

        $role = static::where('name', $name)->where('guard_name', $guard_name)->first();

        if (!$role) {
            return static::query()->create(['name' => $name, 'guard_name' => $guard_name]);
        }

        return $role;
    }

    /**
     * @param array $attributes
     * @return Builder|Model
     * @throws ReflectionException
     */
    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(static::class);

        if (static::where('name', $attributes['name'])->where('guard_name', $attributes['guard_name'])->first()) {
            throw RoleAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        if (is_not_lumen() && app()::VERSION < '5.4') {
            return parent::create($attributes);
        }

        return static::query()->create($attributes);
    }

    /**
     * A role may be given various permissions.
     */
    public function role_permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'role_id');
    }

    /**
     * A role belongs to some clients of the model associated with its guard.
     */
    public function system_workers(): BelongsToMany
    {
        return $this->belongsToMany(SystemWorker::class, 'worker_role', 'role_id', 'system_worker_id');
    }

    /**
     * @return BelongsToMany
     */
    public function franchises(): BelongsToMany
    {
        return $this->belongsToMany(Franchise::class, 'franchise_role', 'role_id', 'franchise_id');
    }

    /**
     * Determine if the user may perform the given permission.
     *
     * @param string|Permission $permission
     *
     * @return bool
     *
     * @throws GuardDoesNotMatch
     * @throws ReflectionException
     */
    public function hasPermissionTo($permission): bool
    {
        $permission_class = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = $permission_class->findByName($permission, $this->getDefaultGuardName());
        }

        if (is_int($permission)) {
            $permission = $permission_class->findById($permission, $this->getDefaultGuardName());
        }

        if (!$this->getGuardNames()->contains($permission->guard_name)) {
            throw GuardDoesNotMatch::create($permission->guard_name, $this->getGuardNames());
        }

        return $permission->where('role_id', $this->getModel()->{$this->getModel()->getKeyName()})->exists();
    }

    /**
     * Find a role by its name and guard name.
     *
     * @param string $name
     * @param string|null $guard_name
     *
     * @return RoleModelContract
     * @throws ReflectionException
     */
    public static function findByName(string $name, $guard_name = null): RoleModelContract
    {
        $guard_name = $guard_name ?? Guard::getDefaultName(static::class);

        $role = static::where('name', $name)->where('guard_name', $guard_name)->first();

        if (!$role) {
            throw RoleDoesNotExist::named($name);
        }

        return $role;
    }

    /**
     * @param int $id
     * @param null $guard_name
     * @return RoleModelContract
     * @throws ReflectionException
     */
    public static function findById(int $id, $guard_name = null): RoleModelContract
    {
        $guard_name = $guard_name ?? Guard::getDefaultName(static::class);

        $role = static::where('role_id', $id)->where('guard_name', $guard_name)->first();

        if (!$role) {
            throw RoleDoesNotExist::withId($id);
        }

        return $role;
    }

    /**
     * @return BelongsTo
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    /**
     * @return HasOne
     */
    public function homepage_route(): HasOne
    {
        return $this->hasOne(Route::class, 'route_id', 'homepage_route_id');
    }

    public function franchise_roles(): HasMany
    {
        return $this->hasMany(FranchiseRole::class, 'role_id', 'role_id');
    }

    /**
     * @return BelongsToMany
     */
    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'route_role', 'route_id', 'role_id');
    }

    /**
     * @return BelongsToMany
     */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'menu_role', 'role_id', 'menu_id');
    }
}
