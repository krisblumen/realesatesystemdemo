<?php

namespace App\Services\Contratos;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

/**
 * Genera imágenes QR como data URI (endroid v6, dependencia ya instalada por RFC-062).
 * Se usa tanto para el QR del enlace público (RFC-064) como para el mini-QR de
 * verificación del sello (RFC-068).
 */
class ContratoQrService
{
    public function dataUri(string $data, int $size = 600): string
    {
        return (new Builder(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
        ))->build()->getDataUri();
    }
}
