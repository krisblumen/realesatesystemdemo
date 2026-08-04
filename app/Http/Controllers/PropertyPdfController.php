<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PropertyPdfController extends Controller
{
    public function show(Property $property): Response
    {
        Gate::authorize('view', $property);

        $property->load(['agent', 'zone', 'features']);

        $pdf = Pdf::loadView('pdf.property-sheet', ['property' => $property])
            ->setPaper('letter');

        return $pdf->download("ficha-{$property->slug}.pdf");
    }
}
