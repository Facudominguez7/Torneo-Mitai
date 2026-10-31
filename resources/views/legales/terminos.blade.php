@php
    $articulos = [
        'alcance' => 'Alcance',
        'aceptacion' => 'Aceptación',
        'cuentas' => 'Cuentas',
        'informacion-del-torneo' => 'Información del torneo',
        'uso-permitido' => 'Uso permitido',
        'panel-de-administracion' => 'Panel de administración',
        'comunicaciones' => 'Comunicaciones por correo electrónico',
        'propiedad-intelectual' => 'Propiedad intelectual',
        'suspension' => 'Suspensión de cuentas',
        'cambios' => 'Cambios',
        'ley-aplicable' => 'Ley aplicable',
        'contacto' => 'Contacto',
    ];

    $resumen = [
        'Información pública' => 'Fixture, resultados y tablas se pueden ver sin crear una cuenta.',
        'Planilla oficial' => 'Ante una diferencia, vale lo que dice la planilla del partido.',
        'Menores' => 'Quien tenga menos de 18 años se registra con autorización de una persona adulta responsable.',
    ];
@endphp

<x-legales.documento titulo="Términos y condiciones" actualizado="25 de octubre de 2026" :articulos="$articulos" :resumen="$resumen">
    <x-legales.articulo ancla="alcance">
        <p>Estos términos regulan el uso del sitio del Torneo Mita'í, un torneo de fútbol formativo: las páginas públicas (fixture, resultados, tablas y estadísticas), las cuentas de usuario y el panel de administración del torneo.</p>
    </x-legales.articulo>

    <x-legales.articulo ancla="aceptacion">
        <p>Al crear una cuenta, ya sea con correo electrónico y contraseña o con una cuenta de Google, la persona usuaria acepta estos términos y la <a href="{{ route('privacidad') }}">Política de privacidad</a>. Quien no esté de acuerdo puede seguir consultando la información pública sin registrarse.</p>
    </x-legales.articulo>

    <x-legales.articulo ancla="cuentas">
        <ul>
            <li>Los datos de registro deben ser verdaderos y mantenerse actualizados.</li>
            <li>La cuenta es personal. Cada persona es responsable de mantener su contraseña en reserva y de la actividad realizada con su cuenta.</li>
            <li>Si quien se registra es menor de edad, debe hacerlo con la autorización de su madre, padre o persona responsable.</li>
            <li>Ante cualquier uso no autorizado de una cuenta, se debe avisar a la organización por los medios de contacto indicados al final.</li>
        </ul>
    </x-legales.articulo>

    <x-legales.articulo ancla="informacion-del-torneo">
        <p>El fixture, los resultados, las tablas de posiciones y las estadísticas se publican con fines informativos. Pueden tener demoras o errores de carga, y la organización puede corregirlos en cualquier momento. Ante una diferencia, prevalece lo registrado en la planilla oficial del partido.</p>
    </x-legales.articulo>

    <x-legales.articulo ancla="uso-permitido">
        <p>No está permitido:</p>
        <ul>
            <li>intentar acceder a cuentas, datos o secciones restringidas sin autorización;</li>
            <li>interferir con el funcionamiento del sitio o extraer su contenido de forma masiva o automatizada;</li>
            <li>usar los datos de jugadores, equipos o personas para fines ajenos al torneo.</li>
        </ul>
    </x-legales.articulo>

    <x-legales.articulo ancla="panel-de-administracion">
        <p>Solo pueden usar el panel las personas autorizadas por la organización. Quienes cargan listas de buena fe, planillas, resultados o cobros son responsables de la exactitud de esos datos y deben contar con la autorización de las personas responsables de cada jugador menor de edad para registrar su información.</p>
    </x-legales.articulo>

    <x-legales.articulo ancla="comunicaciones">
        <p>Las personas que verifican su correo pueden recibir avisos del torneo, como resultados, partidos programados y anuncios. Cada correo incluye un enlace para darse de baja en cualquier momento.</p>
    </x-legales.articulo>

    <x-legales.articulo ancla="propiedad-intelectual">
        <p>El nombre, el logo y el diseño del sitio pertenecen a la organización del Torneo Mita'í. Los escudos y fotos de los equipos pertenecen a sus respectivas instituciones.</p>
    </x-legales.articulo>

    <x-legales.articulo ancla="suspension">
        <p>La organización puede suspender o eliminar las cuentas que incumplan estos términos.</p>
    </x-legales.articulo>

    <x-legales.articulo ancla="cambios">
        <p>Estos términos pueden actualizarse. La versión vigente es la publicada en esta página, con su fecha de última actualización.</p>
    </x-legales.articulo>

    <x-legales.articulo ancla="ley-aplicable">
        <p>Estos términos se rigen por las leyes de la República Argentina.</p>
    </x-legales.articulo>

    <x-legales.articulo ancla="contacto">
        <p>Por consultas sobre estos términos, escribí a <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.</p>
    </x-legales.articulo>
</x-legales.documento>
