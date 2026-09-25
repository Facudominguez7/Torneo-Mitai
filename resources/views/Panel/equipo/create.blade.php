@extends('Panel.admin')

@section('contenido')
    @include('Panel.fragment._errores-formulario')
    <div class="flex items-center justify-center">
        <div class="mx-auto w-full max-w-[550px]">
            <form action="{{route('equipo.store')}}" method="POST" enctype="multipart/form-data">
                @include('Panel.equipo._form')
                <div>
                    <button type="submit"
                        class="panel-form-submit">
                        Agregar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
