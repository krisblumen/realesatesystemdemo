# Auditoría del prompt de reauditoría de diseño — Épica 12.3

**Proyecto:** New Hauz — Plataforma Inmobiliaria  
**Fecha:** 2026-07-27  
**Auditor:** Codex (modelo Sol)  
**Documento auditado:** `docs/prompts/epica-12-3-reauditoria-diseno-prompt.md`  
**Diseño objetivo:** `docs/epicas/epica-12-3-media-servicios-diseno.md`  
**Rama:** `feature/epica-12-content-manager`  
**Commit:** `c662ebb` — `docs(prompts): nombre neutro para el prompt de auditoría de 12.3`

## 1. Veredicto

🟡 **APROBADO CON CORRECCIONES**

El prompt v2 mejora de forma importante la independencia y la verificabilidad:
obliga a leer la auditoría previa, exige líneas literales del código, separa
diseño de implementación y cubre C-1…C-4 con pruebas de PostgreSQL, HTTP,
concurrencia, caché y seguridad.

Todavía debe endurecer el arnés de evidencia: no prueba rama/estado/diff, no
captura de forma uniforme el código de salida de todos los comandos y deja la
suite completa sin límite ni protocolo para una ejecución colgada. Puede orientar
la reauditoría, pero no debe tratarse todavía como un gate probatorio definitivo.

## 2. Evidencia verificada en el repositorio

### Identidad y tamaño del diseño

```text
c662ebb
docs(prompts): nombre neutro para el prompt de auditoría de 12.3
     398 docs/epicas/epica-12-3-media-servicios-diseno.md
```

El prompt llama al diseño “de ~340 líneas” en `:137`, pero el archivo actual tiene
398. No rompe la cobertura, aunque demuestra que esa descripción quedó obsoleta.

### Conteo de tests

```text
1003
```

`./vendor/bin/phpunit --list-tests | grep -c '^ - '` es reproducible en este
checkout. El valor debe reportarse para el commit actual, no funcionar como una
constante fija del prompt.

### Composer y Pint

```text
./composer.json is valid
```

```text
{"tool":"pint","result":"passed"}
```

`composer validate --strict` y `./vendor/bin/pint --test` pasaron.

### PostgreSQL real configurado por el repositorio

El prompt ejecuta `php artisan test` sin variables DB explícitas, pero
`phpunit.xml:24-35` fija actualmente:

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="inmo_test"/>
<env name="DB_HOST" value="127.0.0.1"/>
<env name="DB_PORT" value="5432"/>
```

La omisión no es explotable en este checkout, pero deja el contrato dependiente
de una configuración implícita.

### Suite completa

Se ejecutó literalmente:

```bash
php artisan test; echo "EXIT: $?"
```

No produjo salida durante varios minutos y fue interrumpida manualmente:

```text
[sin salida antes de la interrupción]
```

El proceso terminó con código 130 por la interrupción manual. Esto no demuestra
que la suite falle, pero sí que el prompt no define timeout ni diagnóstico para
este caso.

### Estado del checkout

```text
## feature/epica-12-content-manager...origin/feature/epica-12-content-manager [ahead 98]
 M .atl/skill-registry.md
 M public/css/filament/admin/theme.css
