<?php
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Enrollment;
use App\Models\Certificate;

new class extends Component {
    public int $activeCourses = 0;
    public int $completedCourses = 0;
    public int $certificatesCount = 0;
    public int $totalPoints = 1351; // Dummy points sesuai figma (nanti bisa diambil dr DB)

    public function mount()
    {
        $userId = Auth::id();
        $this->activeCourses = Enrollment::where('user_id', $userId)->where('status', 'active')->count();
        $this->completedCourses = Enrollment::where('user_id', $userId)->where('status', 'completed')->count();
        $this->certificatesCount = Certificate::where('user_id', $userId)->count();
    }
}; ?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4">

    {{-- 1. Total Points (Big Card) - Span 2 baris --}}
    <div class="col-span-2 row-span-2 bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex flex-col justify-center items-center text-center relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-10 bg-indigo-50 rounded-full -mr-10 -mt-10 opacity-50 group-hover:scale-110 transition duration-500"></div>

        <div class="w-12 h-12 bg-indigo-600 rounded-2xl rotate-3 flex items-center justify-center mb-3 shadow-lg shadow-indigo-200 z-10">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        </div>
        <p class="text-gray-500 font-bold text-sm z-10">Total Points</p>
        <h2 class="text-5xl font-extrabold text-[#3730A3] mt-2 tracking-tight z-10">{{ number_format($totalPoints, 0, ',', '.') }}</h2>
    </div>

    {{-- 2. Course Complete --}}
    <div class="bg-white p-4 rounded-3xl shadow-sm border border-gray-100 hover:shadow-md transition duration-300">
        <div class="flex items-start gap-3">
            <div class="p-2 bg-blue-50 rounded-xl text-blue-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase leading-tight tracking-wider">Course<br>Complete</p>
                <p class="text-xl font-extrabold text-gray-800 mt-1">{{ $completedCourses }}</p>
            </div>
        </div>
    </div>

    {{-- 3. Course Ongoing --}}
    <div class="bg-white p-4 rounded-3xl shadow-sm border border-gray-100 hover:shadow-md transition duration-300">
        <div class="flex items-start gap-3">
            <div class="p-2 bg-orange-50 rounded-xl text-orange-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase leading-tight tracking-wider">Course<br>Ongoing</p>
                <p class="text-xl font-extrabold text-gray-800 mt-1">{{ $activeCourses }}</p>
            </div>
        </div>
    </div>

    {{-- 4. Certificate Earned --}}
    <div class="bg-white p-4 rounded-3xl shadow-sm border border-gray-100 hover:shadow-md transition duration-300">
        <div class="flex items-start gap-3">
            <div class="p-2 bg-purple-50 rounded-xl text-purple-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138z"></path></svg></div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase leading-tight tracking-wider">Certificate<br>Earned</p>
                <p class="text-xl font-extrabold text-gray-800 mt-1">{{ $certificatesCount }}</p>
            </div>
        </div>
    </div>

    {{-- 5. Task Complete (Dummy) --}}
    <div class="bg-white p-4 rounded-3xl shadow-sm border border-gray-100 hover:shadow-md transition duration-300">
        <div class="flex items-start gap-3">
            <div class="p-2 bg-green-50 rounded-xl text-green-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg></div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase leading-tight tracking-wider">Task<br>Complete</p>
                <p class="text-xl font-extrabold text-gray-800 mt-1">15 <span class="text-xs font-normal text-gray-400">/ 27</span></p>
            </div>
        </div>
    </div>

    {{-- Baris Bawah: Achievement, Hours, Score (Dummy Data utk Visual Figma) --}}
    <div class="col-span-2 md:col-span-4 grid grid-cols-3 gap-4 mt-2">
         <div class="bg-indigo-50/50 p-3 rounded-2xl flex items-center justify-center gap-3 border border-indigo-50">
            <div class="text-center">
                 <p class="text-[10px] font-bold text-gray-500 uppercase">Total Achievement</p>
                 <p class="text-lg font-bold text-indigo-900">12 <span class="text-[10px] font-normal text-gray-400">/ 18</span></p>
            </div>
         </div>
         <div class="bg-indigo-50/50 p-3 rounded-2xl flex items-center justify-center gap-3 border border-indigo-50">
             <div class="text-center">
                 <p class="text-[10px] font-bold text-gray-500 uppercase">Spend Hours</p>
                 <p class="text-lg font-bold text-indigo-900">22 <span class="text-[10px] font-normal text-gray-400">Hours</span></p>
            </div>
         </div>
         <div class="bg-indigo-50/50 p-3 rounded-2xl flex items-center justify-center gap-3 border border-indigo-50">
             <div class="text-center">
                 <p class="text-[10px] font-bold text-gray-500 uppercase">Avg. Score</p>
                 <p class="text-lg font-bold text-indigo-900">96.3</p>
            </div>
         </div>
    </div>
</div>
