<?php

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    // Data Akun Utama
    public string $name = '';
    public string $email = '';
    public $photo;

    // Data Profil Tambahan
    public $full_name = '';
    public $nik = '';
    public $phone_number = '';
    public $agency_name = '';
    public $position = '';
    public $address = '';
    public $gender = '';
    public $place_of_birth = '';
    public $date_of_birth = '';

    public function mount(): void
    {
        $user = Auth::user();

        // Load Data Akun
        $this->name = $user->name;
        $this->email = $user->email;

        // Load Data Profil (jika ada)
        // Kita akses via relasi profile yang sudah ada di User model
        $profile = $user->profile;

        if ($profile) {
            $this->full_name = $profile->full_name ?? '';
            $this->nik = $profile->nik ?? '';
            $this->phone_number = $profile->phone_number ?? '';
            $this->agency_name = $profile->agency_name ?? '';
            $this->position = $profile->position ?? '';
            $this->address = $profile->address ?? '';
            $this->gender = $profile->gender ?? '';
            $this->place_of_birth = $profile->place_of_birth ?? '';
            // Pastikan format tanggal string agar bisa dibaca input type="date"
            $this->date_of_birth = $profile->date_of_birth ? $profile->date_of_birth->format('Y-m-d') : '';
        }
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            // Validasi Akun
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'photo' => ['nullable', 'image', 'max:2048'], // Max 2MB

            // Validasi Profil
            'full_name' => ['nullable', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:20', Rule::unique(UserProfile::class, 'nik')->ignore($user->id, 'user_id')],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'agency_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'gender' => ['nullable', 'in:L,P'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
        ]);

        // 1. Simpan Data User (Name & Email)
        $user->fill([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // 2. Simpan Foto (Jika ada upload baru)
        if ($this->photo) {
            // Hapus foto lama jika perlu, atau Spatie akan menimpa di collection 'avatar'
            $user->addMedia($this->photo)->toMediaCollection('avatar');
        }

        // 3. Simpan Data Profil (Update atau Create)
        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $this->full_name,
                'nik' => $this->nik,
                'phone_number' => $this->phone_number,
                'agency_name' => $this->agency_name,
                'position' => $this->position,
                'address' => $this->address,
                'gender' => $this->gender,
                'place_of_birth' => $this->place_of_birth,
                'date_of_birth' => $this->date_of_birth ?: null,
            ]
        );

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));
            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section>
    <form wire:submit="updateProfileInformation" class="space-y-6">

        {{-- BAGIAN 1: FOTO & AKUN LOGIN --}}
        <div class="bg-indigo-50/50 p-6 rounded-xl border border-indigo-100 mb-6">
            <h3 class="text-xs font-bold text-indigo-800 uppercase mb-4 tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                Foto & Akun
            </h3>

            <div class="flex flex-col md:flex-row gap-6">
                {{-- Foto Profil --}}
                <div class="shrink-0 flex flex-col items-center">
                    <div class="relative group">
                        @if ($photo && $photo->isPreviewable())
                            <img src="{{ $photo->temporaryUrl() }}" class="h-24 w-24 object-cover rounded-full border-4 border-white shadow-md">
                        @elseif (Auth::user()->getFirstMediaUrl('avatar'))
                            <img src="{{ Auth::user()->getFirstMediaUrl('avatar') }}" class="h-24 w-24 object-cover rounded-full border-4 border-white shadow-md">
                        @else
                            <div class="h-24 w-24 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-3xl border-4 border-white shadow-md">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                        @endif

                        {{-- Icon Upload Overlay --}}
                        <div class="absolute inset-0 bg-black/30 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer pointer-events-none">
                            <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                    </div>

                    <div class="mt-3 w-full">
                        <input wire:model="photo" id="photo" type="file" class="hidden" accept="image/*" />
                        <label for="photo" class="text-xs text-indigo-600 hover:text-indigo-800 cursor-pointer font-semibold text-center block">Ubah Foto</label>
                        <x-input-error class="mt-1 text-xs" :messages="$errors->get('photo')" />
                    </div>
                </div>

                {{-- Form Akun --}}
                <div class="flex-1 grid grid-cols-1 gap-4">
                    <div>
                        <x-input-label for="name" :value="__('Username / Nama Tampilan')" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" required />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" required />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />

                        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                            <div class="mt-2 text-sm text-gray-800">
                                {{ __('Email belum diverifikasi.') }}
                                <button wire:click.prevent="sendVerification" class="underline text-indigo-600 hover:text-indigo-800">
                                    {{ __('Kirim ulang verifikasi.') }}
                                </button>
                                @if (session('status') === 'verification-link-sent')
                                    <p class="mt-2 font-medium text-green-600">{{ __('Tautan dikirim!') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- BAGIAN 2: BIODATA LENGKAP --}}
        <div class="border-t border-gray-200 pt-6">
            <h3 class="text-xs font-bold text-gray-500 uppercase mb-4 tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
                Biodata Diri
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="col-span-1 md:col-span-2">
                    <x-input-label for="full_name" :value="__('Nama Lengkap (Sesuai KTP)')" />
                    <x-text-input wire:model="full_name" id="full_name" type="text" class="mt-1 block w-full" placeholder="Contoh: Budi Santoso, S.Kom" />
                    <x-input-error class="mt-2" :messages="$errors->get('full_name')" />
                </div>

                <div>
                    <x-input-label for="nik" :value="__('NIK (Nomor Induk Kependudukan)')" />
                    <x-text-input wire:model="nik" id="nik" type="number" class="mt-1 block w-full" />
                    <x-input-error class="mt-2" :messages="$errors->get('nik')" />
                </div>

                <div>
                    <x-input-label for="phone_number" :value="__('Nomor WhatsApp / HP')" />
                    <x-text-input wire:model="phone_number" id="phone_number" type="tel" class="mt-1 block w-full" placeholder="+62" />
                    <x-input-error class="mt-2" :messages="$errors->get('phone_number')" />
                </div>

                <div>
                    <x-input-label for="place_of_birth" :value="__('Tempat Lahir')" />
                    <x-text-input wire:model="place_of_birth" id="place_of_birth" type="text" class="mt-1 block w-full" />
                </div>

                <div>
                    <x-input-label for="date_of_birth" :value="__('Tanggal Lahir')" />
                    <x-text-input wire:model="date_of_birth" id="date_of_birth" type="date" class="mt-1 block w-full" />
                </div>

                <div>
                    <x-input-label for="gender" :value="__('Jenis Kelamin')" />
                    <select wire:model="gender" id="gender" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">-- Pilih --</option>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- BAGIAN 3: DATA INSTANSI & ALAMAT --}}
        <div class="border-t border-gray-200 pt-6">
            <h3 class="text-xs font-bold text-gray-500 uppercase mb-4 tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                Pekerjaan & Alamat
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="agency_name" :value="__('Nama Instansi / Perusahaan')" />
                    <x-text-input wire:model="agency_name" id="agency_name" type="text" class="mt-1 block w-full" />
                </div>

                <div>
                    <x-input-label for="position" :value="__('Jabatan / Posisi')" />
                    <x-text-input wire:model="position" id="position" type="text" class="mt-1 block w-full" />
                </div>

                <div class="col-span-1 md:col-span-2">
                    <x-input-label for="address" :value="__('Alamat Domisili Lengkap')" />
                    <textarea wire:model="address" id="address" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 pt-4">
            <x-primary-button class="px-6">{{ __('Simpan Perubahan') }}</x-primary-button>
            <x-action-message class="me-3" on="profile-updated">
                {{ __('Data berhasil disimpan.') }}
            </x-action-message>
        </div>
    </form>
</section>
