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
foreach ($category['questions'] as $question) {
if (!empty($question['photos'])) {
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
    <title>{{ $audit['document_id'] }}</title>
    <style>
        /* 
         * DOMPDF COMPATIBLE CSS
         */
        @page {
            size: A4;
            margin: 1.2cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .header-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .header-title {
            font-size: 22pt;
            font-weight: bold;
            color: #333;
        }

        .status-badge {
            display: inline-block;
            border: 2pt solid #B63352;
            padding: 6pt 15pt;
            border-radius: 6pt;
            font-weight: bold;
            color: #B63352;
            text-transform: uppercase;
            font-size: 10pt;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
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
            padding: 9pt 12pt;
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

        .score-large {
            font-size: 14pt;
            color: #B63352;
        }

        hr {
            border: 0;
            border-top: 1.5pt solid #B63352;
            margin: 25pt 0;
        }

        h2 {
            font-size: 16pt;
            color: #333;
            margin-bottom: 15pt;
            padding-bottom: 6pt;
        }

        /* MODERN ASSESSMENT TABLE */
        .category-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20pt;
            page-break-inside: auto;
            border: 1pt solid #ddd;
        }

        .category-row-header {
            background-color: #B63352;
            color: white;
        }

        .category-row-header td {
            padding: 10pt 12pt;
            font-size: 12pt;
            font-weight: bold;
        }

        .question-row {
            border-bottom: 1pt solid #ddd;
        }

        .question-row td {
            padding: 12pt;
            vertical-align: top;
            border-left: 1pt solid #ddd;
        }

        .question-text-cell {
            width: 85%;
        }

        .score-cell {
            width: 15%;
            text-align: center;
        }

        .score-pill {
            display: block;
            padding: 4pt 0;
            border-radius: 5pt;
            color: white;
            font-weight: bold;
            font-size: 10pt;
            width: 40pt;
            margin: 0 auto;
        }

        .remark-box {
            margin-top: 8pt;
            padding: 8pt 12pt;
            background-color: #fdfdfd;
            border: 1pt solid #eee;
            border-left: 4pt solid #B63352;
            font-size: 9.5pt;
            color: #555;
            border-radius: 4pt;
        }

        .remark-label {
            font-size: 8pt;
            font-weight: bold;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 3pt;
        }

        /* SCORE COLORS */
        .bg-red {
            background-color: #dc2626;
        }

        .bg-orange {
            background-color: #f97316;
        }

        .bg-yellow {
            background-color: #ca8a04;
        }

        .bg-blue {
            background-color: #2563eb;
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
            margin-top: 50pt;
            border-collapse: collapse;
        }

        .signature-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 15pt;
        }

        .signature-label {
            font-size: 10pt;
            font-weight: bold;
            color: #333;
            margin-bottom: 12pt;
            text-transform: uppercase;
            letter-spacing: 1pt;
        }

        .signature-img-container {
            border: 1.5pt solid #eee;
            background-color: #fcfcfc;
            margin: 12pt 0;
            border-radius: 8pt;
            text-align: center;
            padding: 8pt;
            min-height: 40pt;
        }

        .signature-img {
            max-width: 100%;
            max-height: 180pt;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto;
            border-radius: 4pt;
        }

        .signature-name {
            font-weight: bold;
            font-size: 13pt;
            margin-top: 15pt;
            color: #B63352;
        }

        .photo-grid-table {
            width: 100%;
            border-collapse: collapse;
        }

        .photo-grid-td {
            width: 25%;
            padding: 6pt;
        }

        .photo-img {
            width: 100%;
            height: 140pt;
            object-fit: cover;
            border: 1.5pt solid #ddd;
            border-radius: 6pt;
        }

        .annotated-table {
            width: 100%;
            margin-bottom: 20pt;
            border: 1.5pt solid #eee;
            border-radius: 8pt;
            background-color: #f9f9f9;
        }

        .annotated-img-td {
            width: 150pt;
            padding: 10pt;
        }

        .annotated-content-td {
            padding: 15pt;
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
                <div class="header-title">{{ $audit['document_id'] }}</div>
            </td>
            <td align="right">
                <div class="status-badge">{{ fmtStatus($audit['status']) }}</div>
            </td>
        </tr>
    </table>

    <table class="summary-table">
        <tr>
            <td class="summary-label">Departemen</td>
            <td class="summary-value">{{ $audit['department_name'] }}</td>
            <td class="summary-label">Auditor</td>
            <td class="summary-value">{{ $audit['auditor_name'] }}</td>
        </tr>
        <tr>
            <td class="summary-label">Tanggal Audit</td>
            <td class="summary-value">{{ \Carbon\Carbon::parse($audit['audit_date'])->translatedFormat('d F Y') }}</td>
            <td class="summary-label">Tanggal Selesai</td>
            <td class="summary-value">{{ $audit['submitted_at'] ? \Carbon\Carbon::parse($audit['submitted_at'])->translatedFormat('d F Y, H:i') : '-' }}</td>
        </tr>
        <tr>
            <td class="summary-label">Nilai Total</td>
            <td class="summary-value score-large">{{ number_format($audit['total_score'], 1) }} / {{ number_format($audit['max_score'], 1) }}</td>
            <td class="summary-label">Persentase</td>
            <td class="summary-value score-large">{{ number_format($audit['percentage'], 2) }}%</td>
        </tr>
    </table>

    <h2>Hasil Penilaian</h2>

    @foreach($categories as $index => $category)
    @if($index > 0)
    <div style="page-break-before: always;"></div>
    @endif
    <table class="category-table">
        <tr class="category-row-header">
            <td colspan="2">
                <span style="float: right; font-weight: normal; font-size: 10pt;">
                    Achieved: {{ round($category['percentage']) }}%
                </span>
                {{ $category['name'] }}
            </td>
        </tr>
        @foreach($category['questions'] as $qIndex => $question)
        <tr class="question-row">
            <td class="question-text-cell">
                <div style="font-weight: bold; margin-bottom: 4pt;">{{ $qIndex + 1 }}. {{ $question['question'] }}</div>
                @if(!empty($question['response']['remark']))
                <div class="remark-box">
                    <div class="remark-label">Catatan / Temuan</div>
                    {!! nl2br(e($question['response']['remark'])) !!}
                </div>
                @endif
            </td>
            <td class="score-cell">
                @php
                $score = $question['response']['score'];
                $isNa = $question['response']['is_na'];
                $scoreText = $isNa ? 'N/A' : ($score !== null ? rtrim(rtrim(number_format($score, 1), '0'), '.') : '-');
                $scoreBg = 'bg-gray';
                if (!$isNa && $score !== null) {
                $val = (float)$score;
                if ($val == 0) $scoreBg = 'bg-red';
                elseif ($val == 0.5) $scoreBg = 'bg-orange';
                elseif ($val == 1.0) $scoreBg = 'bg-yellow';
                elseif ($val == 1.5) $scoreBg = 'bg-blue';
                elseif ($val == 2.0) $scoreBg = 'bg-green';
                }
                @endphp
                <div class="score-pill {{ $scoreBg }}">{{ $scoreText }}</div>
            </td>
        </tr>
        @endforeach
    </table>
    @endforeach

    <!-- SIGNATURE AREA -->
    <div style="page-break-before: always;"></div>
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-label">Auditor</div>
                <div style="margin: 20pt 0 8pt 0; min-height: 60pt;"></div>
                <div class="signature-name">{{ $audit['auditor_name'] }}</div>
            </td>
            <td>
                <div class="signature-label">Foto Verifikasi</div>
                <div class="signature-img-container">
                    @if($audit['verification_photo'])
                    <img src="{{ getBase64Image($audit['verification_photo']) }}" class="signature-img">
                    @else
                    <span style="color:#ccc; font-size: 9pt; display: block; padding: 20pt 0;">DOKUMEN BELUM DIVERIFIKASI</span>
                    @endif
                </div>
            </td>
            <td>
                <div class="signature-label">Auditee / PIC</div>
                <div style="margin: 20pt 0 8pt 0; min-height: 60pt;"></div>
                <div class="signature-name">{{ $audit['auditee_name'] ?? '-' }}</div>
            </td>
        </tr>
    </table>

    @if($hasPhotos)
    <div class="page-break"></div>
    <h2 style="border-bottom: 2pt solid #333;">Dokumentasi Foto Temuan</h2>

    @foreach($categories as $category)
    @php
    $photoQuestions = array_filter($category['questions'], fn($q) => !empty($q['photos']));
    @endphp

    @if(!empty($photoQuestions))
    <div style="background-color: #f1f1f1; padding: 10pt; margin-top: 20pt; border-left: 5pt solid #B63352; font-weight: bold;">
        {{ $category['name'] }}
    </div>

    @foreach($photoQuestions as $q)
    <div style="margin-top: 15pt; margin-bottom: 25pt;">
        <div style="font-size: 11pt; font-weight: bold; margin-bottom: 10pt; color: #444;">
            {{ $q['question'] }} <span style="font-weight: normal; color: #888;">({{ count($q['photos']) }} foto)</span>
        </div>

        @php
        $gallery = array_filter($q['photos'], fn($p) => empty($p['remark']) && empty($p['action']));
        $annotated = array_filter($q['photos'], fn($p) => !empty($p['remark']) || !empty($p['action']));
        @endphp

        @if(!empty($gallery))
        <table class="photo-grid-table">
            @foreach(array_chunk($gallery, 4) as $row)
            <tr>
                @foreach($row as $p)
                <td class="photo-grid-td">
                    <img src="{{ getBase64Image($p['photo_path']) }}" class="photo-img">
                </td>
                @endforeach
                @for($i = count($row); $i < 4; $i++)
                    <td class="photo-grid-td">
                    </td>
                    @endfor
            </tr>
            @endforeach
        </table>
        @endif

        @if(!empty($annotated))
        @foreach($annotated as $p)
        <table class="annotated-table">
            <tr>
                <td class="annotated-img-td">
                    <img src="{{ getBase64Image($p['photo_path']) }}" style="width: 130pt; height: 130pt; object-fit: cover; border-radius: 4pt;">
                </td>
                <td class="annotated-content-td">
                    @if($p['remark'])
                    <div class="note-label">Temuan / Observasi:</div>
                    <div style="margin-bottom: 12pt; font-size: 10pt;">{{ $p['remark'] }}</div>
                    @endif
                    @if($p['action'])
                    <div class="note-label">Rekomendasi Tindakan:</div>
                    <div style="font-size: 10pt;">{{ $p['action'] }}</div>
                    @endif
                </td>
            </tr>
        </table>
        @endforeach
        @endif
    </div>
    @endforeach
    @endif
    @endforeach
    @endif

</body>

</html>