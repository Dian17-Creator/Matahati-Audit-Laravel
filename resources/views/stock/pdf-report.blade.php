@php
function fmtStatus($status) {
return match ($status) {
"Draft" => "Draft",
"Submitted" => "Selesai",
default => "Dalam Proses",
};
}

/**
* Helper to convert images to Base64 for guaranteed visibility in DomPDF
*/
function getBase64Image($url) {
if (empty($url)) return null;

try {
// Find relative path by stripping host info
$cleanUrl = explode('?', $url)[0];
$hosts = [request()->getSchemeAndHttpHost(), url('/'), 'http://localhost'];

$relativePath = $cleanUrl;
foreach ($hosts as $host) {
if (str_contains($cleanUrl, $host)) {
$relativePath = str_replace($host, '', $cleanUrl);
break;
}
}

$path = public_path(ltrim($relativePath, '/'));

if (file_exists($path)) {
$type = pathinfo($path, PATHINFO_EXTENSION);
$data = file_get_contents($path);
return 'data:image/' . $type . ';base64,' . base64_encode($data);
}
} catch (\Exception $e) {
// Fallback to original URL if anything fails
}

return $url;
}

$hasPhotos = false;
foreach ($categories as $category) {
foreach ($category['items'] as $item) {
if (!empty($item['photos'])) {
$hasPhotos = true;
break 2;
}
}
}
@endphp
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $header['document_id'] }}</title>
    <style>
        /* 
         * DOMPDF COMPATIBLE CSS
         */
        @page {
            size: A4;
            margin: 0.9cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9.5pt;
            line-height: 1.25;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .header-table {
            width: 100%;
            margin-bottom: 15px;
        }

        .header-title {
            font-size: 20pt;
            font-weight: bold;
            color: #333;
        }

        .status-badge {
            display: inline-block;
            border: 2pt solid #B63352;
            padding: 5pt 12pt;
            border-radius: 6pt;
            font-weight: bold;
            color: #B63352;
            text-transform: uppercase;
            font-size: 9pt;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12pt;
            border: 1.5pt solid #e0e0e0;
            border-left: 4pt solid #B63352;
        }

        .summary-table tr {
            border-bottom: 1pt solid #ececec;
        }

        .summary-table td {
            border: none;
            border-bottom: 1pt solid #ececec;
            border-right: 1pt solid #ececec;
            padding: 6pt 8pt;
            vertical-align: middle;
        }

        .summary-label {
            width: 18%;
            background-color: #fdf5f7;
            font-weight: bold;
            color: #B63352;
            font-size: 6.5pt;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            white-space: nowrap;
        }

        .summary-value {
            width: 32%;
            font-weight: bold;
            color: #1a1a1a;
            font-size: 8.5pt;
        }

        hr {
            border: 0;
            border-top: 1.5pt solid #B63352;
            margin: 15pt 0;
        }

        h2 {
            font-size: 14pt;
            color: #333;
            margin-bottom: 8pt;
            padding-bottom: 4pt;
        }

        /* MODERN ASSESSMENT TABLE */
        .category-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10pt;
            page-break-inside: auto;
            border: 1pt solid #ddd;
        }

        .category-row-header {
            background-color: #B63352;
            color: white;
            page-break-after: avoid;
        }

        .category-row-header td {
            padding: 6pt 9pt;
            font-size: 11pt;
            font-weight: bold;
        }

        .question-row {
            border-bottom: 1pt solid #ddd;
            page-break-inside: avoid;
        }

        .question-row td {
            padding: 6pt 8pt;
            vertical-align: top;
            border-left: 1pt solid #ddd;
        }

        .question-text-cell {
            width: 82%;
        }

        .score-cell {
            width: 18%;
            text-align: center;
            vertical-align: middle !important;
        }

        .score-pill {
            display: block;
            padding: 3pt 0;
            border-radius: 4pt;
            color: white;
            font-weight: bold;
            font-size: 9pt;
            width: 35pt;
            margin: 0 auto;
        }

        .qty-label {
            font-size: 7.5pt;
            color: #777;
            text-transform: uppercase;
            display: inline-block;
            width: 25pt;
            text-align: left;
        }

        .qty-val {
            font-size: 8.5pt;
            font-weight: bold;
            color: #333;
            display: inline-block;
            text-align: right;
            width: 15pt;
        }

        .remark-box {
            margin-top: 4pt;
            padding: 5pt 8pt;
            background-color: #fdfdfd;
            border: 1pt solid #eee;
            border-left: 4pt solid #B63352;
            font-size: 8.5pt;
            line-height: 1.2;
            color: #555;
            border-radius: 4pt;
        }

        .remark-label {
            font-size: 7.5pt;
            font-weight: bold;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 2pt;
        }

        /* SCORE COLORS */
        .bg-red {
            background-color: #dc2626;
        }

        .bg-green {
            background-color: #16a34a;
        }

        .bg-gray {
            background-color: #6b7280;
        }

        /* ENHANCED SIGNATURE SECTION */
        .signature-table {
            width: 100%;
            margin-top: 20pt;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .signature-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 8pt;
        }

        .signature-label {
            font-size: 9pt;
            font-weight: bold;
            color: #333;
            margin-bottom: 8pt;
            text-transform: uppercase;
            letter-spacing: 1pt;
        }

        .signature-img-container {
            border: 1.5pt solid #eee;
            background-color: #fcfcfc;
            margin: 8pt 0;
            border-radius: 6pt;
            text-align: center;
            padding: 4pt;
            min-height: 20pt;
        }

        .signature-img {
            max-width: 100%;
            max-height: 100pt;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto;
            border-radius: 4pt;
        }

        .signature-name {
            font-weight: bold;
            font-size: 11pt;
            margin-top: 10pt;
            color: #B63352;
        }

        .photo-grid-table {
            width: 100%;
            border-collapse: collapse;
        }

        .photo-grid-td {
            width: 25%;
            padding: 3pt;
        }

        .photo-img {
            width: 100%;
            height: 105pt;
            object-fit: cover;
            border: 1.5pt solid #ddd;
            border-radius: 6pt;
        }

        .annotated-table {
            width: 100%;
            margin-bottom: 8pt;
            border: 1.5pt solid #eee;
            border-radius: 6pt;
            background-color: #f9f9f9;
        }

        .annotated-img-td {
            width: 100pt;
            padding: 6pt;
        }

        .annotated-content-td {
            padding: 8pt;
            vertical-align: top;
        }

        .note-label {
            font-weight: bold;
            font-size: 9pt;
            color: #B63352;
            margin-bottom: 4pt;
            text-transform: uppercase;
        }

        .clear {
            clear: both;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>

    <table class="header-table">
        <tr>
            <td>
                <div class="header-title">{{ $header['document_id'] }}</div>
            </td>
            <td align="right">
                <div class="status-badge">{{ fmtStatus($header['status']) }}</div>
            </td>
        </tr>
    </table>

    <table class="summary-table">
        <tr>
            <td class="summary-label">Departemen</td>
            <td class="summary-value">{{ $header['department_name'] }}</td>
            <td class="summary-label">Auditor</td>
            <td class="summary-value">{{ $header['auditor_name'] }}</td>
        </tr>
        <tr>
            <td class="summary-label">Tanggal Stok Opname</td>
            <td class="summary-value">{{ \Carbon\Carbon::parse($header['audit_date'])->translatedFormat('d F Y') }}</td>
            <td class="summary-label">Tanggal Selesai</td>
            <td class="summary-value">{{ $header['submitted_at'] ? \Carbon\Carbon::parse($header['submitted_at'])->translatedFormat('d F Y, H:i') : '-' }}</td>
        </tr>
    </table>

    <h2>Hasil Penilaian</h2>

    @foreach($categories as $index => $category)
    <table class="category-table">
        <tr class="category-row-header">
            <td colspan="2">
                {{ $category['name'] }}
            </td>
        </tr>
        @foreach($category['items'] as $qIndex => $item)
        <tr class="question-row">
            <td class="question-text-cell">
                <div style="font-weight: bold; margin-bottom: 4pt;">{{ $qIndex + 1 }}. {{ $item['name'] }}</div>
                @if(!empty($item['response']['remark']))
                <div class="remark-box">
                    <div class="remark-label">Catatan / Temuan</div>
                    {!! nl2br(e($item['response']['remark'])) !!}
                </div>
                @endif
            </td>
            <td class="score-cell">
                <div style="margin-bottom: 2pt;">
                    <span class="qty-label">Sys:</span> <span class="qty-val">{{ $item['response']['qty_stock'] ?? '-' }}</span>
                </div>
                <div style="margin-bottom: 4pt;">
                    <span class="qty-label">Real:</span> <span class="qty-val">{{ $item['response']['qty_real'] ?? '-' }}</span>
                </div>
            </td>
        </tr>
        @endforeach
    </table>
    @endforeach

    <!-- SIGNATURE AREA -->
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-label">Auditor</div>
                <div style="margin: 10pt 0 4pt 0; min-height: 40pt;"></div>
                <div class="signature-name">{{ $header['auditor_name'] }}</div>
            </td>
            <td>
                <div class="signature-label">Foto Verifikasi</div>
                <div class="signature-img-container">
                    @if($header['verification_photo'])
                    <img src="{{ getBase64Image($header['verification_photo']) }}" class="signature-img">
                    @else
                    <span style="color:#ccc; font-size: 9pt; display: block; padding: 20pt 0;">DOKUMEN BELUM DIVERIFIKASI</span>
                    @endif
                </div>
            </td>
            <td>
                <div class="signature-label">Auditee / PIC</div>
                <div style="margin: 10pt 0 4pt 0; min-height: 40pt;"></div>
                <div class="signature-name">{{ $header['auditee_name'] ?? '-' }}</div>
            </td>
        </tr>
    </table>

    @if($hasPhotos)
    <div class="page-break"></div>
    <h2 style="border-bottom: 2pt solid #333;">Dokumentasi Foto Temuan</h2>

    @foreach($categories as $category)
    @php
    $photoQuestions = array_filter($category['items'], fn($q) => !empty($q['photos']));
    @endphp

    @if(!empty($photoQuestions))
    <div style="background-color: #f1f1f1; padding: 6pt 8pt; margin-top: 8pt; margin-bottom: 6pt; border-left: 5pt solid #B63352; font-weight: bold; font-size: 10pt;">
        {{ $category['name'] }}
    </div>

    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
        @foreach(array_chunk($photoQuestions, 3) as $rowQuestions)
        <tr>
            @foreach($rowQuestions as $q)
            <td style="width: 33.33%; padding: 4pt; vertical-align: top;">
                <div style="margin-bottom: 8pt;">
                    <div style="font-size: 9pt; font-weight: bold; margin-bottom: 5pt; color: #444; word-wrap: break-word; line-height: 1.2;">
                        {{ $q['name'] }} <span style="font-weight: normal; color: #888;">({{ count($q['photos']) }} foto)</span>
                    </div>

                    @php
                    $gallery = array_filter($q['photos'], fn($p) => empty($p['remark']) && empty($p['action']));
                    $annotated = array_filter($q['photos'], fn($p) => !empty($p['remark']) || !empty($p['action']));
                    @endphp

                    @if(!empty($gallery))
                    @foreach($gallery as $p)
                    <div style="margin-bottom: 4pt;">
                        <img src="{{ getBase64Image($p['photo_path']) }}" style="width: 100%; height: 130pt; object-fit: cover; border: 1.5pt solid #ddd; border-radius: 6pt;">
                    </div>
                    @endforeach
                    @endif

                    @if(!empty($annotated))
                    @foreach($annotated as $p)
                    <div style="margin-bottom: 6pt; border: 1pt solid #eee; border-radius: 6pt; background-color: #f9f9f9; padding: 4pt;">
                        <img src="{{ getBase64Image($p['photo_path']) }}" style="width: 100%; height: 130pt; object-fit: cover; border-radius: 4pt; margin-bottom: 4pt;">
                        @if($p['remark'])
                        <div style="font-weight: bold; font-size: 7.5pt; color: #B63352; margin-bottom: 2pt; text-transform: uppercase;">Temuan / Observasi:</div>
                        <div style="font-size: 8pt; margin-bottom: 4pt; word-wrap: break-word; line-height: 1.2;">{{ $p['remark'] }}</div>
                        @endif
                        @if($p['action'])
                        <div style="font-weight: bold; font-size: 7.5pt; color: #B63352; margin-bottom: 2pt; text-transform: uppercase;">Rekomendasi Tindakan:</div>
                        <div style="font-size: 8pt; word-wrap: break-word; line-height: 1.2;">{{ $p['action'] }}</div>
                        @endif
                    </div>
                    @endforeach
                    @endif
                </div>
            </td>
            @endforeach
            @for($i = count($rowQuestions); $i < 3; $i++)
                <td style="width: 33.33%; padding: 4pt;">
                </td>
                @endfor
        </tr>
        @endforeach
    </table>
    @endif
    @endforeach
    @endif

</body>

</html>