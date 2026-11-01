<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Header Greeting --}}
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold text-gray-900">Selamat datang kembali, {{ explode(' ', Auth::user()->name)[0] }}!</h1>
                    {{-- <p class="text-sm text-gray-500">Lanjutkan progres belajarmu hari ini.</p> --}}
                </div>
            </div>

            <div class="mb-8">
                <livewire:dashboard.banner-slider />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                <div class="lg:col-span-8 space-y-8">

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-4">Learning Overview</h3>
                        <livewire:dashboard.stats />
                    </div>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-4">Recent Program Course</h3>
                        <livewire:dashboard.recent />
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-lg text-gray-900">My Program Course</h3>
                            <a href="{{ route('student.my-courses') }}" wire:navigate class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition">View All ></a>
                        </div>
                        <livewire:dashboard.courses />
                    </div>

                     <div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-lg text-gray-900">Recomended for you</h3>
                            <a href="{{ route('student.browse') }}" wire:navigate class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition">Browse Program Courses ></a>
                        </div>
                        <livewire:dashboard.recommended />
                    </div>
                </div>

                <div class="lg:col-span-4 space-y-8">

                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                        <h3 class="font-bold text-lg text-gray-900 mb-4">Assignments</h3>
                        <livewire:dashboard.assignments />
                    </div>

                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                        <h3 class="font-bold text-lg text-gray-900 mb-4">Leaderboard</h3>
                        <livewire:dashboard.leaderboard />
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
