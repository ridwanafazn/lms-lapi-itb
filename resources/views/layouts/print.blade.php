<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cetak Dokumen</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; color: black; }
            @page { size: A4; margin: 2cm; }
        }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto mb-6 no-print flex justify-end">
        <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded shadow">
            Cetak Sekarang
        </button>
    </div>

    <div class="max-w-4xl mx-auto bg-white p-10 shadow-sm border border-gray-200">
        {{ $slot }}
    </div>
</body>
</html>
