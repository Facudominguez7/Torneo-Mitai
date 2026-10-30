# Design System — Torneo Mitai

Sincronizado desde las fuentes del proyecto. Los valores listados provienen textualmente de los archivos fuente; nada fue inventado.

## Fuentes de diseño leídas

- `tailwind.config.js` (raíz del proyecto)
- `resources/css/app.css` (`:root`)
- `resources/css/admin/admin.css` (`:root`)
- `resources/views/head.blade.php` (declaración de fuente)
- `resources/views/welcome.blade.php` (uso real en la home)
- `resources/views/components/marca-agua-portal.blade.php` (marca de agua compartida)

## Tipografía

| Token | Valor | Uso | Fuente |
| --- | --- | --- | --- |
| `font-sans` | `Barlow`, fallback `defaultTheme.fontFamily.sans` | Todo el sitio | `tailwind.config.js` (`theme.extend.fontFamily.sans`), cargada en `head.blade.php` (pesos 400/500/600/700) |

Nota para artboards: los mockups no cargan fuentes remotas; aproximan Barlow con la pila del sistema (`system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`).

## Logo e identidad

| Variante | Archivo | Dimensiones | Fuente |
| --- | --- | --- | --- |
| Logo principal | `public/fotos/Logo_Mitai_SinFondo.png` | 498×501 PNG RGBA (fondo transparente) | `navigation.blade.php` línea 25, `head.blade.php` líneas 53/56 |
| Alta resolución | `public/fotos/Logo_Mitai_SinFondo1.png` | 1280×1198 PNG RGBA | `public/fotos/` |

Uso real en el proyecto:

| Contexto | Uso | Fuente |
| --- | --- | --- |
| Navegación del portal | `<img>` 40×40 (`h-10 w-10`) + wordmark "TORNEO MITAI": 14px/600, mayúsculas, `tracking-[0.3em]`, `text-white/60` | `layouts/navigation.blade.php` líneas 25–27 |
| Favicon y apple-touch-icon | El mismo `Logo_Mitai_SinFondo.png` | `head.blade.php` líneas 53/56 |
| Header de emails | Logo inline al inicio del header | `vendor/mail/html/header.blade.php` línea 8 |
| Fallback de escudos de equipos | Imagen por defecto vía `onerror` cuando el equipo no tiene foto | `layouts/campeones.blade.php`, `layouts/tabla-vallas.blade.php` |

Lockup de navegación (valores reales del código):

```
[Logo 40×40]  TORNEO MITAI   ← 14px / 600 / uppercase / letter-spacing 0.3em / rgba(255,255,255,.6)
```

Reglas propuestas (no extraídas de código; marcar como convención a acordar):

- Mantener el logo cuadrado: nunca estirar, rotar ni teñir; el PNG transparente funciona sobre fondo oscuro y claro sin recolorar.
- Tamaño mínimo en UI: 24px; tamaño de navegación: 40px.
- En los artboards de `design/` se embebe una copia reducida a 128px en base64 para mantenerlos autocontenidos.

### Marca de agua del portal · convención aprobada

El fondo con el logo es parte durable del sistema de diseño del portal público, incluida la página web de baja de suscripción. No se aplica al panel, login ni emails; se oculta al imprimir. Mantener legibles los textos y controles por encima de la marca.

El componente `resources/views/components/marca-agua-portal.blade.php` usa `fotos/Logo_Mitai_SinFondo.png` como imagen decorativa (`aria-hidden` y `alt` vacío). Según `resources/css/app.css`, la imagen está fija y centrada en la ventana, sin desenfoque (`filter: none`), con ancho `min(760px, 95vw)` y altura automática. El contexto aislado y `z-index: -1` la sitúan detrás del contenido; `pointer-events: none` evita interferir con las interacciones. La opacidad `--tm-marca-agua` es `.17` en oscuro y `.08` en claro, siguiendo el tema del sistema o el override manual.

## Colores de marca (portal)

Variables crudas en `resources/css/app.css` `:root`, expuestas como `primary`, `secondary`, `brand.*` en `tailwind.config.js`:

