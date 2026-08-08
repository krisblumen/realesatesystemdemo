<?php

namespace App\Providers;

use App\Enums\PropertyStatus;
use App\Enums\ZoneStatus;
use App\Events\LeadAssigned;
use App\Events\LeadCaptured;
use App\Events\UserRegistered;
use App\Listeners\ActivatePendingUserOnPasswordReset;
use App\Listeners\AssignCapturedLead;
use App\Listeners\SendLeadAssignedNotification;
use App\Listeners\SendLeadConfirmationToClient;
use App\Listeners\SendWelcomeNotification;
use App\Listeners\UpdateUserLastLoginAt;
use App\Models\ContratoIntermediacion;
use App\Models\Feature;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\FrontendService;
use App\Models\FrontendSetting;
use App\Models\Lead;
use App\Models\LonaBatch;
use App\Models\LonaRequest;
use App\Models\LonaUnit;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Models\Zone;
use App\Observers\FrontendMediaObserver;
use App\Observers\PropertyObserver;
use App\Observers\ZoneObserver;
use App\Policies\ContratoIntermediacionPolicy;
use App\Policies\FeaturePolicy;
use App\Policies\FrontendPagePolicy;
use App\Policies\FrontendSectionPolicy;
use App\Policies\FrontendServicePolicy;
use App\Policies\FrontendSettingPolicy;
use App\Policies\LeadPolicy;
use App\Policies\LonaBatchPolicy;
use App\Policies\LonaRequestPolicy;
use App\Policies\LonaUnitPolicy;
use App\Policies\PropertyOwnerPolicy;
use App\Policies\PropertyPolicy;
use App\Policies\UserPolicy;
use App\Policies\ZonePolicy;
use App\Services\Frontend\Contracts\FrontendContent;
use App\Services\Frontend\Contracts\FrontendPublisher;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendCachePublisher;
use App\Services\Frontend\FrontendSettingsService;
use App\Services\Frontend\Media\PromotableMediaOwners;
use App\Services\Frontend\Media\ServiceMediaReference;
use App\Services\Frontend\PublishedMediaReference;
use App\Tenancy\InquilinoActual;
use App\Tenancy\InquilinoEnLaCola;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El inquilino de la petición: SINGLETON, si no el middleware lo fija
        // en una instancia y quien lo consulta lee otra vacía. Es el único
        // punto por el que se pregunta de quién es la petición.
        $this->app->singleton(InquilinoActual::class);

        // Frontend kernel (Épica 12): singletons so the per-request generation
        // memo is shared, and contract bindings for the read/write sides.
        $this->app->singleton(FrontendCacheGeneration::class);
        $this->app->bind(
            FrontendContent::class,
            FrontendSettingsService::class,
        );
        $this->app->bind(
            FrontendPublisher::class,
            FrontendCachePublisher::class,
        );

        // Estrategias de promoción de media (Épica 12.3 §3.2). El registry es
        // fail-closed y sin default: un model_type que no esté en esta lista NO
        // se promueve. Agregar un dueño es agregarlo acá, nunca tocar el job.
        $this->app->singleton(PromotableMediaOwners::class, fn ($app) => new PromotableMediaOwners([
            $app->make(PublishedMediaReference::class),
            $app->make(ServiceMediaReference::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // En producción todas las URLs generadas (assets, endpoints de Livewire)
        // deben ser https: el sitio corre detrás del proxy TLS de CloudPanel.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Un trabajo encolado vuelve solo a la base de su inquilino. Se registra
        // acá y no en cada trabajo porque el mecanismo tiene que actuar ANTES de
        // deserializar, donde el trabajo todavía no existe como objeto.
        InquilinoEnLaCola::registrar();

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Zone::class, ZonePolicy::class);
        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(PropertyOwner::class, PropertyOwnerPolicy::class);
        Gate::policy(Feature::class, FeaturePolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(LonaBatch::class, LonaBatchPolicy::class);
        Gate::policy(LonaRequest::class, LonaRequestPolicy::class);
        Gate::policy(LonaUnit::class, LonaUnitPolicy::class);
        Gate::policy(ContratoIntermediacion::class, ContratoIntermediacionPolicy::class);
        Gate::policy(FrontendSetting::class, FrontendSettingPolicy::class);
        Gate::policy(FrontendService::class, FrontendServicePolicy::class);
        Gate::policy(FrontendPage::class, FrontendPagePolicy::class);
        Gate::policy(FrontendSection::class, FrontendSectionPolicy::class);

        // Rate limiting de los endpoints públicos de contratos (Mn-4). El de verificación
        // usa un límite más bajo como control anti-enumeración de folios (M-5).
        RateLimiter::for('contratos-publico', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('contratos-verificar', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));

        Event::listen(Login::class, UpdateUserLastLoginAt::class);
        // Orden importa: AssignCapturedLead resuelve el agente (si hay) antes
        // de que SendLeadConfirmationToClient arme el mail al cliente, asi
        // puede personalizarlo con el asesor cuando corresponde.
        Event::listen(LeadCaptured::class, AssignCapturedLead::class);
        Event::listen(LeadCaptured::class, SendLeadConfirmationToClient::class);
        Event::listen(LeadAssigned::class, SendLeadAssignedNotification::class);
        Event::listen(UserRegistered::class, SendWelcomeNotification::class);
        Event::listen(PasswordReset::class, ActivatePendingUserOnPasswordReset::class);
        Property::observe(PropertyObserver::class);
        // Media de entidades del frontend invalida la caché pública (§16.8).
        Media::observe(FrontendMediaObserver::class);
        Zone::observe(ZoneObserver::class);

        Zone::updated(function (Zone $zone): void {
            if ($zone->wasChanged('status') && $zone->status === ZoneStatus::Inactive) {
                self::pausePublishedProperties($zone);
            }
        });

        // Un inmueble publicado no se queda sin imagen principal.
        //
        // El candado cuenta los reemplazos mirando la base, así que sólo tolera
        // la secuencia «primero agrego la nueva, después borro la vieja». El
        // formulario de Filament hace lo CONTRARIO —borra y después guarda—, y
        // ahí este candado cortaba un cambio de foto perfectamente válido: en
        // ese instante la base no tenía ninguna, aunque el agente sí había
        // elegido una.
        //
        // Un modelo no puede distinguir «me están reemplazando» de «me están
        // borrando», porque el reemplazo todavía no existe. Quien sí lo sabe es
        // la pantalla, que tiene el archivo elegido en la mano; por eso durante
        // su guardado el candado se aparta y la regla la aplica
        // {@see EditProperty::beforeSave()}, que además pone el error en el
        // campo correcto y así se ve.
        //
        // Fuera de esa pantalla —un `clearMediaCollection('cover')` por código—
        // el candado sigue entero.
        Media::deleting(function (Media $media): void {
            if ($media->collection_name !== 'cover' || Property::isCoverGuardDeferred()) {
                return;
            }

            $property = $media->model;

            if (! $property instanceof Property || ! $property->isPublished()) {
                return;
            }

            $hasReplacement = Media::query()
                ->where('model_type', $media->model_type)
                ->where('model_id', $property->getKey())
                ->where('collection_name', 'cover')
                ->whereKeyNot($media->getKey())
                ->exists();

            if (! $hasReplacement) {
                throw ValidationException::withMessages([
                    'cover' => 'Un inmueble publicado no puede quedarse sin imagen principal. Pausa primero.',
                ]);
            }
        });
    }

    private static function pausePublishedProperties(Zone $zone): void
    {
        DB::transaction(function () use ($zone): void {
            $zone->properties()
                ->where('status', PropertyStatus::Publicado->value)
                ->lockForUpdate()
                ->get()
                ->each(function (Property $property): void {
                    $property->status = PropertyStatus::Pausado;
                    $property->save();
                });
        });
    }
}
