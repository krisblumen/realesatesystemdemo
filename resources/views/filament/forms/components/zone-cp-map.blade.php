{{-- Renderer global: se define una sola vez (corre en la carga inicial de la página).
     Reutiliza una única instancia de mapa y redibuja los polígonos en cada cambio. --}}
<script>
    window.nhBuildZoneCpMap = window.nhBuildZoneCpMap || function (el, zones, apiKey) {
        if (! el || ! apiKey) {
            return;
        }

        const loadGoogleMaps = (key) => {
            if (window.google?.maps?.Map) {
                return Promise.resolve(window.google.maps);
            }
            window.landraGoogleMapsLoader = window.landraGoogleMapsLoader || new Promise((resolve, reject) => {
                const script = document.createElement('script');
                const params = new URLSearchParams({ key, libraries: 'geometry', v: 'weekly' });
                script.src = `https://maps.googleapis.com/maps/api/js?${params.toString()}`;
                script.async = true;
                script.defer = true;
                script.onload = () => resolve(window.google.maps);
                script.onerror = () => reject(new Error('No se pudo cargar Google Maps.'));
                document.head.appendChild(script);
            });
            return window.landraGoogleMapsLoader;
        };

        loadGoogleMaps(apiKey).then((maps) => {
            if (! el._nhMap) {
                el._nhMap = new maps.Map(el, {
                    disableDefaultUI: true,
                    zoomControl: true,
                    gestureHandling: 'cooperative',
                    center: { lat: 20.5888, lng: -100.3899 },
                    zoom: 11,
                });
            }
            const map = el._nhMap;

            // Redibuja: limpia lo anterior y agrega los polígonos actuales.
            map.data.forEach((feature) => map.data.remove(feature));

            (zones || []).forEach((zone) => {
                if (zone.geometry) {
                    map.data.addGeoJson({ type: 'Feature', properties: { color: zone.color }, geometry: zone.geometry });
                }
            });

            map.data.setStyle((feature) => {
                const color = feature.getProperty('color') || '#dc2626';
                return { fillColor: color, fillOpacity: 0.25, strokeColor: color, strokeWeight: 2 };
            });

            const bounds = new maps.LatLngBounds();
            map.data.forEach((feature) => feature.getGeometry().forEachLatLng((latLng) => bounds.extend(latLng)));
            if (! bounds.isEmpty()) {
                map.fitBounds(bounds);
            }
        }).catch((error) => console.error(error));
    };
</script>

@if (! $apiKey)
    <div class="flex items-center justify-center rounded-xl bg-gray-50 px-4 text-center text-xs text-gray-400 dark:bg-white/5" style="height: 160px;">
        Configura la API de Google Maps para ver el mapa.
    </div>
@else
    <div>
        <p class="mb-1.5 text-sm font-medium text-gray-950 dark:text-white">Mapa de la zona</p>
        <div
            wire:ignore
            x-data="{
                apiKey: @js($apiKey),
                zones: $wire.entangle('data.map_zones'),
                init() {
                    this.$watch('zones', () => window.nhBuildZoneCpMap(this.$refs.map, this.zones || [], this.apiKey));
                    this.$nextTick(() => window.nhBuildZoneCpMap(this.$refs.map, this.zones || [], this.apiKey));
                },
            }"
        >
            <div
                x-ref="map"
                class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
                style="height: 360px; width: 100%;"
            ></div>
        </div>
    </div>
@endif
