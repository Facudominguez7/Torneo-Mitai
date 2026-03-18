# Diseno tecnico - equipos sin asistencia en panel admin

## Resumen

Se incorporara un nuevo bloque informativo en `Panel/admin` que muestre los equipos con asistencia pendiente dentro del mismo conjunto de partidos que el dashboard ya renderiza: ultima fecha de la edicion seleccionada y, si aplica, horario filtrado.

La solucion reutilizara el flujo actual del dashboard admin y concentrara la logica de deteccion en un servicio, para mantener `ControladorHome` liviano y evitar consultas repetidas desde la vista.

## Componentes involucrados

### Controlador

- `app/Http/Controllers/ControladorHome.php`
  - El metodo `admin()` seguira resolviendo la edicion, la ultima fecha, los partidos visibles y las estadisticas existentes.
  - Sumara una nueva variable de vista: `equiposSinAsistencia`.
  - La obtendra desde `EstadisticasService`, junto con las metricas ya presentes del sidebar.

### Servicio

- `app/Services/EstadisticasService.php`
  - Se ampliara con un metodo dedicado, por ejemplo `obtenerEquiposSinAsistenciaUltimaFecha(int $idEdicion, ?string $horario = null): array`.
  - Este metodo resolvera toda la logica de consulta, agregacion y formateo del reporte.

Motivo de esta decision:

- El dashboard admin ya depende de `EstadisticasService` para datos derivados de la ultima fecha.
- La regla de "equipo pendiente" es logica de negocio/reporting, no presentacion.
- La cantidad de partidos en la ultima fecha es acotada, por lo que una agregacion puntual en servicio mantiene buen rendimiento y baja complejidad.

### Vista

- `resources/views/Panel/admin.blade.php`
  - Se agregara una seccion visible solo en la ruta `admin`.
  - La seccion se ubicara antes del listado de partidos, para que funcione como alerta operativa del contexto actual.
  - Renderizara un estado resumido y una lista de equipos pendientes con enlace directo a `planilla.show`.

## Modelo de datos utilizado

### Tablas existentes

- `partidos`
  - fuente del contexto visible en el dashboard admin.
- `planilla_jugadores`
  - fuente del estado de asistencia por equipo y partido.
- `equipos`
  - nombre y foto de local/visitante.
- `categorias`
  - nombre de categoria.
- `fechas`
  - nombre de la fecha visible.

### Regla de lectura

La deteccion se hara solo sobre registros de `planilla_jugadores` con:

- `partido_type = App\Models\Partido`
- `idEdicion = <edicion activa>`
- `partido_id` dentro de los partidos visibles del dashboard admin

Esto evita mezclar planillas de instancias finales y mantiene el reporte alineado con la pantalla actual.

## Estrategia de consulta

### Paso 1 - Resolver contexto visible

El servicio obtendra:

1. la ultima `Fecha` de la edicion seleccionada;
2. los `Partido` de esa fecha y edicion;
3. el filtro opcional por horario usando la misma regla actual del dashboard.

Los partidos se cargaran con sus relaciones necesarias para evitar N+1:

- `equipoLocal`
- `equipoVisitante`
- `categoria`
- `fecha`

### Paso 2 - Agregar asistencia por partido y equipo

Se ejecutara una consulta agregada sobre `planilla_jugadores`, agrupada por:

- `partido_id`
- `idEquipo`

Campos agregados requeridos:

- `total_planilla`: cantidad total de registros del equipo en ese partido.
- `total_asistencias`: suma de jugadores con `asistio = 1`.

La implementacion puede resolverse con Query Builder de Laravel 11 usando `groupBy` y, si conviene para claridad, `leftJoinSub` para anexar el resumen a los partidos. La referencia oficial consultada valida el uso de `leftJoinSub`, `groupBy` y `having` para este tipo de reportes agregados en Laravel 11.

### Paso 3 - Normalizar local y visitante

Con la coleccion de partidos y el agregado de planillas, el servicio recorrera cada partido y evaluara dos entradas posibles:

- equipo local
- equipo visitante

Para cada una:

- si no existe resumen de planilla, el estado sera `sin_planilla`;
- si existe resumen y `total_asistencias = 0`, el estado sera `sin_asistencias_marcadas`;
- si `total_asistencias > 0`, ese equipo no se incluye en el reporte.

Esta normalizacion en PHP reduce complejidad SQL, mantiene legible la regla y es suficiente para el volumen esperado de partidos por fecha.

## Contrato de salida del servicio

El metodo retornara una estructura lista para vista, por ejemplo:

```php
[
    'nombre_fecha' => 'Fecha 7',
    'total_pendientes' => 3,
    'equipos' => [
        [
            'partido_id' => 15,
            'equipo_id' => 22,
            'equipo' => 'Mitai Azul',
            'rival' => 'Mitai Rojo',
            'categoria' => 'Sub 12',
            'fecha' => 'Fecha 7',
            'horario' => '10:30',
            'cancha' => '2',
            'condicion' => 'Local',
            'estado' => 'sin_planilla',
        ],
    ],
]
```

## Render en la vista

La nueva seccion de `resources/views/Panel/admin.blade.php` mostrara:

- un encabezado con cantidad de equipos pendientes;
- un texto de contexto con la fecha visible y, si existe, el horario filtrado;
- una grilla responsiva de tarjetas o filas compactas;
- una etiqueta visual para distinguir `sin planilla` de `sin asistencias marcadas`;
- un boton/enlace a la planilla del partido usando la misma ruta `planilla.show` y preservando el contexto de `idEdicion`, `horario`, `origen` y `horario_filtro`.

Si no hay pendientes:

- se mostrara un estado positivo y breve, en vez de ocultar el bloque, para evitar dudas sobre si el reporte cargo correctamente.

## Impacto en rutas y base de datos

- No se agregan rutas nuevas.
- No se modifican migraciones ni tablas.
- No se altera el flujo de guardado de planillas.

## Casos borde contemplados

1. Sin edicion seleccionada: el servicio retorna estructura vacia y la vista no muestra alertas enganosas.
2. Sin ultima fecha: retorno vacio.
3. Sin partidos visibles por horario: retorno vacio o estado sin pendientes para el contexto filtrado.
4. Partido con ambos equipos pendientes: se renderizan dos items independientes.
5. Partido con planilla cargada pero ninguna asistencia marcada: sigue figurando pendiente.
6. Partido nunca abierto en planilla: figura como `sin planilla`.

## Validacion prevista para implementacion

- Prueba del servicio para confirmar que detecta:
  - equipo sin planilla;
  - equipo con planilla pero sin asistencias;
  - equipo con al menos una asistencia cargada.
- Verificacion visual del bloque en `Panel/admin` con y sin filtro horario.
- Verificacion de navegacion al enlace de planilla correcto.
