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

// Sort categories alphabetically
usort($categories, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

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
        /* DOMPDF COMPATIBLE CSS - Match Native PHP Export */
        @page {
            size: A4;
            margin: 12mm 12mm 15mm 12mm;
        }

        #footer {
            position: fixed;
            bottom: -5mm;
            left: 0;
            right: 0;
            width: 100%;
            font-size: 8pt;
            color: #444;
        }

        .page-number:before {
            content: counter(page) "/" counter(pages);
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #222;
            margin: 0;
            padding: 0;
        }

        .header-table {
            width: 99%;
            margin-bottom: 18px;
        }

        .header-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .status-badge {
            display: inline-block;
            border: 1px solid #999;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 10pt;
            color: #222;
        }

        .summary {
            width: 99%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .summary td {
            border: 1px solid #ddd;
            font-size: 9.5pt;
            padding: 4px;
        }

        .summary td.label {
            width: 120px;
            background: #f5f5f5;
            font-weight: bold;
        }

        .large-text {
            font-size: 20px;
            font-weight: 800;
        }

        hr {
            border: none;
            border-top: 1px solid #999;
            margin: 10px 0;
            width: 99%;
        }

        h2 {
            margin-top: 10px;
            margin-bottom: 10px;
            font-size: 18px;
            page-break-after: avoid;
        }

        .category-container {
            width: 99%;
            margin-bottom: 14px;
        }

        .category-title {
            font-size: 13px;
            font-weight: bold;
            padding: 6px 6px;
            background: #f2f2f2;
            border: 1px solid #ccc;
            page-break-after: avoid;
        }

        .category-score {
            font-weight: normal;
            float: right;
        }

        .question {
            border: 1px solid #ddd;
            border-top: none;
            padding: 3px 6px;
            page-break-inside: avoid;
        }

        .question-table {
            width: 100%;
            border-collapse: collapse;
        }

        .question-text-td {
            vertical-align: top;
        }

        .score-td {
            vertical-align: top;
            text-align: right;
            width: 40px;
            font-size: 11pt;
            font-weight: 700;
        }

        .remark {
            margin-top: 2px;
            margin-left: 15px;
            padding: 3px 4px;
            font-size: 9.5pt;
            border-left: 3px solid #bbb;
        }

        .score-red    { color: #dc2626; }
        .score-orange { color: #f97316; }
        .score-yellow { color: #ca8a04; }
        .score-blue   { color: #2563eb; }
        .score-green  { color: #16a34a; }
        .score-na     { color: #4b5563; }
        .score-gray   { color: #9ca3af; }

        .signature {
            width: 99%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 28px;
            page-break-inside: avoid;
        }

        .signature td {
            padding: 0 14px;
            text-align: center;
            vertical-align: top;
        }

        .signature-person {
            width: 30%;
        }

        .signature-photo-cell {
            width: 40%;
        }

        .sig-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 11px;
            color: #666;
            letter-spacing: .5px;
        }

        .sig-name {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .verification-photo {
            display: block;
            max-width: 100%;
            max-height: 150px;
            height: auto;
            object-fit: contain;
            border: 1px solid #bbb;
            margin: 0 auto;
        }

        .verification-photo-empty {
            height: 135px;
            border: 1px dashed #bbb;
            color: #777;
            font-size: 9pt;
            text-align: center;
            line-height: 135px; /* Vertical center trick for DomPDF */
        }

        .photo-evidence {
            page-break-before: always;
        }

        .photo-category {
            width: 99%;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .photo-question {
            margin-top: 24px;
        }
        
        .photo-question.first {
            margin-top: 12px;
        }

        .photo-question-title {
            font-size: 12pt;
            font-weight: 700;
            margin-bottom: 14px;
            padding-bottom: 6px;
            border-bottom: 1px solid #cfcfcf;
        }

        .photo-count {
            font-size: 10pt;
            font-weight: 400;
            color: #666;
        }

        /* DomPDF Gallery Table */
        .photo-gallery-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 18px;
            page-break-inside: avoid;
        }

        .photo-gallery-td {
            width: 25%;
            padding: 0 6px 12px 6px;
            vertical-align: top;
            text-align: center;
        }

        .gallery-photo {
            max-width: 99%;
            height: auto;
            max-height: 250px;
            object-fit: contain;
            border: 1px solid #bbb;
            display: block;
            margin: 0 auto;
        }

        /* Annotated Table */
        .annotated-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            page-break-inside: avoid;
        }

        .annotated-td {
            width: 50%;
            padding: 0 9px;
            vertical-align: top;
        }
        
        .annotated-inner-table {
            width: 100%;
            border-collapse: collapse;
        }

        .annotated-photo-td {
            width: 45%;
            vertical-align: top;
            padding-right: 12px;
        }
        
        .annotated-notes-td {
            width: 55%;
            vertical-align: top;
            font-size: 10pt;
            line-height: 1.35;
        }

        .annotated-photo {
            max-width: 100%;
            height: auto;
            max-height: 250px;
            object-fit: contain;
            border: 1px solid #ddd;
            display: block;
        }

        .annotated-notes p {
            margin: 0 0 8px;
        }
    </style>
</head>

<body class="report">

    <div id="footer">
        <table style="width: 100%; border-collapse: collapse; border: none;">
            <tr>
                <td style="text-align: left; border: none; padding: 0;">
                    {{ request()->url() }}
                </td>
                <td style="text-align: right; border: none; padding: 0;">
                    <span class="page-number"></span>
                </td>
            </tr>
        </table>
    </div>

    <table class="header-table">
        <tr>
            <td valign="top">
                <h1 class="header-title">{{ $audit['document_id'] }}</h1>
            </td>
            <td valign="top" align="right">
                <div class="status-badge">{{ fmtStatus($audit['status']) }}</div>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td class="label">Departemen/Divisi</td>
            <td colspan="2">{{ $audit['department_name'] }}</td>
            <td class="label">Auditor</td>
            <td>{{ $audit['auditor_name'] }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Audit</td>
            <td colspan="2">{{ \Carbon\Carbon::parse($audit['audit_date'])->translatedFormat('d F Y') }}</td>
            <td class="label">Tanggal Selesai</td>
            <td>{{ $audit['submitted_at'] ? \Carbon\Carbon::parse($audit['submitted_at'])->translatedFormat('d F Y, H:i') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Nilai Total</td>
            <td class="large-text" colspan="2">
                {{ number_format($audit['total_score'], 2) }} / {{ number_format($audit['max_score'], 2) }}
            </td>
            <td class="label">Persentase</td>
            <td class="large-text">
                {{ number_format($audit['percentage'], 2) }}%
            </td>
        </tr>
    </table>

    <hr>

    <h2>Hasil Audit</h2>

    @foreach($categories as $category)
    <div class="category-container">
        <div class="category-title">
            {{ $category['name'] }}
            <span class="category-score">
                {{ number_format($category['total_score'] ?? 0, 1) }} / {{ number_format($category['max_score'] ?? 0, 1) }}
                ({{ round($category['percentage'] ?? 0) }}%)
            </span>
        </div>

        @foreach($category['questions'] as $index => $question)
        <div class="question">
            <table class="question-table">
                <tr>
                    <td class="question-text-td">
                        {{ $index + 1 }}. {{ $question['question'] }}
                    </td>
                    <td class="score-td">
                        @php
                        $score = $question['response']['score'];
                        $isNa = $question['response']['is_na'];
                        $scoreText = '-';
                        $scoreClass = 'score-gray';

                        if ($isNa) {
                            $scoreText = 'N/A';
                            $scoreClass = 'score-na';
                        } elseif ($score !== null) {
                            $val = (float)$score;
                            $scoreText = rtrim(rtrim(number_format($val, 1), '0'), '.');
                            if ($val == 0) $scoreClass = 'score-red';
                            elseif ($val == 0.5) $scoreClass = 'score-orange';
                            elseif ($val == 1.0) $scoreClass = 'score-yellow';
                            elseif ($val == 1.5) $scoreClass = 'score-blue';
                            elseif ($val == 2.0) $scoreClass = 'score-green';
                        }
                        @endphp
                        <span class="{{ $scoreClass }}">{{ $scoreText }}</span>
                    </td>
                </tr>
            </table>

            @if(!empty($question['response']['remark']))
            <div class="remark">
                {!! nl2br(e($question['response']['remark'])) !!}
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endforeach

    <hr>
    
    <!-- SIGNATURE -->
    <table class="signature">
        <tr>
            <td class="signature-person">
                <div class="sig-title">Auditor</div>
                <div style="height: 60px;"></div>
                <div class="sig-name">{{ $audit['auditor_name'] ?? '-' }}</div>
            </td>
            <td class="signature-photo-cell">
                <div class="sig-title">Foto Verifikasi</div>
                @if(!empty($audit['verification_photo']))
                <img src="{{ getBase64Image($audit['verification_photo']) }}" class="verification-photo" alt="Foto verifikasi audit">
                @else
                <div class="verification-photo-empty">Tidak ada foto</div>
                @endif
            </td>
            <td class="signature-person">
                <div class="sig-title">Auditee / PIC</div>
                <div style="height: 60px;"></div>
                <div class="sig-name">{{ $audit['auditee_name'] ?? '-' }}</div>
            </td>
        </tr>
    </table>

    @if($hasPhotos)
    <div class="photo-evidence">
        <h2>Dokumentasi Foto</h2>

        @foreach($categories as $category)
        @php
        $photoQuestions = array_filter($category['questions'], fn($q) => !empty($q['photos']));
        @endphp

        @if(empty($photoQuestions))
            @continue
        @endif

        <div class="photo-category">
            <div class="category-title">
                {{ $category['name'] }}
            </div>

            @foreach($photoQuestions as $index => $q)
            <div class="photo-question {{ $loop->first ? 'first' : '' }}">
                <div class="photo-question-title">
                    {{ $q['question'] }}
                    <span class="photo-count">
                        ({{ count($q['photos']) }} foto)
                    </span>
                </div>

                @php
                $galleryPhotos = [];
                $annotatedPhotos = [];

                foreach ($q['photos'] as $photo) {
                    if (trim($photo['remark'] ?? '') !== '' || trim($photo['action'] ?? '') !== '') {
                        $annotatedPhotos[] = $photo;
                    } else {
                        $galleryPhotos[] = $photo;
                    }
                }
                @endphp

                @if(!empty($galleryPhotos))
                <table class="photo-gallery-table">
                    @foreach(array_chunk($galleryPhotos, 4) as $row)
                    <tr>
                        @foreach($row as $photo)
                        <td class="photo-gallery-td">
                            <img src="{{ getBase64Image($photo['photo_path']) }}" class="gallery-photo">
                        </td>
                        @endforeach
                        
                        {{-- Fill remaining cells --}}
                        @for($i = count($row); $i < 4; $i++)
                        <td class="photo-gallery-td"></td>
                        @endfor
                    </tr>
                    @endforeach
                </table>
                @endif

                @if(!empty($annotatedPhotos))
                    @foreach(array_chunk($annotatedPhotos, 2) as $row)
                    <table class="annotated-table">
                        <tr>
                            @foreach($row as $photo)
                            <td class="annotated-td">
                                <table class="annotated-inner-table">
                                    <tr>
                                        <td class="annotated-photo-td">
                                            <img src="{{ getBase64Image($photo['photo_path']) }}" class="annotated-photo">
                                        </td>
                                        <td class="annotated-notes-td">
                                            @if(trim($photo['remark'] ?? '') !== '')
                                            <p>
                                                <strong>Hasil Pengamatan :</strong><br>
                                                {!! nl2br(e($photo['remark'])) !!}
                                            </p>
                                            @endif

                                            @if(trim($photo['action'] ?? '') !== '')
                                            <p>
                                                <strong>Rekomendasi :</strong><br>
                                                {!! nl2br(e($photo['action'])) !!}
                                            </p>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            @endforeach
                            
                            {{-- Fill remaining cell if odd --}}
                            @if(count($row) == 1)
                            <td class="annotated-td"></td>
                            @endif
                        </tr>
                    </table>
                    @endforeach
                @endif
            </div>
            @endforeach
        </div>
        @endforeach
    </div>
    @endif

</body>
</html>