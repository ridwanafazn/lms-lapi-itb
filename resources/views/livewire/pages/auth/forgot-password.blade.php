<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('layouts.blank')] class extends Component {
    #[Validate('required|email')]
    public string $email = '';

    public function sendPasswordResetLink(): void
    {
        $this->validate();

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('status', __($status));
            $this->email = ''; // Optional: Kosongkan field setelah sukses
            return; // <--- INI KUNCI PERBAIKANNYA (Agar tidak lanjut ke error)
        }

        $this->addError('email', __($status));
    }
}; ?>

<div class="fixed inset-0 flex items-center justify-center bg-gray-200 p-4 font-sans overflow-y-auto">
    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden w-full max-w-6xl flex flex-col md:flex-row h-[90vh] md:h-[85vh]">

        <div class="hidden md:block md:w-5/12 bg-[#9fa8da] relative">
            <img src="{{ asset('assets/img/aset2.webp') }}" alt="illustration" />
        </div>

        <div class="w-full md:w-7/12 p-8 md:p-12 flex flex-col justify-center overflow-y-auto relative">

            <a href="{{ route('login') }}" class="absolute top-6 left-6 flex items-center group transition duration-150 ease-in-out" wire:navigate>
                <div class="bg-gray-100 text-gray-500 rounded-lg p-2 group-hover:bg-gray-200 mr-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </div>
                <span class="text-gray-500 font-medium text-sm group-hover:text-gray-700">Back to Login</span>
            </a>

            <a href="{{ route('login') }}" class="absolute top-4 right-4 text-gray-300 hover:text-gray-500 transition duration-150 ease-in-out bg-indigo-50 rounded-full p-1 hover:bg-indigo-100" wire:navigate>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </a>

            <div class="flex flex-col items-center mb-6 mt-10 md:mt-0">
                <img src="{{ asset('img/Logo_PT LAPI_PNG.png') }}" alt="PT LAPI ITB" class="h-9 mb-6">
                <h2 class="text-3xl font-extrabold text-gray-800">Forgot Password</h2>
                <p class="text-sm text-gray-500 mt-2 text-center max-w-md">
                    Enter the email address that you registered with. You will receive an email with instructions for updating your password.
                </p>
            </div>

            <x-auth-session-status class="mb-4 text-center" :status="session('status')" />

            <form wire:submit="sendPasswordResetLink" class="space-y-5 max-w-md mx-auto w-full">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Masukkan Email</label>
                    <input wire:model="email" type="email" placeholder="email@example.com" autofocus
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400 p-3">

                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <div class="border-t border-gray-200 w-full pt-2"></div>

                <button type="submit" class="w-full text-white bg-primary hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-base px-5 py-3 text-center">
                    Send Reset Link
                </button>

            </form>
        </div>
    </div>
</div>
