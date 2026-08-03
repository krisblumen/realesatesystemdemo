# Épica DEMO — Prompts de las cuatro fases

Reparto: **diseño e implementación los hace Claude; las dos auditorías las hace
Codex.**

No es una preferencia de herramienta. Una auditoría escrita por quien produjo lo
auditado sirve para lo que se encuentra releyendo con intención de romper —y no
es poco, la auditoría de diseño de esta épica encontró tres críticos así— pero es
ciega a los supuestos compartidos. Si el diseño arranca de una premisa
equivocada, el mismo autor arranca de la misma. Auditar con otro modelo y sin
historia de conversación es lo que compra esa independencia.

## La regla que ordena todo este documento

> **Un prompt de auditoría no dice dónde están los problemas.**

Es la tentación obvia y arruina la auditoría. Si el prompt dice «revisá
especialmente el cerrojo, que me preocupa», Codex va a revisar el cerrojo y va a
confirmar lo que ya sabíamos. Lo que buscamos es justo lo otro: lo que no
sabemos que no sabemos.

Por eso los prompts de auditoría entregan **los artefactos y los criterios**, no
las conclusiones. Y cuando el diseño hace una afirmación cargante —«copiar la
plantilla cuesta 0.2 s», «el `Host` no se puede confundir»— se la pasa como
**afirmación a verificar**, sin decir si es cierta.

---

## Bloque de contexto (va en los cuatro prompts)

```
PROYECTO
Repositorio: realestatesystemDemo. Laravel 13, PHP 8.3, PostgreSQL 16 +
PostGIS, Filament 3, Livewire 3, Tailwind 4. Sin React ni Vue.

Es una copia de un sistema inmobiliario real (New Hauz) que se está
convirtiendo en un demo multi-inquilino. La documentación y la identidad
visual todavía son las del proyecto original; eso es sabido y es trabajo
aparte.

BASES DE DATOS — REGLA INVIOLABLE
- Desarrollo: demo_db. Tests: demo_test. Plantilla: demo_template.
- NUNCA ejecutar migrate:fresh, migrate:refresh, db:wipe ni ningún DROP
  contra inmo_db o inmo_test: son de OTRO proyecto que está en producción.
- Antes de correr la suite, verificar que no haya otra corriendo:
  pgrep -fl "artisan test". Dos procesos sobre la misma base producen
  fallos fantasma (relation does not exist, deadlocks) que no son bugs.

ESTILO
- PHP sigue PSR-12 con Laravel Pint. Correr ./vendor/bin/pint antes de dar
  algo por terminado.
- Los comentarios, la documentación y los mensajes de commit de este repo
  están en español. El código, los identificadores y las cadenas de UI, en
  inglés, salvo donde el repo ya use español.
- Conventional commits. Sin coautoría ni atribución a IA.
```

---

# Fase 1 — Diseño (Claude)

Ya ejecutada para la fase 1 de la épica. Queda como plantilla para los lotes
siguientes, para la fase 2, y para rehacer un diseño que la auditoría rechace.

```
Vas a diseñar {LOTE / RFC}, sin escribir código.

LEER PRIMERO
- docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md
- docs/epicas/epica-demo-multi-inquilino.md
- docs/rfcdemo/README.md y los RFC del lote
- Los diseños de detalle ya escritos en docs/epicas/epica-demo-*-diseno.md
- docs/audits/epica-demo-auditoria-diseno.md

CÓMO TRABAJAR
1. VERIFICAR ANTES DE AFIRMAR. Todo lo que el diseño dé por cierto sobre el
   framework, sobre Postgres o sobre este código se comprueba antes de
   escribirlo: leyendo vendor/, corriendo la consulta, mirando el archivo.
   Cada afirmación cargante va con su evidencia y su referencia
   archivo:línea. Si algo no se pudo verificar, se dice.
2. Preferir una base descartable a suponer. Crear una base de prueba,
   correrlo de verdad y medir es más barato que una discusión.
3. Bajar hasta donde aparezcan los errores silenciosos. El nivel de RFC
   esconde agujeros que sólo se ven al escribir columnas, pasos y estados
   de falla.
4. Para cada mecanismo, decir qué pasa cuando falla y qué estado deja.
5. Marcar qué NO entra y por qué.

QUÉ ENTREGAR
Un documento en docs/epicas/ con: evidencia verificada del framework y del
código; las decisiones con su motivo; los pasos y sus modos de falla; los
archivos a crear; la tabla de tests que cierran el lote y qué protege cada
uno; y las dependencias fuera del lote.

Si el diseño contradice un RFC, se corrige el RFC en el mismo cambio. Dos
documentos que se contradicen son peores que uno solo.

NO ESCRIBIR CÓDIGO DE PRODUCCIÓN. Sondas descartables para verificar, sí,
y se borran al terminar.
```

---

# Fase 2 — Auditoría de diseño (Codex)

