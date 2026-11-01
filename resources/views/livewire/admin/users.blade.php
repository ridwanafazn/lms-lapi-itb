<?php

use App\Models\User;
use App\Models\UserProfile;
use App\Models\ActivityLog;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use function Livewire\Volt\{state, computed, usesPagination, layout};

layout('layouts.app');
usesPagination();

state([
    'isModalOpen' => false,
    'isDeleteModalOpen' => false,
    'isEnrollmentModalOpen' => false,
    'deleteId' => null,

    'selectedUserName' => '',
    'selectedEnrollments' => [],

    'search' => '',
    'filterRole' => '',
    'filterStatus' => '',
    'sortCol' => 'created_at',
    'sortAsc' => false,

    'user_id' => null,
    'name' => '',
    'email' => '',
    'password' => '',
    'role' => 'student',
    'is_active' => true,

    'nik' => '',
    'phone_number' => '',
    'agency_name' => '',
    'position' => '',
    'gender' => '',
    'address' => '',
    'place_of_birth' => '',
    'date_of_birth' => '',
]);

$updatingSearch = fn() => $this->resetPage();
$updatingFilterRole = fn() => $this->resetPage();

$sortBy = function($col) {
    if ($this->sortCol === $col) {
        $this->sortAsc = !$this->sortAsc;
    } else {
        $this->sortCol = $col;
        $this->sortAsc = true;
    }
};

$users = computed(function () {
    return User::query()
        ->with(['roles', 'profile', 'media'])
        ->withCount(['enrollments' => function ($q) {
            $q->where('status', 'active');
        }])
        ->when($this->search, function ($query) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhereHas('profile', function($p) {
                      $p->where('nik', 'like', '%' . $this->search . '%')
                        ->orWhere('agency_name', 'like', '%' . $this->search . '%');
                  });
            });
        })
        ->when($this->filterRole, fn($q) => $q->role($this->filterRole))
        ->when($this->filterStatus !== '', fn($q) => $q->where('is_active', $this->filterStatus))
        ->orderBy($this->sortCol, $this->sortAsc ? 'asc' : 'desc')
        ->paginate(10);
});

$roles = computed(fn () => Role::pluck('name', 'name'));

$showEnrollments = function($userId) {
    $user = User::with([
        'enrollments' => function($q) {
            $q->where('status', 'active')->latest();
        },
        'enrollments.enrollable'
    ])->findOrFail($userId);

    $this->selectedUserName = $user->name;
    $this->selectedEnrollments = $user->enrollments;
    $this->isEnrollmentModalOpen = true;
};

$openModal = function () {
    $this->resetValidation();
    $this->resetExcept(['search', 'filterRole', 'filterStatus', 'sortCol', 'sortAsc']);
    $this->is_active = true;
    $this->role = 'student';
    $this->isModalOpen = true;
};

$closeModal = fn() => $this->isModalOpen = false;

$edit = function ($id) {
    $this->resetValidation();
    $user = User::with('profile')->findOrFail($id);

    $this->user_id = $id;
    $this->name = $user->name;
    $this->email = $user->email;
    $this->is_active = $user->is_active;
    $this->role = $user->roles->first()->name ?? 'student';
    $this->password = '';

    $this->nik = $user->profile->nik ?? '';
    $this->phone_number = $user->profile->phone_number ?? '';
    $this->agency_name = $user->profile->agency_name ?? '';
    $this->position = $user->profile->position ?? '';
    $this->gender = $user->profile->gender ?? '';
    $this->address = $user->profile->address ?? '';
    $this->place_of_birth = $user->profile->place_of_birth ?? '';
    $this->date_of_birth = $user->profile->date_of_birth ? \Carbon\Carbon::parse($user->profile->date_of_birth)->format('Y-m-d') : '';

    $this->isModalOpen = true;
};

