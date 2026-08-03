<?php

namespace App\Filament\Forms\Components;

use App\Support\Frontend\CtaResolver;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;

/**
 * The three fields of a CTA value object — `{label, type, target}` of RFC-073 —
 * as ONE shared definition (Épica 12.1 §6.2).
 *
 * It used to be a private method of FrontendSettingsPage plus a second private
 * method for the guidance, which meant every new consumer had to copy both or
 * silently ship a different experience: a `type` without `->live()`, a `target`
 * without help, or a fourth wording of the same instruction. The design audit
 * flagged exactly that (M-3), so the pair lives here together.
 *
 * The UI is NOT the authority: whatever the owner types is validated
 * server-side by {@see CtaResolver} at the save boundary.
 * These fields only make the right answer easy to give.
 */
class CtaFields
{
    /** What the owner may pick as a destination type. */
    public const TYPES = [
        'route' => 'Página del sitio',
        'url' => 'URL externa (https)',
        'whatsapp' => 'WhatsApp',
        'mailto' => 'Correo',
        'tel' => 'Teléfono',
    ];

    /**
     * @param  string  $prefix  state path prefix; `''` for a repeater item, or e.g. `primary_cta`
     * @param  bool  $withLabel  false when the consumer supplies its own label field
     * @return list<Component>
     */
    public static function make(string $prefix = '', bool $withLabel = true): array
    {
        $p = $prefix === '' ? '' : $prefix.'.';

        $fields = [];

        if ($withLabel) {
            $fields[] = TextInput::make($p.'label')->label('Texto')->maxLength(40)
                ->placeholder('Texto del botón');
        }

        // `->live()` is what makes the guidance below react. Without it the
        // helper text freezes on whatever was selected when the form loaded.
        $fields[] = Select::make($p.'type')->label('Tipo')->native(false)->live()
            ->options(self::TYPES)
            ->placeholder('Elige el tipo de destino');

        $fields[] = TextInput::make($p.'target')->label('Destino')->maxLength(255)
            ->placeholder(fn (Get $get): string => self::guidance($get($p.'type'))['placeholder'])
            ->helperText(fn (Get $get): string => self::guidance($get($p.'type'))['help']);

        return $fields;
    }

    /**
     * What to write in «Destino», in the owner's words, for the chosen type.
     * A single generic sentence would be useless: «destino» means a route key,
     * an https URL, a phone number or an email depending on the type above.
     *
     * @return array{help: string, placeholder: string}
     */
    public static function guidance(?string $type): array
    {
        return match ($type) {
            'route' => [
                'help' => 'Escribe el nombre de una página del sitio: home, nosotros, servicios, proyectos, inmuebles, inversionistas o contacto.',
                'placeholder' => 'nosotros',
            ],
            'url' => [
                'help' => 'Pega la dirección completa de la página externa, empezando con https://',
                'placeholder' => 'https://ejemplo.com/pagina',
            ],
            'whatsapp' => [
                'help' => 'Escribe el número de WhatsApp con lada de país, solo dígitos (sin +, espacios ni guiones).',
                'placeholder' => '524421234567',
            ],
            'mailto' => [
                'help' => 'Escribe el correo electrónico al que quieres que escriban.',
                'placeholder' => 'hola@tudominio.com',
            ],
            'tel' => [
                'help' => 'Escribe el número telefónico, solo dígitos (incluye la lada).',
                'placeholder' => '4421234567',
            ],
            default => [
                'help' => 'Primero elige el tipo de enlace arriba y aquí te diré exactamente qué escribir.',
                'placeholder' => 'Elige un tipo de enlace…',
            ],
        };
    }
}
