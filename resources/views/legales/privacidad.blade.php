<x-legales.documento titulo="Política de privacidad" actualizado="25 de octubre de 2026">
    <section>
        <h2>1. Responsable</h2>
        <p>La organización del Torneo Mita'í es responsable de los datos personales que se tratan en este sitio. Para cualquier consulta, escribí a <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.</p>
    </section>

    <section>
        <h2>2. Qué datos se guardan</h2>
        <ul>
            <li><strong>Cuenta de usuario:</strong> nombre, correo electrónico y contraseña. La contraseña se guarda cifrada y nadie puede leerla. Si la cuenta se crea con Google, también se guardan el identificador de la cuenta de Google y la foto de perfil.</li>
            <li><strong>Preferencias:</strong> la suscripción a los correos del torneo, la fecha del último aviso enviado y la fecha en que se aceptaron estos documentos.</li>
            <li><strong>Jugadores:</strong> nombre, apellido, DNI, fecha de nacimiento, equipo, categoría y estadísticas deportivas (partidos, goles y tarjetas). Los carga la organización o las personas responsables de cada equipo en la lista de buena fe. La mayoría de los jugadores son menores de edad.</li>
            <li><strong>Equipos:</strong> nombre, institución, escudo o foto y los cobros de planilla de cada partido (montos, medio de pago y exenciones).</li>
            <li><strong>Uso del sitio:</strong> una cookie técnica de sesión, necesaria para iniciar sesión; estadísticas de visitas medidas con Google Analytics (páginas vistas, tipo de dispositivo y ubicación aproximada); y el equipo favorito, que se guarda solo en el navegador de cada persona.</li>
        </ul>
    </section>

    <section>
        <h2>3. Para qué se usan</h2>
        <ul>
            <li>Administrar las cuentas y el inicio de sesión.</li>
            <li>Organizar el torneo: habilitar jugadores por categoría, armar las planillas de los partidos, registrar resultados, sanciones y cobros, y calcular las estadísticas.</li>
            <li>Publicar el fixture, los resultados y las tablas del torneo.</li>
            <li>Enviar avisos por correo a quienes los aceptaron.</li>
            <li>Conocer cómo se usa el sitio para mejorarlo.</li>
        </ul>
    </section>

    <section>
        <h2>4. Qué se publica</h2>
        <p>En las páginas públicas se muestran el nombre y apellido de los jugadores junto con sus estadísticas deportivas, y el nombre y el escudo de los equipos. El DNI y la fecha de nacimiento no se publican: solo los ve la organización en el panel de administración.</p>
    </section>

    <section>
        <h2>5. Jugadores menores de edad</h2>
        <p>Los datos de los jugadores menores de edad se cargan con la autorización de sus madres, padres o personas responsables y se limitan a lo necesario para organizar el torneo. Esas personas pueden pedir en cualquier momento el acceso, la corrección o la eliminación de los datos.</p>
    </section>

    <section>
        <h2>6. Con quién se comparten</h2>
        <p>Los datos no se venden ni se ceden con fines comerciales. Solo intervienen los proveedores necesarios para que el sitio funcione: Google, para el inicio de sesión con Google y la medición de visitas, y los servicios de alojamiento del sitio y de envío de correos.</p>
    </section>

    <section>
        <h2>7. Cuánto tiempo se conservan</h2>
        <p>Los datos de la cuenta se conservan mientras la cuenta esté activa, y cada persona puede eliminarla desde su perfil. Los resultados y las estadísticas de ediciones anteriores se conservan como registro histórico del torneo.</p>
    </section>

    <section>
        <h2>8. Derechos de las personas</h2>
        <p>Toda persona puede solicitar el acceso, la rectificación, la actualización o la supresión de sus datos escribiendo a <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>, conforme a la Ley 25.326 de Protección de los Datos Personales.</p>
        <p>El titular de los datos personales tiene la facultad de ejercer el derecho de acceso a los mismos en forma gratuita a intervalos no inferiores a seis meses, salvo que se acredite un interés legítimo al efecto conforme lo establecido en el artículo 14, inciso 3 de la Ley N° 25.326. La Agencia de Acceso a la Información Pública, en su carácter de Órgano de Control de la Ley N° 25.326, tiene la atribución de atender las denuncias y reclamos que interpongan quienes resulten afectados en sus derechos por incumplimiento de las normas vigentes en materia de protección de datos personales.</p>
    </section>

    <section>
        <h2>9. Seguridad</h2>
        <p>Las contraseñas se guardan cifradas, el panel de administración solo está disponible para cuentas autorizadas y la conexión con el sitio viaja cifrada.</p>
    </section>

    <section>
        <h2>10. Cambios</h2>
        <p>Esta política puede actualizarse. La versión vigente es la publicada en esta página, con su fecha de última actualización. Ver también los <a href="{{ route('terminos') }}">Términos y condiciones</a>.</p>
    </section>
</x-legales.documento>
