{{--
    El armazón de las páginas del host central.

    NO CONSULTA NADA. Ni base de datos, ni CMS, ni configuración de inquilino: la
    base central sólo tiene el padrón, las sesiones y la cola, y buscar tablas
    del CMS ahí es exactamente lo que devolvía un 500.

    Tampoco usa los assets compilados: si el build no corrió en el servidor,
    estas páginas tienen que seguir contestando. Son las últimas que deberían
    fallar — una la ve quien llegó por error, la otra quien todavía no es cliente.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Landra')</title>
    <link rel="icon" href="{{ asset('images/brand/landra-core.ico') }}">
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem;
            background: #f2f4f6;
            color: #2e3842;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            text-align: center;
        }
        main { max-width: 30rem; width: 100%; }
        img { max-height: 4.5rem; max-width: 100%; width: auto; object-fit: contain; }
        h1 { margin: 1.75rem 0 0.75rem; font-size: 1.25rem; font-weight: 600; letter-spacing: -0.01em; }
        p { margin: 0; line-height: 1.6; color: #55636f; }
        form { margin-top: 1.5rem; display: grid; gap: 0.75rem; }
        input[type="email"] {
            width: 100%;
            padding: 0.75rem 0.9rem;
            font: inherit;
            color: inherit;
            background: #fff;
            border: 1px solid #cbd2d9;
            border-radius: 0.5rem;
            text-align: center;
        }
        input[type="email"]:focus-visible { outline: 2px solid #f5a624; outline-offset: 1px; border-color: #f5a624; }
        button {
            padding: 0.75rem 1rem;
            font: inherit;
            font-weight: 600;
            color: #171d23;
            background: #f5a624;
            border: 0;
            border-radius: 0.5rem;
            cursor: pointer;
        }
        button:hover { background: #e0951c; }
        .aviso { margin-top: 1.25rem; padding: 0.85rem 1rem; border-radius: 0.5rem; line-height: 1.5; }
        .aviso-bien { background: #e7f2ec; color: #1f5138; }
        .aviso-mal { background: #fbeaea; color: #8a2b2b; }
        .letra-chica { margin-top: 1.25rem; font-size: 0.8125rem; color: #7b858f; }

        /* El señuelo. Se esconde de la vista Y de los lectores de pantalla: una
           persona no debería poder llenarlo ni por accidente. */
        .senuelo { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }

        @media (prefers-color-scheme: dark) {
            body { background: #171d23; color: #f2f4f6; }
            p { color: #9ca4ac; }
            input[type="email"] { background: #202830; border-color: #3a444e; }
            .aviso-bien { background: #1c3a2c; color: #a8d8bf; }
            .aviso-mal { background: #3a1f1f; color: #e0a5a5; }
        }
    </style>
</head>
<body>
    <main>
        <img src="{{ asset('images/brand/logo-lockup-on-light.png') }}" alt="Landra">

        @yield('contenido')
    </main>
</body>
</html>
