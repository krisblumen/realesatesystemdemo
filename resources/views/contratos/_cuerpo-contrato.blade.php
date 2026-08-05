{{--
    Cuerpo legal completo del contrato de intermediación (machote de docs/legal).
    FUENTE ÚNICA: lo incluyen tanto el formulario público del cliente (RFC-066) como el PDF
    final (RFC-068), para garantizar que el cliente y el documento firmado vean EXACTAMENTE
    el mismo texto. Usa HTML semántico (h3/h4/p) sin clases: cada contexto lo estiliza.

    Variables: $contrato, $clausulaPago, $clausulaExclusividad, $modalidad.
    Las cláusulas QUINTA (pago) y SEXTA (exclusividad) son dinámicas (ContratoClausuladoService).
    Texto sujeto a validación jurídica previa a producción (ver machote).
--}}
@php
    $comision = rtrim(rtrim(number_format((float) $contrato->comision_porcentaje, 2), '0'), '.');
    $vigInicio = optional($contrato->vigencia_inicio)->format('d/m/Y') ?? '—';
    $vigFin = optional($contrato->vigencia_fin)->format('d/m/Y') ?? '—';
    $operacion = mb_strtoupper($contrato->tipo_operacion->label());
@endphp

<h3>CONTRATO DE PRESTACIÓN DE SERVICIOS DE INTERMEDIACIÓN PARA LA {{ $operacion }} DE INMUEBLES DESTINADOS A
    “{{ $contrato->inmueble_tipo }}”</h3>
<p>Que celebran, por una parte, INMOBILIARIA DEMO, S. de R.L. de C.V., que opera bajo el nombre
    comercial LANDRA (en lo sucesivo denominado como el <strong>“PROFESIONAL INMOBILIARIO”</strong>); y
    por la otra, <strong>{{ $contrato->cliente_nombre }}</strong> (en lo sucesivo denominado como
    <strong>“EL PROPIETARIO”</strong>); al tenor de las siguientes declaraciones y cláusulas:</p>

{{-- DATOS DEL PROFESIONAL INMOBILIARIO: HOY SON DE RELLENO, A PROPÓSITO.

     Este cuerpo traía la razón social y el RFC REALES de la empresa de origen.
     En un demo eso significa que cualquier invitado puede generar y descargar un
     documento con apariencia legal a nombre de una empresa que existe — que es
     bastante peor que un logo equivocado.

     `XAXX010101000` es el RFC genérico nacional del SAT: no pertenece a ningún
     contribuyente, así que no puede colisionar con nadie.

     LO QUE FALTA: estos datos tienen que salir de la configuración del inquilino.
     `legal_name` ya existe en `frontend_settings`; el RFC no. Mientras no se
     haga, cada inquilino firma con el mismo relleno.
--}}
<h3>Declaraciones</h3>
<p><strong>I. Declara “EL PROFESIONAL INMOBILIARIO”.</strong> Que INMOBILIARIA DEMO,
    S. de R.L. de C.V., que opera bajo el nombre comercial LANDRA, es una sociedad mercantil
    constituida conforme a las leyes mexicanas, con RFC XAXX010101000; que dentro de su objeto se encuentra la
    prestación de servicios de promoción, mediación e intermediación inmobiliaria; que cuenta con facultades y
    recursos para prestar los servicios materia de este contrato, sin asumir funciones reservadas a fedatarios,
    valuadores, autoridades o instituciones financieras.</p>
<p><strong>II. Declara “EL PROPIETARIO”.</strong> Que tiene capacidad legal para contratar y obligarse; que es
    legítimo propietario, copropietario o representante con facultades suficientes respecto del inmueble descrito;
    que la información y documentación proporcionadas son veraces, vigentes y suficientes, y que informará por
    escrito cualquier gravamen, limitación de dominio, litigio, arrendamiento, adeudo o impedimento para la
    operación; y que autoriza el tratamiento de sus datos personales conforme al aviso de privacidad aceptado
    electrónicamente, únicamente para la relación contractual, la integración del expediente, la promoción del
    inmueble y el cumplimiento legal.</p>
