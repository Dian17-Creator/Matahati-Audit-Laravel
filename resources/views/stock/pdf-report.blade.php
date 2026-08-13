@php
if (!function_exists('fmtStatus')) {
    function fmtStatus($status) {
        return match ($status) {
            "Draft" => "Draft",
            "Submitted" => "Selesai",
            default => "Dalam Proses",
        };
    }
}

if (!function_exists('getLocalImagePath')) {
    function getLocalImagePath($url) {
        if (empty($url)) return null;

        try {
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
                return str_replace('\\', '/', $path);
            }
        } catch (\Exception $e) {
            // Fallback
        }
        return $url;
    }
}

if (!function_exists('fmtQty')) {
    function fmtQty($value) {
        if ($value === null || $value === "") return "-";
        if (!is_numeric($value)) return (string)$value;
        return rtrim(rtrim(number_format((float)$value, 6, ".", ""), "0"), ".");
    }
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
        /* DOMPDF COMPATIBLE CSS - Match Native PHP Export */
        @page {
            size: A4;
            margin: 12mm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5pt;
            line-height: 1.25;
            color: #222;
            margin: 0;
            padding: 0;
        }

        .header-table {
            width: 100%;
            margin-bottom: 18px;
        }

        .header-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }

        .status-badge {
            display: inline-block;
            border: 1px solid #999;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 8.5pt;
            color: #222;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .summary td {
            border: 1px solid #ddd;
            font-size: 8.5pt;
            padding: 4px 6px;
        }

        .summary td.label {
            width: 120px;
            background: #f5f5f5;
            font-weight: bold;
        }

        hr {
            border: none;
            border-top: 1px solid #999;
            margin: 10px 0;
            width: 100%;
        }

        h2 {
            margin-top: 10px;
            margin-bottom: 10px;
            font-size: 14px;
            page-break-after: avoid;
        }

        .category {
            width: 100%;
            margin-bottom: 14px;
        }

        .category-title {
            font-size: 11px;
            font-weight: bold;
            padding: 6px;
            background: #f2f2f2;
            border: 1px solid #ccc;
            page-break-after: avoid;
        }

        /* DomPDF specific table layout for Stock Opname */
        .stock-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: auto;
        }

        .stock-table th,
        .stock-table td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            font-size: 7.5pt; /* INI FONT TABLE HASIL */
            vertical-align: top;
            line-height: 1.25;
        }

        .stock-table th {
            background: #f5f5f5;
            font-weight: bold;
            text-align: center;
        }

        .col-no { width: 25px; }
        .col-item { width: auto; }
        .col-num { width: 55px; }

        .stock-table td.center { text-align: center; }
        .stock-table td.item-name { text-align: left; }
        .stock-table td.num { text-align: right; white-space: nowrap; }
        .stock-table td.variance { font-weight: bold; }

        .stock-table tr.item-row { page-break-inside: avoid; }
        .stock-table tr.item-has-remark td { border-bottom: none; }
        .stock-table tr.item-has-remark, .stock-table tr.remark-row { page-break-inside: avoid; }
        
        .stock-table tr.remark-row td {
            border-top: none;
            padding-top: 0;
            padding-bottom: 4px;
        }

        .stock-table tr.remark-row td.remark-empty {
            padding-left: 0;
            padding-right: 0;
        }

        .stock-table td.remark-cell {
            padding-left: 3px;
            padding-right: 7px;
        }

        .stock-table .remark {
            margin: 1px 0 1px 5px;
            padding: 0px 4px;
            font-size: 7.5pt; /* INI FONT TABLE HASIL */
            line-height: 1.25;
            border-left: 3px solid #bbb;
        }

        /* SIGNATURE */
        .signature {
            width: 100%;
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

        .signature-person { width: 30%; }
        .signature-photo-cell { width: 40%; vertical-align: top; }

        .sig-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 9.5pt;
            color: #666;
            letter-spacing: .5px;
        }

        .sig-name {
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .verification-photo {
            display: block;
            max-width: 100%;
            max-height: 135px;
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
            line-height: 135px;
        }

        /* PHOTO EVIDENCE */
        .photo-evidence { page-break-before: always; }
        .photo-category { width: 100%; margin-bottom: 20px; }
        
        .photo-question { margin-top: 24px; }
        .photo-question.first { margin-top: 12px; }

        .photo-question-title {
            font-size: 10pt;
            font-weight: 700;
            margin-bottom: 14px;
            padding-bottom: 6px;
            border-bottom: 1px solid #cfcfcf;
        }

        .photo-count {
            font-size: 8.5pt;
            font-weight: 400;
            color: #666;
        }

        .photo-gallery-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 18px;
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

        .annotated-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
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
            font-size: 8.5pt;
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
    </style>
</head>

<body class="report">

    <table class="header-table">
        <tr>
            <td valign="top">
                <h1 class="header-title">{{ $header['document_id'] }}</h1>
            </td>
            <td valign="top" align="right">
                <div class="status-badge">{{ fmtStatus($header['status']) }}</div>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td class="label">Departemen/Divisi</td>
            <td colspan="2">{{ $header['department_name'] }}</td>
            <td class="label">Auditor</td>
            <td>{{ $header['auditor_name'] }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Audit</td>
            <td colspan="2">{{ \Carbon\Carbon::parse($header['audit_date'])->translatedFormat('d F Y') }}</td>
            <td class="label">Tanggal Selesai</td>
            <td>{{ $header['submitted_at'] ? \Carbon\Carbon::parse($header['submitted_at'])->translatedFormat('d F Y, H:i') : '-' }}</td>
        </tr>
    </table>

    <hr>

    <h2>Hasil Stok Opname</h2>

    @foreach($categories as $category)
    <div class="category">
        <div class="category-title">
            {{ $category['name'] }}
        </div>

        <table class="stock-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-item">Nama Barang</th>
                    <th class="col-num">Tercatat</th>
                    <th class="col-num">Aktual</th>
                    <th class="col-num">Kurang</th>
                    <th class="col-num">Lebih</th>
                </tr>
            </thead>
            <tbody>
                @foreach($category['items'] as $index => $item)
                @php
                    $remark = trim((string)($item['response']['remark'] ?? ''));
                    $stock = $item['response']['qty_stock'] ?? null;
                    $actual = $item['response']['qty_real'] ?? null;
                    $under = $item['response']['diff_under'] ?? 0;
                    $over = $item['response']['diff_over'] ?? 0;
                @endphp
                <tr class="item-row {{ $remark !== '' ? 'item-has-remark' : '' }}">
                    <td class="center">{{ $index + 1 }}</td>
                    <td class="item-name">{{ $item['name'] }}</td>
                    <td class="num">{{ fmtQty($stock) }}</td>
                    <td class="num">{{ fmtQty($actual) }}</td>
                    <td class="num {{ (float)$under > 0 ? 'variance' : '' }}">
                        {{ (float)$under > 0 ? fmtQty($under) : '-' }}
                    </td>
                    <td class="num {{ (float)$over > 0 ? 'variance' : '' }}">
                        {{ (float)$over > 0 ? fmtQty($over) : '-' }}
                    </td>
                </tr>

                @if($remark !== '')
                <tr class="remark-row">
                    <td class="remark-empty"></td>
                    <td class="remark-cell">
                        <div class="remark">
                            {!! nl2br(e($remark)) !!}
                        </div>
                    </td>
                    <td class="remark-empty"></td>
                    <td class="remark-empty"></td>
                    <td class="remark-empty"></td>
                    <td class="remark-empty"></td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach

    <hr>

    <!-- SIGNATURE -->
    <table class="signature">
        <tr>
            <td class="signature-person">
                <div class="sig-title">Auditor</div>
                <div style="height: 60px;"></div>
                <div class="sig-name">{{ $header['auditor_name'] ?? '-' }}</div>
            </td>
            <td class="signature-photo-cell">
                <div class="sig-title">Foto Verifikasi</div>
                @if(!empty($header['verification_photo']))
                <img src="{{ getLocalImagePath($header['verification_photo']) }}" class="verification-photo" alt="Foto verifikasi stok opname">
                @else
                <div class="verification-photo-empty">Tidak ada foto</div>
                @endif
            </td>
            <td class="signature-person">
                <div class="sig-title">Auditee / PIC</div>
                <div style="height: 60px;"></div>
                <div class="sig-name">{{ $header['auditee_name'] ?? '-' }}</div>
            </td>
        </tr>
    </table>

    @if($hasPhotos)
    <div class="photo-evidence">
        <h2>Dokumentasi Foto</h2>

        @foreach($categories as $category)
        @php
        $photoQuestions = array_filter($category['items'], fn($q) => !empty($q['photos']));
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
                    {{ $q['name'] }}
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
                            <img src="{{ getLocalImagePath($photo['photo_path']) }}" class="gallery-photo">
                        </td>
                        @endforeach

                        {{-- Fill remaining cells --}}
                        @for($i = count($row); $i < 4; $i++)
                            <td class="photo-gallery-td">
                            </td>
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
                                        <img src="{{ getLocalImagePath($photo['photo_path']) }}" class="annotated-photo">
                                    </td>
                                    <td class="annotated-notes-td">
                                        @if(trim($photo['remark'] ?? '') !== '')
                                        <p style="margin: 0 0 8px;">
                                            <strong>Keterangan :</strong><br>
                                            {!! nl2br(e($photo['remark'])) !!}
                                        </p>
                                        @endif
                                        @if(trim($photo['action'] ?? '') !== '')
                                        <p style="margin: 0 0 8px;">
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