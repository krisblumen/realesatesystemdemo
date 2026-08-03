<?php

namespace App\Actions\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;

/**
 * Creates the five canonical pages and their registry sections (RFC-075).
 *
 * Insert-if-missing and idempotent (firstOrCreate), never destructive: the
 * migration, a seeder or a test can run it repeatedly without clobbering a page
 * or section an owner already edited. Production seeds through this action (the
 * migration invokes it); the seeder is dev/test only.
 *
 * Sections are seeded as DRAFT with a null payload and enabled: page(key) serves
 * the §16.7 fallback until the owner publishes, and the rows give the owner UI
 * something to edit. Content lives in the section draft; the render never reads
 * it directly — it reads the published snapshot or the fallback.
 */
class SeedFrontendPages
{
    public function run(): void
    {
        foreach ((array) config('frontend-sections.pages') as $pageKey => $sections) {
            $page = FrontendPage::query()->firstOrCreate(
                ['key' => $pageKey],
                ['is_enabled' => true],
            );

            $order = 0;
            foreach ($sections as $sectionKey => $type) {
                FrontendSection::query()->firstOrCreate(
                    ['frontend_page_id' => $page->id, 'section_key' => $sectionKey],
                    ['type' => $type, 'payload' => null, 'is_enabled' => true, 'sort_order' => $order],
                );
                $order++;
            }
        }
    }
}
