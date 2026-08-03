<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;

/**
 * El peso de la letra del título y del antetítulo de una sección.
 *
 * Son SELECTORES DE TRES ESTADOS y no interruptores, y la diferencia importa:
 * un interruptor sólo sabe decir sí o no, así que al guardarlo habría que
 * escribir una de las dos respuestas en cada sección — y desde ese momento la
 * configuración del sitio dejaría de mandar sobre ella. Con el tercer estado
 * («como el sitio»), no elegir se guarda como no elegir, y cambiar la
 * tipografía general sigue moviendo todo lo que nadie tocó a mano.
 *
 * La tipografía en sí NO se elige acá. Es una decisión de marca y va una sola
 * vez en la configuración del sitio: dejar que cada sección eligiera su familia
 * es la forma más rápida de que un sitio deje de parecer uno solo.
 */
class TypographyFields
{
    /**
     * @return list<Component>
     */
    public static function make(string $statePath = 'payload'): array
    {
        return [
            self::weight("{$statePath}.title_bold", 'Título'),
            self::weight("{$statePath}.eyebrow_bold", 'Antetítulo'),
        ];
    }

    private static function weight(string $path, string $label): Select
    {
        return Select::make($path)
            ->label("Grosor del {$label}")
            ->native(false)
            ->options(['1' => 'Negrita', '0' => 'Normal'])
            // El placeholder ES el tercer estado: sin elegir, el valor viaja en
            // `null` y el compilador no guarda la clave.
            ->placeholder('Como la configuración del sitio')
            // Lo guardado es un booleano y las opciones son texto. Sin esto, una
            // sección puesta en negrita se reabre mostrando «como el sitio» —el
            // dato estaría bien y el formulario mentiría.
            ->formatStateUsing(fn ($state): ?string => $state === null || $state === ''
                ? null
                : ((bool) (int) $state ? '1' : '0'));
    }
}
