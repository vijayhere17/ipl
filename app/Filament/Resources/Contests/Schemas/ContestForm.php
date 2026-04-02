<?php

namespace App\Filament\Resources\Contests\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use App\Models\CricketMatch;

class ContestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('name')
                    ->label('Contest Name')
                    ->required(),

                TextInput::make('contest_badge')
                    ->label('Contest Badge')
                    ->placeholder('Example: Free Bike'),

               Select::make('cricket_match_id')
    ->label('Match')
    ->options(
        CricketMatch::where('status', 'upcoming')
            ->orderBy('match_start_time', 'asc')
            ->get()
            ->mapWithKeys(fn ($match) => [
                $match->id =>
                    $match->team_1 . ' vs ' . $match->team_2 .
                    ' (' . $match->match_start_time->format('d M h:i A') . ')'
            ])
    )
    ->searchable()
    ->required(),

                Select::make('contest_type')
                    ->options([
                        'public' => 'Public',
                        'private' => 'Private',
                    ])
                    ->required(),

                TextInput::make('entry_fee')
                    ->label('Entry Fee')
                    ->numeric()
                    ->required(),

                TextInput::make('total_slots')
                    ->label('Total Slots')
                    ->numeric()
                    ->required(),

                TextInput::make('prize_pool')
                    ->label('Prize Pool')
                    ->numeric()
                    ->required(),

                /*
                |--------------------------------------------------------------------------
                | Prize Distribution
                |--------------------------------------------------------------------------
                */

                Repeater::make('prizes')
                    ->relationship()
                    ->label('Prize Distribution')
                    ->schema([

                        TextInput::make('rank_from')
                            ->label('Rank From')
                            ->numeric()
                            ->required(),

                        TextInput::make('rank_to')
                            ->label('Rank To')
                            ->numeric()
                            ->required(),

                        TextInput::make('prize_amount')
                            ->label('Prize Amount')
                            ->numeric()
                            ->required(),

                        TextInput::make('extra_prize')
                            ->label('Extra Prize')
                            ->placeholder('Bike, iPhone etc')
                            ->nullable(),

                    ])
                    ->columns(4)
                    ->defaultItems(1),

                TextInput::make('first_prize')
                    ->label('First Prize')
                    ->placeholder('Example: ₹10,000 + Bike'),

                TextInput::make('total_winners')
                    ->label('Total Winners')
                    ->numeric()
                    ->required(),

                TextInput::make('max_team_per_user')
                    ->label('Max Teams Per User')
                    ->numeric()
                    ->default(1)
                    ->required(),

                TextInput::make('platform_fee')
                    ->label('Platform Fee')
                    ->numeric()
                    ->required(),

                Toggle::make('is_guaranteed')
                    ->label('Guaranteed Contest')
                    ->default(true),

                Select::make('status')
                    ->options([
                        'upcoming' => 'Upcoming',
                        'live' => 'Live',
                        'completed' => 'Completed',
                    ])
                    ->default('upcoming')
                    ->required(),

                Toggle::make('is_prize_distributed')
                    ->label('Prize Distributed')
                    ->default(false),

            ]);
    }
}