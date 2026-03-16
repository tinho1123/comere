<?php

namespace App\Filament\Master\Resources;

use App\Filament\Master\Resources\CompanyResource\Pages;
use App\Filament\Master\Resources\CompanyResource\RelationManagers\UsersRelationManager;
use App\Models\Company;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Lojas';

    protected static ?string $modelLabel = 'Loja';

    protected static ?string $pluralModelLabel = 'Lojas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nome da loja')
                ->required()
                ->maxLength(255),

            TextInput::make('admin_email')
                ->label('E-mail do administrador')
                ->email()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->maxLength(255)
                ->visibleOn('create'),

            TextInput::make('admin_password')
                ->label('Senha do administrador')
                ->password()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->minLength(8)
                ->visibleOn('create'),

            FileUpload::make('logo_path')
                ->label('Logo')
                ->image()
                ->disk('public')
                ->directory(fn ($record) => 'store/'.($record?->uuid ?? 'temp').'/images/logo')
                ->maxSize(512)
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth(400)
                ->imageResizeTargetHeight(400)
                ->imageResizeUpscale(false)
                ->getUploadedFileNameForStorageUsing(
                    fn (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file) => \Illuminate\Support\Str::uuid().'.'.$file->getClientOriginalExtension()
                )
                ->visibleOn('edit'),

            FileUpload::make('banner_path')
                ->label('Banner')
                ->image()
                ->disk('public')
                ->directory(fn ($record) => 'store/'.($record?->uuid ?? 'temp').'/images/banner')
                ->maxSize(2048)
                ->imageResizeMode('cover')
                ->imageResizeTargetWidth(1280)
                ->imageResizeTargetHeight(480)
                ->imageResizeUpscale(false)
                ->getUploadedFileNameForStorageUsing(
                    fn (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file) => \Illuminate\Support\Str::uuid().'.'.$file->getClientOriginalExtension()
                )
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('users.email')
                    ->label('Administrador')
                    ->searchable(),

                IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Criada em')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
