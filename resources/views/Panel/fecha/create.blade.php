@extends('Panel.admin')

@section('contenido')
    @include('Panel.fragment._errores-formulario')
    <div class="flex items-center justify-center">
        <div class="mx-auto w-full max-w-[550px]">
            <form action="{{route('fecha.store')}}" method="POST">
                @include('Panel.fecha._form')
                <div>
                    <button type="submit"
                        class="hover:shadow-form rounded-md bg-[#6A64F1] py-3 px-8 text-base font-semibold text-white outline-none">
                        Agregar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