```
Sos auditor de diseño. No escribiste nada de esto y no tenés historia de
conversación con quien lo escribió. Esa es exactamente tu ventaja: usala.

Tu trabajo NO es confirmar que el diseño es coherente consigo mismo. Es
averiguar si es correcto, si sus premisas se sostienen y si va a funcionar
en el servidor real.

LEER
- docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md
- docs/epicas/epica-demo-multi-inquilino.md
- docs/epicas/epica-demo-lote-a-diseno.md
- docs/epicas/epica-demo-lotes-b-c-diseno.md
- docs/epicas/epica-demo-lotes-d-e-f-diseno.md
- docs/rfcdemo/ completo
- docs/deployment/DEMO-MULTI-INQUILINO.md
- El código que el diseño toca. Empezar por app/Services/Frontend/,
  app/Models/, bootstrap/app.php, config/, routes/web.php.

AFIRMACIONES A VERIFICAR
El diseño se apoya en las siguientes. No te decimos si son ciertas:
compruébalas vos, contra vendor/, contra Postgres, o corriéndolas.

 1. Copiar una plantilla con CREATE DATABASE ... TEMPLATE cuesta ~0.2 s y
    trae PostGIS, los índices GIST y las seis páginas del CMS.
 2. Postgres rechaza copiar una plantilla que tenga conexiones encima, en
    16 y en 18.
 3. CREATE DATABASE no puede correr dentro de una transacción.
 4. Apuntar la conexión POR DEFECTO al inquilino deja intactos los 28
    modelos existentes.
 5. Dejar el nombre de base vacío NO falla: Postgres conecta a una base con
    el nombre del usuario.
 6. La sesión y el caché siguen la conexión por defecto sin configurarse
    aparte.
 7. RefreshDatabase se puede acotar a una sola conexión.
 8. El glob de migraciones no es recursivo, así que un subdirectorio no se
    cuela.
 9. Filament expone Panel::domain().
10. Un middleware puede garantizarse antes de StartSession.
11. El generador de rutas de la librería de medios es reemplazable por
    configuración.
12. Las claves de caché del frontend hoy no llevan inquilino.
13. La app confía en todos los proxies y entre los encabezados confiados
    está X-Forwarded-Host.
14. Un inquilino recién creado pesa ~18 MB y el VPS tiene 55 GB libres.

QUÉ BUSCAR, ADEMÁS DE ESO
- Premisas que nadie cuestionó. La pregunta más útil suele ser «¿por qué
  esto tiene que ser así?».
- Aislamiento que se pueda evadir: por caché, por disco, por sesión, por
  cola, por una consulta cruda, por un enlace público.
- Mecanismos que dependan de que alguien se acuerde de algo. El diseño
  dice que la disciplina no es protección; verificá si se lo aplica a sí
  mismo.
- Modos de falla sin dueño: qué pasa si el proceso muere entre dos pasos.
- Sobreingeniería. Piezas que existen por un riesgo que no está presente.
- Contradicciones entre documentos. Se escribieron en distintos momentos y
  las decisiones cambiaron dos veces: de abierto a por invitación, y de
  público a cerrado.
- Lo que falta. Un caso de uso sin diseño vale más que un detalle mal
  puesto.

CÓMO REPORTAR
Un documento en docs/audits/, siguiendo el formato del repo (ver
docs/audits/epica-10-auditoria-diseno.md):
  Evidencia verificada en código real · Veredicto · Hallazgos críticos
  (C-N) · Medios (M-N) · Menores (Mn-N) · Sobreingeniería detectada ·
  Riesgos de implementación · Riesgos de seguridad

Cada hallazgo lleva: qué está mal, la evidencia (archivo:línea, salida de
consola, cita de documentación), y **un escenario concreto de falla** —
entradas o estado que producen el resultado incorrecto.

REGLAS DEL REPORTE
- Sin hallazgos especulativos. Si no podés mostrar cómo falla, no es un
  hallazgo: es una duda, y va aparte en una sección de preguntas.
- Prescribí correcciones sólo cuando estés seguro. Una prescripción
  equivocada cuesta más que no darla: la auditoría anterior pidió barrer
  cerrojos huérfanos donde no los hay.
- Si algo está bien resuelto, decilo. Un veredicto que sólo enumera
  problemas no ayuda a decidir qué tocar.
- Si el diseño está listo para implementar, decilo con esas palabras.
```

---

# Fase 3 — Implementación (Claude)

