<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Batch;
use App\Models\Course;
use Illuminate\Support\Str;

new
#[Layout('layouts.app')]
class extends Component {
    use WithPagination;

    public string $activeTab = 'active';
    public string $filterType = 'all';

    // FITUR BARU: Auto-Sync Data Membership ke tabel Enrollments
    public function mount()
    {
        $user = Auth::user();

        // Pastikan user punya plan yang aktif dan valid
        if ($user->hasActiveMembership() && $user->membership_plan_id) {

            // 1. Cari ID Program yang match dengan plan user saat ini
            // (Asumsi: Jika program punya required_membership_plan_id yang sama dengan punya user)
            $membershipPrograms = Program::where('required_membership_plan_id', $user->membership_plan_id)
                ->where('is_active', true)
                ->pluck('id');

            if ($membershipPrograms->isNotEmpty()) {
                // 2. Cari ID yang SUDAH ada di enrollments (agar tidak duplikat)
                $existingEnrollments = Enrollment::where('user_id', $user->id)
                    ->where('enrollable_type', Program::class)
                    ->whereIn('enrollable_id', $membershipPrograms)
                    ->pluck('enrollable_id');

                // 3. Filter program yang BELUM disinkronisasi
                $missingPrograms = $membershipPrograms->diff($existingEnrollments);

                // 4. Lakukan injeksi data massal (Sangat cepat & efisien)
                $newEnrollments = [];
                foreach ($missingPrograms as $progId) {
                    $newEnrollments[] = [
                        'id' => Str::uuid()->toString(),
                        'user_id' => $user->id,
                        'enrollable_type' => Program::class,
                        'enrollable_id' => $progId,
                        'status' => 'active',
                        'progress_percentage' => 0,
                        'enrolled_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (!empty($newEnrollments)) {
                    Enrollment::insert($newEnrollments);
                }
            }
        }
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function setFilter($type)
    {
        $this->filterType = $type;
        $this->resetPage();
    }

    public function with(): array
    {
        $userId = Auth::id();

        $query = Enrollment::where('user_id', $userId)
            ->with(['enrollable'])
            ->latest('updated_at');

        // 1. Filter Tab (Active / Completed)
        if ($this->activeTab === 'active') {
            $query->where('status', 'active');
        } else {
            $query->whereIn('status', ['completed', 'dropped']);
        }

        // 2. Filter Kategori (SMART FILTERING)
        if ($this->filterType === 'program_self_paced') {
            $query->whereHasMorph('enrollable', [Program::class], function (Builder $q) {
                $q->where('type', 'self_paced');
            });

        } elseif ($this->filterType === 'program_cohort') {
            $query->where(function (Builder $group) {
                $group->whereHasMorph('enrollable', [Program::class], function (Builder $q) {
                    $q->where('type', 'cohort');
                })
                ->orWhereHasMorph('enrollable', [Batch::class], function (Builder $q) {
                    $q->whereNotNull('program_id');
                });
            });

        } elseif ($this->filterType === 'course') {
            $query->where(function (Builder $group) {
                $group->whereHasMorph('enrollable', [Course::class])
                ->orWhereHasMorph('enrollable', [Batch::class], function (Builder $q) {
                    $q->whereNull('program_id');
                });
            });
        }

        return [
            'enrollments' => $query->paginate(9)
        ];
    }
}; ?>

<div class="min-h-screen bg-gray-50 pb-12 font-sans text-gray-900">

    {{-- CSS Khusus untuk menyembunyikan Scrollbar tapi tetap bisa discroll --}}
    <style>
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
    </style>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        {{-- Header Section --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-2">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Kelas Saya</h1>
                <p class="mt-2 text-sm text-gray-500">Lanjutkan perjalanan belajar Anda hari ini.</p>
            </div>

            {{-- Tab Switcher --}}
            <div class="bg-white p-1 rounded-full border border-gray-200 shadow-sm inline-flex">
                <button
                    wire:click="setTab('active')"
                    class="px-5 py-2 rounded-full text-sm font-medium transition-colors duration-200 flex items-center gap-2
                    {{ $activeTab === 'active' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">
                    Sedang Berjalan
                </button>
                <button
                    wire:click="setTab('completed')"
                    class="px-5 py-2 rounded-full text-sm font-medium transition-colors duration-200 flex items-center gap-2
                    {{ $activeTab === 'completed' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">
                    Selesai
                </button>
            </div>
        </div>

        {{-- Filter Categories --}}
        <div class="border-b border-gray-200 mb-8 overflow-x-auto no-scrollbar">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button wire:click="setFilter('all')"
                    class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors {{ $filterType === 'all' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                    Semua
                </button>
                <button wire:click="setFilter('program_self_paced')"
                    class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors {{ $filterType === 'program_self_paced' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                    Program Mandiri
                </button>
                <button wire:click="setFilter('program_cohort')"
                    class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors {{ $filterType === 'program_cohort' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                    Program Angkatan
                </button>
                <button wire:click="setFilter('course')"
                    class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors {{ $filterType === 'course' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                    Pelatihan Tunggal
                </button>
            </nav>
        </div>

        {{-- Loading State --}}
        <div wire:loading.flex class="w-full justify-center py-12">
            <div class="flex flex-col items-center gap-3">
                <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sm font-medium text-gray-500">Memuat data...</span>
            </div>
        </div>

        {{-- Content Grid --}}
        <div wire:loading.remove>
            @if($enrollments->isEmpty())
                {{-- Empty State --}}
                <div class="text-center py-20 bg-white rounded-2xl border border-dashed border-gray-300 shadow-sm">
                    <div class="mx-auto h-12 w-12 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.499 5.221 69.17 69.17 0 00-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                        </svg>
                    </div>
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">Tidak ada data</h3>
                    <p class="mt-1 text-sm text-gray-500">Tidak ada kelas yang ditemukan pada kategori ini.</p>
                    <div class="mt-6">
                        <a href="{{ route('student.browse') }}" wire:navigate class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                            <svg class="-ml-0.5 mr-1.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                            </svg>
                            Cari Kelas Baru
                        </a>
                    </div>
                </div>
            @else
                {{-- Grid Cards --}}
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($enrollments as $enrollment)
                        @if($enrollment->enrollable)
                            @php
                                $enrollable = $enrollment->enrollable;
                                $type = $enrollment->enrollable_type;

                                // Default False
                                $isCohort = false;
                                $isSelfPaced = false;
                                $isCourse = false;

                                // Identifikasi Tipe
                                if ($type === 'App\Models\Program') {
                                    $isCohort = $enrollable->type === 'cohort';
                                    $isSelfPaced = $enrollable->type === 'self_paced';
                                } elseif ($type === 'App\Models\Batch') {
                                    if ($enrollable->program_id) {
                                        $isCohort = true;
                                    } else {
                                        $isCourse = true;
                                    }
                                } else {
                                    $isCourse = true;
                                }
                            @endphp

                            <div class="flex flex-col bg-white rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg transition-all duration-300 h-full overflow-hidden group">
                                {{-- Card Image / Header --}}
                                <div class="h-40 w-full relative overflow-hidden bg-gray-200">
                                    <div class="absolute inset-0 bg-gradient-to-br
                                        {{ $isCohort ? 'from-purple-600 to-indigo-700' :
                                           ($isSelfPaced ? 'from-emerald-500 to-teal-600' : 'from-blue-500 to-indigo-600') }}">
                                    </div>

                                    <svg class="absolute inset-0 h-full w-full opacity-10" width="100%" height="100%">
                                        <pattern id="pattern-{{ $enrollment->id }}" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                                            <circle cx="2" cy="2" r="1" class="text-white" fill="currentColor" />
                                        </pattern>
                                        <rect width="100%" height="100%" fill="url(#pattern-{{ $enrollment->id }})" />
                                    </svg>

                                    <div class="absolute top-4 left-4">
                                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-bold ring-1 ring-inset shadow-sm
                                            {{ $isCohort ? 'bg-white text-purple-700 ring-white/20' :
                                               ($isSelfPaced ? 'bg-white text-teal-700 ring-white/20' : 'bg-white/90 text-blue-700 ring-blue-700/10') }}">
                                            @if($isCohort) Program Angkatan
                                            @elseif($isSelfPaced) Program Mandiri
                                            @else Pelatihan
                                            @endif
                                        </span>
                                    </div>

                                    <div class="absolute -bottom-6 -right-6 opacity-20 transform rotate-12 group-hover:scale-110 transition-transform">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-32 w-32 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                                        </svg>
                                    </div>
                                </div>

                                {{-- Card Body --}}
                                <div class="flex flex-1 flex-col p-5">
                                    <h3 class="text-lg font-bold text-gray-900 line-clamp-2 leading-snug mb-2 group-hover:text-blue-600 transition-colors">
                                        {{ $enrollable->title ?? $enrollable->batch_name ?? 'Untitled' }}
                                    </h3>

                                    <div class="flex items-center gap-2 mb-6">
                                        <div class="rounded-full bg-gray-100 p-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <span class="text-xs font-medium text-gray-500 truncate">Instructor Team</span>
                                    </div>

                                    <div class="mt-auto">
                                        <div class="flex justify-between items-end mb-1">
                                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Progress</span>
                                            <span class="text-sm font-bold {{ $enrollment->progress_percentage == 100 ? 'text-green-600' : 'text-blue-600' }}">
                                                {{ number_format($enrollment->progress_percentage, 0) }}%
                                            </span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2 mb-5">
                                            <div class="h-2 rounded-full transition-all duration-500 {{ $enrollment->progress_percentage == 100 ? 'bg-green-500' : 'bg-blue-600' }}"
                                                 style="width: {{ $enrollment->progress_percentage }}%"></div>
                                        </div>

                                        @if($type === 'App\Models\Program' || ($type === 'App\Models\Batch' && $enrollable->program_id))
                                            {{-- Di web.php namanya student.program.detail, bukan show, dan butuh param program, bukan batch --}}
                                            @php
                                                $programId = $type === 'App\Models\Program' ? $enrollable->id : $enrollable->program_id;
                                            @endphp
                                            <a href="{{ route('student.program.detail', ['program' => $programId]) }}" wire:navigate class="flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700 transition-colors">
                                                Lanjutkan Belajar
                                                <svg xmlns="http://www.w3.org/2000/svg" class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                                </svg>
                                            </a>
                                        @else
                                            {{-- Di web.php player butuh parameter 'course', bukan 'id' --}}
                                            <a href="{{ route('student.learning.player', ['course' => $enrollment->enrollable_id]) }}" class="...">
                                                Lanjut Belajar
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $enrollments->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
