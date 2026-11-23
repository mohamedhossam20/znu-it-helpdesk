<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Room QR labels') }}</title>
    <style>
        @page { size: A4; margin: 10mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, "Noto Kufi Arabic", sans-serif;
            color: #111827;
            background: #f3f4f6;
        }

        .toolbar {
            position: sticky;
            top: 0;
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        .toolbar button {
            padding: 8px 18px;
            border: 0;
            border-radius: 8px;
            background: #2563eb;
            color: #fff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .sheet {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8mm;
            width: 190mm;
            margin: 20px auto;
        }

        .label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2mm;
            height: 62mm;
            padding: 4mm;
            border: 1.5px dashed #9ca3af;
            border-radius: 4mm;
            background: #fff;
            text-align: center;
            break-inside: avoid;
        }

        .org { font-size: 9pt; color: #4b5563; }
        .qr svg { width: 34mm; height: 34mm; display: block; }
        .code { font-size: 16pt; font-weight: 800; letter-spacing: .5px; }
        .name { font-size: 10pt; font-weight: 600; }
        .where { font-size: 8pt; color: #4b5563; }
        .hint { font-size: 8pt; color: #1d4ed8; font-weight: 600; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>{{ trans_choice(':count label|:count labels', $labels->count(), ['count' => $labels->count()]) }}</span>
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    </div>

    <main class="sheet">
        @foreach ($labels as $label)
            <section class="label">
                <div class="org">{{ $organization }} — {{ __('IT support') }}</div>
                <div class="qr" title="{{ $label['url'] }}">{!! $label['svg'] !!}</div>
                <div class="code">{{ $label['room']->code }}</div>
                <div class="name">{{ $label['room']->name_ar }}</div>
                <div class="where">{{ $label['room']->floor->building->name_ar }} › {{ $label['room']->floor->name_ar }}</div>
                <div class="hint">{{ __('Scan to report a technical problem in this room') }}</div>
            </section>
        @endforeach
    </main>
</body>
</html>