<p><strong>III. Declaran “LAS PARTES”.</strong> Que se reconocen mutuamente personalidad y capacidad; que su
    consentimiento se expresa libre de error, dolo, violencia o mala fe; que reconocen la validez de los mensajes de
    datos y de la firma electrónica utilizada y de la trazabilidad técnica asociada; y que la celebración de este
    instrumento no transmite la propiedad ni sustituye el contrato definitivo que corresponda.</p>

<h3>Cláusulas</h3>

<h4>PRIMERA. Objeto.</h4>
<p>“EL PROPIETARIO” encomienda a “EL PROFESIONAL INMOBILIARIO”, quien acepta, la prestación de servicios de
    asesoría, preparación comercial, promoción, corretaje, mediación y seguimiento de prospectos para procurar la
    celebración de la operación de <strong>{{ $contrato->tipo_operacion->label() }}</strong> respecto del inmueble
    identificado en este instrumento. “EL PROFESIONAL INMOBILIARIO” actuará como intermediario y no como adquirente,
    arrendatario, depositario, mandatario para actos de dominio ni garante del cumplimiento de terceros, salvo
    autorización expresa y escrita para un acto concreto.</p>

<h4>SEGUNDA. Alcance de los servicios.</h4>
<p>Los servicios comprenden: análisis comercial orientativo y recomendación de estrategia de precio; integración y
    revisión administrativa del expediente, sin emitir dictamen jurídico; elaboración de ficha comercial, fotografías
    y material gráfico según se acuerde; difusión en medios propios, redes, portales y redes de colaboración;
    coordinación de visitas con aviso razonable; recepción y comunicación oportuna de propuestas; acompañamiento de
    negociación; y coordinación administrativa con notaría, institución financiera u otros terceros cuando la
    operación lo requiera. Los servicios extraordinarios, publicidad pagada, avalúos, certificados, gestorías,
    trámites, honorarios notariales o legales no están incluidos, salvo convenio escrito.</p>

<h4>TERCERA. Precio y condiciones autorizadas.</h4>
<p>El precio o renta inicial autorizado para la operación de <strong>{{ $contrato->tipo_operacion->label() }}</strong>
    es de <strong>{{ $contrato->precioFormateado() }}</strong>. Toda modificación deberá constar por escrito o mediante
    aceptación electrónica registrada. “EL PROFESIONAL INMOBILIARIO” no podrá obligar a “EL PROPIETARIO” a aceptar
    oferta alguna; cualquier oferta quedará sujeta a la aceptación expresa del propietario, a la verificación
    documental, a la solvencia del interesado y a la formalización del instrumento definitivo.</p>

<h4>CUARTA. Comisión y causación.</h4>
<p>Como contraprestación, “EL PROPIETARIO” pagará a “EL PROFESIONAL INMOBILIARIO” la comisión de
    <strong>{{ $comision }}%</strong> más IVA cuando corresponda. La comisión se causará cuando: a) “EL PROPIETARIO”
    acepte una oferta presentada o gestionada por “EL PROFESIONAL INMOBILIARIO” y se formalice el contrato definitivo;
    b) la operación se celebre directa o indirectamente con un prospecto identificado, presentado, atendido o
    registrado durante la vigencia o dentro del periodo de protección; o c) exista incumplimiento imputable a “EL
    PROPIETARIO” después de haber aceptado por escrito una oferta vinculante, siempre que “EL PROFESIONAL
    INMOBILIARIO” haya cumplido sustancialmente sus obligaciones.</p>

<h4>QUINTA. Forma y momento de pago.</h4>
<p>{{ $clausulaPago }} El pago se realizará mediante transferencia, cheque nominativo u otro medio rastreable a la
    cuenta indicada por “EL PROFESIONAL INMOBILIARIO”, contra el comprobante fiscal que proceda.</p>

