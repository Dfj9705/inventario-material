<?php

namespace App\Filament\Resources;

use App\Enums\MaterialType;
use App\Filament\Resources\MaterialResource\Pages;
use App\Filament\Resources\MaterialResource\RelationManagers\InventoryMovementsRelationManager;
use App\Filament\Resources\MaterialResource\RelationManagers\WarehouseStocksRelationManager;
use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Materiales';

    protected static ?string $modelLabel = 'material';

    protected static ?string $pluralModelLabel = 'materiales';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información general')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(
                                fn(string $state): string =>
                                strtoupper(trim($state))
                            )
                            ->extraInputAttributes([
                                'style' => 'text-transform: uppercase',
                            ]),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('category_id')
                            ->label('Categoría')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre de la categoría')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\Textarea::make('description')
                                    ->label('Descripción')
                                    ->rows(3),
                            ])
                            ->createOptionModalHeading('Nueva categoría')
                            ->required(),

                        Forms\Components\Select::make('unit_id')
                            ->label('Unidad de medida')
                            ->relationship('unit', 'name')
                            ->getOptionLabelFromRecordUsing(
                                fn($record): string =>
                                "{$record->name} ({$record->abbreviation})"
                            )
                            ->searchable(['name', 'abbreviation'])
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('type')
                            ->label('Tipo de material')
                            ->options(MaterialType::options())
                            ->native(false)
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Material activo')
                            ->default(true)
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Imagen')
                    ->schema([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Fotografía')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '4:3',
                                '1:1',
                            ])
                            ->disk('public')
                            ->directory('materials')
                            ->visibility('public')
                            ->maxSize(5120)
                        ,
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Imagen')
                    ->disk('public')
                    ->square(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Material')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock.quantity')
                    ->label('Stock')
                    ->formatStateUsing(
                        fn($state): string =>
                        $state instanceof MaterialType
                        ? $state->label()
                        : MaterialType::from($state)->label()
                    ),

                Tables\Columns\TextColumn::make('unit.abbreviation')
                    ->label('Unidad')
                    ->badge(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(
                        fn($state): string =>
                        $state instanceof MaterialType
                        ? $state->label()
                        : MaterialType::from($state)->label()
                    )
                    ->color(
                        fn($state): string =>
                        $state instanceof MaterialType
                        ? $state->color()
                        : MaterialType::from($state)->color()
                    ),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(MaterialType::options()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos')
                    ->placeholder('Todos'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('material.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('material.create') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('material.update') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('material.delete') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('material.delete') ?? false;
    }

    public static function canRestore(Model $record): bool
    {
        return auth()->user()?->can('material.restore') ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return auth()->user()?->can('material.restore') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [
            WarehouseStocksRelationManager::class,
            InventoryMovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }


}