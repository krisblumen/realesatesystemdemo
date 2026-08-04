<?php

use App\Http\Controllers\ContratoBorradorController;
use App\Http\Controllers\ContratoMediaController;
use App\Http\Controllers\FrontendPreviewController;
use App\Http\Controllers\FrontendSectionMediaController;
use App\Http\Controllers\FrontendServiceMediaController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeadCaptureController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyPdfController;
use App\Http\Controllers\Public\ContratoFirmaController;
use App\Http\Controllers\Public\ContratoPublicoController;
use App\Http\Controllers\Public\ContratoVerificacionController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Sitio público de inmuebles (sólo publicados; sólo se muestra la zona).
Route::get('/inmuebles', [PropertyController::class, 'index'])->name('inmuebles.index');
Route::get('/inmuebles/{property:slug}', [PropertyController::class, 'show'])->name('inmuebles.show');

// Páginas institucionales (contenido de marca).
Route::view('/nosotros', 'site.nosotros')->name('nosotros');
Route::view('/servicios', 'site.servicios')->name('servicios');
Route::view('/inversionistas', 'site.inversionistas')->name('inversionistas');
Route::get('/proyectos', [ProjectController::class, 'index'])->name('proyectos');
Route::get('/proyectos/{project:slug}', [ProjectController::class, 'show'])->name('proyectos.show');

Route::get('/contacto', LeadCaptureController::class)->name('leads.create');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Preview owner-only del borrador de una página institucional (RFC-077, Lote G).
// El owner-gate (403 para anónimo/no-owner) y el 404 uniforme por pageKey viven
// en el controlador — es la única frontera. Sin token público reusable;
// noindex,nofollow en el shell.
Route::get('/admin/frontend/preview/{pageKey}', FrontendPreviewController::class)
    ->name('frontend.preview');

// Media EN BORRADOR de una sección de página (Épica 12.1 §7.7). La colección
// `images` vive en el disco privado `frontend-private`: no existe URL /storage
// hasta que una publicación la promueve. Deliberadamente SIN middleware `auth`:
// redirige al login (302 + HTML) en vez de responder uniforme, y delata que la
// ruta existe. Autenticación, policy owner-only (rol + permiso) y pertenencia
// del uuid se resuelven en el controlador, que responde 404 en los cinco casos.
Route::get('/admin/frontend/secciones/{section}/media/{uuid}', FrontendSectionMediaController::class)
    ->name('frontend.sections.media');

// Ídem para la imagen EN BORRADOR de un servicio (Épica 12.3 §8.1). Mismas
// razones y mismo contrato: sin middleware `auth` para no delatar la ruta con
// un 302, y 404 uniforme en los cinco casos. `withTrashed` en el binding porque
// un servicio dado de baja debe poder previsualizarse en el panel: su media
// sigue siendo suya.
Route::get('/admin/frontend/servicios/{service}/media/{uuid}', FrontendServiceMediaController::class)
    ->withTrashed()
    ->name('frontend.services.media');

// Ficha PDF del inmueble para el panel de administración (requiere sesión).
Route::get('/admin/inmuebles/{property}/ficha-pdf', [PropertyPdfController::class, 'show'])
    ->middleware('auth')
    ->name('properties.pdf.show');

// Media privada de contratos (identificación, firma, PDF). Disco 'local', servida solo
// tras autorización de Policy (C-2/M-3). No existe URL /storage para estas colecciones.
Route::get('/admin/contratos/{contrato}/media/{coleccion}', ContratoMediaController::class)
    ->middleware('auth')
    ->name('contratos.media');

// Vista previa del contrato SIN firmar: se renderiza al vuelo y no se guarda.
// Va aparte de `contratos.media` a propósito — esa ruta sirve archivos que
// existen en disco, y ésta uno que nace y muere en la respuesta.
Route::get('/admin/contratos/{contrato}/vista-previa', ContratoBorradorController::class)
    ->middleware('auth')
    ->name('contratos.borrador');

// Formulario público del cliente (RFC-066). Sin login: acceso solo por token de un solo
// uso. Throttle obligatorio: endpoints sin sesión (Mn-4). Firmar/rechazar llegan en Lote E.
Route::prefix('contrato')->name('contratos.publico.')->middleware('throttle:contratos-publico')->group(function () {
    Route::get('/{token}', [ContratoPublicoController::class, 'show'])->name('show');
    Route::post('/{token}/firmar', [ContratoFirmaController::class, 'firmar'])->name('firmar');
    Route::post('/{token}/rechazar', [ContratoFirmaController::class, 'rechazar'])->name('rechazar');
});

// Verificación pública de integridad por folio (RFC-068). Respuesta uniforme
// anti-enumeración (M-5/P-3): no revela existencia/estatus sin subir el PDF correcto.
Route::middleware('throttle:contratos-verificar')->group(function () {
    Route::get('/verificar/{folio}', [ContratoVerificacionController::class, 'show'])->name('contratos.verificar');
    Route::post('/verificar/{folio}', [ContratoVerificacionController::class, 'comparar'])->name('contratos.verificar.comparar');
});

// Temporal (Fase 0): guía de estilo viva para validar las fundaciones del frontend.
Route::view('/styleguide', 'styleguide');
