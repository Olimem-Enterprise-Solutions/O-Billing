<?php

declare(strict_types=1);

namespace App\Filament\Pages\Tenancy;

use App\Models\Municipality;
use App\Support\Currencies;
use App\Support\DefaultSetup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * First step of onboarding: create the municipality (tenant). Captures identity,
 * the base + supported currencies, and the tax rate, then seeds a sensible
 * starting set of area types and services so the Setup Wizard has a baseline.
 */
class RegisterMunicipality extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register your municipality';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Municipality name')
                ->required()
                ->maxLength(255),
            TextInput::make('code')
                ->label('Municipality code')
                ->maxLength(50),
            Select::make('base_currency')
                ->label('Base currency')
                ->options(Currencies::OPTIONS)
                ->default('ZAR')
                ->required()
                ->live(),
            Select::make('supported_currencies')
                ->label('Additional billing currencies')
                ->helperText('Customers can be billed in any of these. The base currency is always available.')
                ->options(Currencies::OPTIONS)
                ->multiple(),
            TextInput::make('tax_rate')
                ->label('Tax rate (%)')
                ->helperText('e.g. 15 for 15% VAT. Leave 0 if rates are tax-exempt.')
                ->numeric()
                ->default(15)
                ->required(),
            TextInput::make('tax_label')
                ->label('Tax label')
                ->default('VAT')
                ->required(),
            TextInput::make('contact_email')
                ->label('Contact email')
                ->email(),
        ]);
    }

    protected function handleRegistration(array $data): Model
    {
        return DB::transaction(function () use ($data): Municipality {
            // Tax rate is entered as a percentage; store as a fraction.
            $data['tax_rate'] = (float) ($data['tax_rate'] ?? 0) / 100;

            $municipality = Municipality::create($data);

            // Link the registering user so tenant access checks pass.
            auth()->user()->municipalities()->attach($municipality);

            // Seed baseline area types + services for the Setup Wizard to refine.
            DefaultSetup::seed($municipality);

            return $municipality;
        });
    }
}
