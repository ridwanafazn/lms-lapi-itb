<?php

namespace App\Livewire\Student;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Certificate as CertificateModel;
use Illuminate\Support\Facades\Auth;

class Certificate extends Component
{
    public function downloadPdf($id)
    {
        // TODO: Nanti ini akan di-redirect ke Route Controller PDF.
        // Untuk sementara, kita berikan notifikasi bahwa fitur sedang disiapkan.
        $this->dispatch('notify', [
            'message' => 'Sistem pembuat PDF sedang disiapkan. Mohon tunggu update selanjutnya!',
            'type' => 'warning'
        ]);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        // Mengambil data sertifikat milik siswa yang sedang login
        // Eager load relasi 'batch' dan 'batch.course' agar query efisien (mencegah N+1)
        $certificates = CertificateModel::with(['course', 'batch.course'])
            ->where('user_id', Auth::id())
            ->orderBy('issued_at', 'desc')
            ->get();

        return view('livewire.student.certificate', [
            'certificates' => $certificates
        ]);
    }
}
