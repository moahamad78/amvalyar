<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>چاپ پلاک اموال</title>
    <style>
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #f3f4f6;
            font-family: Tahoma, Arial, sans-serif;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
            padding: 10px;
            background: #fff;
            border-bottom: 1px solid #d1d5db;
        }

        .toolbar button {
            border: 1px solid #cbd5e1;
            background: #fff;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
        }

        .toolbar .primary {
            background: #111827;
            color: #fff;
            border-color: #111827;
        }

        .print-area {
            display: flex;
            flex-wrap: wrap;
            gap: 4mm;
            align-items: flex-start;
            justify-content: flex-start;
            padding: 10mm;
            direction: rtl;
        }

        .plate-page {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .plate {
            position: relative;
            overflow: hidden;
            background: #fff;
            color: #000;
            border: 0.15mm solid #d1d5db;
        }

        .plate-element {
            position: absolute;
            display: flex;
            align-items: center;
            overflow: hidden;
            line-height: 1.15;
            white-space: normal;
        }

        .align-left { justify-content: flex-start; text-align: left; }
        .align-center { justify-content: center; text-align: center; }
        .align-right { justify-content: flex-end; text-align: right; }

        .machine-code { direction: ltr; }

        .machine-code svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .field-label { margin-left: 2px; }

        @media print {
            html, body { background: #fff; }
            .no-print { display: none !important; }

            .print-area {
                padding: 0;
                gap: {{ $mode === 'sheet' ? '4mm' : '0' }};
            }

            .plate { border: none; }

            @if($mode === 'label')
                @page {
                    size:
                        {{ number_format((float) $template->width_mm, 2, '.', '') }}mm
                        {{ number_format((float) $template->height_mm, 2, '.', '') }}mm;
                    margin: 0;
                }

                .plate-page {
                    width: {{ number_format((float) $template->width_mm, 2, '.', '') }}mm;
                    height: {{ number_format((float) $template->height_mm, 2, '.', '') }}mm;
                    page-break-after: always;
                    break-after: page;
                }

                .plate-page:last-child {
                    page-break-after: auto;
                    break-after: auto;
                }
            @else
                @page {
                    size: A4 portrait;
                    margin: 8mm;
                }
            @endif
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" class="primary" onclick="window.print()">چاپ</button>
        <button type="button" onclick="window.close()">بستن</button>
        <span>{{ $plates->count() }} پلاک — {{ $template->name }}</span>
    </div>

    <main class="print-area">
        @foreach($plates as $plate)
            <div class="plate-page">
                <section
                    class="plate"
                    style="
                        width: {{ number_format((float) $plate['width_mm'], 2, '.', '') }}mm;
                        height: {{ number_format((float) $plate['height_mm'], 2, '.', '') }}mm;
                    "
                    data-asset-code="{{ $plate['asset_code'] }}"
                >
                    @foreach($plate['elements'] as $element)
                        <div
                            @class([
                                'plate-element',
                                'machine-code' => in_array($element['type'], ['qr', 'barcode'], true),
                                'align-left' => $element['align'] === 'left',
                                'align-center' => $element['align'] === 'center',
                                'align-right' => $element['align'] === 'right',
                            ])
                            style="
                                left: {{ number_format(((float) $element['x'] / (float) $plate['width_mm']) * 100, 4, '.', '') }}%;
                                top: {{ number_format(((float) $element['y'] / (float) $plate['height_mm']) * 100, 4, '.', '') }}%;
                                width: {{ number_format(((float) $element['w'] / (float) $plate['width_mm']) * 100, 4, '.', '') }}%;
                                height: {{ number_format(((float) $element['h'] / (float) $plate['height_mm']) * 100, 4, '.', '') }}%;
                                font-size: {{ number_format((float) $element['font_size'], 1, '.', '') }}px;
                                font-weight: {{ $element['bold'] ? '700' : '400' }};
                            "
                        >
                            @if(in_array($element['type'], ['qr', 'barcode'], true))
                                {!! $element['svg'] !!}
                            @else
                                @if($element['label'] !== '' && $element['type'] === 'field')
                                    <span class="field-label">{{ $element['label'] }}:</span>
                                @endif
                                <span>{{ $element['value'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </section>
            </div>
        @endforeach
    </main>
</body>
</html>