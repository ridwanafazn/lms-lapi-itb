<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.blank')] class extends Component
{
    // State
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    // Logic Login
    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Coba Login
        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', trans('auth.failed'));
            return;
        }

        // Regenerate Session (Keamanan standar Laravel)
        Session::regenerate();

        // [PERBAIKAN] Redirect ke Dashboard tanpa 'navigate: true' (Hard Redirect)
        $this->redirectIntended(default: route('dashboard', absolute: false));
    }
}; ?>

<div class="fixed inset-0 flex items-center justify-center bg-gray-200 p-4 font-sans overflow-y-auto">
    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden w-full max-w-6xl flex flex-col md:flex-row h-[90vh] md:h-[85vh]">

        <div class="hidden md:block md:w-5/12 bg-[#9fa8da] relative">
            <img src="{{ asset('assets/img/aset2.webp') }}" alt="illustration" />
        </div>

        <div class="w-full md:w-7/12 p-8 md:p-12 flex flex-col justify-center overflow-y-auto relative">

            <a href="/" class="absolute top-4 right-4 text-gray-300 hover:text-gray-500 transition duration-150 ease-in-out bg-indigo-50 rounded-full p-1 hover:bg-indigo-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </a>

            <div class="flex flex-col items-center mb-6">
                <img src="{{ asset('img/Logo_PT LAPI_PNG.png') }}" alt="PT LAPI ITB" class="h-9 mb-6">
                <h2 class="text-3xl font-extrabold text-gray-800">Login</h2>
                <p class="text-sm text-gray-500 mt-2 text-center">Selamat Datang! Silakan masuk ke akunmu untuk melanjutkan</p>
            </div>

            <form wire:submit="login" class="space-y-5 max-w-md mx-auto w-full">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Masukkan Email</label>
                    <input wire:model="email" type="email" placeholder="Email" autofocus
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary placeholder-gray-400 p-3">
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <div x-data="{ show: false }">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Masukkan Password</label>
                    <div class="relative">
                        <input wire:model="password" :type="show ? 'text' : 'password'" placeholder="Password"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary placeholder-gray-400 p-3 pr-10">

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

                <div class="flex items-center justify-between mt-4">
                    <label for="remember_me" class="inline-flex items-center">
                        <input wire:model="remember" id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="ms-2 text-sm text-gray-600">Remember Me</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="text-sm text-[#2d5c9e] font-bold hover:underline" href="{{ route('password.request') }}" wire:navigate>
                            Forgot Password?
                        </a>
                    @endif
                </div>

                <button type="submit" class="w-full text-white bg-primary hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-base px-5 py-3 text-center mt-2">
                    Masuk
                </button>

                <div class="pt-4">
                    <div class="border-t border-gray-200 w-full mb-4"></div> <div class="text-left text-sm text-gray-600">
                        Don't have an Account?
                        <a href="{{ route('register') }}" class="text-[#2d5c9e] font-bold hover:underline ml-1" wire:navigate>
                            Let's Register
                        </a>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>
