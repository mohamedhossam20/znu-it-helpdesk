<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('IT support report') }} — {{ $period }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        body { margin: 0; font-family: "Segoe UI", Tahoma, "Noto Sans Arabic", sans-serif; color: #111827; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-bottom: 1px solid #e5e7eb; }
        .toolbar button { padding: 8px 18px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .page { max-width: 190mm; margin: 0 auto; padding: 20px; }
        header h1 { font-size: 1.4rem; margin: 0; }
        header p { margin: .25rem 0 1.5rem; opacity: .7; }
        @media print { .toolbar { display: none; } .page { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>{{ __('Use "Save as PDF" in the print dialog to get a PDF.') }}</span>
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    </div>

    <div class="page">
        <header>
            <h1>{{ $organization }} — {{ __('IT support report') }}</h1>
            <p>{{ $period }}@if ($building) · {{ $building }}@endif · {{ __('Generated :at', ['at' => now(config('helpdesk.working_hours.timezone'))->format('Y-m-d H:i')]) }}</p>
        </header>

        @include('reports.body')
    </div>
</body>
</html>
