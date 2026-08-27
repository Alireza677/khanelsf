<?php
namespace App\Filament\Resources\NetworkLocationResource\Pages;
use App\Filament\Resources\NetworkLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditNetworkLocation extends EditRecord { protected static string $resource=NetworkLocationResource::class; protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; } }
