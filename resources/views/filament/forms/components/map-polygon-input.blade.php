@php
    $statePath = $getStatePath();
    $pathPrefix = str($statePath)->beforeLast('.')->toString();
    $siblingPath = fn (?string $field): string => filled($pathPrefix) ? $pathPrefix.'.'.$field : (string) $field;
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="mapPolygon({
            statePath: @js($statePath),
            stateField: @js($field->stateField),
            muniField: @js($field->muniField),
            cpField: @js($field->cpField),
            descriptionPath: @js($siblingPath($field->descriptionField)),
            apiKey: @js(config('services.google_maps.key')),
            value: $wire.entangle('{{ $statePath }}').live,
            stateValue: $wire.entangle('{{ $siblingPath($field->stateField) }}').live,
            muniValue: $wire.entangle('{{ $siblingPath($field->muniField) }}').live,
            cpValue: $wire.entangle('{{ $siblingPath($field->cpField) }}').live,
        })"
        wire:ignore
    >
        <x-filament::section>
            <x-slot name="heading">Polígono de la zona</x-slot>

            <x-slot name="headerEnd">
                <div style="display:flex; gap:8px;">
                    <button
                        type="button"
                        x-on:click="fetchCpPolygon()"
                        style="background-color:#111827; color:#ffffff; border-radius:8px; padding:6px 14px; font-size:14px; font-weight:600; box-shadow:0 1px 2px rgba(0,0,0,.12);"
                    >
                        Obtener Zona
                    </button>
                    <button
                        type="button"
                        x-on:click="clearPolygon()"
                        style="background-color:#dc2626; color:#ffffff; border-radius:8px; padding:6px 14px; font-size:14px; font-weight:600; box-shadow:0 1px 2px rgba(0,0,0,.12);"
                    >
                        Limpiar Mapa
                    </button>
                </div>
            </x-slot>

            <div x-ref="map" class="w-full rounded-lg border border-gray-300 dark:border-gray-700" style="height: 420px"></div>

            <template x-if="!apiKey">
                <p class="mt-2 text-sm text-danger-600 dark:text-danger-400">
                    Configura GOOGLE_MAPS_API_KEY para habilitar el mapa.
                </p>
            </template>
        </x-filament::section>
    </div>
</x-dynamic-component>

