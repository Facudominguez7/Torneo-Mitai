@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
@else
<img src="{{ $url }}/fotos/Logo_Mitai_SinFondo.png" class="logo" alt="{{ $slot }}" style="display:inline-block;vertical-align:middle;margin-right:8px;">
<span style="display:inline-block;vertical-align:middle;">{{ $slot }}</span>
@endif
</a>
</td>
</tr>