<h4>SEXTA. Exclusividad.</h4>
<p>{{ $clausulaExclusividad }} En cualquier modalidad, “EL PROPIETARIO” deberá informar de inmediato las gestiones,
    ofertas y contactos directos relacionados con el inmueble, a fin de evitar duplicidad, publicidad contradictoria o
    conflictos entre intermediarios.</p>

<h4>SÉPTIMA. Periodo de protección de prospectos.</h4>
<p>Al terminar la vigencia, “EL PROFESIONAL INMOBILIARIO” podrá entregar un listado verificable de prospectos
    atendidos. Si dentro del periodo de protección posterior a la terminación se formaliza una operación con alguno de
    ellos, o con personas relacionadas que actúen por su cuenta, se causará la comisión pactada, siempre que exista
    evidencia de la intervención previa.</p>

<h4>OCTAVA. Publicidad, imagen y acceso al inmueble.</h4>
<p>“EL PROPIETARIO” autoriza la toma y utilización de fotografías, video, planos esquemáticos, ubicación aproximada y
    descripción comercial exclusivamente para promover el inmueble durante la vigencia. Las visitas se coordinarán con
    aviso razonable. La entrega de llaves, si la hubiere, deberá documentarse por separado y no convierte a “EL
    PROFESIONAL INMOBILIARIO” en depositario ni lo hace responsable por caso fortuito, fuerza mayor o actos de
    terceros, salvo dolo o culpa grave comprobada.</p>

<h4>NOVENA. Documentación y deber de información.</h4>
<p>“EL PROPIETARIO” proporcionará, según aplique: identificación oficial; título de propiedad y datos registrales;
    predial y agua; constancias de no adeudo; régimen de condominio y mantenimiento; documentos de gravamen; poderes;
    y demás documentos requeridos para la operación. La recepción de copias no implica validación jurídica definitiva.
    “EL PROPIETARIO” responderá por falsedad, omisión o desactualización de la información.</p>

<h4>DÉCIMA. Obligaciones de “EL PROPIETARIO”.</h4>
<p>Permitir la promoción y visitas acordadas; mantener el inmueble en condiciones razonables de seguridad y
    presentación; salvaguardar bienes y objetos de valor; mantener al corriente contribuciones, servicios y cuotas;
    informar defectos, vicios, riesgos y situación posesoria; no discriminar ilícitamente a prospectos; comparecer y
    entregar documentación cuando resulte necesario; y pagar la comisión y gastos autorizados en los supuestos
    pactados.</p>

<h4>DÉCIMA PRIMERA. Obligaciones de “EL PROFESIONAL INMOBILIARIO”.</h4>
<p>Actuar con diligencia, buena fe y confidencialidad; respetar precio y condiciones autorizados; identificar
    claramente su calidad de intermediario; comunicar ofertas y avances relevantes; resguardar el expediente conforme
    a sus controles de acceso; abstenerse de recibir numerario en nombre del propietario sin autorización específica y
    recibo; revelar conflictos de interés conocidos; y entregar copia del contrato firmado.</p>

<h4>DÉCIMA SEGUNDA. Declaraciones sobre el inmueble y limitación de responsabilidad.</h4>
<p>“EL PROFESIONAL INMOBILIARIO” no garantiza la condición física, estructural, ambiental, fiscal, registral o
    jurídica del inmueble ni la solvencia de terceros. No responderá por vicios ocultos, defectos constructivos,
    invasiones, adeudos, gravámenes no informados, restricciones administrativas o hechos atribuibles a “EL
    PROPIETARIO” o terceros. Lo anterior no excluye responsabilidad por dolo, mala fe, negligencia grave o
    incumplimiento propio acreditado.</p>

