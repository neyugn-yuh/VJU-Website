@php
    $currentLocale = app()->getLocale();
    $locales = [
        'vi' => ['code' => 'vi', 'short' => 'VI', 'label' => 'Tiếng Việt', 'flag' => '🇻🇳'],
        'en' => ['code' => 'en', 'short' => 'EN', 'label' => 'English', 'flag' => '🇬🇧'],
        'ja' => ['code' => 'ja', 'short' => 'JA', 'label' => '日本語', 'flag' => '🇯🇵'],
    ];
@endphp

<div class="vju-admin-lang-switcher" role="group" aria-label="{{ __('admin.language') }}">
    <div class="vju-lang-pill-track">
        @foreach($locales as $code => $data)
            <a 
                href="{{ route('admin.locale', ['locale' => $code]) }}" 
                class="vju-lang-pill {{ $currentLocale === $code ? 'is-active' : '' }}"
                title="{{ $data['label'] }}"
                aria-label="{{ $data['label'] }}"
            >
                <span class="vju-lang-code">{{ $data['short'] }}</span>
            </a>
        @endforeach
    </div>
</div>