| Token | Valor | Uso | Fuente |
| --- | --- | --- | --- |
| `primary` / `brand-navy` | `rgb(14, 75, 94)` = `#0E4B5E` | Color primario de marca | `app.css` líneas 14/34 |
| `secondary` / `brand-teal` | `rgb(42, 109, 129)` = `#2A6D81` | Color secundario de marca | `app.css` líneas 15/35 |
| `gold` / `brand-gold` | `rgb(247, 242, 128)` = `#F7F280` | Acento dorado claro | `app.css` líneas 16/36 |
| `brand-navy-deep` | `rgb(8, 47, 59)` = `#082F3B` | Navy profundo (fondos) | `app.css` línea 38 |
| `brand-teal-light` | `rgb(155, 211, 226)` = `#9BD3E2` | Teal claro | `app.css` línea 39 |

## Tema semántico dual implementado

Fuente de todas las filas: `resources/css/app.css` (`:root`, media query y atributos `data-tema`); los alias indicados están en `tailwind.config.js` (`colors.tema`). Canales RGB se consumen como `rgb(var(--tm-*) / <alpha-value>)`; los valores `rgba`, `#FFFFFF`, opacidad y sombra son valores completos, no canales.

Sin atributo, oscuro es el valor base y `prefers-color-scheme: light` activa claro. `data-tema="claro"` fuerza claro y `data-tema="oscuro"` fuerza oscuro, independientemente del sistema. El selector sistema → claro → oscuro es la guía aprobada; la CSS define la precedencia, no la persistencia del control.

| Variable | Oscuro | Claro | Rol / alias Tailwind |
| --- | --- | --- | --- |
| `--tm-fondo` | `8 47 59` | `255 255 255` | Fondo / `tema.fondo` |
| `--tm-tinta` | `255 255 255` | `8 47 59` | Texto / `tema.tinta` |
| `--tm-dim` | `235 244 248` | `8 47 59` | Texto secundario / `tema.dim` |
| `--tm-linea` | `255 255 255` | `8 47 59` | Bordes / `tema.linea` |
| `--tm-fila` | `255 255 255` | `14 75 94` | Filas / `tema.fila` |
| `--tm-superficie` | `0 0 0` | `14 75 94` | Superficie / `tema.superficie` |
| `--tm-superficie-bg` | `rgba(0, 0, 0, .35)` | `rgba(14, 75, 94, .055)` | Fondo de superficie / `tema.superficie-bg` |
| `--tm-fila-bg` | `rgba(255, 255, 255, .06)` | `rgba(14, 75, 94, .05)` | Fondo de fila / `tema.fila-bg` |
| `--tm-fila-hover-bg` | `rgba(255, 255, 255, .12)` | `rgba(14, 75, 94, .09)` | Hover de fila |
| `--tm-campo-borde` | `rgba(18, 38, 63, .3)` | `rgba(255, 255, 255, .4)` | Borde de campo |
| `--tm-acento` | `155 211 226` | `42 109 129` | Acento / `tema.acento` |
| `--tm-panel-borde` | `255 204 51` | `8 47 59` | Borde del panel / `tema.panel-borde` |
| `--tm-panel-bg` | `rgba(255, 255, 255, .06)` | `#FFFFFF` | Superficie del panel |
| `--tm-panel-brillo` | `rgba(255, 255, 255, .10)` | `rgba(14, 75, 94, .03)` | Brillo del panel |
| `--tm-campo-fondo` | `255 255 255` | `8 47 59` | Fondo del campo / `tema.campo-fondo` |
| `--tm-campo-tinta` | `18 38 63` | `255 255 255` | Texto del campo / `tema.campo-tinta` |
| `--tm-velo` | `8 47 59` | `255 255 255` | Velo / `tema.velo` |
| `--tm-brillo` | `rgba(255, 255, 255, .16)` | `rgba(255, 191, 0, .28)` | Brillo interactivo |
| `--tm-marca-agua` | `.17` | `.08` | Opacidad de marca de agua |
| `--tm-sombra-panel` | `none` | `5px 5px 0 rgba(8, 47, 59, .1)` | Sombra de panel |