<h4>DÉCIMA TERCERA. Vigencia, terminación y cancelación.</h4>
<p>El contrato tendrá vigencia del <strong>{{ $vigInicio }}</strong> al <strong>{{ $vigFin }}</strong>. Podrá terminar
    por cumplimiento de su objeto; por vencimiento; por acuerdo escrito; por incumplimiento esencial no subsanado tras
    requerimiento; o por cancelación comunicada con la anticipación pactada. La terminación no extingue comisiones ya
    causadas, reembolsos autorizados, confidencialidad, protección de prospectos ni obligaciones de conservación del
    expediente.</p>

<h4>DÉCIMA CUARTA. Datos personales y aviso de privacidad.</h4>
<p>“EL PROPIETARIO” reconoce haber consultado y aceptado el aviso de privacidad. Los datos e imágenes de
    identificación serán tratados para autenticar la voluntad, integrar el expediente, ejecutar la intermediación,
    prevenir fraude y atender obligaciones legales. La identificación oficial quedará vinculada exclusivamente al
    expediente de este contrato, con acceso restringido y conforme al periodo de retención aplicable.</p>

<h4>DÉCIMA QUINTA. Contratación electrónica y evidencia.</h4>
<p>LAS PARTES acuerdan que este contrato podrá formarse y firmarse mediante mensajes de datos. La manifestación de
    voluntad de “EL PROPIETARIO” se asocia al documento mediante el enlace/token de acceso, la aceptación del aviso de
    privacidad, la confirmación de campos, el trazo de firma, la dirección IP, el user-agent, la fecha y hora del
    servidor, el folio y los registros de auditoría. La firma utilizada es <strong>electrónica simple con evidencia
    reforzada</strong> y no constituye firma electrónica avanzada ni constancia NOM-151.</p>

<h4>DÉCIMA SEXTA. Documento final, sello digital y verificación.</h4>
<p>Al completarse la firma, el sistema genera un PDF final con el folio, los datos contractuales, la firma, la
    evidencia técnica resumida y el sello digital de LANDRA. Se calcula un hash SHA-256 que se almacena en el
    expediente. La página pública de verificación permite comprobar folio, estatus, fecha de firma e integridad
    mediante comparación del hash, sin exponer datos personales.</p>

<h4>DÉCIMA SÉPTIMA. Notificaciones.</h4>
<p>Las comunicaciones contractuales podrán enviarse a los correos, teléfonos y domicilios registrados. Las
    notificaciones electrónicas se tendrán por enviadas cuando el sistema registre su transmisión. “EL PROPIETARIO” se
    obliga a mantener actualizados sus datos de contacto.</p>

<h4>DÉCIMA OCTAVA. Integridad, modificaciones y nulidad parcial.</h4>
<p>Este contrato, sus anexos, el aviso de privacidad y las aceptaciones electrónicas constituyen el acuerdo entre LAS
    PARTES respecto de su objeto. Toda modificación deberá constar por escrito o en mensaje de datos atribuible a ambas
    partes. Si una disposición se declara inválida, las restantes conservarán sus efectos en la medida permitida por la
    ley.</p>

<h4>DÉCIMA NOVENA. Legislación, competencia y solución de controversias.</h4>
<p>Para la interpretación y cumplimiento se aplicarán las leyes mexicanas. Cuando proceda una relación de consumo, la
    Procuraduría Federal del Consumidor será competente en la vía administrativa. Sin perjuicio de mecanismos de
    negociación, mediación o conciliación, LAS PARTES se someten a los tribunales competentes de Santiago de Querétaro,
    Querétaro, renunciando al fuero que pudiera corresponderles por domicilio presente o futuro, salvo competencia
    irrenunciable.</p>

<h4>VIGÉSIMA. Aceptación.</h4>
<p>“EL PROPIETARIO” declara que tuvo oportunidad razonable de leer el contenido completo, aclarar dudas, descargar o
    recibir una copia y decidir libremente. La firma electrónica asentada al final expresa su consentimiento respecto
    de este contrato y sus anexos.</p>
