<?php

namespace App\Tenancy;

use RuntimeException;

/**
 * Ese correo ya tiene un demo activo.
 *
 * ES UNA EXCEPCIÓN Y NO UN BOOLEANO porque los dos caminos de alta —el formulario
 * y la API— tienen que contestar cosas distintas ante el mismo hecho: uno vuelve
 * al formulario con el error sobre el campo, la otra devuelve 409. Un booleano
 * obligaría a cada llamador a acordarse de mirarlo; una excepción no se puede
 * ignorar por olvido.
 *
 * EL MENSAJE VIVE ACÁ, en un solo lugar. Antes estaba escrito dentro del
 * controlador web; al abrirse el segundo camino, tenerlo duplicado era garantía
 * de que un día dijeran cosas distintas.
 *
 * Sobre informar que ya existe: la decisión y su costo están razonados en
 * `RegistroDeDemoController`. Acá sólo se transporta.
 */
class YaHayUnDemo extends RuntimeException
{
    public function __construct(
        string $mensaje = 'Ya hay un demo activo para ese correo. Revisa tu bandeja de entrada y la carpeta de spam.',
    ) {
        parent::__construct($mensaje);
    }
}
