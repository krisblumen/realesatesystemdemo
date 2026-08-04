<?php

namespace App\Http\Controllers;

use App\Services\Frontend\FrontendServicesService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * `/contacto` (RFC-074): the `?service=` preselection is validated server-side
 * through the same fail-closed rule as the submit, so a query can only ever
 * preselect a lead-eligible service. Anything absent, malformed, unknown or
 * ineligible is ignored UNIFORMLY — HTTP 200, form with no selection — so the
 * URL leaks nothing about which codes exist, and the preselection never grants
 * eligibility the submit will re-check under lock.
 */
class LeadCaptureController extends Controller
{
    public function __invoke(Request $request, FrontendServicesService $services): View
    {
        $service = $request->query('service');

        $preselected = is_string($service) && strlen($service) <= 30 && $services->isLeadEligible($service)
            ? $service
            : null;

        return view('leads.create', ['preselectedServiceType' => $preselected]);
    }
}
