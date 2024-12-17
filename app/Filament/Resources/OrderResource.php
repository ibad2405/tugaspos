<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                    
                 
                        Forms\Components\TextInput::make('user_id')
                            ->label('Nama Kasir')
                            ->default(Filament::auth()->user()->name)
                            ->disabled(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Pelanggan')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('gender')
                            ->label('Jenis kelamin')
                            ->options([
                                'male' => 'Laki-laki',
                                'female' => 'Perempuan',
                            ])
                            ->required(),
                    
                
               

                Forms\Components\Section::make('Produk dipesan')
                    ->schema([
                        self::getItemsRepeater(),
                    ]),

                
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make()
                        ->schema([
                            Forms\Components\TextInput::make('total_price')
                            ->required()
                            ->readOnly()
                            ->prefix('Rp.')
                            ->numeric(),
                        Forms\Components\Textarea::make('note')
                            ->columnSpanFull(),
                        ])
                    ]),
                
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Pembayaran')
                        ->schema([        
                            Forms\Components\Select::make('payment_method_id')
                                ->relationship('paymentMethod', 'name')
                                ->reactive()
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get){
                                    $paymentMethod = PaymentMethod::find($state);
                                    $set('isCash', $paymentMethod?->isCash ?? false);
                                    if(!$paymentMethod->isCash){
                                        $set('changeAmount', 0);
                                        $set('paidAmount', $get('total_price'));

                                    }
                                })
                                ->afterStateHydrated(function (Forms\Set $set, Forms\Get $get, $state){
                                    $paymentMethod = PaymentMethod::find($state);
                                    if(!$paymentMethod->isCash){
                                        $set('paidAmount', $get('total_price'));
                                        $set('changeAmount', 0);
                                    }

                                    $set('isCash', $paymentMethod?->isCash ?? false);
                                })
                                ->default(1),
                            Forms\Components\TextInput::make('paidAmount')
                                ->label('Jumlah yang dibayar')
                                ->numeric()
                                ->reactive()
                                ->readOnly(fn (Forms\Get $get) => $get('isCash') == false)
                                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state){
                                    // Menghitung uang kembalian
                                    self::UpdateExchangePaid($get, $set);
                                }),
                            Forms\Components\TextInput::make('changeAmount')
                                ->label('Kembalian')
                                ->numeric()
                            ])
                    ]),
                
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gender'),
                Tables\Columns\TextColumn::make('total_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paidAmount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('changeAmount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                    // ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getItemsRepeater(): Repeater
    {
        return Repeater::make('orderProducts')
            ->label('Produk')
            ->relationship()
            ->live()
            ->columns([
                'md' => 10,
            ])
            ->afterStateUpdated(function(Forms\Get $get, Forms\Set $set){
                self::UpdateTotalPrice($get, $set);
            })
            ->schema([
                
                Forms\Components\Select::make('product_id')
                ->label('Produk')
                ->required()
                ->options(Product::query()->where('stock', '>', 1)->pluck('name', 'id'))
                ->columnSpan([
                    'md' => 5,
                ])
                ->afterStateUpdated(function($state, Forms\Set $set, Forms\Get $get){
                    $product = Product::find($state);
                    $set('stock', $product->stock);
                    $set('unit_price', $product->price);
                })
                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                Forms\Components\TextInput::make('quantity')
                ->numeric()
                ->required()
                ->default(1)
                ->minValue(1)
                ->columnSpan([
                    'md' => 1,
                ])
                ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set){
                    $stock = $get('stock');
                    if($state > $stock ){
                        $set('quantity', $stock);
                        
                        Notification::make()
                        ->title('Stok tidak mencukupi')
                        ->warning()
                        ->send();
                    }
                    self::UpdateTotalPrice($get, $set);
                }),

                Forms\Components\TextInput::make('stock')
                ->numeric()
                ->required()
                ->readOnly()
                ->columnSpan([
                    'md' => 1,
                ]),
                

                Forms\Components\TextInput::make('unit_price')
                ->label('Harga saat ini')
                ->numeric()
                ->required()
                ->readOnly()
                ->columnSpan([
                    'md' => 2,
                ]),
               
            ]);
    }

    protected static function UpdateTotalPrice(Forms\Get $get, Forms\Set $set) : void{
        $selectedProducts = collect($get('orderProducts'))->filter(fn($item) => !empty($item['product_id']) && !empty($item['quantity']));

        $prices = Product::find($selectedProducts->pluck('product_id'))->pluck('price', 'id');
        $total = $selectedProducts->reduce(function ($total, $product) use ($prices) {
            return $total + ($prices[$product['product_id']] * $product['quantity']);
        }, 0);
        
        $set('total_price', $total);
    }

    protected static function UpdateExchangePaid(Forms\Get $get, Forms\Set $set): void
    {
        $paidAmount = (int) $get('paidAmount') ?? 0;
        $totalPrice = (int) $get('total_price') ?? 0;
        //$exchangePaid = $paidAmount - $totalPrice;
         $exchangePaid = max(0, $paidAmount - $totalPrice); // Menghindari nilai negatif
        $set('changeAmount', $exchangePaid);
    }
}
