{{--
    Preview shell (RFC-077, Lote G): renders a page's WORKING DRAFT in the real
    public layout, in preview mode (noindex,nofollow + not-production banner). It
    reuses the same section dispatcher as the live site, so what the owner sees is
    exactly what publishing would produce — the only difference is the source
    (draft rows vs the published revision). Never reachable without an owner
    session; never in the sitemap.

    @var string      $title         the page's title for the <title> tag
    @var array|null  $seo           the DRAFT seo, so the preview title/meta match a publish
    @var string|null $disabledNote  a banner note when the page is currently disabled
    @var array       $sections      the draft view-model from FrontendPageRenderer::renderDraft
--}}
<x-layouts.public :title="$title" :seo="$seo" :preview="true" :preview-note="$disabledNote" :floating-whatsapp="false">
    @include('frontend.render', ['sections' => $sections])
</x-layouts.public>
