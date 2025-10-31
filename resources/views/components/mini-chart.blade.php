@props(['data' => [40, 65, 55, 80, 70, 90, 85], 'color' => 'primary'])

@php
    $max = max($data);
    $height = 20;
    $width = count($data) * 4;
@endphp

<svg class="w-{{ $width }} h-5" viewBox="0 0 {{ $width }} {{ $height }}" fill="none">
    @foreach($data as $index => $value)
        <rect
            x="{{ $index * 4 }}"
            y="{{ $height - ($value / $max * $height) }}"
            width="3"
            height="{{ $value / $max * $height }}"
            rx="1"
            class="fill-{{ $color }}-400 opacity-{{ $index < count($data) - 1 ? '50' : '100' }}"
        />
    @endforeach
</svg>