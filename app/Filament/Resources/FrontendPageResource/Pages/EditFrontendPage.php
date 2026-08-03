<?php

namespace App\Filament\Resources\FrontendPageResource\Pages;

use App\Filament\Resources\FrontendPageResource;
use App\Models\FrontendPage;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendPagePublisher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class EditFrontendPage extends EditRecord
{
    protected static string $resource = FrontendPageResource::class;

    /** The draft_revision this screen loaded — sent verbatim to the publisher. */
    public ?int $loadedDraftRevision = null;

    /**
     * Qué página se está editando, dicho en el título.
     *
     * Las cinco páginas usan esta misma pantalla, y el encabezado salía del
     * nombre del MODELO: «Editar Página Del Sitio» en las cinco. Nada indicaba
     * cuál estabas tocando, y las secciones de abajo tampoco alcanzan para
     * deducirlo — varias páginas comparten los mismos tipos.
     */
    public function getTitle(): string
    {
        $pagina = $this->record;

        return 'Editar Página Del Sitio: '.($pagina instanceof FrontendPage ? $pagina->label() : '');
    }

    protected function afterFill(): void
    {
        // Capture the revision AT LOAD (M-E1): publishing sends this, not the
        // value at click time, so a screen that has gone stale — because another
        // session edited the draft — is refused instead of overwriting it.
        $this->loadedDraftRevision = $this->record->draft_revision;
    }

    /**
     * La sección se guardó en ESTA pantalla: la revisión que lleva el botón
     * Publicar queda al día.
     *
     * El versionado optimista sigue intacto para lo que importa —otra sesión que
     * edite el mismo borrador NO dispara este evento, así que su cambio sigue
     * frenando la publicación—. Lo que deja de pasar es que el owner se frene a
     * sí mismo por un cambio que acaba de hacer y de ver.
     */
    #[On('borrador-actualizado')]
    public function refreshLoadedDraftRevision(): void
    {
        $this->loadedDraftRevision = $this->record->refresh()->draft_revision;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                // El botón CAMBIA según haya o no trabajo pendiente. Antes se
                // veía idéntico en los dos casos, así que quien guardaba una
                // sección y cerraba no tenía forma de saber que faltaba un paso
                // — el sitio seguía mostrando lo anterior y parecía un bug.
                ->label(fn (): string => $this->record->hasUnpublishedChanges()
                    ? 'Publicar cambios'
                    : 'Publicar')
                ->color(fn (): string => $this->record->hasUnpublishedChanges() ? 'warning' : 'primary')
                ->badge(fn (): ?string => $this->record->hasUnpublishedChanges() ? 'pendiente' : null)
                ->icon('heroicon-o-rocket-launch')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => $this->record->hasUnpublishedChanges()
                    ? 'El sitio pasará a mostrar lo que guardaste en el borrador.'
                    : 'No hay cambios pendientes: se volverá a publicar el contenido actual.')
                ->action(function (): void {
                    /** @var FrontendPage $page */
                    $page = $this->record;

                    try {
                        app(FrontendPagePublisher::class)->publish($page, (int) $this->loadedDraftRevision, auth()->user());

                        // Se refresca el REGISTRO, no una copia: la etiqueta y el
                        // color del botón se calculan sobre `$this->record`, así
                        // que con una copia el botón seguía diciendo «pendiente»
                        // después de una publicación exitosa — la misma clase de
                        // confusión que este arreglo vino a resolver.
                        $this->record->refresh();
                        $this->loadedDraftRevision = $this->record->draft_revision;
                        Notification::make()->title('Página publicada')->success()->send();
                    } catch (ValidationException $e) {
                        Notification::make()->title('No se pudo publicar')
                            ->body(collect($e->errors())->flatten()->first())->danger()->send();
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {
        // Enabling/disabling a page changes the public render; invalidate.
        app(FrontendCacheGeneration::class)->bump();
    }
}
