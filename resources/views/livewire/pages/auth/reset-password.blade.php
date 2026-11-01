<?php

use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.blank')] class extends Component {
    public string $token;
    public string $email;
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->email = request()->query('email', '');
        $this->token = $token;
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            // UPDATED: Validasi password diperketat
            'password' => [
                'required',
                'confirmed',
                Rules\Password::min(8)
                    ->mixedCase()
                    ->numbers()
            ],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new Illuminate\Auth\Events\PasswordReset($user));
            }
        );

        if ($status == Password::RESET_LINK_SENT) {
            session()->flash('status', __($status));
        }

        $this->redirect(route('login', absolute: false), navigate: true);
    }
}; ?>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-gray-900 bg-gray-200">

    <div class="fixed inset-0 flex items-center justify-center p-4 overflow-y-auto">

        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden w-full max-w-6xl flex flex-col md:flex-row h-[90vh] md:h-[85vh]">

            <div class="w-full md:w-1/2 p-8 md:p-16 flex flex-col justify-center bg-white relative overflow-y-auto">

                <div class="mb-4">
                    <h2 class="text-3xl font-extrabold text-gray-900 mb-2">Buat Kata Sandi Baru</h2>
                    <p class="text-sm text-gray-500">
                        Silahkan buat kata sandi baru untuk akun <span class="font-bold text-gray-800">{{ $email }}</span>
                    </p>
                </div>

                <form wire:submit="resetPassword" class="space-y-6">
                    <input type="hidden" name="token" wire:model="token">

                    <div x-data="{ show: false }">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kata Sandi Baru</label>
                        <div class="relative">
                            <input
                                :type="show ? 'text' : 'password'"
                                wire:model="password"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400 p-3 pr-10"
                                placeholder="Masukkan kata sandi baru"
                            >
                            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600">
                                <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.05 10.05 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.058 10.058 0 01-1.07 2.47M16.732 3.732l1.139-1.139" />
                                </svg>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>

                    <div class="text-xs text-gray-500 space-y-1">
                        <p class="font-bold text-gray-700 mb-1 flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-gray-400">
                                <path fill-rule="evenodd" d="M8 7a5 5 0 1 1 3.61 1.553l2.705 2.704a.75.75 0 0 1 0 1.06l-1.5 1.5a.75.75 0 0 1-1.06 0l-1.5-1.5a.75.75 0 0 1 0-1.06l.822-.823A5 5 0 0 1 8 7Zm-1.5 0a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Z" clip-rule="evenodd" />
                            </svg>
                            Persyaratan Kata Sandi
                        </p>
                        <ul class="list-disc pl-6 space-y-0.5 ml-1">
                            <li>Minimal 8 karakter</li>
                            <li>Memuat huruf kapital</li>
                            <li>Memuat huruf kecil</li>
                            <li>Memuat angka</li>
                        </ul>
                    </div>

                    <div x-data="{ show: false }">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Kata Sandi</label>
                        <div class="relative">
                            <input
                                :type="show ? 'text' : 'password'"
                                wire:model="password_confirmation"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400 p-3 pr-10"
                                placeholder="Konfirmasi kata sandi baru"
                            >
                            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600">
                                <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.05 10.05 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.058 10.058 0 01-1.07 2.47M16.732 3.732l1.139-1.139" />
                                </svg>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                    </div>

                    <button type="submit" class="w-full text-white bg-[#2d5c9e] hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-bold rounded-lg text-base px-5 py-3 text-center mt-4 transition ease-in-out duration-150 shadow-md">
                        Masuk
                    </button>
                </form>
            </div>

            <div class="hidden md:block md:w-1/2 bg-[#9fa8da] relative">
                 <div class="absolute inset-0 bg-gradient-to-br from-indigo-300 to-purple-400 opacity-50"></div>
            </div>

        </div>
    </div>
</body>
</html>
