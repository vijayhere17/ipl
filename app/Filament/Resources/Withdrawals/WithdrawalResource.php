<?php

namespace App\Filament\Resources\Withdrawals;

use App\Filament\Resources\Withdrawals\Pages\ListWithdrawals;
use App\Filament\Resources\Withdrawals\Tables\WithdrawalsTable;
use App\Models\Withdrawal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WithdrawalResource extends Resource
{
    protected static ?string $model = Withdrawal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Withdrawals';

    protected static ?string $modelLabel = 'Withdrawal';

    protected static ?string $pluralModelLabel = 'Withdrawals';

    public static function table(Table $table): Table
    {
        return WithdrawalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWithdrawals::route('/'),
        ];
    }
}