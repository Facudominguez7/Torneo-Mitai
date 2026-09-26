@if ($errors->any())
    <div role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
        <p class="text-sm font-semibold text-red-800">Revisá los campos marcados</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif
