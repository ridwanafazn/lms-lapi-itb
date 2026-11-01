<div class="min-h-screen bg-[#F4F7F9] font-sans text-slate-800 flex flex-col">

    {{-- TOP HEADER (Sesuai Mockup) --}}
    <header class="bg-white border-b border-slate-200 h-20 flex items-center justify-between px-6 lg:px-12 shadow-sm shrink-0 z-50">
        <div class="flex items-center gap-4">
            <h1 class="text-xl font-bold text-slate-800 truncate max-w-sm lg:max-w-xl">
                {{ $course->title ?? 'Course Title' }}
            </h1>
        </div>

        @if(!$isFinished)
            <div class="hidden sm:flex items-center gap-4 w-72">
                <div class="flex-1">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs font-bold text-slate-500">Answered {{ $answeredCount }} of {{ $totalQuestions }} Question</span>
                        <span class="text-xs font-bold text-blue-600">{{ round($progressPercent) }}%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" style="width: {{ $progressPercent }}%"></div>
                    </div>
                </div>
            </div>
        @endif

        <button wire:click="backToCourse" class="shrink-0 ml-4 lg:ml-8 px-4 py-2 text-sm font-bold text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-colors">
            Tutup Kuis
        </button>
    </header>

    {{-- MAIN AREA --}}
    <main class="flex-1 w-full max-w-7xl mx-auto p-4 lg:p-8 flex flex-col lg:flex-row gap-6 relative">

        @if($isFinished)
            {{-- RESULT CARD --}}
            <div class="w-full flex items-center justify-center p-6">
                <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 max-w-lg w-full p-8 sm:p-12 text-center">

                    @if($isPendingGrading)
                        <h2 class="text-3xl font-black text-slate-800 mb-2">Menunggu Penilaian Esai</h2>
                        <p class="text-slate-500 mb-8">Kuis sudah dikumpulkan. Nilai akhir dan kelulusan tersedia setelah seluruh esai dinilai.</p>
                    @elseif($isPassed)
                        <div class="w-24 h-24 bg-[#E5F0FF] rounded-full flex items-center justify-center mx-auto mb-6 text-blue-600">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <h2 class="text-3xl font-black text-slate-800 mb-2">Lulus Kuis!</h2>
                        <p class="text-slate-500 font-medium mb-8">Selamat, Anda mencapai batas nilai minimal ({{ $passingGrade }}).</p>
                    @else
                        <div class="w-24 h-24 bg-rose-50 rounded-full flex items-center justify-center mx-auto mb-6 text-rose-500">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </div>
                        <h2 class="text-3xl font-black text-slate-800 mb-2">Belum Lulus</h2>
                        <p class="text-slate-500 font-medium mb-8">Nilai Anda belum mencapai batas minimal ({{ $passingGrade }}).</p>
                    @endif

                    <div class="bg-[#F4F7F9] rounded-2xl p-6 mb-8 border border-slate-100">
                        <p class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-1">Skor Akhir</p>
                        <p class="text-6xl font-black {{ $isPassed ? 'text-blue-600' : 'text-rose-500' }}">
                            {{ $finalScore === null ? '—' : number_format($finalScore, 2) }}
                        </p>
                    </div>

                    @foreach($essayResults as $result)
                        <div class="text-left border rounded-xl p-4 mb-4" wire:key="essay-result-{{ $result->id }}">
                            <p class="font-semibold">{{ strip_tags($result->question->question_text) }}</p>
                            <p class="whitespace-pre-wrap text-sm my-2">{{ $result->essay_answer ?: '(Tidak dijawab)' }}</p>
                            <p class="text-sm">Nilai: {{ $result->graded_at ? $result->score . ' / ' . $result->question->score_weight : 'Menunggu penilaian' }}</p>
                            @if($result->instructor_feedback)
                                <p class="whitespace-pre-wrap text-sm mt-2">Umpan balik: {{ $result->instructor_feedback }}</p>
                            @endif
                        </div>
                    @endforeach

                    <div class="flex flex-col sm:flex-row gap-3">
                        @if($attemptsExhausted)
                            <p class="flex-1 px-6 py-4 bg-rose-50 text-rose-600 font-bold rounded-xl">
                                Batas percobaan kuis sudah habis.
                            </p>
                        @elseif(!$isPassed)
                            <button wire:click="retakeQuiz" class="flex-1 px-6 py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-all shadow-sm">
                                Ulangi Kuis{{ $remainingAttempts !== null ? ' (' . $remainingAttempts . 'x tersisa)' : '' }}
                            </button>
                        @endif
                        <button wire:click="backToCourse" class="flex-1 px-6 py-4 bg-white border-2 border-slate-200 hover:border-slate-300 text-slate-700 font-bold rounded-xl transition-all">
                            Kembali ke Kelas
                        </button>
                    </div>
                </div>
            </div>

        @elseif($currentQuestion)
            {{-- ACTIVE QUIZ INTERFACE (Sesuai Mockup CBT) --}}

            {{-- Kiri: Area Soal --}}
            {{-- Tambahkan x-data untuk Font Level dengan LocalStorage persistence --}}
            <div class="flex-1 bg-white rounded-3xl shadow-sm border border-slate-200 p-6 lg:p-10 flex flex-col min-w-0"
                 x-data="{
                     fontLevel: localStorage.getItem('quizFontLevel') ? parseInt(localStorage.getItem('quizFontLevel')) : 1
                 }"
                 x-init="$watch('fontLevel', val => localStorage.setItem('quizFontLevel', val))">

                {{-- Question Meta Bar --}}
                <div class="flex items-center justify-between mb-8 pb-4 border-b border-slate-100">
                    <span class="bg-[#E5F0FF] text-blue-700 px-4 py-1.5 rounded-full text-xs font-extrabold tracking-wide uppercase">
                        QUESTION {{ $currentQuestionIndex + 1 }} OF {{ $totalQuestions }}
                    </span>

                    {{-- Font Resizer Functional --}}
                    <div class="hidden sm:flex items-center bg-slate-50 rounded-lg border border-slate-200 p-1 select-none">
                        <button @click="if(fontLevel > 0) fontLevel--"
                                class="px-3 text-sm font-bold transition-colors focus:outline-none"
                                :class="fontLevel === 0 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-500 hover:text-slate-800'">A-</button>

                        <div class="w-px h-4 bg-slate-300"></div>

                        <button @click="fontLevel = 1"
                                class="px-3 text-sm font-bold transition-colors focus:outline-none"
                                :class="fontLevel === 1 ? 'text-blue-600' : 'text-slate-500 hover:text-slate-800'">A</button>

                        <div class="w-px h-4 bg-slate-300"></div>

                        <button @click="if(fontLevel < 3) fontLevel++"
                                class="px-3 text-sm font-bold transition-colors focus:outline-none"
                                :class="fontLevel === 3 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-500 hover:text-slate-800'">A+</button>
                    </div>
                </div>

                {{-- Question Text --}}
                <div class="mb-8">
                    {{-- Binding dynamic class berdasarkan fontLevel --}}
                    <div class="font-bold text-slate-800 leading-snug mb-2 transition-all prose-slate max-w-none"
                         :class="{
                             'prose prose-base': fontLevel === 0,
                             'prose prose-lg': fontLevel === 1,
                             'prose prose-xl': fontLevel === 2,
                             'prose prose-2xl': fontLevel === 3
                         }">
                        {!! $currentQuestion->question ?? $currentQuestion->question_text ?? $currentQuestion->title !!}
                    </div>

                    @if($isMultipleChoice)
                        <p class="text-sm text-slate-500 font-medium mt-2">(Select all that apply)</p>
                    @endif
                </div>

                {{-- Options List --}}
                <div class="space-y-4 mb-12">
                    @if($currentQuestion->isEssay())
                        <label for="essay-{{ $currentQuestion->id }}" class="block text-sm font-semibold">Jawaban esai (maksimal {{ $currentQuestion->score_weight }} poin)</label>
                        <textarea id="essay-{{ $currentQuestion->id }}" wire:key="essay-{{ $currentQuestion->id }}"
                            wire:model.live.debounce.500ms="answers.{{ $currentQuestion->id }}" maxlength="50000" rows="10"
                            class="w-full rounded-xl border-slate-300" placeholder="Tulis jawaban Anda..."></textarea>
                        <p class="text-sm text-slate-500" wire:loading> Menyimpan jawaban...</p>
                        @error('essayAnswer') <p class="text-red-600">{{ $message }}</p> @enderror
                    @else
                    @foreach($currentQuestion->options as $option)
                        @php
                            $ans = $answers[$currentQuestion->id] ?? null;
                            if ($isMultipleChoice) {
                                $isSelected = is_array($ans) && in_array($option->id, $ans);
                            } else {
                                $isSelected = $ans == $option->id;
                            }
                        @endphp

                        <label wire:click="selectAnswer('{{ $currentQuestion->id }}', '{{ $option->id }}')"
                               class="relative flex cursor-pointer rounded-2xl border-2 p-5 transition-all items-center
                               {{ $isSelected ? 'bg-[#E5F0FF] border-blue-400' : 'bg-white border-slate-200 hover:border-blue-200' }}">

                            <div class="shrink-0 mr-5">
                                @if($isMultipleChoice)
                                    <div class="w-6 h-6 rounded flex items-center justify-center transition-colors {{ $isSelected ? 'bg-blue-600 border-blue-600' : 'border-2 border-slate-300 bg-white' }}">
                                        @if($isSelected)
                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                        @endif
                                    </div>
                                @else
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center transition-colors {{ $isSelected ? 'border-[6px] border-blue-600 bg-white' : 'border-2 border-slate-300 bg-white' }}">
                                    </div>
                                @endif
                            </div>

                            {{-- Teks Opsi Jawaban Terpengaruh fontLevel --}}
                            <div class="flex-1 text-slate-700 font-semibold transition-all"
                                 :class="{
                                     'text-sm': fontLevel === 0,
                                     'text-base': fontLevel === 1,
                                     'text-lg': fontLevel === 2,
                                     'text-xl': fontLevel === 3
                                 }">
                                {!! $option->option_text ?? $option->text ?? $option->title !!}
                            </div>
                        </label>
                    @endforeach
                    @endif
                </div>

                {{-- Bottom Navigation Controls (Tetap Sama) --}}
                <div class="mt-auto pt-6 flex flex-col-reverse sm:flex-row items-center justify-between gap-4 shrink-0 border-t border-slate-100">
                    <button wire:click="prevQuestion"
                            class="w-full sm:w-auto px-6 py-3 font-bold rounded-xl transition-all border-2 flex items-center justify-center gap-2
                            {{ $currentQuestionIndex > 0 ? 'bg-white text-blue-600 border-blue-100 hover:bg-blue-50' : 'opacity-0 pointer-events-none' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Previous Question
                    </button>

                    <label class="flex items-center gap-2 cursor-pointer text-slate-600 font-bold text-sm select-none">
                        <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-amber-400 focus:ring-amber-400"
                               wire:click="toggleFlag('{{ $currentQuestion->id }}')"
                               @if(isset($flaggedQuestions[$currentQuestion->id])) checked @endif>
                        Tandai Ragu-ragu
                    </label>

                    @if($currentQuestionIndex < $totalQuestions - 1)
                        <button wire:click="nextQuestion" class="w-full sm:w-auto px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-all flex items-center justify-center gap-2 shadow-sm">
                            Next Question
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    @else
                        <button wire:click="submitQuiz" onclick="return confirm('Yakin ingin mengumpulkan kuis ini?')" class="w-full sm:w-auto px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-all shadow-sm">
                            Submit Quiz
                        </button>
                    @endif
                </div>
            </div>

            {{-- Kanan: Panel Timer & Navigasi Grid --}}
            <aside class="w-full lg:w-[340px] flex flex-col gap-6 shrink-0 z-10">

                {{-- Timer Card --}}
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
                    <div class="flex items-center gap-2 text-blue-600 font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="text-sm tracking-wider uppercase">Time Left</span>
                    </div>
                    <div x-data="{ time: @entangle('timeRemaining') }"
                         x-init="if (@js((int) $quiz->duration_minutes > 0)) { const timer = setInterval(() => { if(time > 0) time--; else { clearInterval(timer); $wire.submitQuiz(true); } }, 1000); }"
                         class="text-2xl font-black text-slate-800 font-mono">
                        <span x-text="Math.floor(time / 60).toString().padStart(2, '0') + ' : ' + (time % 60).toString().padStart(2, '0')"></span>
                    </div>
                </div>

                {{-- Navigator Grid Card --}}
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 flex-1">
                    <h3 class="text-sm font-bold text-slate-800 mb-4">Question Navigator</h3>

                    {{-- Grid 5 Kolom --}}
                    <div class="grid grid-cols-5 gap-3 mb-8">
                        @foreach($questions as $index => $q)
                            @php
                                $isAnswered = isset($answers[$q->id]) && (!is_array($answers[$q->id]) || count($answers[$q->id]) > 0);
                                $isCurrent = $currentQuestionIndex === $index;
                                $isFlagged = isset($flaggedQuestions[$q->id]);

                                // Penentuan Class Berdasarkan Prioritas Sesuai Mockup
                                if ($isCurrent) {
                                    $btnClass = 'bg-blue-600 text-white border-blue-600 shadow-md';
                                } elseif ($isFlagged) {
                                    $btnClass = 'bg-[#FFF3CD] text-[#856404] border-[#FFE8A1]';
                                } elseif ($isAnswered) {
                                    $btnClass = 'bg-[#E5F0FF] text-blue-700 border-[#CCE0FF]';
                                } else {
                                    $btnClass = 'bg-white text-slate-500 border-slate-200 hover:border-slate-300';
                                }
                            @endphp

                            <button wire:click="jumpToQuestion({{ $index }})"
                                    class="h-11 w-full rounded-xl flex items-center justify-center text-sm font-bold transition-all border-2 {{ $btnClass }}">
                                {{ $index + 1 }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Legend / Keterangan Warna --}}
                    <div class="space-y-3 pt-6 border-t border-slate-100">
                        <div class="flex items-center gap-3 text-sm font-medium text-slate-500">
                            <div class="w-4 h-4 rounded-full bg-blue-600"></div> Current Question
                        </div>
                        <div class="flex items-center gap-3 text-sm font-medium text-slate-500">
                            <div class="w-4 h-4 rounded-full bg-[#E5F0FF] border border-[#CCE0FF]"></div> Answered
                        </div>
                        <div class="flex items-center gap-3 text-sm font-medium text-slate-500">
                            <div class="w-4 h-4 rounded-full bg-white border-2 border-slate-200"></div> Unanswered
                        </div>
                        <div class="flex items-center gap-3 text-sm font-medium text-slate-500">
                            <div class="w-4 h-4 rounded-full bg-[#FFF3CD]"></div> Ragu-ragu
                        </div>
                    </div>
                </div>
            </aside>
        @endif

    </main>
</div>
