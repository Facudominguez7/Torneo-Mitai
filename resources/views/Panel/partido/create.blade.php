@extends('Panel.admin')

@section('contenido')
    @include('Panel.fragment._errores-formulario')
    <div class="flex items-center justify-center px-4 md:px-0">
        <div class="mx-auto w-full max-w-[600px]">
            <form action="{{route('partido.store')}}" method="POST">
                @include('Panel.partido._form')
                <div>
                    <button type="submit"
                        class="hover:shadow-form rounded-md bg-[#6A64F1] py-3 px-8 text-base font-semibold text-white outline-none w-full md:w-auto">
                        Agregar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
