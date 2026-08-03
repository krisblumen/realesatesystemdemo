<?php

namespace App\Filament\Widgets;

use App\Enums\LonaUnitStatus;
use App\Enums\OperationType;
use App\Models\LonaUnit;
use App\Models\Property;
use App\Models\User;
use App\Services\Lonas\LonaEligibilityService;
use App\Services\Lonas\LonaRequestService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Validation\ValidationException;

/**
 * Tabla de lonas del agente autenticado en su página "Mis Lonas". Se excluye del
 * auto-descubrimiento ($isDiscovered = false) para NO aparecer en el dashboard general;
 * sólo se registra explícitamente como header widget de AgentLonas.
 */
class AgentLonaUnitsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    // Sin esto Filament deriva el título del nombre de la clase y muestra
    // "Agent Lona Units" en inglés dentro de una interfaz en español.
    protected static ?string $heading = 'Mis lonas asignadas';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('agente') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                LonaUnit::query()
                    ->where('agent_id', auth()->id())
                    ->with(['batch', 'property'])
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('operation_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (OperationType $state): string => $state->label())
                    ->color(fn (OperationType $state): string => $state->color()),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (LonaUnitStatus $state): string => $state->label())
                    ->color(fn (LonaUnitStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('property.title')
                    ->label('Colocada en')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('ubicacion_referencia')
                    ->label('Referencia')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('placed_at')
                    ->label('Colocada el')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente'),
            ])
            ->headerActions([
                $this->requestMoreAction(OperationType::Venta),
                $this->requestMoreAction(OperationType::Renta),
            ])
            ->actions([
                Tables\Actions\Action::make('registerEvidence')
                    ->label('Registrar evidencia')
                    ->icon('heroicon-o-camera')
                    ->visible(fn (LonaUnit $record): bool => ! $record->isPlaced())
                    ->modalHeading('Registrar colocación')
                    ->modalContent(fn (LonaUnit $record) => view(
                        'filament.lonas.evidence-modal',
                        ['unit' => $record],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ]);
    }

    private function requestMoreAction(OperationType $type): Tables\Actions\Action
    {
        $disponible = $this->availableToRequest($type);
        $eligible = $disponible > 0;

        return Tables\Actions\Action::make('requestMore'.ucfirst($type->value))
            ->label('Solicitar más '.$type->label())
            ->icon('heroicon-o-plus')
            ->color($type->color())
            ->disabled(! $eligible)
            ->tooltip($eligible ? null : 'Coloca lonas de este tipo con evidencia para liberar cupo (máximo '.LonaEligibilityService::CAP_PER_TYPE.' sin colocar), o ya tienes una solicitud pendiente.')
            ->form([
                Forms\Components\TextInput::make('cantidad')
                    ->label('Cantidad')
                    ->helperText('Puedes solicitar hasta '.$disponible.' lona(s) de '.$type->label().' ahora.')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue($disponible)
                    ->required(),
                Forms\Components\Select::make('property_id')
                    ->label('Inmueble sugerido para el QR (opcional)')
                    ->options(fn (): array => $this->ownPublishedPropertyOptions())
                    ->searchable()
                    ->nullable(),
            ])
            ->action(function (array $data) use ($type): void {
                $agent = auth()->user();

                if (! $agent instanceof User) {
                    return;
                }

                $property = ! empty($data['property_id'])
                    ? Property::query()->find($data['property_id'])
                    : null;

                try {
                    app(LonaRequestService::class)->submit($agent, $type, (int) $data['cantidad'], $property);

                    Notification::make()
                        ->success()
                        ->title('Solicitud enviada')
                        ->body('Tu solicitud de lonas de '.$type->label().' fue enviada para aprobación.')
                        ->send();
                } catch (ValidationException $e) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo enviar la solicitud')
                        ->body(collect($e->errors())->flatten()->first())
                        ->send();
                }
            });
    }

    private function availableToRequest(OperationType $type): int
    {
        $agent = auth()->user();

        if (! $agent instanceof User) {
            return 0;
        }

        return app(LonaEligibilityService::class)->availableToRequest($agent, $type);
    }

    /** @return array<int, string> */
    private function ownPublishedPropertyOptions(): array
    {
        return Property::query()
            ->published()
            ->where('agent_id', auth()->id())
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }
}
