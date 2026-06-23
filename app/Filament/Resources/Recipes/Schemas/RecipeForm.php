<?php

namespace App\Filament\Resources\Recipes\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RecipeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                Textarea::make('steps')
                    ->label('Langkah (satu langkah per baris)')
                    ->required()
                    ->rows(8)
                    // model stores array<string>; textarea edits as newline-joined text
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : $state),
                FileUpload::make('image_url')->image()->directory('recipes')->nullable(),
                TextInput::make('servings')->numeric()->nullable(),
                Select::make('source')->options([
                    'seed' => 'Seed',
                    'ai' => 'AI',
                ])->default('seed')->required(),
                Select::make('ingredients')
                    ->relationship('ingredients', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->createOptionForm([
                        TextInput::make('name')->required(),
                    ]),
            ]);
    }
}
