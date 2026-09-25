@extends('Panel.admin')

@section('contenido')
    @include('Panel.fragment._errores-formulario')
    <div class="flex items-center justify-center">
        <div class="mx-auto w-full max-w-[550px]">
            <form action="{{route('valla.store')}}" method="POST">
                @include('Panel.valla._form')
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