En fixture, `--fx-destacado` es `#FFCC33` en oscuro y `#997300` en claro (`resources/css/app.css`, `.fixture-tablero`). La navegación móvil activa usa los mismos oros. Estos son usos existentes, no autorización para introducir nuevos colores.

## Escalas de posición (torneo)

| Token | DEFAULT | 50 | 100 | 300 | 500 | 700 | 900 | Fuente |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `oro` | `#FFD700` | `#FFF9E6` | `#FFF2CC` | `#FFD966` | `#FFBF00` | `#997300` | `#332600` | `tailwind.config.js` |
| `bronce` | `#CD7F32` | `#FBF0E6` | `#F6E0CC` | `#E2A266` | `#CD7F32` | `#7B4C1E` | `#29190A` | `tailwind.config.js` |

Valores restantes de las escalas (`tailwind.config.js`): oro 200 `#FFE699`, 400 `#FFCC33`, 600 `#CC9900`, 800 `#664D00`; bronce 200 `#ECC199`, 400 `#D88333`, 600 `#A46528`, 800 `#523314`.

Uso histórico: campeones → oro; subcampeones → plata/gris; tercer puesto Mitai → bronce (convención de las vistas `layouts/campeones.blade.php`).

## Panel de administración

| Token | Valor | Uso | Fuente |
| --- | --- | --- | --- |
| `panel.border` | `#E0E0E0` | Bordes de formularios del panel | `tailwind.config.js` |
| `panel.accent` | `#6A64F1` | Botones/acentos de formularios del panel | `tailwind.config.js` |
| `--light` | `#EDF2F9` | Fondo claro del panel | `admin.css` |
| `--dark` | `#152E4D` | Sidebar/texto del panel | `admin.css` |
| `--darker` | `#12263F` | Variante más oscura | `admin.css` |
| `--color-green` | `#16A34A` | Estado éxito | `admin.css` |
| `--color-red` | `#DC2626` | Estado error/peligro | `admin.css` |
| `--color-yellow` | `#CA8A04` | Estado advertencia | `admin.css` |

Otros tokens de `resources/css/admin/admin.css`: `--color-blue: #2563eb`, `--color-cyan: #0891b2`, `--color-teal: #0d9488`, `--color-fuchsia: #c026d3`, `--color-orange: #ea580c`, `--color-violet: #7c3aed`. Son paleta heredada del panel, no nuevos acentos aprobados para el portal.

## Forma, sombras y patrones en uso

| Patrón | Valor | Fuente |
| --- | --- | --- |
| Radios portal | `rounded-2xl` (cards/chips), `rounded-3xl` (cards destacadas), `rounded-full` (pills/CTAs) | `welcome.blade.php` |
| Radios formularios panel | `rounded-md` | `app.css` (`@apply` de inputs/botones) |
| Sombra glow destacada | `0 24px 60px -30px rgba(color, .35–.9)` | `welcome.blade.php` + `safelist` de `tailwind.config.js` |
| Glassmorphism | `bg-white/10` + `backdrop-blur-md` + borde `border-white/10–15` | `welcome.blade.php` (card "Seguir mi equipo") |
| Fondo oscuro de la home | `bg-slate-950` con gradientes `via-black` | `welcome.blade.php` |

### Patrones implementados para home y fixture

Fuente: `resources/css/app.css`. Selector de edición: gap `6px`, padding `10px 44px 10px 18px`, radio `999px`, foco de `3px`; lista con radio `14px` y sombra `0 24px 50px -18px rgba(8, 47, 59, .55)`. En fixture, chips con altura mínima `2.25rem`, gap `.5rem`, radio `999px`; tarjeta compacta con padding `1.125rem 1.25rem`, radio `1rem` y separación `.875rem`. El estado activo usa `brand-teal-light` sobre `brand-navy-deep`; el buscador usa `--tm-fila-bg`, `--tm-acento` y `--tm-tinta`. Datos de fixture usan `ui-monospace, 'SF Mono', Menlo, Consolas, monospace`. Son ejemplos reales de espaciado y forma, no una escala global personalizada.

