@php
    $font = "font-family:Barlow,'Segoe UI',Arial,Helvetica,sans-serif;";
    $parrafo = "margin:0 0 14px;font-size:15px;line-height:1.6;color:#3E6170;{$font}";
@endphp
<x-correo.marco
    etiqueta="Avisos por correo"
    titulo="Los avisos ahora llegan desde una lista nueva"
    :url-boton="$urlPreferencias"
    texto-boton="Elegir mis avisos"
    :url-preferencias="$urlPreferencias"
    motivo="Recibes este correo porque habías aceptado recibir correos con tu cuenta de Torneos Mita'í."
>
    <p style="{{ $parrafo }}">Desde ahora, los avisos de Torneos Mita'í (el resumen de cada fecha, la próxima fecha y las novedades del torneo) llegan desde una lista de suscriptores nueva.</p>
    <p style="{{ $parrafo }}">Te sumamos porque habías aceptado recibir correos con tu cuenta. No tienes que hacer nada para seguir recibiéndolos.</p>
    <p style="{{ $parrafo }}margin-bottom:0;">Puedes elegir qué avisos recibir o darte de baja cuando quieras.</p>
</x-correo.marco>
