@php
    $currentLocale = app()->getLocale();
    $nextLocale = $currentLocale === 'en' ? 'km' : 'en';
    $fullName = $nextLocale === 'en' ? 'English' : 'ខ្មែរ';
@endphp

<div class="flex items-center">
    <a href="{{ route('locale.switch', $nextLocale) }}" 
       class="flex h-9 min-w-[2.25rem] px-2 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-500/10 dark:text-gray-400 dark:hover:bg-gray-400/10 transition-colors border border-gray-200 dark:border-gray-800" 
       title="{{ __('Switch to') }} {{ $fullName }}">
        <span class="text-xs font-extrabold uppercase tracking-wider">{{ $nextLocale === 'en' ? 'EN' : 'ខ្មែរ' }}</span>
    </a>
</div>
