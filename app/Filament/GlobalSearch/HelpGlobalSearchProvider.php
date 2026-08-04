<?php

namespace App\Filament\GlobalSearch;

use App\Filament\Pages\Ayuda;
use Filament\GlobalSearch\DefaultGlobalSearchProvider;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;

/**
 * Extiende el buscador global de Filament (que solo indexa Resources) para que
 * también encuentre secciones del manual de Ayuda por su título o su contenido.
 *
 * El filtrado por rol lo resuelve Ayuda::globalSearchResults(), que delega en el
 * gate real de cada sección — no se replican listas de roles aquí.
 */
class HelpGlobalSearchProvider extends DefaultGlobalSearchProvider
{
    public function getResults(string $query): ?GlobalSearchResults
    {
        $results = parent::getResults($query) ?? GlobalSearchResults::make();

        $helpResults = array_map(
            fn (array $section): GlobalSearchResult => new GlobalSearchResult(
                title: $section['label'],
                url: Ayuda::getUrl(['seccion' => $section['key']]),
                // Lista (no asociativa): el snippet se muestra sin un label
                // redundante delante, ya que la categoría "Ayuda" lo encabeza.
                details: filled($section['snippet']) ? [$section['snippet']] : [],
            ),
            Ayuda::globalSearchResults($query),
        );

        if (filled($helpResults)) {
            $results->category('Ayuda', $helpResults);
        }

        return $results;
    }
}