```
Vas a implementar {LOTE}. El diseño está cerrado; si algo del diseño está
mal, se corrige el documento en el mismo cambio, no se improvisa en el
código.

LEER PRIMERO
- El diseño de detalle del lote en docs/epicas/
- Los RFC del lote en docs/rfcdemo/
- La auditoría de diseño y sus hallazgos abiertos
- CLAUDE.md

MODO TDD ESTRICTO
1. Escribir el test primero. Verlo fallar, y **fallar por la razón
   correcta** — un test que falla por un typo no probó nada.
2. Escribir lo mínimo para que pase.
3. Refactorizar con el test en verde.

VERIFICACIÓN POR MUTACIÓN, OBLIGATORIA AL CERRAR
Por cada defecto que el lote previene: reintroducirlo, confirmar que el
test cae con el mensaje correcto, restaurar. Un test que no se cae cuando
el bug vuelve no protege nada, y de esos hay muchos.

REGLAS DE ESTE LOTE
- La suite corre contra demo_test. NUNCA contra inmo_db ni inmo_test.
- Verificar pgrep -fl "artisan test" antes de lanzar.
- Correr suites acotadas, no la completa, salvo al cerrar el lote.
- ./vendor/bin/pint antes de cada commit.
- Las bases de prueba se crean y se borran; una corrida interrumpida no
  puede dejar bases atrás.

COMMITS
Conventional commits, en español, uno por unidad revisable. El mensaje
explica POR QUÉ, no qué: el qué está en el diff. Si el cambio arregla algo
sutil, el mensaje dice cuál era el síntoma.

Commit sí, push sólo cuando se pida.

AL TERMINAR
Reportar: qué quedó hecho, qué tests lo cubren, qué mutaciones se probaron
y con qué mensaje cayeron, y qué quedó fuera y por qué.
```

---

# Fase 4 — Auditoría de implementación (Codex)

```
Sos auditor de implementación. No escribiste este código.

Tu trabajo NO es revisar estilo ni gustos. Es responder tres preguntas:
¿hace lo que el diseño dice? ¿los tests lo prueban de verdad? ¿qué se
rompe en producción que nadie vio?

LEER
- El diseño de detalle del lote y sus RFC
- La auditoría de diseño
- El diff completo del lote
- Los tests del lote

CÓMO AUDITAR

1. CONTRA EL DISEÑO. Cada contrato del lote: ¿está implementado, o sólo
   parece? Un contrato «cerrado» sin test que lo verifique está abierto.

2. LOS TESTS, CON DESCONFIANZA. Es la parte que más rinde.
   - ¿Un test falla si se rompe lo que dice proteger? Comprobalo:
     reintroducí el defecto y corré. Si el test sigue verde, el test miente
     y hay que reportarlo como hallazgo.
   - ¿Hay tests acoplados a detalles —clases CSS exactas, texto literal,
     orden de resultados— que se van a romper por cambios legítimos?
   - ¿Alguno pasa por estado residual de la base en vez de por lo que
     prueba? En este proyecto ya pasó: cuatro tests eran verdes sólo
     porque la base de tests arrastraba una tabla de corridas anteriores.
   - ¿Las factories producen datos que la aplicación produciría? En este
     proyecto una factory creaba zonas con una forma que la app nunca
     genera, y por eso un bug real llegó a producción con la suite en
     verde.

3. LO QUE SE ROMPE Y NO SE VE.
   - Escrituras en la base equivocada. Este diseño tiene un modo de falla
     que no lanza excepción: un trabajo que hereda la conexión del
     anterior escribe bien, en el lugar equivocado.
   - Fugas entre inquilinos por caché, disco, sesión, cola o un enlace
     público.
   - Consultas crudas que esquivan el aislamiento.
   - N+1 introducidos.
   - Recursos que quedan tomados si el proceso muere a mitad.

4. SEGURIDAD, con foco en lo que este sistema promete: que los datos de un
   cliente son de ese cliente. Y en el único punto donde una falla no
   significa «uno ve a otro» sino «se pierden todos»: la composición del
   nombre de una base a partir de un identificador.

CÓMO REPORTAR
Documento en docs/audits/, mismo formato que la auditoría de diseño:
crítico / medio / menor, cada uno con evidencia archivo:línea y un
escenario concreto de falla.

Los hallazgos sobre tests van en su propia sección. Un test que no protege
es peor que ninguno: ocupa el lugar del que sí protegería.

REGLAS
- Sin hallazgos especulativos. Si no podés mostrar la falla, va a
  preguntas.
- Distinguí «está mal» de «yo lo habría hecho distinto». Lo segundo casi
  nunca es un hallazgo.
- Si el lote está listo para mezclar, decilo con esas palabras.
```

---

## Cómo se encadenan

```
Diseño (Claude) ──► Auditoría de diseño (Codex)
                         │
              ┌──────────┴──────────┐
         hallazgos                listo
              │                     │
     corregir el diseño ◄───┐       ▼
              │             │  Implementación (Claude)
              └─────────────┘       │
                                    ▼
                    Auditoría de implementación (Codex)
                         │
              ┌──────────┴──────────┐
         hallazgos                listo
              │                     │
        corregir código        mezclar
```

Dos reglas del encadenado:

1. **Los críticos se corrigen antes de pasar de fase.** Los medios y menores
   pueden quedar anotados y abiertos, pero un crítico abierto significa que la
   fase siguiente se hace sobre algo que sabemos que está mal.
2. **Corregir un hallazgo no es negociar con el auditor.** Si la corrección
   cambia el diseño, el documento se actualiza. Si el hallazgo estaba
   equivocado, se anota por qué en la auditoría y se deja el rastro — como se
   hizo con el barrido de cerrojos, que se pidió y no correspondía.
