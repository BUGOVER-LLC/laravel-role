<?php

declare(strict_types=1);

namespace Nucleus\Role;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Nucleus\Role\Commands\CacheReset;
use Nucleus\Role\Commands\CreatePermission;
use Nucleus\Role\Commands\CreateRole;
use Nucleus\Role\Commands\Show;
use Nucleus\Role\Contracts\PermissionModelContract as PermissionContract;
use Nucleus\Role\Contracts\RoleModelContract as RoleContract;

/**
 * Class RoleServiceProvider
 * @package App\Providers
 */
class RoleServiceProvider extends ServiceProvider
{
    /**
     * @param RoleRegister $permissionLoader
     * @param Filesystem $filesystem
     */
    public function boot(RoleRegister $permissionLoader, Filesystem $filesystem): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(
                [
                    CacheReset::class,
                    CreateRole::class,
                    CreatePermission::class,
                    Show::class,
                ]
            );
        }

        $this->registerModelBindings();

        // CONFLICT BROADCAST DASHBOARD
        $this->app->environment('production') ? $permissionLoader->registerPermissions() : null;

        $this->app->singleton(RoleRegister::class, fn($app) => $permissionLoader);
    }

    /**
     *
     */
    protected function registerModelBindings(): void
    {
        $config = $this->app->config['permission.models'];

        $this->app->bind(PermissionContract::class, $config['permission']);
        $this->app->bind(RoleContract::class, $config['role']);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerBladeExtensions();
    }

    /**
     *
     */
    protected function registerBladeExtensions(): void
    {
        $this->app->afterResolving(
            'blade.compiler',
            function (BladeCompiler $bladeCompiler) {
                $bladeCompiler->directive(
                    'role',
                    function ($arguments) {
                        [$role, $guard] = explode(',', $arguments . ',');

                        return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasRole({$role})): ?>";
                    }
                );
                $bladeCompiler->directive(
                    'elserole',
                    function ($arguments) {
                        [$role, $guard] = explode(',', $arguments . ',');

                        return "<?php elseif(auth({$guard})->check() && auth({$guard})->user()->hasRole({$role})): ?>";
                    }
                );
                $bladeCompiler->directive(
                    'endrole',
                    function () {
                        return '<?php endif; ?>';
                    }
                );

                $bladeCompiler->directive(
                    'hasrole',
                    function ($arguments) {
                        [$role, $guard] = explode(',', $arguments . ',');

                        return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasRole({$role})): ?>";
                    }
                );
                $bladeCompiler->directive(
                    'endhasrole',
                    function () {
                        return '<?php endif; ?>';
                    }
                );

                $bladeCompiler->directive(
                    'hasanyrole',
                    function ($arguments) {
                        [$roles, $guard] = explode(',', $arguments . ',');

                        return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasAnyRole({$roles})): ?>";
                    }
                );
                $bladeCompiler->directive(
                    'endhasanyrole',
                    function () {
                        return '<?php endif; ?>';
                    }
                );

                $bladeCompiler->directive(
                    'hasallroles',
                    function ($arguments) {
                        [$roles, $guard] = explode(',', $arguments . ',');

                        return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasAllRoles({$roles})): ?>";
                    }
                );
                $bladeCompiler->directive(
                    'endhasallroles',
                    function () {
                        return '<?php endif; ?>';
                    }
                );

                $bladeCompiler->directive(
                    'unlessrole',
                    function ($arguments) {
                        [$role, $guard] = explode(',', $arguments . ',');

                        return "<?php if(!auth({$guard})->check() || ! auth({$guard})->user()->hasRole({$role})): ?>";
                    }
                );
                $bladeCompiler->directive(
                    'endunlessrole',
                    function () {
                        return '<?php endif; ?>';
                    }
                );
            }
        );
    }

    /**
     *
     */
    protected function registerMacroHelpers(): void
    {
        Route::macro(
            'role',
            function ($roles = []) {
                if (!\is_array($roles)) {
                    $roles = [$roles];
                }

                $roles = implode('|', $roles);

                $this->middleware("role:$roles");

                return $this;
            }
        );

        Route::macro(
            'permission',
            function ($permissions = []) {
                if (!\is_array($permissions)) {
                    $permissions = [$permissions];
                }

                $permissions = implode('|', $permissions);

                $this->middleware("permission:$permissions");

                return $this;
            }
        );
    }
}
