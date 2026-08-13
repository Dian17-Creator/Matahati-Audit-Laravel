<!DOCTYPE html>
<html>

<body style="font-family: Arial, sans-serif; font-size: 14px; color: #333;">

    @if(!empty($messageText))
        <p>{!! nl2br(e($messageText)) !!}</p>
    @endif

    <p>Berikut terlampir {{ $reportName }}</p>

    <p>Terima kasih.</p>

    <br>

    <hr style="border: none; border-top: 1px solid #ddd;">

    <p style="font-style: italic; font-size: 12px; color: #777;">
        Email dikirim otomatis. Mohon tidak membalas email ini.
    </p>
</body>

</html>
