<x-filament-widgets::widget>
    <x-filament::section heading="Mis zonas asignadas">
        @php($apiKey = config('services.google_maps.key'))
        @php($palette = ['#dc2626', '#2563eb', '#16a34a', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#65a30d'])
        @php($zones = collect($this->getZoneMaps())->values()->map(fn (array $z, int $i): array => [
            'name' => $z['name'],
            'municipality' => $z['municipality'],
            'color' => $palette[$i % count($palette)],
            'geometry' => $z['geojson'] ? json_decode($z['geojson'], true) : null,
        ])->all())

        @if (empty($zones))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Aún no tienes zonas asignadas. Contacta al administrador.
            </p>
        @else
            <div wire:ignore class="flex flex-col gap-4">
                {{-- Chips de zonas (leyenda + navegación): el color coincide con el polígono --}}
                <ul id="agent-zones-list" class="flex flex-wrap gap-2">
                    @foreach ($zones as $i => $zone)
                        <li>
                            <button
                                type="button"
                                @if ($zone['geometry']) data-zone-index="{{ $i }}" @endif
                                @class([
                                    'inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1.5 text-sm transition dark:border-white/10',
                                    'hover:border-primary-400 hover:bg-gray-50 dark:hover:bg-white/5' => (bool) $zone['geometry'],
                                    'cursor-default opacity-70' => ! $zone['geometry'],
                                ])
                                title="{{ $zone['geometry'] ? 'Ver esta zona en el mapa' : 'Sin polígono' }}"
                            >
                                <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $zone['color'] }}"></span>
                                <span class="font-medium text-gray-950 dark:text-white">{{ $zone['name'] }}</span>
                                @if ($zone['municipality'])
                                    <span class="text-xs text-gray-500 dark:text-gray-400">· {{ $zone['municipality'] }}</span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>

                {{-- Un solo mapa con todos los polígonos (altura por estilo inline, robusto) --}}
                @if ($apiKey)
                    <div
                        id="agent-zones-map"
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
                        style="height: 460px; width: 100%;"
                    ></div>
                @else
                    <div
                        class="flex items-center justify-center rounded-xl bg-gray-50 px-4 text-center text-xs text-gray-400 dark:bg-white/5"
                        style="height: 200px;"
                    >
                        Configura la API de Google Maps para ver el mapa.
                    </div>
                @endif
            </div>

            @if ($apiKey)
                @script
                <script>
                    const apiKey = @js($apiKey);
                    const zones = @js($zones);

                    const loadGoogleMaps = (key) => {
                        if (window.google?.maps?.Map) {
                            return Promise.resolve(window.google.maps);
                        }

                        window.newHauzGoogleMapsLoader = window.newHauzGoogleMapsLoader || new Promise((resolve, reject) => {
                            const script = document.createElement('script');
                            const params = new URLSearchParams({ key, libraries: 'geometry', v: 'weekly' });
                            script.src = `https://maps.googleapis.com/maps/api/js?${params.toString()}`;
                            script.async = true;
                            script.defer = true;
                            script.onload = () => resolve(window.google.maps);
                            script.onerror = () => reject(new Error('No se pudo cargar Google Maps.'));
                            document.head.appendChild(script);
                        });

                        return window.newHauzGoogleMapsLoader;
                    };

                    const renderAgentZonesMap = () => {
                        const el = document.getElementById('agent-zones-map');
                        if (! el || el.dataset.rendered) {
                            return;
                        }
                        el.dataset.rendered = '1';

                        const map = new google.maps.Map(el, {
                            disableDefaultUI: true,
                            zoomControl: true,
                            gestureHandling: 'cooperative',
                        });

                        // Un solo Data layer con todos los polígonos; el color viaja por feature.
                        zones.forEach((zone, index) => {
                            if (! zone.geometry) {
                                return;
                            }
                            map.data.addGeoJson({
                                type: 'Feature',
                                properties: { color: zone.color, index },
                                geometry: zone.geometry,
                            });
                        });

                        map.data.setStyle((feature) => {
                            const color = feature.getProperty('color') || '#dc2626';
                            return { fillColor: color, fillOpacity: 0.25, strokeColor: color, strokeWeight: 2 };
                        });

                        // Bounds global (encuadra todo el territorio) y por zona (para el zoom del click).
                        const globalBounds = new google.maps.LatLngBounds();
                        const zoneBounds = {};

                        map.data.forEach((feature) => {
                            const index = feature.getProperty('index');
                            const bounds = new google.maps.LatLngBounds();
                            feature.getGeometry().forEachLatLng((latLng) => {
                                bounds.extend(latLng);
                                globalBounds.extend(latLng);
                            });
                            zoneBounds[index] = bounds;
                        });

                        if (! globalBounds.isEmpty()) {
                            map.fitBounds(globalBounds);
                        }

                        // Click en un chip → zoom a esa zona.
                        document.querySelectorAll('#agent-zones-list [data-zone-index]').forEach((item) => {
                            item.addEventListener('click', () => {
                                const bounds = zoneBounds[Number(item.dataset.zoneIndex)];
                                if (bounds) {
                                    map.fitBounds(bounds);
                                }
                            });
                        });
                    };

                    loadGoogleMaps(apiKey).then(renderAgentZonesMap).catch((error) => console.error(error));
                </script>
                @endscript
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
