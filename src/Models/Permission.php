<?php

declare(strict_types=1);

namespace Nucleus\Role\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ReflectionException;
use Service\Models\Entity\Highlight;
use Service\Models\Entity\ServiceModel;
use Service\Role\Contracts\PermissionModelContract;
use Service\Role\RoleRegister;
use Service\Role\Traits\HasRoles;
use Src\Core\Additional\Guard;
use Src\Core\Traits\RefreshRoleCache;
use Src\Exceptions\Role\PermissionAlreadyExists;
use Src\Exceptions\Role\PermissionDoesNotExist;
use Src\Models\Role\Role;
use Src\Models\SystemUsers\SystemWorker;
use Src\Models\Views\Route;

use function Src\Models\Role\app;
use function Src\Models\Role\config;
use function Src\Models\Role\is_not_lumen;

/**
 * Class Permission
 *
 * @property int $permission_id
 * @property int $role_id
 * @property int $homepage_route_id
 * @property string $name
 * @property string $guard_name
 * @property string|null $alias
 * @property string|null $description
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Role|null $role
 * @property-read \Illuminate\Database\Eloquent\Collection|Role[] $roles
 * @property-read int|null $roles_count
 * @property-read Route $route
 * @method static bool|null forceDelete()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission newQuery()
 * @method static Builder|Permission onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission permission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission query()
 * @method static bool|null restore()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereAlias($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereGuardName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereHomepageRouteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission wherePermissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereRouteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereUpdatedAt($value)
 * @method static Builder|Permission withTrashed()
 * @method static Builder|Permission withoutTrashed()
 * @mixin Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceModel search($search, $threshold = null, $entireText = false, $entireTextOnly = false)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceModel searchRestricted($search, $restriction, $threshold = null, $entireText = false, $entireTextOnly = false)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceModel except($values = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceModel distance($latitude, $longitude)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceModel distanceCord($latitude, $longitude, $distance = 1)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceModel geofence($latitude, $longitude, $inner_radius, $outer_radius)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceModel disatnceCordsss($latitude, $longitude, $distance)
 * @property int|null $route_id
 * @property-read \Illuminate\Database\Eloquent\Collection|Permission[] $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection|SystemWorker[] $users
 * @property-read int|null $users_count
 * @property string $text
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceModel cordDistance($latitude, $longitude)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceModel distanceCordsACOS($latitude, $longitude, $distance)
 * @property Highlight|null $highlight
 * @method static \Illuminate\Database\Eloquent\Builder|Permission role($roles, $guard = null)
 */
class Permission extends ServiceModel implements PermissionModelContract
{
    use HasRoles;
    use RefreshRoleCache;
    use SoftDeletes;

    /**
     * @var string
     */
    protected $table = 'permissions';

    /**
     * @var string
     */
    protected $primaryKey = 'permission_id';

    /**
     * @var array
     */
    protected $fillable = [];

    /**
     * @var array
     */
    protected $guarded = ['permission_id'];

    /**
     * Permission constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->setTable(config('permission.table_names.permissions'));
    }

    /**
     * Find a permission by its name (and optionally guardName).
     *
     * @param string $name
     * @param string|null $guard_name
     *
     * @return PermissionModelContract
     * @throws ReflectionException
     */
    public static function findByName(string $name, $guard_name = null): PermissionModelContract
    {
        $guard_name = $guard_name ?? Guard::getDefaultName(static::class);
        $permission = static::getPermissions(['name' => $name, 'guard_name' => $guard_name])->first();
        if (!$permission) {
            throw PermissionDoesNotExist::create($name, $guard_name);
        }

        return $permission;
    }

    /**
     * Get the current cached permissions.
     * @param array $params
     * @return Collection
     */
    protected static function getPermissions(array $params = []): Collection
    {
        return app(RoleRegister::class)->getPermissions($params);
    }

    /**
     * @param array $attributes
     * @return Model|ServiceModel
     * @throws ReflectionException
     */
    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(static::class);

        $permission = static::getPermissions(
            [
                'name' => $attributes['name'],
                'guard_name' => $attributes['guard_name'],
            ]
        )->first();

        if ($permission) {
            throw PermissionAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        if (is_not_lumen() && app()::VERSION < '5.4') {
            return parent::create($attributes);
        }

        return static::query()->create($attributes);
    }

    /**
     * Find a permission by its id (and optionally guardName).
     *
     * @param int $id
     * @param string|null $guard_name
     *
     * @return PermissionModelContract
     * @throws ReflectionException
     */
    public static function findById(int $id, string $guard_name = null): PermissionModelContract
    {
        $guard_name = $guard_name ?? Guard::getDefaultName(static::class);
        $permission = static::getPermissions(['permission_id' => $id, 'guard_name' => $guard_name])->first();

        if (!$permission) {
            throw PermissionDoesNotExist::withId($id, $guard_name);
        }

        return $permission;
    }

    /**
     * Find or create Components permission by its name (and optionally guardName).
     *
     * @param string $name
     * @param string|null $guard_name
     *
     * @return PermissionModelContract
     * @throws ReflectionException
     */
    public static function findOrCreate(string $name, $guard_name = null): PermissionModelContract
    {
        $guard_name = $guard_name ?? Guard::getDefaultName(static::class);
        $permission = static::getPermissions(['name' => $name, 'guard_name' => $guard_name])->first();

        if (!$permission) {
            return static::query()->create(['name' => $name, 'guard_name' => $guard_name]);
        }

        return $permission;
    }

    /**
     * @return BelongsTo
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    /**
     * A permission belongs to some clients of the model associated with its guard.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(SystemWorker::class, 'worker_permission', 'permission_id', 'system_worker_id');
    }

    /**
     * @return BelongsTo
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, $this->primaryKey, 'route_id');
    }
}
