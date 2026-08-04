{{--
    Section dispatcher (RFC-076): renders the presenter's view-ready sections in
    order. A section is dispatched ONLY when its type is in the canonical
    registry allowlist (config/frontend-sections.php) AND a partial exists for it
    — fail-closed defense in depth (the publisher already validates the registry,
    but the render frontier re-checks so an unexpected type never resolves an
    arbitrary view). Anything else is skipped in silence; the render never throws.

    @var array $sections  list of ['key'=>, 'type'=>, 'data'=>]
--}}
@php $allowedTypes = array_keys((array) config('frontend-sections.types')); @endphp
@foreach ($sections as $section)
    @if (in_array($section['type'], $allowedTypes, true) && view()->exists('frontend.sections.'.$section['type']))
        @include('frontend.sections.'.$section['type'], ['s' => $section['data'], 'sectionKey' => $section['key']])
    @endif
@endforeach
