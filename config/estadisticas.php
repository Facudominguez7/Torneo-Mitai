<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Participaciones mínimas
    |--------------------------------------------------------------------------
    |
    | Cantidad de partidos realmente disputados que separa a los jugadores
    | «con N o más partidos» en el KPI del dashboard y en los contadores del
    | listado de Estadísticas de jugadores (#202). Es también el valor
    | inicial del filtro «Participaciones mínimas», que acepta cualquier
    | cantidad entera entre 1 y 999.
    |
    */

    'participaciones_minimas' => (int) env('ESTADISTICAS_PARTICIPACIONES_MINIMAS', 5),

];
