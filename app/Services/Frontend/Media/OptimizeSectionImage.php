<?php

namespace App\Services\Frontend\Media;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Throwable;

/**
 * Achica y convierte a WebP la imagen recién subida, ANTES de guardarla.
 *
 * **Por qué se procesa el original y no se usan conversiones de Spatie.** El
 * pipeline de promoción mueve UN archivo por media: copia el original al disco
 * público, lo verifica y voltea el disco de la fila. Una conversión sería un
 * segundo archivo de la misma media, y la promoción tendría que mover la
 * FAMILIA completa o dejaría el derivado privado con la URL pública a medias
 * —el propio job lo advierte—. Procesar el original mantiene el contrato
 * intacto: sigue habiendo exactamente un archivo, sólo que ya optimizado.
 *
 * **Por qué WebP.** Es el mejor equilibrio disponible hoy entre peso y calidad
 * con soporte universal: pesa alrededor de un tercio que un JPEG de calidad
 * equivalente y lo entienden todos los navegadores vigentes. AVIF comprime aún
 * mejor pero tarda mucho más en codificar, y esto corre dentro del guardado del
 * owner.
 *
 * **Qué NO hace: recortar.** Se ajusta para ENTRAR en 1920×1080 conservando la
 * proporción, nunca se recorta ni se agranda. Recortar decidiría por el owner
 * qué parte de su foto importa, y el render ya hace `object-fit: cover`. Una
 * imagen más chica que el máximo se deja como está —agrandarla sumaría peso sin
 * sumar un solo píxel de detalle.
 *
 * Si el procesamiento falla, se conserva el archivo original: una foto pesada
 * es un problema menor que un guardado que se cae.
 */
class OptimizeSectionImage
{
    /** El lado mayor que necesita un fondo a pantalla completa. */
    public const MAX_WIDTH = 1920;

    public const MAX_HEIGHT = 1080;

    /** Punto donde el peso baja fuerte y el ojo no distingue la pérdida. */
    public const QUALITY = 82;

    /**
     * Procesa el archivo en el disco privado y devuelve la ruta a usar.
     *
     * Devuelve la ruta ORIGINAL si algo falla, para que un problema de
     * optimización nunca impida guardar.
     */
    public function __invoke(string $path, string $disk = 'frontend-private'): string
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return $path;
        }

        // Se trabaja sobre una copia local: los drivers de imagen operan con
        // rutas del sistema de archivos, no con el API de Storage, y el disco
        // puede no ser local en producción.
        $origen = tempnam(sys_get_temp_dir(), 'nh_src_');
        $destino = tempnam(sys_get_temp_dir(), 'nh_out_').'.webp';

        try {
            file_put_contents($origen, $storage->get($path));

            Image::load($origen)
                // `Max` y no `Contain`: los dos conservan la proporción, pero
                // sólo `Max` lleva `DoNotUpsize`. Con `Contain`, una foto de
                // 1280×720 se estiraba a 1920×1080 — más peso, cero detalle
                // nuevo y bordes blandos.
                ->fit(Fit::Max, self::MAX_WIDTH, self::MAX_HEIGHT)
                ->format('webp')
                ->quality(self::QUALITY)
                ->save($destino);

            $optimizada = dirname($path).'/'.Str::uuid()->toString().'.webp';
            $storage->put($optimizada, file_get_contents($destino));

            Log::info('frontend.image_optimized', [
                'antes' => $storage->size($path),
                'despues' => $storage->size($optimizada),
            ]);

            // El temporal original queda para que lo limpie el barrido de
            // temporales de Filament; borrarlo acá no aporta y arriesga cortar
            // un guardado por un permiso.
            return $optimizada;
        } catch (Throwable $e) {
            // Se conserva el original: mejor una imagen pesada que un guardado
            // caído por una foto que el driver no supo leer.
            Log::warning('frontend.image_optimization_failed', [
                'path' => $path,
                'reason' => $e->getMessage(),
            ]);

            return $path;
        } finally {
            @unlink($origen);
            @unlink($destino);
        }
    }
}
