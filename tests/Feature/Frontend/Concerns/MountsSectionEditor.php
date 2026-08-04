<?php

namespace Tests\Feature\Frontend\Concerns;

use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\HeroRelationManager;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendSection;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Monta el bloque del panel que edita una sección.
 *
 * La portada vive en su PROPIO bloque, arriba y fuera del listado que se
 * acomoda con flechas: una fila dentro de una lista ordenable se lee como algo
 * que se puede mover, y la portada es fija. Cada bloque mira sólo sus filas, así
 * que montar el hero sobre el listado de secciones no encuentra el registro.
 *
 * Elegir el bloque acá y no en cada test evita que la próxima prueba del hero
 * tenga que acordarse de esta distinción — el formulario es el mismo en los dos.
 */
trait MountsSectionEditor
{
    protected function sectionEditor(FrontendSection $section): Testable
    {
        return Livewire::test(
            $section->type === 'hero' ? HeroRelationManager::class : SectionsRelationManager::class,
            ['ownerRecord' => $section->page, 'pageClass' => EditFrontendPage::class],
        );
    }
}
