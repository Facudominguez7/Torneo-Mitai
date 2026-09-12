<?php

namespace App\View\Composers;

use App\Models\Edicion;
use Illuminate\View\View;

class NavigationComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        if (! array_key_exists('ultimaEdicion', $data)) {
            $view->with('ultimaEdicion', Edicion::query()->latest('id')->first());
        }
    }
}
