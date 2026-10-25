<footer class="bg-black text-white text-center py-6">
    <p class="text-sm md:text-base">&copy; Mita'í {{ date('Y') }}. Todos los derechos reservados.</p>
    <nav aria-label="Legales" class="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-1 text-sm">
        <a href="{{ route('terminos') }}" class="text-white/80 underline-offset-4 hover:text-white hover:underline">Términos y condiciones</a>
        <span aria-hidden="true" class="text-white/50">·</span>
        <a href="{{ route('privacidad') }}" class="text-white/80 underline-offset-4 hover:text-white hover:underline">Política de privacidad</a>
    </nav>
    <p class="text-sm md:text-base mt-2">Sitio desarrollado por <span class="font-semibold">Facundo Díaz Domínguez</span>.</p>
    <p class="mt-4">
        <a 
            href="https://www.linkedin.com/in/facundo-diaz-dominguez-0a826b230" 
            class="text-blue-400 hover:underline focus:ring focus:ring-blue-500 rounded" 
            target="_blank" 
            rel="noopener noreferrer"
            lang="es"
        >
            Conéctate conmigo en LinkedIn
        </a>
    </p>
</footer>