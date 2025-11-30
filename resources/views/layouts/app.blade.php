@include('head')
<body class="font-sans antialiased ">
    <div class="min-h-screen bg-[--color-primary] flex flex-col">
        @include('layouts.navigation')
        <!-- Page Content -->
        <main class="flex-grow">
            {{ $slot }}
        </main>
        @include('layouts.footer')
    </div>
</body>
