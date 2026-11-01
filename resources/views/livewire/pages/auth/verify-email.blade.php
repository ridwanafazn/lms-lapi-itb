<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.blank')] class extends Component
{
    /**
     * Mengirimkan ulang email verifikasi.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
            return;
        }

        Auth::user()->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Keluar dari aplikasi.
     */
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="fixed inset-0 flex items-center justify-center bg-gray-200 p-4 font-sans overflow-y-auto">
    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden w-full max-w-6xl flex flex-col md:flex-row h-[90vh] md:h-[85vh]">

        <div class="hidden md:block md:w-5/12 bg-[#9fa8da] relative">
             <div class="absolute inset-0 bg-gradient-to-br from-indigo-300 to-purple-400 opacity-50"></div>
        </div>

        <div class="w-full md:w-7/12 p-8 md:p-12 flex flex-col justify-center overflow-y-auto relative">

            <a href="/" class="absolute top-4 right-4 text-gray-300 hover:text-gray-500 transition duration-150 ease-in-out bg-indigo-50 rounded-full p-1 hover:bg-indigo-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </a>

            <div class="flex flex-col items-center mb-6">
                <img src="{{ asset('img/Logo_PT LAPI_PNG.png') }}" alt="PT LAPI ITB" class="h-9 mb-6">
                <h2 class="text-3xl font-extrabold text-gray-800 text-center">Verifikasi Email</h2>
                <p class="text-sm text-gray-500 mt-2 text-center max-w-sm">
                    Terima kasih telah mendaftar! Harap konfirmasi email Anda untuk mengakses fitur penuh LMS.
                </p>
            </div>

            <div class="max-w-md mx-auto w-full space-y-6">

                @if (session('status') == 'verification-link-sent')
                    <div class="font-medium text-sm text-green-600 bg-green-50 p-4 rounded-lg border border-green-100 text-center">
                        Tautan verifikasi baru telah dikirim ke alamat email yang Anda berikan.
                    </div>
                @endif

                <div class="bg-indigo-50 p-6 rounded-2xl text-center border border-indigo-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-[#2d5c9e] mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        Silakan periksa kotak masuk (inbox) atau folder spam email Anda untuk mengklik tautan aktivasi akun.
                    </p>
                </div>

                <div class="space-y-3">
                    <button wire:click="sendVerification" class="w-full text-white bg-[#2d5c9e] hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-base px-5 py-3 text-center transition duration-150">
                        Kirim Ulang Email Verifikasi
                    </button>

                    <div class="pt-15 text-center">
                        <button wire:click="logout" class="text-sm text-gray-500 font-bold hover:text-red-600 hover:underline transition duration-150">
                            Keluar (Log Out)
                        </button>
                    </div>
                </div>

                <div class="pt-6">
                    <div class="border-t border-gray-200 w-full mb-2"></div>
                    <p class="text-center text-xs text-gray-400">
                        &copy; {{ date('Y') }} PT LAPI ITB. Seluruh hak cipta dilindungi.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