En el fixture, la barra compacta visible se fija debajo de la navegación con fondo `--tm-fondo`; sus botones tienen altura mínima `2.75rem`, separación `.5rem`, radio `999px` y foco `3px` con `--tm-acento`. Desde `640px` los botones de texto tienen ancho mínimo `10rem`; desde `1024px` el fixture usa columna de fechas `13.75rem`, separación `2rem` y dos columnas de partidos. En móvil (`max-width: 639px`), chips de `2.75rem` y buscador de `3rem`. La acción de planilla usa altura mínima `2rem`, radio `999px` y una variante no disponible con borde discontinuo. Fuente: `resources/css/app.css`.

La implementación real incluye gradientes, marca de agua sin desenfoque, tilt y sombra de lista; la propuesta editorial siguiente desaconseja algunos de esos recursos para nuevas direcciones. La marca de agua aprobada es una convención vigente, no una propuesta pendiente. Mantener explícita esta diferencia, sin presentar la propuesta como descripción de la home existente. El movimiento de entrada y tilt está condicionado a `prefers-reduced-motion: no-preference`.

### Ranking público de goleadores

Fuente: `resources/css/app.css`, selectores `.goleadores-*`. El fondo combina `--tm-fondo` con líneas de cancha `brand-teal` a baja opacidad. El podio usa `brand-navy-deep`, `brand-navy`, `brand-gold` y `brand-teal-light`, con borde redondeado de `1rem`; las cifras usan la pila monoespaciada del proyecto. Los filtros usan `--tm-fila-bg`, radio `1rem`, foco de `3px` y flecha con `--tm-acento`. En móvil hay barra compacta desde el inicio; en escritorio y tablet aparece al desplazar. Los botones compactos tienen altura mínima `2.75rem` y radio `999px`. Entrada y realce por mouse respetan movimiento reducido.

### Tabla pública de posiciones

Fuente: `resources/css/app.css`, selectores `.posiciones-*`. El tablero usa fondo `--tm-fondo` con cuadrícula de `brand-teal` al 8% cada 44px, texto `--tm-tinta`, encabezado `brand-navy`, bordes `--tm-linea` al 22% y filas `--tm-fila-bg`. Los filtros tienen altura mínima `2.75rem`, radio `999px` y acento `brand-teal-light`; el tablero usa datos monoespaciados. Los escudos se muestran sobre fondo blanco circular, con `2px` de padding, para conservar contraste. En móvil (hasta 700px) quedan visibles puesto, equipo, puntos, jugados y diferencia; el resto se conserva accesible. Las filas se animan solo si no se solicita movimiento reducido.

### Planilla pública compartida

Fuente: `resources/css/app.css`, reglas `.planilla-publica` (fixture y finales). Es un patrón implementado, no una nueva escala global ni una propuesta para posiciones.

| Elemento | Valores y comportamiento existentes |
| --- | --- |
| Velo y hoja | Capa fija `z-index: 50`, velo `rgb(var(--brand-navy-deep) / .8)`; hoja centrada de `min(100%, 65.625rem)`, alto máximo `calc(100dvh - 2rem)`, borde `1px` de `--tm-linea` al `.2` y fondo `--tm-fondo`. |
| Cabecera | Padding `1.125rem 1.5rem`, separación `1rem`, borde inferior `2px` de `--tm-acento`; título `1.5rem`/700; cierre `2.75rem` cuadrado con foco `3px` y offset `2px`. |
| Contenido y equipos | Contenido con scroll vertical y padding `1.25rem 1.5rem 1.875rem`; equipos en dos columnas con gap `1.75rem`; rótulos y subtítulos `.75rem`/700, mayúsculas y tracking `.1em`. |
| Datos y estados | Tabla con padding de celda `.5625rem .3125rem`, borde inferior `1px`, primera y última columna en `ui-monospace, 'SF Mono', Menlo, Consolas, monospace` y última alineada a derecha. Aviso y reporte usan `--tm-fila-bg` y borde izquierdo de acento `4px` y `3px`, respectivamente; estado con alto mínimo `12.5rem`. |
| Móvil | Hasta `700px`, hoja alineada abajo, sin padding exterior y alto máximo `94dvh`; cabecera y contenido con padding `1rem`, equipos en una columna con gap `1.25rem` y metadatos en columna. |

