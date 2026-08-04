<?php

return [
    // Vigencia del token de acceso (QR/enlace) del formulario público, en horas (RFC-064).
    'token_ttl_horas' => (int) env('CONTRATOS_TOKEN_TTL_HORAS', 72),

    // Tamaño máximo (KB) del PNG de la firma capturada en el canvas (RFC-067).
    'firma_max_kb' => (int) env('CONTRATOS_FIRMA_MAX_KB', 512),

    // Tamaño máximo (KB) de cada cara de la identificación oficial (RFC-063/066).
    'id_max_kb' => (int) env('CONTRATOS_ID_MAX_KB', 4096),
];
