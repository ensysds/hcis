@props(['label','value','icon'=>'graph-up','tone'=>'primary','hint'=>null])
<div class="stat-tile tone-{{ $tone }}">
    <div class="stat-icon"><i class="bi bi-{{ $icon }}"></i></div>
    <div class="stat-copy"><span>{{ $label }}</span><strong>{{ $value }}</strong>@if($hint)<small>{{ $hint }}</small>@endif</div>
</div>
