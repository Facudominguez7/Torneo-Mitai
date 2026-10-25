<x-mail::message>
# Confirmá tu suscripción

Pediste recibir los avisos de Torneos Mita'í: resultados de tus equipos, resumen de cada fecha y novedades del torneo.

<x-mail::button :url="$urlConfirmacion">
Confirmar suscripción
</x-mail::button>

El enlace vence en {{ config('suscripciones.horas_confirmacion') }} horas. Si no fuiste vos, ignorá este correo: no te vamos a escribir de nuevo.
</x-mail::message>
