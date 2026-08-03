<div class="mt-2">
    <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
        {{ $this->getSectionTitle() }}
    </h2>

    @if ($this->getSectionDescription())
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $this->getSectionDescription() }}
        </p>
    @endif
</div>
