@extends('Panel.admin')

@section('contenido')
    @include('Panel.fragment._errores-formulario')
    <div class="flex items-center justify-center p-12">
        <div class="mx-auto w-full max-w-[550px]">
            <form action="{{route('grupos.update', $grupo->id)}}" method="POST">
                @method('PATCH')
                @include('Panel.grupo._form')
                <div>
                    <button type="submit"
                        class="hover:shadow-form rounded-md bg-[#6A64F1] py-3 px-8 text-base font-semibold text-white outline-none">
                        Editar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
