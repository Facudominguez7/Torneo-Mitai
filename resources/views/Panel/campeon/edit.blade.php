@extends('Panel.admin')

@section('contenido')
    @include('Panel.fragment._errores-formulario')
    <div class="flex items-center justify-center p-12">
        <div class="mx-auto w-full max-w-[550px]">
            <form action="{{route('campeon.update', $campeon->id)}}" method="POST">
                @method('PATCH')
                @include('Panel.campeon._form')
                <div>
                    <button type="submit"
                        class="panel-form-submit">
                        Editar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
