@php
    $font = "font-family:Barlow,'Segoe UI',Arial,Helvetica,sans-serif;";
@endphp
<x-correo.marco
    etiqueta="Aviso del torneo"
    :titulo="$titulo"
    :url-boton="url('/')"
    texto-boton="Ir a Torneos Mita'í"
    :url-preferencias="$urlPreferencias"
>
    {{-- El mensaje se escapa y conserva los saltos de línea --}}
    <p style="margin:0 0 8px;font-size:16px;line-height:1.7;color:#082F3B;{{ $font }}">{!! nl2br(e($mensaje)) !!}</p>
</x-correo.marco>
