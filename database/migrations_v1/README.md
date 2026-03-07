Migraciones legacy archivadas.

Esta carpeta queda fuera del flujo operativo normal de Laravel.

- Laravel ejecuta por defecto solo `database/migrations`.
- Los archivos en `database/migrations_v1` se conservan como referencia historica.
- No deben usarse para altas nuevas, despliegues ni auditorias del esquema vigente sin una revision manual.

Nota de seguridad:

- Cualquier definicion legacy relacionada con autenticacion o credenciales, como tablas antiguas de usuarios, debe considerarse material historico y no evidencia del esquema activo.
- Si alguna migracion de esta carpeta necesitara reutilizarse, debe copiarse al flujo actual y revisarse antes para evitar reintroducir estructuras obsoletas o inseguras.
