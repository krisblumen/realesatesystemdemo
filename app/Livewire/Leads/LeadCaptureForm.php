<?php

namespace App\Livewire\Leads;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\PropertyStatus;
use App\Events\LeadCaptured;
use App\Models\Lead;
use App\Services\Frontend\FrontendServicesService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LeadCaptureForm extends Component
{
    public string $name = '';

    public string $email = '';

    public ?string $phone = null;

    public ?string $message = null;

    public ?int $property_id = null;

    public string $source = 'web';

    public string $service_type = '';

    #[Locked]
    public ?string $forced_service_type = null;

    public string $company_website = '';

    public bool $submitted = false;

    public function mount(
        ?int $propertyId = null,
        ?string $source = null,
        ?string $serviceType = null,
        bool $locked = false,
    ): void {
        $this->property_id = $propertyId;

        if ($source !== null) {
            $this->source = $source;
        }

        if ($serviceType !== null) {
            $this->service_type = $serviceType;
        }

        if ($locked && $serviceType !== null) {
            $this->forced_service_type = $serviceType;
        }
    }

    public function submit(): void
    {
        if (RateLimiter::tooManyAttempts($this->rateLimitKey(), 3)) {
            $this->addError('rate_limit', 'Demasiados intentos. Intenta de nuevo en un minuto.');

            return;
        }

        RateLimiter::hit($this->rateLimitKey(), 60);

        if ($this->company_website !== '') {
            $this->reset(['name', 'email', 'phone', 'message', 'company_website']);
            $this->submitted = true;

            return;
        }

        if ($this->forced_service_type !== null) {
            $this->service_type = $this->forced_service_type;
        }

        $validated = $this->validate();

        // §16.6 / M-2: validation and creation are atomic under a lock. The
        // eligibility rule above runs unlocked, so between it and the insert a
        // concurrent toggle of ServiceType.active or FrontendService.allow_leads
        // could slip an ineligible service through. The row is re-verified while
        // holding the locks, in the SAME order every authority mutation uses
        // (service_types → frontend_services), so eligibility cannot flip under
        // us and the locks never deadlock.
        $lead = DB::transaction(function () use ($validated): Lead {
            $code = $validated['service_type'];

            $active = DB::table('service_types')->where('code', $code)->lockForUpdate()->value('active');
            $allowLeads = DB::table('frontend_services')
                ->where('service_type_code', $code)->whereNull('deleted_at')
                ->lockForUpdate()->value('allow_leads');

            if (! $active || ! $allowLeads) {
                throw ValidationException::withMessages([
                    'service_type' => 'El servicio seleccionado no está disponible.',
                ]);
            }

            return Lead::create([
                'name' => $this->sanitizeText($validated['name']),
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'message' => isset($validated['message']) ? $this->sanitizeText($validated['message']) : null,
                'service_type' => $code,
                'property_id' => $validated['property_id'] ?? null,
                'source' => $validated['source'],
                'status' => LeadStatus::Nuevo,
            ]);
        });

        LeadCaptured::dispatch($lead);

        $this->reset(['name', 'email', 'phone', 'message', 'company_website']);
        $this->submitted = true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'message' => ['nullable', 'string', 'max:2000'],
            // Fail-closed through the single eligibility rule: active type AND
            // allow_leads, no FrontendService row => not eligible (§16.6).
            'service_type' => [
                'required',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (! is_string($value) || ! app(FrontendServicesService::class)->isLeadEligible($value)) {
                        $fail('El servicio seleccionado no está disponible.');
                    }
                },
            ],
            'property_id' => $this->service_type === 'comercializacion'
                ? [
                    'nullable',
                    'integer',
                    Rule::exists('properties', 'id')
                        ->where('status', PropertyStatus::Publicado->value)
                        ->whereNotNull('agent_id'),
                ]
                : ['prohibited'],
            'source' => ['required', Rule::in(array_column(LeadSource::cases(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.max' => 'El correo electrónico no puede superar los 255 caracteres.',
            'phone.regex' => 'El teléfono no tiene un formato válido (ej. +52 442 000 0000).',
            'phone.max' => 'El teléfono no puede superar los 40 caracteres.',
            'message.max' => 'El mensaje no puede superar los 2 000 caracteres.',
            'service_type.required' => 'Selecciona tu servicio de interés.',
            'service_type.exists' => 'El servicio seleccionado no está disponible.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'message' => 'mensaje',
            'service_type' => 'servicio de interés',
        ];
    }

    public function render(): mixed
    {
        return view('livewire.leads.lead-capture-form');
    }

    private function rateLimitKey(): string
    {
        return 'lead-capture:'.request()->ip();
    }

    private function sanitizeText(string $value): string
    {
        return trim(strip_tags($value));
    }
}
