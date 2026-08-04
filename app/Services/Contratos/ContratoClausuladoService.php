<?php

namespace App\Services\Contratos;

use App\Enums\TipoOperacionContrato;

/**
 * Resuelve el clausulado dinámico del contrato: una sola plantilla base (el machote de
 * docs/legal) cuyo texto varía según tipo de operación (cláusula QUINTA de pago) y
 * exclusividad (cláusula SEXTA), según el Anexo C del machote. Lo consumen tanto el
 * formulario público de revisión (RFC-066) como el PDF final (RFC-068), para garantizar
 * que el cliente y el documento firmado vean EXACTAMENTE el mismo texto.
 *
 * NOTA: el machote está "sujeto a validación jurídica previa a producción". Este texto es
 * fiel a las reglas de ensamble, pero la redacción legal definitiva la aprueba jurídico.
 */
class ContratoClausuladoService
{
    /** Texto de la cláusula QUINTA (forma y momento de pago) según la operación. */
    public function clausulaPago(TipoOperacionContrato $operacion): string
    {
        return match ($operacion) {
            TipoOperacionContrato::Venta => 'Tratándose de una operación de VENTA, la comisión se causará y pagará en una sola exhibición al momento de la firma de la escritura pública de compraventa o del instrumento definitivo que formalice la transmisión de la propiedad.',
            TipoOperacionContrato::Renta => 'Tratándose de una operación de RENTA, la comisión se causará y pagará al momento de la firma del contrato de arrendamiento y la entrega del inmueble al arrendatario presentado o gestionado por “EL PROFESIONAL INMOBILIARIO”.',
            TipoOperacionContrato::RentaOpcionCompra => 'Tratándose de una operación de RENTA CON OPCIÓN A COMPRA, la comisión de arrendamiento se causará al firmarse el contrato de arrendamiento; y en caso de ejercerse la opción de compra, se causará adicionalmente la comisión de compraventa correspondiente al formalizarse el instrumento definitivo de transmisión de la propiedad.',
        };
    }

    /** Texto de la cláusula SEXTA (exclusividad) según la modalidad pactada. */
    public function clausulaExclusividad(bool $exclusividad): string
    {
        return $exclusividad
            ? 'La presente intermediación se pacta CON EXCLUSIVIDAD. Durante la vigencia, “EL PROPIETARIO” se obliga a no contratar a otros intermediarios ni a promover o cerrar directamente la operación, salvo excepciones expresas pactadas por escrito; en caso de operación directa durante la vigencia, se causará la comisión pactada a favor de “EL PROFESIONAL INMOBILIARIO”.'
            : 'La presente intermediación se pacta SIN EXCLUSIVIDAD. “EL PROPIETARIO” podrá promover el inmueble de forma concurrente por otros medios; la comisión se causará únicamente cuando la operación derive de la intervención acreditada de “EL PROFESIONAL INMOBILIARIO” o de un prospecto protegido conforme a este contrato.';
    }

    /** Etiqueta de la modalidad para la cabecera del contrato. */
    public function modalidadTexto(bool $exclusividad): string
    {
        return $exclusividad ? 'CON EXCLUSIVIDAD' : 'SIN EXCLUSIVIDAD';
    }
}
