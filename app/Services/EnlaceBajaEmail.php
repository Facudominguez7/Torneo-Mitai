<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class EnlaceBajaEmail
{
    public static function generar(User $user): string
    {
        return URL::signedRoute('email.baja', ['id' => $user->id]);
    }
}
