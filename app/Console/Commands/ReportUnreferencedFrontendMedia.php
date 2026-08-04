<?php

namespace App\Console\Commands;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\FrontendService;
use App\Services\Frontend\FrontendPageContentService;
use App\Services\Frontend\Media\ServiceMediaReference;
use App\Services\Frontend\PublishedMediaReference;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * READ-ONLY inventory of editorial media nothing references any more (§16.4,
 * "marcado, no borrado").
 *
 * v1 deliberately has NO physical media deletion (§18.13): replacing an image,
 * dropping a slide or soft-deleting a section leaves the file on disk. That is
 * an accepted, measured trade-off — the risk of deleting a file a published
 * revision still uses is worse than the disk it costs. This command exists to
 * MEASURE that cost with real numbers, so the decision to build a deletion epic
 * can be taken on data instead of intuition.
 *
 * It reports. It never writes, never deletes, and must never be turned into a
 * prune: the scheduler is explicitly forbidden from running anything that
 * deletes files (§16.4).
 */
class ReportUnreferencedFrontendMedia extends Command
{
    protected $signature = 'frontend:media:report-unreferenced';

    protected $description = 'List frontend section and service media referenced by nothing live (read-only).';

    public function handle(FrontendPageContentService $content, PublishedMediaReference $published): int
    {
        // Referenced by any DRAFT payload — including soft-deleted sections,
        // whose payload can still be restored.
        $referenced = [];

        FrontendSection::withTrashed()->orderBy('id')->cursor()
            ->each(function (FrontendSection $section) use ($content, &$referenced): void {
                foreach ($content->mediaIds($section->payload) as $uuid) {
                    $referenced[$uuid] = true;
                }
            });

        // Referenced by any PUBLISHED revision.
        FrontendPage::query()->whereNotNull('published_revision')->orderBy('id')->cursor()
            ->each(function (FrontendPage $page) use ($published, &$referenced): void {
                foreach ($published->mediaIdsOf($page) as $uuid) {
                    $referenced[$uuid] = true;
                }
            });

        // Referenciada por la columna de un servicio VIVO (Épica 12.3 §9.1).
        // Es el instrumento que mide la deuda residual que el diseño declara: sin
        // esto, «aceptamos que el histórico siga público» es una frase sin
        // evidencia, y nadie puede decidir una limpieza con un número.
        FrontendService::query()->whereNotNull('image_media_id')->orderBy('id')->cursor()
            ->each(function (FrontendService $service) use (&$referenced): void {
                $referenced[(string) $service->image_media_id] = true;
            });

        $rows = [];
        $totalBytes = 0;

        Media::query()
            ->where(fn ($q) => $q
                ->where(fn ($s) => $s
                    ->where('model_type', (new FrontendSection)->getMorphClass())
                    ->where('collection_name', PublishedMediaReference::COLLECTION))
                ->orWhere(fn ($s) => $s
                    ->where('model_type', (new FrontendService)->getMorphClass())
                    ->where('collection_name', ServiceMediaReference::COLLECTION)))
            ->orderBy('id')
            ->cursor()
            ->each(function (Media $media) use ($referenced, &$rows, &$totalBytes): void {
                if (isset($referenced[(string) $media->uuid])) {
                    return;
                }

                $totalBytes += (int) $media->size;

                $rows[] = [
                    $media->uuid,
                    $media->file_name,
                    $media->disk,
                    class_basename((string) $media->model_type),
                    number_format(((int) $media->size) / 1024, 1).' KB',
                    $media->created_at?->diffForHumans() ?? '—',
                ];
            });

        if ($rows === []) {
            $this->info('No unreferenced frontend media.');

            return self::SUCCESS;
        }

        $this->table(['uuid', 'file', 'disk', 'owner', 'size', 'age'], $rows);
        $this->info(sprintf(
            '%d unreferenced file(s), %s MB total. Nothing was deleted (§18.13).',
            count($rows),
            number_format($totalBytes / 1048576, 2),
        ));

        return self::SUCCESS;
    }
}
