# 🏆 Torneo Mitai

<p align="center">
  <img src="public/og-torneos-mitai.jpg" width="600" alt="Torneo Mitai">
</p>

<p align="center">
  <a href="https://torneosmitai.com">🌐 torneosmitai.com</a> •
  <a href="#instalación">📦 Instalación</a> •
  <a href="#arquitectura">🏛️ Arquitectura</a> •
  <a href="#licencia">📜 Licencia</a>
</p>

Sistema web en **Laravel 11** para la gestión integral de torneos de fútbol infantil: portal público de consulta (fixture, posiciones, goleadores, vallas, instancias finales e historial) y panel de administración completo (ediciones, equipos, partidos, listas de buena fe, cobros y notificaciones por email).

## 🛠️ Stack

PHP 8.3 · Laravel 11 · Blade · TailwindCSS · Alpine.js · MySQL · DomPDF · Vite · Pest · Docker (opcional)

## ⚙️ Funcionalidades

- **Portal público**: fixture por categoría y fecha, tablas de posiciones y goleadores, vallas menos vencidas, instancias finales, historial de campeones y estadísticas por edición.
- **Panel admin**: gestión de ediciones, categorías, equipos e instituciones, grupos y fechas, carga de resultados, listas de buena fe con revisión de conflictos, estadísticas y cobros del torneo.
- **Reportes**: planillas de partido y listas de buena fe imprimibles en PDF.

## 📦 Instalación

Requisitos: PHP >= 8.3, Composer, Node.js >= 18, MySQL >= 8.0.

```bash
git clone https://github.com/Facudominguez7/Torneo-Mitai.git
cd Torneo-Mitai-Laravel
composer install && npm install
cp .env.example .env
php artisan key:generate
# Configurar credenciales de MySQL y Google OAuth en .env
php artisan migrate
npm run build
php artisan serve
```

Con Docker: `./docker-start.sh` — ver [DOCKER.md](DOCKER.md) para más detalle.

## 🧪 Testing

```bash
php artisan test    # o ./vendor/bin/pest
```

## 🏛️ Arquitectura

```mermaid
erDiagram
    EDICION ||--o{ CATEGORIA : tiene
    EDICION ||--o{ EQUIPO_EDICION : participa
    EDICION ||--o{ FECHA : contiene
    CATEGORIA ||--o{ GRUPO : agrupa
    EQUIPO ||--o{ EQUIPO_EDICION : inscrito_en
    EQUIPO ||--o{ JUGADOR : pertenece
    EQUIPO }o--|| INSTITUCION : asociado_a
    GRUPO ||--o{ EQUIPO_GRUPO : contiene
    GRUPO ||--o{ PARTIDO : programa
    PARTIDO ||--o{ PLANILLA_JUGADOR : registra
    PARTIDO }o--|| FECHA : jugado_en
    JUGADOR ||--o{ PLANILLA_JUGADOR : participa
    JUGADOR ||--o{ LISTA_BUENA_FE : habilitado_en
    JUGADOR ||--o{ TABLA_GOLEADOR : goles
```

Despliegue automático a Hostinger vía GitHub Actions (push a `main` → build → deploy SSH).

## 📜 Licencia

Proyecto **privado y propietario**: todos los derechos reservados. La publicación del código no otorga licencia de uso. Ver [LICENSE](LICENSE).

## 👨‍💻 Autor

**Ing. Facundo Díaz Domínguez** — [LinkedIn](https://www.linkedin.com/in/facundo-diaz-dominguez-0a826b230) · [torneosmitai.com](https://torneosmitai.com)

<p align="center">
  <sub>Hecho con ❤️ para el fútbol formativo</sub>
</p>
