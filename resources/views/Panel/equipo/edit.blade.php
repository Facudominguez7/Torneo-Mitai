@extends('Panel.admin')

@section('contenido')
    @include('Panel.fragment._errores-formulario')
    <div class="flex items-center justify-center p-12">
        <div class="mx-auto w-full max-w-[550px]">
            <form action="{{route('equipo.update', $equipo->id)}}" method="POST" enctype="multipart/form-data">
                @method('PATCH')
                @include('Panel.equipo._form')
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
