<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;

/**
 * The image slot of a repeater row (Épica 12.1-B §7.1–§7.5).
 *
 * Extracted from the hero when `team` and `feature_sequence` needed the same
 * thing: three types uploading images through three hand-copied adapters would
 * drift, and the one that drifted would be the one nobody audited. The pipeline
 * behind it — private disk, promotion on publish, no physical delete — is the
 * one already approved in 12.1-A; this is its single entry point in the forms.
 *
 * El MÍNIMO de dimensiones es por consumidor y no tiene default: 1200×675 es
 * 16:9, correcto para un fondo o un panel y absurdo para el retrato de una
 * persona, que nadie sube apaisado. Heredarlo sin pensar rechazaba fotos
 * perfectamente buenas, así que ahora cada llamador declara la suya.
 */
class SectionImageFields
{
    /**
     * @param  \Closure(mixed): HtmlString  $preview  renders the current image
     * @return list<Component>
     */
    public static function make(
        \Closure $preview,
        int $minWidth,
        int $minHeight,
        string $shape,
        bool $required = false,
        int $previewSpan = 2,
        int $uploadSpan = 4,
    ): array {
        return [
            // QUITAR la imagen. Sin esto, subir una era irreversible: el
            // `media_id` vive en un campo oculto que el upload vacío no toca
            // —«vacío conserva la actual», dice su propia ayuda—, así que la
            // única salida era borrar la fila entera del repeater… y en las
            // ranuras sueltas, marcadas «(opcional)», no había ninguna.
            //
            // Sólo aparece con una imagen puesta y sólo donde NO es obligatoria:
            // ofrecer quitar la foto de un panel que el schema exige sería
            // ofrecer romper el guardado.
            Toggle::make('remove_media')
                ->label('Quitar la imagen')
                ->inline(false)
                ->live()
                ->dehydrated(true)
                ->visible(fn (Get $get): bool => ! $required && filled($get('media_id')))
                ->helperText('Al guardar, la sección queda sin imagen.')
                ->columnSpan(['default' => 1, 'sm' => 12]),

            // The uuid already stored for this row. Hidden because it is an
            // identifier, not something the owner should ever read or type; it
            // survives an edit that does not replace the image.
            Hidden::make('media_id'),

            Placeholder::make('preview')->hiddenLabel()
                ->content(fn (Get $get): HtmlString => $preview($get('media_id')))
                ->columnSpan(['default' => 1, 'sm' => $previewSpan]),

            // Deliberately the BASE FileUpload, not the Spatie one: the canonical
            // state of these lists is the array of media_id in the payload, not a
            // single column, and the Spatie component runs deleteAbandonedFiles()
            // on every save — which would destroy files a published revision still
            // references (§18.18). It lands straight on the private disk; nothing
            // becomes public until a publish promotes it.
            FileUpload::make('upload')
                ->label('Imagen')
                ->disk('frontend-private')
                ->directory('section-uploads')
                ->image()
                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                ->maxSize(12288)
                // Se reduce EN EL NAVEGADOR antes de viajar. Aceptar 12 MB sin
                // esto significaba mandar 12 MB por una puerta que PHP tiene en
                // 10 MB (`upload_max_filesize`): entre esos dos números el
                // archivo entraba en el formulario y moría en el servidor.
                //
                // No reemplaza al procesado del guardado —que además convierte a
                // WebP y controla la calidad—: le evita el viaje inútil. El lado
                // es 2400 y no 1920 para que el recorte del editor todavía tenga
                // píxeles de sobra antes de que el servidor ajuste al final.
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth('2400')
                ->imageResizeTargetHeight('2400')
                ->imageResizeUpscale(false)
                ->rules(["dimensions:min_width={$minWidth},min_height={$minHeight}"])
                // Required only while the row has no image yet: demanding a new
                // file on every save would force the owner to re-upload a photo
                // just to fix a typo next to it.
                ->required(fn (Get $get): bool => $required && blank($get('media_id')))
                // Una sola línea: cada renglón de ayuda que envuelve son ~20 px
                // multiplicados por la cantidad de filas del repeater. El formato
                // ya está en `acceptedFileTypes` y el navegador lo filtra solo, así
                // que enumerarlo acá era decorativo.
                // Se acepta hasta 12 MB porque la foto se procesa al guardarla:
                // se ajusta a 1920×1080 y se convierte a WebP, así que el peso
                // final no depende del que suba el owner. Antes había que
                // achicarla a mano para entrar en 3 MB.
                ->helperText(fn (Get $get): string => filled($get('media_id'))
                    ? "{$shape}, mín. {$minWidth}×{$minHeight} · vacío conserva la actual"
                    : "{$shape}, mín. {$minWidth}×{$minHeight} · hasta 12 MB, se optimiza sola")
                ->columnSpan(['default' => 1, 'sm' => $uploadSpan]),
        ];
    }
}
