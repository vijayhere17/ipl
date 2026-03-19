<?php

namespace App\Filament\Resources\Withdrawals\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use App\Models\WalletTransaction;

class WithdrawalsTable
{
    public static function configure(Table $table): Table
    {
        return $table

            ->defaultSort('created_at', 'desc')

            ->columns([

                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),

                TextColumn::make('amount')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('wallet_address')
                    ->label('Wallet Address')
                    ->copyable(),

                TextColumn::make('network')
                    ->badge(),

                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                TextColumn::make('created_at')
                    ->label('Requested At')
                    ->dateTime(),

            ])

            ->filters([

                SelectFilter::make('status')
                    ->label('Withdrawal Status')
                    ->options([
                        'pending' => 'Pending Withdrawals',
                        'approved' => 'Approved Withdrawals',
                        'rejected' => 'Rejected Withdrawals',
                    ])

            ])

            ->actions([

                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {

                        $record->update([
                            'status' => 'approved',
                            'processed_at' => now()
                        ]);

                        WalletTransaction::create([
                            'user_id' => $record->user_id,
                            'type' => 'withdrawal',
                            'wallet_type' => 'winning',
                            'amount' => $record->amount,
                            'reference_id' => $record->id,
                            'description' => 'Withdrawal approved'
                        ]);
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {

                        $wallet = \App\Models\Wallet::where('user_id', $record->user_id)->first();

                        if ($wallet) {
                            $wallet->winning_balance += $record->amount;
                            $wallet->save();
                        }

                        $record->update([
                            'status' => 'rejected',
                            'processed_at' => now()
                        ]);
                    }),
            ]);
    }
}