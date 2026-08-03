<?php

namespace App\Enums;

/**
 * Origen de un token de acceso emitido para un contrato (RFC-064). Enum en vez de
 * string libre — hallazgo Mn-3 de la auditoría de diseño de la Épica 10.
 */
enum OrigenAccesoContrato: string
{
    case Inicial = 'inicial';
    case Reenvio = 'reenvio';
}
