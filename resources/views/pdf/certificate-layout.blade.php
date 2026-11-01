<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat - {{ $certificate->user->name }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            background-color: #ffffff;
            width: 100%;
            height: 100%;
            position: relative;
        }

        /* ORNAMEN GEOMETRIS - Ramping & Profesional */
        .corner-top-left {
            position: absolute;
            top: 0; left: 0;
            border-top: 130px solid #002B5B;
            border-right: 130px solid transparent;
            z-index: 1;
        }
        .corner-top-left-red {
            position: absolute;
            top: 0; left: 0;
            border-top: 170px solid #D10000;
            border-right: 170px solid transparent;
            z-index: 0;
        }
        .corner-bottom-right {
            position: absolute;
            bottom: 0; right: 0;
            border-bottom: 100px solid #000000;
            border-left: 600px solid transparent;
            z-index: 2;
        }
        .corner-bottom-right-blue {
            position: absolute;
            bottom: 0; right: 0;
            border-bottom: 130px solid #002B5B;
            border-left: 750px solid transparent;
            z-index: 1;
        }
        .corner-bottom-right-red {
            position: absolute;
            bottom: 0; right: 0;
            border-bottom: 160px solid #D10000;
            border-left: 900px solid transparent;
            z-index: 0;
        }

        /* KONTEN UTAMA */
        .content {
            position: absolute;
            top: 40px; left: 60px; right: 60px; bottom: 40px;
            text-align: center;
            z-index: 10;
        }

        .logo {
            text-align: right;
            margin-bottom: 5px;
        }
        .logo img {
            width: 160px;
        }

        /* Header & Nomor */
        .title {
            font-size: 48px;
            font-weight: bold;
            letter-spacing: 8px;
            margin-top: 0;
            margin-bottom: 2px;
            font-family: 'Times New Roman', Times, serif;
            color: #0f172a;
        }
        .number {
            font-size: 12px;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 25px;
        }

        /* Nama Penerima */
        .given-to {
            font-size: 13px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 4px;
        }
        .name {
            font-family: 'Times New Roman', Times, serif;
            font-size: 34px;
            font-weight: bold;
            color: #002B5B;
            margin-bottom: 18px;
            display: inline-block;
            padding-bottom: 4px;
            border-bottom: 1.5px solid #cbd5e1;
            width: 70%;
        }

        /* Informasi Kursus */
        .completion {
            font-size: 14px;
            color: #475569;
            margin-bottom: 6px;
        }
        .course {
            font-size: 20px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 20px;
            line-height: 1.3;
            max-width: 80%;
            margin-left: auto;
            margin-right: auto;
        }

        .agency-date {
            font-size: 13px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        /* CONTAINER BAWAH: QR CODE & SIGNATURES */
        .footer-container {
            width: 85%;
            margin: 0 auto;
            margin-top: 10px;
            position: relative;
            height: 150px;
        }

        /* QR Code Stamp (Kiri) */
        .qr-stamp {
            position: absolute;
            left: 0;
            bottom: 20px;
            text-align: center;
            width: 100px;
        }
        .qr-stamp img {
            width: 80px;
            height: 80px;
            padding: 5px;
            border: 1px solid #e2e8f0;
            background: #fff;
        }
        .qr-label {
            font-size: 8px;
            color: #94a3b8;
            margin-top: 5px;
            font-weight: bold;
            text-transform: uppercase;
        }

        /* Tanda Tangan (Tengah ke Kanan) */
        table.signatures {
            width: 80%;
            float: right;
            border-collapse: collapse;
        }
        table.signatures td {
            text-align: center;
            width: 50%;
            vertical-align: bottom;
            padding: 0 10px;
        }
        .sign-img {
            height: 75px;
            margin-bottom: 2px;
            object-fit: contain;
        }
        .sign-name {
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
            font-size: 15px;
            color: #0f172a;
            border-bottom: 1px solid #94a3b8;
            display: inline-block;
            padding-bottom: 2px;
            margin-bottom: 4px;
            width: 80%;
        }
        .sign-title {
            font-size: 11px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="corner-top-left-red"></div>
    <div class="corner-top-left"></div>
    <div class="corner-bottom-right-red"></div>
    <div class="corner-bottom-right-blue"></div>
    <div class="corner-bottom-right"></div>

    <div class="content">
        <div class="logo">
            <img src="{{ public_path('img/Logo_PT LAPI_PNG.png') }}" alt="Logo LAPI ITB">
        </div>

        <div class="title">SERTIFIKAT</div>
        <div class="number">Nomor : {{ $certificate->formal_number ?? 'Sedang diproses' }}</div>

        <div class="given-to">DIBERIKAN KEPADA</div>
        <div class="name">{{ strtoupper($certificate->user->name) }}</div>

        <div class="completion">Telah Menyelesaikan</div>
        <div class="course">{{ strtoupper($certificate->course->title) }}</div>

        <div class="agency-date">
            {{ $certificate->user->profile->agency_name ?? 'Peserta Pelatihan' }} <br>
            Bandung, {{ \Carbon\Carbon::parse($certificate->issued_at)->translatedFormat('d F Y') }}
        </div>

        <div class="footer-container">
            <div class="qr-stamp">
                <img src="data:image/svg+xml;base64,{{ $qrCodeBase64 }}" alt="QR Verification">
                <div class="qr-label">Pindai Verifikasi</div>
            </div>

            <table class="signatures">
                <tr>
                    <td>
                        <img src="{{ public_path('img/ttd-example-dirut.png') }}" class="sign-img"><br>
                        <span class="sign-name">Xusmar Anggadinata</span><br>
                        <span class="sign-title">Direktur Utama</span>
                    </td>
                    <td>
                        <img src="{{ public_path('img/ttd-example-instruktur.png') }}" class="sign-img"><br>
                        <span class="sign-name">Instruktur Pelatihan</span><br>
                        <span class="sign-title">Instruktur</span>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
