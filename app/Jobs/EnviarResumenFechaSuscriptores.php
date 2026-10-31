<?php

namespace App\Jobs;

/**
 * Nombre anterior del job de envíos: se conserva para que los trabajos que ya estaban en la cola
 * con este nombre se sigan procesando. Los envíos nuevos encolan EnviarAvisoSuscriptores.
 */
class EnviarResumenFechaSuscriptores extends EnviarAvisoSuscriptores {}
