<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo fue la última vez que alguien ENTRÓ a este demo.
 *
 * Con el registro público, la mayoría de las altas no vuelve nunca: alguien deja
 * su correo, mira dos pantallas y se va. Esa base sigue ocupando disco y
 * conexiones —de las 100 que compartimos con la producción vecina— durante todo
 * su plazo, sin que nadie la use.
 *
 * `null` significa «todavía nadie entró», y ahí manda `created_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('ultimo_acceso_en')->nullable()->after('expira_en')->index();
        });

        // SE LE DA CUERDA A LOS QUE YA EXISTEN, y no es cortesía.
        //
        // Sin esto, todo inquilino creado antes de esta migración arranca con
        // `null` —o sea, contando desde su `created_at`— y el primer barrido
        // después de desplegar expiraría de golpe a los que llevan más días que
        // el tope. Nadie los usó, es cierto, pero tampoco existía la regla
        // cuando se crearon: aplicarla hacia atrás es cambiar el trato después
        // de haber invitado.
        DB::table('tenants')->whereNull('ultimo_acceso_en')->update(['ultimo_acceso_en' => now()]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('ultimo_acceso_en');
        });
    }
};
