<?php

namespace Tests\Unit\Tenancy;

use App\Tenancy\GeneradorDeSlug;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * El slug es el subdominio Y el nombre de la base de datos.
 *
 * Eso último es lo que lo vuelve delicado: `CREATE DATABASE` es DDL y Postgres
 * NO acepta parámetros enlazados para identificadores, así que el nombre se
 * interpola en la sentencia sí o sí. Es el único punto del sistema donde una
 * falla no significa «un inquilino ve a otro» sino «se pierden todos».
 */
class GeneradorDeSlugTest extends TestCase
{
    public function test_a_generated_slug_always_matches_the_closed_format(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $slug = GeneradorDeSlug::generar();

            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9]{7,31}$/', $slug);
        }
    }

    public function test_the_alphabet_leaves_out_characters_that_get_misread(): void
    {
        // El slug es un subdominio que alguien lee de una pantalla y tipea en
        // otra. Una `l` que resulta ser un `1` no es un bug: es una consulta de
        // soporte, y de las que no se resuelven por teléfono.
        for ($i = 0; $i < 200; $i++) {
            $slug = GeneradorDeSlug::generar();

            foreach (['l', '1', '0', 'o'] as $confundible) {
                $this->assertStringNotContainsString($confundible, $slug);
            }
        }
    }

    public function test_two_slugs_in_a_row_are_not_the_same(): void
    {
        $muestras = [];
        for ($i = 0; $i < 500; $i++) {
            $muestras[] = GeneradorDeSlug::generar();
        }

        $this->assertCount(500, array_unique($muestras), 'Un slug repetido sería un choque de bases.');
    }

    public function test_a_slug_outside_the_format_is_refused(): void
    {
        // La validación NO existe para desconfiar del generador de arriba.
        // Existe porque dentro de seis meses va a haber un segundo camino hasta
        // acá —una importación, un comando nuevo, una prueba— y ese camino no va
        // a pasar por el generador.
        foreach ([
            '',                        // vacío
            'ab',                      // corto
            'Abcdefgh',                // mayúscula
            'abcdefg-',                // guion
            '1abcdefg',                // arranca con dígito
            'abc defgh',               // espacio
            'abcdefg;DROP DATABASE x', // lo que este formato existe para frenar
            str_repeat('a', 33),       // largo
        ] as $malo) {
            try {
                GeneradorDeSlug::validar($malo);
                $this->fail("Debió rechazar «{$malo}».");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_database_name_carries_a_fixed_prefix(): void
    {
        // Prefijo fijo más slug validado: el identificador queda entre 14 y 39
        // bytes, bajo el límite de 63 de Postgres, y no puede coincidir con una
        // palabra reservada.
        $nombre = GeneradorDeSlug::baseDe('abcdefgh');

        $this->assertStringStartsWith('demo_t_', $nombre);
        $this->assertLessThan(63, strlen($nombre));
    }

    public function test_composing_a_database_name_validates_again(): void
    {
        // La validación va PEGADA AL USO PELIGROSO. Si viviera sólo donde nace
        // el slug, este camino quedaría abierto.
        $this->expectException(InvalidArgumentException::class);

        GeneradorDeSlug::baseDe('abc"; DROP DATABASE demo_db; --');
    }
}
