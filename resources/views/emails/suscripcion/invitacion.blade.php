<x-mail::message>
# ¿Querés recibir los avisos por correo?

Tenés una cuenta en Torneos Mita'í. Ahora los avisos por correo son una lista aparte: el resumen de cada fecha, la próxima fecha y las novedades del torneo.

Si querés recibirlos, confirmá con el botón:

<x-mail::button :url="$urlConfirmacion">
Quiero recibir los avisos
</x-mail::button>

El enlace vence en {{ config('suscripciones.horas_confirmacion') }} horas. Si no te interesa, ignorá este correo: no te vamos a escribir de nuevo.
</x-mail::message>