@once
    <script>
        window.newHauzGoogleMapsLoader = window.newHauzGoogleMapsLoader || null;

        function newHauzLoadGoogleMaps(apiKey) {
            if (window.google?.maps?.Map) {
                return Promise.resolve(window.google.maps);
            }

            if (window.newHauzGoogleMapsLoader) {
                return window.newHauzGoogleMapsLoader;
            }

            window.newHauzGoogleMapsLoader = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                const params = new URLSearchParams({
                    key: apiKey,
                    libraries: 'places,geometry',
                    v: 'weekly',
                });

                script.src = `https://maps.googleapis.com/maps/api/js?${params.toString()}`;
                script.async = true;
                script.defer = true;
                script.onload = () => resolve(window.google.maps);
                script.onerror = () => reject(new Error('No se pudo cargar Google Maps.'));

                document.head.appendChild(script);
            });

            return window.newHauzGoogleMapsLoader;
        }

        function mapPolygon(cfg) {
            return {
                map: null,
                geocoder: null,
                initialized: false,
                destroyed: false,
                polygonRegistryKey: cfg.statePath,
                value: cfg.value,
                stateValue: cfg.stateValue,
                muniValue: cfg.muniValue,
                cpValue: cfg.cpValue,
                apiKey: cfg.apiKey,

                init() {
                    // Alpine invoca automáticamente init() en los objetos de x-data.
                    // Este guard evita mapas duplicados si otro ciclo intenta inicializar
                    // de nuevo la misma instancia.
                    if (this.initialized) {
                        return;
                    }

                    this.initialized = true;
                    this.destroyed = false;

                    if (!this.apiKey) {
                        return;
                    }

                    newHauzLoadGoogleMaps(this.apiKey).then(() => {
                        if (this.destroyed || this.map) {
                            return;
                        }

                        // x-filament::section tiene su propio x-data (es colapsable), por lo que
                        // $refs.map queda fuera de este scope. Buscamos el contenedor del mapa
                        // dentro del árbol de este componente.
                        const mapEl = this.$el.querySelector('[x-ref="map"]');

                        if (!mapEl) {
                            return;
                        }

                        this.map = new google.maps.Map(mapEl, {
                            center: { lat: 20.5888, lng: -100.3899 },
                            zoom: 12,
                            mapTypeControl: false,
                            streetViewControl: false,
                        });
                        this.geocoder = new google.maps.Geocoder();
                        this.map.data.setStyle({
                            fillColor: '#dc2626',
                            fillOpacity: 0.3,
                            strokeColor: '#dc2626',
                            strokeOpacity: 1,
                            strokeWeight: 2,
                        });
                        this.polygonRegistry().dataLayers.add(this.map.data);
                        this.renderExisting();
                        this.watchAddressFields();
                        this.recenter();
                    }).catch((error) => console.error(error));
                },

                destroy() {
                    this.destroyed = true;
                    const registry = this.polygonRegistry();
                    registry.generation++;
                    this.removeCurrentPolygon();

                    if (this.map) {
                        registry.dataLayers.delete(this.map.data);
                        google.maps.event.clearInstanceListeners(this.map);
                    }

                    this.map = null;
                    this.geocoder = null;
                },

                watchAddressFields() {
                    // El polígono se obtiene exclusivamente por código postal (botón "Obtener Zona").
                    this.$watch('stateValue', () => this.recenter());
                    this.$watch('muniValue', () => this.recenter());
                    // Al cambiar el CP, la zona anterior deja de ser válida: se elimina del mapa
                    // y se limpia el valor hasta que se vuelva a pulsar "Obtener Zona".
                    this.$watch('cpValue', () => {
                        this.clearPolygon();
                        this.recenter();
                    });
                },

                async recenter() {
                    if (!this.geocoder) {
                        return;
                    }

                    const estado = await this.labelOf(cfg.stateField, this.stateValue);
                    const muni = await this.labelOf(cfg.muniField, this.muniValue);
                    const cp = this.cpValue;

                    if (!estado && !cp) {
                        return;
                    }

                    const address = [cp, muni, estado, 'México'].filter(Boolean).join(', ');

                    this.geocoder.geocode({ address }, (results, status) => {
                        if (status === 'OK' && results[0]) {
                            this.map.setCenter(results[0].geometry.location);
                            this.map.setZoom(14);
                        }
                    });
                },

                async fetchCpPolygon() {
                    if (!this.cpValue) {
                        return;
                    }

                    const requestedCp = String(this.cpValue);
                    const registry = this.polygonRegistry();
                    const requestGeneration = ++registry.generation;
                    const result = await this.$wire.call('fetchPostalCodePolygon', requestedCp);

                    // Ignora respuestas obsoletas si el CP cambió, se borró la zona o se
                    // inició otra consulta mientras Livewire resolvía esta petición.
                    if (
                        registry.generation !== requestGeneration
                        || String(this.cpValue) !== requestedCp
                    ) {
                        return;
                    }

                    if (!result) {
                        // La notificación de "sin cobertura" la dispara el backend (Filament Notification).
                        return;
                    }

                    this.removeCurrentPolygon();
                    this.value = result;
                    this.$wire.set(cfg.statePath, result);
                    this.renderExisting();

                    // Autocompleta la descripción con las colonias del CP obtenido.
                    if (cfg.descriptionPath) {
                        const description = await this.$wire.call('coloniasDescriptionForPostalCode', requestedCp);

                        if (description) {
                            this.$wire.set(cfg.descriptionPath, description);
                        }
                    }
                },

                clearPolygon() {
                    this.polygonRegistry().generation++;
                    this.removeCurrentPolygon();
                    this.value = null;
                    this.$wire.set(cfg.statePath, null);
                },

                polygonRegistry() {
                    window.newHauzZonePolygonRegistry ??= new Map();

                    if (!window.newHauzZonePolygonRegistry.has(this.polygonRegistryKey)) {
                        window.newHauzZonePolygonRegistry.set(this.polygonRegistryKey, {
                            generation: 0,
                            dataLayers: new Set(),
                        });
                    }

                    const registry = window.newHauzZonePolygonRegistry.get(this.polygonRegistryKey);

                    // Compatibilidad con una instancia que hubiera quedado viva antes
                    // de desplegar este cambio durante navegación SPA.
                    registry.generation ??= 0;
                    registry.dataLayers ??= new Set();

                    if (registry.polygons) {
                        registry.polygons.forEach((polygon) => polygon.setMap(null));
                        registry.polygons.clear();
                        delete registry.polygons;
                    }

                    return registry;
                },

                removeCurrentPolygon() {
                    // map.data mantiene una colección única de geometrías por mapa. Se
                    // vacían también las capas de instancias anteriores del mismo campo
                    // para cubrir re-montajes de Livewire y navegación SPA de Filament.
                    const registry = this.polygonRegistry();

                    registry.dataLayers.forEach((dataLayer) => {
                        const features = [];
                        dataLayer.forEach((feature) => features.push(feature));
                        features.forEach((feature) => dataLayer.remove(feature));
                    });
                },

                renderExisting() {
                    if (!this.map || !this.value) {
                        return;
                    }

                    try {
                        const geoJson = JSON.parse(this.value);

                        if (geoJson.type !== 'Polygon' || !Array.isArray(geoJson.coordinates?.[0])) {
                            return;
                        }

                        const ring = geoJson.coordinates[0].map(([lng, lat]) => ({ lat, lng }));

                        // Siempre reemplaza el polígono anterior: una zona = un CP.
                        this.removeCurrentPolygon();
                        this.map.data.addGeoJson({
                            type: 'Feature',
                            properties: {},
                            geometry: geoJson,
                        });

                        const bounds = new google.maps.LatLngBounds();
                        ring.forEach((point) => bounds.extend(point));

                        if (!bounds.isEmpty()) {
                            this.map.fitBounds(bounds);
                        }
                    } catch (error) {
                        console.error('GeoJSON inválido', error);
                    }
                },

                async labelOf(field, id) {
                    if (!id) {
                        return null;
                    }

                    return await this.$wire.call('resolveZoneMapAddressLabel', field, id);
                },
            };
        }
    </script>
@endonce
