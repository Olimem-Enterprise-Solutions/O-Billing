<?php

namespace App\Providers;

use App\Support\Tenancy\CurrentMunicipality;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentMunicipality::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Bigger page-size choices on every table (some, like Properties, run to
        // thousands of rows), with page navigation, so more items load per page.
        Table::configureUsing(function (Table $table): void {
            $table
                ->paginationPageOptions([10, 25, 50, 100])
                ->defaultPaginationPageOption(25);
        });
    }
}
