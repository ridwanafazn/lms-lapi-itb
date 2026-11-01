<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pembayaran Berhasil</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f7;font-family:Helvetica,Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f7;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;padding:32px;">
                    <tr>
                        <td>
                            <h1 style="margin:0 0 8px;font-size:20px;color:#D93F21;">Pembayaran Berhasil</h1>
                            <p style="margin:0 0 24px;font-size:14px;color:#6b7280;">
                                Halo {{ $transaction->user->name ?? 'Peserta' }}, terima kasih atas pembayaran Anda.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-top:1px solid #e5e7eb;">
                                <tr>
                                    <td style="padding:10px 0;color:#6b7280;">Invoice</td>
                                    <td style="padding:10px 0;text-align:right;font-weight:600;">{{ $transaction->invoice_code }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;color:#6b7280;">Item</td>
                                    <td style="padding:10px 0;text-align:right;">{{ $transaction->purchased_item_name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;color:#6b7280;">Metode</td>
                                    <td style="padding:10px 0;text-align:right;">{{ strtoupper($transaction->payment_method ?? $transaction->gateway_name ?? '-') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;color:#6b7280;">Waktu</td>
                                    <td style="padding:10px 0;text-align:right;">
                                        {{ optional($transaction->paid_at)->translatedFormat('d F Y H:i') ?? '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 0;border-top:1px solid #e5e7eb;font-weight:600;">Total</td>
                                    <td style="padding:14px 0;border-top:1px solid #e5e7eb;text-align:right;font-weight:700;color:#D93F21;">
                                        Rp{{ number_format((float) $transaction->amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:28px 0 0;font-size:13px;color:#6b7280;">
                                Akses belajar Anda sudah aktif. Buka dashboard untuk mulai belajar.
                            </p>
                            <p style="margin:8px 0 0;font-size:12px;color:#9ca3af;">
                                Email ini dikirim otomatis, mohon tidak membalas.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