$save = function () {
    $rules = [
        'name' => 'required|min:3',
        'email' => ['required', 'email', Rule::unique('users')->ignore($this->user_id)],
        'role' => 'required|exists:roles,name',
        'nik' => ['nullable', 'numeric', Rule::unique('user_profiles', 'nik')->ignore($this->user_id, 'user_id')],
        'phone_number' => 'nullable|numeric',
    ];

    $passwordRequirement = $this->user_id ? 'nullable' : 'required';
    $rules['password'] = [
        $passwordRequirement,
        Password::min(8)->mixedCase()->numbers()
    ];

    $this->validate($rules);

    DB::transaction(function () {
        $userData = [
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
        ];
        if ($this->password) {
            $userData['password'] = Hash::make($this->password);
        }

        $idToUse = $this->user_id ?: (string) Str::uuid();
        $isNew = !$this->user_id;

        $user = User::updateOrCreate(['id' => $idToUse], array_merge($userData, ['id' => $idToUse]));
        $user->syncRoles([$this->role]);

        UserProfile::updateOrCreate(['user_id' => $user->id], [
            'full_name' => $this->name,
            'nik' => $this->nik ?: null,
            'phone_number' => $this->phone_number,
            'agency_name' => $this->agency_name,
            'position' => $this->position,
            'gender' => $this->gender ?: null,
            'address' => $this->address,
            'place_of_birth' => $this->place_of_birth,
            'date_of_birth' => $this->date_of_birth ?: null,
        ]);

        try {
            ActivityLog::create([
                'actor_id' => Auth::id(),
                'actor_name' => Auth::user()->name ?? 'System',
                'action' => $isNew ? 'CREATE_USER' : 'UPDATE_USER',
                'description' => $isNew ? "Create user: {$this->name}" : "Update user: {$this->name}",
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {}
    });

    session()->flash('message', 'Data berhasil disimpan.');
    $this->closeModal();
};

$confirmDelete = function ($id) {
    if (auth()->id() == $id) return;
    $this->deleteId = $id;
    $this->isDeleteModalOpen = true;
};

$delete = function () {
    try {
        $user = User::findOrFail($this->deleteId);
        $user->delete();
        $this->isDeleteModalOpen = false;
        session()->flash('message', 'User berhasil dihapus.');
    } catch (QueryException $e) {
        $this->isDeleteModalOpen = false;
        session()->flash('error', $e->getCode() == "23000" ? 'Gagal: User memiliki data aktif.' : 'Database error.');
    }
};

?>

<div class="min-h-screen bg-gray-50/50">

    {{-- <div class="py-8 px-4 sm:px-6 lg:px-8 bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="font-bold text-2xl text-gray-900 leading-tight">Manajemen Pengguna</h2>
                <p class="text-sm text-gray-500 mt-1">Kelola data akun, hak akses, dan monitoring peserta.</p>
            </div>

            <button wire:click="openModal" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Buat Akun Baru
            </button>
        </div>
    </div> --}}

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session()->has('message'))
                <div class="p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm font-medium flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    {{ session('message') }}
                </div>
            @endif
            @if (session()->has('error'))
                <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-medium flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex flex-col lg:flex-row gap-4 justify-between items-center">


                <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 mb-6">

    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
        <h1 class="font-bold text-2xl text-gray-900 leading-tight whitespace-nowrap">
            Manajemen Pengguna
        </h1>
        <p class="text-sm text-gray-500">
            Kelola data akun, hak akses, dan monitoring peserta.
        </p>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 w-full xl:w-auto">
        <div class="relative w-full sm:w-72">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <input wire:model.live.debounce.300ms="search" type="text" class="pl-10 block w-full rounded-xl border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Cari Nama, Email, NIK ...">
        </div>

        <select wire:model.live="filterRole" class="block w-full sm:w-40 rounded-xl border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <option value="">Role</option>
            @foreach($this->roles as $roleName)
                <option value="{{ $roleName }}">{{ ucfirst($roleName) }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterStatus" class="block w-full sm:w-40 rounded-xl border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <option value="">Status</option>
            <option value="1">Aktif</option>
            <option value="0">Non-Aktif</option>
        </select>
    </div>

</div>

                {{-- <div class="text-sm text-gray-500 font-medium whitespace-nowrap px-2">
                    Total: <span class="text-indigo-600 font-bold">{{ $this->users->total() }}</span> Data
                </div> --}}
                <button wire:click="openModal" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Buat Akun Baru
                </button>
            </div>

            <div class="bg-white overflow-hidden shadow-sm rounded-2xl border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider cursor-pointer hover:text-indigo-600 transition group" wire:click="sortBy('name')">
                                    Identitas User
                                    @if($sortCol === 'name') <span class="ml-1 group-hover:text-indigo-800">{{ $sortAsc ? '↑' : '↓' }}</span> @endif
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    Detail Profil
                                </th>
                                <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider cursor-pointer hover:text-indigo-600 transition group" wire:click="sortBy('created_at')">
                                    Status
                                    @if($sortCol === 'created_at') <span class="ml-1 group-hover:text-indigo-800">{{ $sortAsc ? '↑' : '↓' }}</span> @endif
                                </th>
                                <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    Partisipasi
                                </th>
                                <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($this->users as $user)
                                <tr class="hover:bg-gray-50 transition duration-150 ease-in-out group">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                @if($user->hasMedia('avatar'))
                                                    <img class="h-10 w-10 rounded-full object-cover ring-2 ring-gray-100" src="{{ $user->getFirstMediaUrl('avatar') }}">
                                                @else
                                                    <div class="h-10 w-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm shadow-sm">
                                                        {{ substr($user->name, 0, 1) }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-bold text-gray-900 group-hover:text-indigo-600 transition">{{ $user->name }}</div>
                                                <div class="text-xs text-gray-500">{{ $user->email }}</div>
                                                <div class="text-[10px] text-gray-400 mt-0.5">Joined: {{ $user->created_at->format('d M Y') }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $user->profile->agency_name ?? '-' }}</div>
                                        <div class="text-xs text-gray-500">{{ $user->profile->position ?? '-' }}</div>
                                        @if($user->profile && $user->profile->phone_number)
                                            <div class="text-[10px] text-gray-400 mt-1 flex items-center">
                                                <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                                {{ $user->profile->phone_number }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <div class="flex flex-col gap-1.5 items-center">
                                            @foreach($user->roles as $role)
                                                <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full border
                                                    {{ $role->name === 'admin' ? 'bg-red-50 text-red-700 border-red-100' :
                                                      ($role->name === 'instructor' ? 'bg-purple-50 text-purple-700 border-purple-100' : 'bg-blue-50 text-blue-700 border-blue-100') }}">
                                                    {{ ucfirst($role->name) }}
                                                </span>
                                            @endforeach
                                            <span class="inline-flex items-center text-xs font-medium {{ $user->is_active ? 'text-green-600' : 'text-gray-400' }}">
                                                <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $user->is_active ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @if($user->enrollments_count > 0)
                                            <button wire:click="showEnrollments('{{ $user->id }}')" class="inline-flex items-center px-3 py-1.5 border border-indigo-200 shadow-sm text-xs font-medium rounded-full text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                {{ $user->enrollments_count }} Kelas Aktif
                                            </button>
                                        @else
                                            <span class="text-xs text-gray-400 italic">Belum ada kelas</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <div class="flex justify-center items-center gap-2">
                                            <button wire:click="edit('{{ $user->id }}')" class="p-1.5 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition" title="Edit">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button wire:click="confirmDelete('{{ $user->id }}')" class="p-1.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Delete">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                            <p class="text-base font-medium text-gray-900">Tidak ada pengguna ditemukan</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-gray-100">
                    {{ $this->users->links() }}
                </div>
            </div>
        </div>
    </div>

    @if($isEnrollmentModalOpen)
        <div class="fixed z-50 inset-0 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="$set('isEnrollmentModalOpen', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                    <div class="bg-white px-6 pt-6 pb-4">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Kelas Aktif Peserta</h3>
                                <p class="text-sm text-gray-500 mt-1">Daftar kelas yang diikuti oleh <span class="font-semibold text-indigo-600">{{ $selectedUserName }}</span>.</p>
                            </div>
                            <button wire:click="$set('isEnrollmentModalOpen', false)" class="text-gray-400 hover:text-gray-500">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <div class="overflow-hidden border border-gray-200 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Nama Kelas / Program</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Tipe</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Konten</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($selectedEnrollments as $enrollment)
                                        @if($enrollment->enrollable)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $enrollment->title }}
                                                <div class="text-xs text-gray-500 font-normal mt-0.5">
                                                    @if(isset($enrollment->enrollable->start_date))
                                                        {{ \Carbon\Carbon::parse($enrollment->enrollable->start_date)->format('d M Y') }}
                                                        @if(isset($enrollment->enrollable->end_date))
                                                            s/d {{ \Carbon\Carbon::parse($enrollment->enrollable->end_date)->format('d M Y') }}
                                                        @endif
                                                    @else
                                                        Self-paced / Flexible
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @if($enrollment->is_program)
                                                    <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs font-bold">PROGRAM</span>
                                                @else
                                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-bold">BATCH</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-700">
                                                {{ $enrollment->context_title }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Active</span>
                                            </td>
                                        </tr>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-6 py-8 text-center text-gray-500 italic">Tidak ada data kelas aktif.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="$set('isEnrollmentModalOpen', false)" class="w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:ml-3 sm:w-auto sm:text-sm">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($isModalOpen)
        <div class="fixed z-50 inset-0 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="closeModal"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white px-6 pt-6 pb-4">
                            <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
                                <h3 class="text-xl font-bold text-gray-900">{{ $user_id ? 'Edit User' : 'Buat Akun Baru' }}</h3>
                                <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-500">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="space-y-5">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                                        <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                                        <input type="email" wire:model="email" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Password @if(!$user_id) <span class="text-red-500">*</span> @endif</label>
                                        <input type="password" wire:model="password" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <p class="text-[10px] text-gray-400 mt-1">Password minimal 8 karakter, memuat huruf besar, memuat huruf kecil, dan memuat angka.</p>
                                        @error('password') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Role <span class="text-red-500">*</span></label>
                                            <select wire:model="role" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                @foreach($this->roles as $roleName)
                                                    <option value="{{ $roleName }}">{{ ucfirst($roleName) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="flex items-center mt-6">
                                            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            <span class="ml-2 text-sm text-gray-700">Akun Aktif</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-5 bg-gray-50 p-5 rounded-xl border border-gray-100">
                                    <h4 class="text-sm font-bold text-gray-700 border-b pb-2">Detail Profil</h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">NIK</label>
                                            <input type="text" wire:model="nik" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            @error('nik') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">No. WA</label>
                                            <input type="text" wire:model="phone_number" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Instansi</label>
                                        <input type="text" wire:model="agency_name" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Jabatan</label>
                                        <input type="text" wire:model="position" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-6 py-4 flex flex-row-reverse gap-3">
                            <button type="submit" class="inline-flex justify-center rounded-xl shadow-md px-6 py-2.5 bg-indigo-600 text-sm font-bold text-white hover:bg-indigo-700 transition">Simpan</button>
                            <button type="button" wire:click="closeModal" class="inline-flex justify-center rounded-xl border border-gray-300 px-6 py-2.5 bg-white text-sm font-bold text-gray-700 hover:bg-gray-50 transition">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if($isDeleteModalOpen)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/60 transition-opacity" wire:click="$set('isDeleteModalOpen', false)"></div>
                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Hapus Pengguna</h3>
                                <div class="mt-2"><p class="text-sm text-gray-500">Tindakan ini tidak dapat dibatalkan.</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button wire:click="delete" type="button" class="w-full inline-flex justify-center rounded-lg shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 transition sm:ml-3 sm:w-auto sm:text-sm">Ya, Hapus</button>
                        <button wire:click="$set('isDeleteModalOpen', false)" type="button" class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 transition sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Batal</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