## Desviación conocida (a sistematizar)

La home actual (`welcome.blade.php`) usa acentos directos de la paleta default de Tailwind — `yellow-300`, `emerald-400`, `sky-400`, `rose-500` — en lugar de los tokens de marca. El amarillo de CTA (`#FDE047`) se aproxima a `oro-300/400` de la escala del sistema. Nuevos diseños deben usar los tokens declarados (`brand-*`, `oro-*`, `bronce-*`) y no introducir colores fuera del sistema.

## Propuesta de lenguaje visual — dirección de arte (oct 2026)

> Propuesta derivada del brief de rediseño con identidad propia. No reemplaza los tokens de arriba: define **roles y reglas de uso**. Sujeta a aprobación del usuario; si se aprueba, se promueve a regla del sistema.

**Análisis de producto que la sustenta**

- **Propósito**: servicio de consulta del torneo (qué juega, cuándo, cómo quedó, quién va arriba). No es un producto SaaS: es la hoja de fixture del club, digitalizada.
- **Usuarios**: familias que siguen una categoría (mobile, visitas cortas los findes), delegados/clubes (tablas y goleadores), organización (panel aparte).
- **Jerarquía real**: partidos de la fecha → resultados → tablas/goleadores → historial → comunidad.

**Reglas de lenguaje visual**

| Regla | Decisión | Justificación |
| --- | --- | --- |
| Composición | Editorial asimétrica (columnas 7-8/4-5), contenido como filas y tablas, no cards uniformes | El contenido es tabular y jerárquico; evita la simetría repetitiva genérica. Referencias: Carbon (grilla/densidad), Primer (tipografía funcional), USWDS (claridad) |
| Bordes y radios | Filetes 1px; radios 2-6px máximo | El torneo vive de tablas: los bordes rectos leen "tabla", no "app de consumo" |
| Sombras | Ninguna o dura de 2-3px; prohibido el glow difuso | La sombra suave exagerada es el patrón genérico más reconocible |
| Tipografía de datos | Todo dato vivo (horarios, marcadores, posiciones, goles, fechas) en pila monoespaciada (`ui-monospace, ...`) | Firma visual "tablero de resultados" + alineación numérica real en columnas |
| Tipografía de display | Barlow 800 con tracking negativo leve; propuesta: sumar Barlow Condensed (Google Fonts, licencia abierta) para titulares de póster | Barlow ya es la fuente del proyecto y no está en el circuito genérico IA (Inter/Poppins) |
| Color funcional | `navy-deep` = tinta; blanco = papel en direcciones claras; `teal` = dato/enlace informativo; **oro reservado a acción primaria y logros** (con `bronce`); rojo solo estados | El oro pierde valor si se usa como decoración; prohibidos gradientes violeta/azul/rosa |
| Superficies | Sin glassmorphism, sin blur decorativo, sin blobs de fondo | Ninguno aporta a la tarea del usuario |
| Microinteracciones | Discretas y funcionales: subrayado que aparece, fondo de fila en hover, flecha que avanza | Nada que "flote"; movimiento solo si informa |
| Iconografía | SVG inline stroke 1.5-2px homogéneo; sin emojis como íconos | Coherencia y accesibilidad |
| Estados | Todo componente con hover, focus-visible (outline 3px), activo y vacío definidos | Calidad de servicio, no de demo |
| **Tema** | **Dual: sigue `prefers-color-scheme` del sistema y ofrece override manual (sistema → claro → oscuro)**. Ambos temas usan los mismos tokens de marca; en claro la tinta es `navy-deep` sobre papel blanco y el elemento focal (buscador) invierte a navy para conservar jerarquía | Requisito del usuario; el override manual cubre sistemas que no reflejan la preferencia real (aprobado oct 2026, artboard 06) |

Las tres direcciones del rediseño (`design/inicio-identidad-propia/`) aplican estas reglas con distinta estructura y superficie: clara editorial (01), oscura de estadio (02), póster tipográfico (03).
