<?php

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.blank')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $whatsapp = '';
    public string $instansi = '';
    public string $jabatan = '';
    public string $alamat = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $terms = false;

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'whatsapp' => ['required', 'string', 'max:20'],
            'instansi' => ['required', 'string', 'max:255'],
            'jabatan' => ['required', 'string', 'max:255'],
            'alamat' => ['required', 'string', 'max:500'],
            // UPDATED: Validasi password diperketat
            'password' => [
                'required',
                'string',
                'confirmed',
                Rules\Password::min(8)
                    ->mixedCase() // Minimal 1 huruf besar & 1 kecil
                    ->numbers()   // Minimal 1 angka
            ],
            'terms' => ['accepted'],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = DB::transaction(function () use ($validated) {

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_active' => true,
            ]);

            $user->assignRole('student');

            UserProfile::create([
                'user_id' => $user->id,
                'full_name' => $validated['name'],
                'phone_number' => $validated['whatsapp'],
                'agency_name' => $validated['instansi'],
                'position' => $validated['jabatan'],
                'address' => $validated['alamat'],
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('verification.notice', absolute: false), navigate: true);
    }
}; ?>

<div class="fixed inset-0 flex items-center justify-center bg-gray-200 p-4 font-sans overflow-y-auto">
    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden w-full max-w-6xl flex flex-col md:flex-row h-[90vh] md:h-[85vh]">

        <div class="hidden md:block md:w-5/12 bg-[#9fa8da] relative">
            <img src="{{ asset('assets/img/aset2.webp') }}" alt="illustration" />
        </div>

        <div class="w-full md:w-7/12 p-8 md:p-10 overflow-y-auto relative">

            <a href="/" class="absolute top-4 right-4 text-gray-300 hover:text-gray-500 transition duration-150 ease-in-out bg-indigo-50 rounded-full p-1 hover:bg-indigo-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </a>

            <div class="flex flex-col items-center mb-6">
                <img src="{{ asset('img/Logo_PT LAPI_PNG.png') }}" alt="PT LAPI ITB" class="h-9 mb-6">
                <h2 class="text-3xl font-extrabold text-gray-800">Register</h2>
                <p class="text-sm text-gray-500 mt-1 text-center">Selamat Datang! Silakan masuk ke akunmu untuk melanjutkan</p>
            </div>

            <div class="text-sm text-gray-600 mb-4 text-left">
                Already have an Account? Let's <a href="{{ route('login') }}" class="text-[#2d5c9e] font-bold hover:underline" wire:navigate>Log In</a>
            </div>

            <form wire:submit="register" class="space-y-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                    <input wire:model="name" type="text" placeholder="Nama Lengkap"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400 p-2.5">
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input wire:model="email" type="email" placeholder="Email"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400 p-2.5">
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Whatsapp</label>
                    <div class="flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                            +62
                        </span>
                        <input wire:model="whatsapp" type="text" placeholder="Contoh 812345678"
                            class="flex-1 min-w-0 block w-full px-3 py-2.5 rounded-none rounded-r-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm placeholder-gray-400">
                    </div>
                    <x-input-error :messages="$errors->get('whatsapp')" class="mt-1" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Instansi Asal</label>
                    <input wire:model="instansi" type="text" placeholder="Instansi Asal"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400 p-2.5">
                    <x-input-error :messages="$errors->get('instansi')" class="mt-1" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan</label>
                    <input wire:model="jabatan" type="text" placeholder="Jabatan"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400 p-2.5">
                    <x-input-error :messages="$errors->get('jabatan')" class="mt-1" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                    <textarea wire:model="alamat" placeholder="Alamat" rows="2"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400 p-2.5"></textarea>
                    <x-input-error :messages="$errors->get('alamat')" class="mt-1" />
                </div>

                <div x-data="{ show: false }">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <input wire:model="password" :type="show ? 'text' : 'password'" placeholder="Buat Password"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400 p-2.5 pr-10">
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
                    <div class="mt-2 text-xs text-gray-500 space-y-1">
                        <p class="flex items-center"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg> Password Requirements</p>
                        <ul class="list-disc list-inside ml-4">
                            <li>8 characters minimum</li>
                            <li>one uppercase letter</li>
                            <li>one lowercase letter</li>
                            <li>one number</li>
                        </ul>
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                </div>

                <div x-data="{ show: false }">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password</label>
                    <div class="relative">
                        <input wire:model="password_confirmation" :type="show ? 'text' : 'password'" placeholder="Konfirmasi Password"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400 p-2.5 pr-10">
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
                </div>

                <div class="flex items-start mt-4">
                    <div class="flex items-center h-5">
                        <input wire:model="terms" id="terms" type="checkbox" class="w-4 h-4 border border-gray-300 rounded bg-gray-50 focus:ring-3 focus:ring-indigo-300">
                    </div>
                    <label for="terms" class="ml-2 text-xs font-medium text-gray-900">
                        Saya telah membaca dan menyetujui Ketentuan Pengguna dan Kebijakan Privasi. Jika Anda berusia di bawah 18 tahun, Anda wajib dan kami anggap telah mendapatkan persetujuan orang tua/wali Anda untuk mengisi formulir ini.
                    </label>
                </div>
                <x-input-error :messages="$errors->get('terms')" class="mt-1" />

                <div class="border-t border-gray-200 w-full mt-6 mb-4"></div>

                <button type="submit" class="w-full text-white bg-primary hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                    Daftar
                </button>

            </form>
        </div>
    </div>
</div>
