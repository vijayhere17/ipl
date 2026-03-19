<?php

namespace App\Filament\Resources\Contests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('name')
                    ->label('Contest Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('match.team_1')
                    ->label('Team 1'),

                TextColumn::make('match.team_2')
                    ->label('Team 2'),

                BadgeColumn::make('contest_type')
                    ->colors([
                        'success' => 'public',
                        'warning' => 'private',
                    ]),

                TextColumn::make('entry_fee')
                    ->money('INR'),

                TextColumn::make('prize_pool')
                    ->label('Prize'),

                TextColumn::make('filled_slots')
                    ->label('Slots')
                    ->formatStateUsing(fn ($record) =>
                        $record->filled_slots . ' / ' . $record->total_slots
                    ),

                BadgeColumn::make('status')
                    ->colors([
                        'primary' => 'upcoming',
                        'warning' => 'live',
                        'success' => 'completed',
                    ]),

            ])

            ->recordActions([

                EditAction::make(),

                Action::make('complete')
                    ->label('Mark Completed')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record) => $record->status !== 'completed')
                    ->action(function ($record) {

                        $record->update([
                            'status' => 'completed'
                        ]);

                        if ($record->match) {
                            $record->match->update([
                                'is_locked' => true
                            ]);
                        }
                    }),

            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}