?? "docs/letras canciones hubiera.docx"
```

El prompt lista correctamente esos archivos ajenos en `:95-96`, pero no ordena
ejecutar `git status` ni verificar que diseño y prompt correspondan al commit
reportado.

## 3. Qué está bien resuelto

### Independencia y alcance

- Declara que es un gate de diseño, no de implementación: `:40-44`.
- Prohíbe modificar código y diseño: `:170`.
- Obliga a leer el diseño, la auditoría rechazada, el contrato de lotes y las
  fuentes normativas: `:52-60`.
- No convierte la ausencia de código 12.3 en hallazgo: `:128-133`.

### Cierre de C-1…C-4

La tabla `:98-107` exige exactamente lo que faltaba en la auditoría anterior:

- C-1: regla única de resolución pública y fallback.
- C-2: lock, pending, commit, `afterCommit`, relectura y retry.
- C-3: índice único parcial en SQL y carrera con dos conexiones PostgreSQL.
- C-4: resultado tipado, estado común, registry fail-closed y compatibilidad con
  `PublishedMediaReference`.

El foco adicional de `:109-115` es correcto: obliga a comparar los métodos que el
job invoca con la interfaz y la máquina de estados, en vez de aceptar una
abstracción nominal.

### Seguridad y regresiones

El prompt exige revisar media privada, histórico público, ownership, IDOR,
carreras, no borrado físico, `FrontendSetting`, roles, `ServiceType` y el
pipeline aprobado de 12.1: `:117-126` y `:159-161`.

La obligación de copiar la línea literal para cada cita `archivo:línea`
(`:62-74`) también corrige el riesgo metodológico principal de la v1.

## 4. Hallazgos medios

### M-P1 — La evidencia de todos los comandos no queda exigida de forma uniforme

**Estado:** CONFIRMADO.

El prompt exige salida cruda de los cinco comandos de `:30-36`, pero los comandos
de Composer, Pint, migración y build aparecen en `:80-86` sin una exigencia
equivalente de salida literal y código de salida. La regla general de `:21` ayuda,
pero el formato de `:150-154` vuelve inequívocos sólo los cinco primeros.

**Corrección:** exigir para **todos** los comandos una tabla con `comando`, salida
cruda, `exit code`, duración y observación. Ningún “OK” debe aceptarse sin código
de salida 0.

### M-P2 — La procedencia del checkout no está probada completamente

**Estado:** CONFIRMADO.

El prompt captura SHA y subject (`:30-33`), pero no rama, estado ni diff de los
documentos. El workspace real está adelantado 98 commits y tiene cambios locales;
la lista de excepciones es correcta, pero no sustituye la prueba de procedencia.

**Corrección:** agregar al bloque obligatorio:

```bash
git rev-parse --abbrev-ref HEAD
git status --short --branch
git diff --name-status HEAD -- docs/prompts/epica-12-3-reauditoria-diseno-prompt.md docs/epicas/epica-12-3-media-servicios-diseno.md
```

### M-P3 — La suite completa no tiene límite ni protocolo para una ejecución colgada

**Estado:** CONFIRMADO por ejecución.

`php artisan test; echo "EXIT: $?"` (`:33`) no establece timeout, heartbeat ni
criterio para diferenciar una suite lenta de un proceso detenido. En este
checkout no hubo salida durante varios minutos y hubo que interrumpirla.

**Corrección:** definir timeout, registrar tiempo/último output/exit code al
expirar, ejecutar el diagnóstico verbose de `:92` y marcar la reauditoría como
bloqueada hasta obtener una corrida completa con código 0.

### M-P4 — El conteo usa un pipeline sin `pipefail`

**Estado:** CONFIRMADO.

`./vendor/bin/phpunit --list-tests | grep -c '^ - '` (`:34`) devuelve 1003 hoy,
pero no conserva el código de PHPUnit. Un fallo de PHPUnit podría dejar una cifra
parcial y un estado exitoso de `grep`.

**Corrección:** usar `set -o pipefail` o capturar por separado salida y código de
`phpunit`; reportar ambos valores y no imponer una cifra fija.

## 5. Hallazgos menores

### Mn-P1 — Descripción de tamaño desactualizada

`docs/prompts/epica-12-3-reauditoria-diseno-prompt.md:137` dice “~340 líneas”; el
diseño actual tiene 398. Conviene eliminar el número aproximado.

### Mn-P2 — “Últimas 15 líneas” puede ocultar la causa del fallo

La regla `:21` evita informes enormes, pero en migración, HTTP o concurrencia
puede ocultar el error. Deben conservarse siempre resumen de fallos, tests
afectados y código de salida, aunque estén fuera de esas 15 líneas.

### Mn-P3 — Conviene hacer explícito `--env=testing`

`phpunit.xml` ya fija PostgreSQL e `inmo_test`, por lo que no es un defecto actual.
Usar `APP_ENV=testing DB_CONNECTION=pgsql DB_DATABASE=inmo_test php artisan test`
haría el contrato visible y más portable.

## 6. Riesgos de seguridad del propio prompt

No encontré un hueco conceptual importante en la cobertura: el prompt sí exige
comprobar la frontera privada/pública, 404 uniforme, ownership, UUID,
fail-closed, no borrado físico y carreras.

El riesgo restante es epistémico: aceptar cifras o checks verdes sin código de
salida puede aprobar una reauditoría que nunca terminó. M-P1, M-P3 y M-P4 son
importantes porque afectan la autenticidad del gate.

## 7. Sobreingeniería

- La línea literal por cada cita es estricta, pero está justificada por el fallo
  concreto de C-3.
- La matriz C-1…C-4 y T3-1…T3-19 está enfocada al riesgo y debe conservarse.
- El conteo fijo y “sólo 15 líneas” son reglas frágiles de presentación; deben
  transformarse en evidencia con código de salida.

## 8. Correcciones obligatorias al prompt

1. Agregar verificación de rama, `git status` y diff de los dos documentos.
2. Exigir salida cruda, duración y exit code para Composer, Pint, migración, suite
   y build.
3. Hacer explícito `DB_CONNECTION=pgsql DB_DATABASE=inmo_test` en los comandos
   Laravel, aunque `phpunit.xml` ya lo garantice.
4. Añadir `pipefail` o captura separada del código de `phpunit --list-tests`.
5. Definir timeout y diagnóstico; una suite interrumpida o sin resultado debe
   cerrar el gate como bloqueado/rechazado.
6. Sustituir la expectativa fija de tests por el conteo del commit actual y su
   código de salida.
7. Eliminar la referencia obsoleta a “~340 líneas”.

## 9. Gate explícito

**PROMPT DE REAUDITORÍA 12.3: APROBADO CON CORRECCIONES**

Este veredicto evalúa el prompt, no aprueba el diseño 12.3. Las correcciones
M-P1…M-P4 deben incorporarse antes de considerar su futura reauditoría como
evidencia definitiva de gate.

