{{--
    La portada del host central.

    NO CONSULTA NADA. Ni base de datos, ni CMS, ni configuración de inquilino: es
    justamente el lugar donde no hay ninguna de esas cosas, y donde intentarlo
    devolvía un 500.

    Tampoco usa los assets compilados de la aplicación: si el build no corrió en
    el servidor, esta página tiene que seguir contestando. Es la última que
    debería fallar, porque es la que ve alguien que llegó acá por error.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Landra</title>
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
        main { max-width: 30rem; }
        img { max-height: 4.5rem; max-width: 100%; width: auto; object-fit: contain; }
        h1 { margin: 1.75rem 0 0.75rem; font-size: 1.25rem; font-weight: 600; letter-spacing: -0.01em; }
        p { margin: 0; line-height: 1.6; color: #55636f; }
        @media (prefers-color-scheme: dark) {
            body { background: #171d23; color: #f2f4f6; }
            p { color: #9ca4ac; }
        }
    </style>
</head>
<body>
    <main>
        <img src="{{ asset('images/brand/logo-lockup-on-light.png') }}" alt="Landra">

        <h1>Este es el acceso a los demos de Landra</h1>

        <p>
            Cada demo vive en su propia dirección, y llega por invitación.
            Si ya tenés la tuya, entrá por ahí.
        </p>
    </main>
</body>
</html>
