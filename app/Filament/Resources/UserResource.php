<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use DragonCode\Contracts\Cashier\Auth\Auth;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TogglesColumn;
use Illuminate\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Gate as FacadesGate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $label = 'Data Pengguna';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\TextInput::make('name')
                ->label('Nama')
                ->minLength('3')
                ->required(),
                Forms\Components\TextInput::make('email')->required(),
                Forms\Components\TextInput::make('password')->label('Kata sandi')
                    ->password()->revealable()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),    
                Forms\Components\Select::make('role')->label('Level akses')
                ->relationship('roles', 'name')->required(),
                Forms\Components\Toggle::make('isActive')->label('Status Akun'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable(),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('roles.name')->label('Level akses'),
                Tables\Columns\IconColumn::make('isActive')->label('Status aktif')->boolean(),
                // ->trueIcon('heroicon-s-eye')   // Mata terbuka (aktif)
                // ->falseIcon('heroicon-s-eye-slash') // Mata tertutup (nonaktif)
                // ->action(function ($record, $column) {
                //     $name = $column->getName();
                //     $record->update([
                //         $name => !$record->$name, // Toggle nilai isActive
                //     ]);
                // }),
                // ->action(function ($record, $column){
                //     $name = $column->getName();
                //     $record->update([
                //         $name => !$record->$name
                //     ]);
                // }),
                Tables\Columns\IconColumn::make('eyeToggle') // Kolom baru, bukan 'isActive'
                ->label('Ubah Status')
                // ->visible(fn () => FacadesGate::allows('viewUbahStatus', Filament::auth()->user()))
                ->icon(fn ($record) => $record->isActive ? 'heroicon-s-eye' : 'heroicon-s-eye-slash') // Ikon mata terbuka/tutup
                ->color(fn ($record) => $record->isActive ? 'success' : 'danger') // Warna ikon
                ->action(function ($record) {
                    if ($record->role === 'admin') { // Ubah sesuai kolom role Anda
                        // Abort dengan pesan atau Anda bisa mengembalikan error response
                        Notification::make()
                        ->title('Aksi Tidak Diizinkan')
                        ->body('User dengan role Admin tidak dapat dinonaktifkan.')
                        ->warning()
                        ->send();
                        return;
                    }
                    // Toggle nilai isActive
                    $record->update([
                        'isActive' => !$record->isActive, // Men-toggle nilai isActive
                    ]);
                })
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
