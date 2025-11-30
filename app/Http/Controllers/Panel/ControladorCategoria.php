<?php
namespace App\Http\Controllers\Panel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Categoria\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use Illuminate\Http\Request;


class ControladorCategoria extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $categorias = Categoria::where('idEdicion', $idEdicion)
            ->orderBy('nombreCategoria', 'desc')
            ->paginate(7);
        $categorias->appends(['idEdicion' => $idEdicion]);
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        return view('Panel.categoria.index', compact('EdicionSeleccionada', 'categorias', 'ediciones'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $categoria = new Categoria();
        return view('Panel.categoria.create', compact('ediciones', 'categoria', 'EdicionSeleccionada'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        Categoria::create($data);
        return to_route('categoria.index', ['idEdicion' => $request->idEdicion])->with('status', 'Categoria creada.');;
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Categoria $categorium)
    {
        $ediciones = Edicion::all();
        $categoria = $categorium;
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        return view('Panel.categoria.show', compact('categoria', 'ediciones', 'EdicionSeleccionada'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Categoria $categorium)
    {
        $ediciones = Edicion::all();
        $categoria = $categorium;
        $idEdicion = $categoria->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        return view('Panel.categoria.edit', compact('ediciones', 'categoria', 'EdicionSeleccionada'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, Categoria $categorium)
    {
        $data = $request->validated();
        $categoria = $categorium;
        
        $categoria->update($data);
        return to_route('categoria.index', ['idEdicion' => $request->idEdicion]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Categoria $categorium)
    {
        $categoria = $categorium;
        $categoria->delete();
        return to_route('categoria.index', ['idEdicion' => $request->idEdicion]);
    }
}