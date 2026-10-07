<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Schema::defaultStringLength(191);

        /*
         * Actualización masiva que pasa por cada modelo para que la bitácora
         * registre quién cambió cada registro (update() directo no dispara eventos).
         * Devuelve cuántos registros se actualizaron.
         */
        Builder::macro('updateEach', function (array $values): int {
            /** @var Builder $this */
            return $this->get()->each(fn ($model) => $model->forceFill($values)->save())->count();
        });
    }
}
