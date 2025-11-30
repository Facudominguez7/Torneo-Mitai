<?php
namespace App\View\Components;

use App\Models\Edicion;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public $EdicionSeleccionada;

    /**
     * Create a new component instance.
     *
     * @param  int|null  $idEdicion
     * @return void
     */
    public function __construct($idEdicion = null)
    {
        $this->EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return View
     */
    public function render(): View
    {
        return view('layouts.app', ['EdicionSeleccionada' => $this->EdicionSeleccionada]);
    }
}