<x-legales.documento titulo="Términos y condiciones" actualizado="25 de octubre de 2026">
    <section>
        <h2>1. Alcance</h2>
        <p>Estos términos regulan el uso del sitio del Torneo Mita'í, un torneo de fútbol formativo: las páginas públicas (fixture, resultados, tablas y estadísticas), las cuentas de usuario y el panel de administración del torneo.</p>
    </section>

    <section>
        <h2>2. Aceptación</h2>
        <p>Al crear una cuenta, ya sea con correo electrónico y contraseña o con una cuenta de Google, la persona usuaria acepta estos términos y la <a href="{{ route('privacidad') }}">Política de privacidad</a>. Quien no esté de acuerdo puede seguir consultando la información pública sin registrarse.</p>
    </section>

    <section>
        <h2>3. Cuentas</h2>
        <ul>
            <li>Los datos de registro deben ser verdaderos y mantenerse actualizados.</li>
            <li>La cuenta es personal. Cada persona es responsable de mantener su contraseña en reserva y de la actividad realizada con su cuenta.</li>
            <li>Si quien se registra es menor de edad, debe hacerlo con la autorización de su madre, padre o persona responsable.</li>
            <li>Ante cualquier uso no autorizado de una cuenta, se debe avisar a la organización por los medios de contacto indicados al final.</li>
        </ul>
    </section>

    <section>
        <h2>4. Información del torneo</h2>
        <p>El fixture, los resultados, las tablas de posiciones y las estadísticas se publican con fines informativos. Pueden tener demoras o errores de carga, y la organización puede corregirlos en cualquier momento. Ante una diferencia, prevalece lo registrado en la planilla oficial del partido.</p>
    </section>

    <section>
        <h2>5. Uso permitido</h2>
        <p>No está permitido:</p>
        <ul>
            <li>intentar acceder a cuentas, datos o secciones restringidas sin autorización;</li>
            <li>interferir con el funcionamiento del sitio o extraer su contenido de forma masiva o automatizada;</li>
            <li>usar los datos de jugadores, equipos o personas para fines ajenos al torneo.</li>
        </ul>
    </section>

    <section>
        <h2>6. Panel de administración</h2>
        <p>Solo pueden usar el panel las personas autorizadas por la organización. Quienes cargan listas de buena fe, planillas, resultados o cobros son responsables de la exactitud de esos datos y deben contar con la autorización de las personas responsables de cada jugador menor de edad para registrar su información.</p>
    </section>

    <section>
        <h2>7. Comunicaciones por correo electrónico</h2>
        <p>Las personas que verifican su correo pueden recibir avisos del torneo, como resultados, partidos programados y anuncios. Cada correo incluye un enlace para darse de baja en cualquier momento.</p>
    </section>

    <section>
        <h2>8. Propiedad intelectual</h2>
        <p>El nombre, el logo y el diseño del sitio pertenecen a la organización del Torneo Mita'í. Los escudos y fotos de los equipos pertenecen a sus respectivas instituciones.</p>
    </section>

    <section>
        <h2>9. Suspensión de cuentas</h2>
        <p>La organización puede suspender o eliminar las cuentas que incumplan estos términos.</p>
    </section>

    <section>
        <h2>10. Cambios</h2>
        <p>Estos términos pueden actualizarse. La versión vigente es la publicada en esta página, con su fecha de última actualización.</p>
    </section>

    <section>
        <h2>11. Ley aplicable</h2>
        <p>Estos términos se rigen por las leyes de la República Argentina.</p>
    </section>

    <section>
        <h2>12. Contacto</h2>
        <p>Por consultas sobre estos términos, escribí a <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.</p>
    </section>
</x-legales.documento>
