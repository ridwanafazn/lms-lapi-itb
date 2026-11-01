<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public Quiz $quiz;
    public $activeTab = 'questions';

    // Quiz Settings
    public $title;
    public $duration_minutes;
    public $passing_grade;
    public $is_randomized;
    public $max_attempts;

    // State
    public $questionsState = [];
    public $deletedQuestions = [];
    public $deletedOptions = [];

    public function mount(Quiz $quiz)
    {
        $this->quiz = $quiz;
        $this->title = $quiz->title;
        $this->duration_minutes = $quiz->duration_minutes;
        $this->passing_grade = $quiz->passing_grade;
        $this->is_randomized = (bool) $quiz->is_randomized;
        $this->max_attempts = $quiz->max_attempts;

        $this->loadQuestions();
    }

    public function loadQuestions()
    {
        $questions = $this->quiz->questions()->with('options')->orderBy('created_at')->get();

        foreach ($questions as $index => $q) {
            $options = [];
            foreach ($q->options as $opt) {
                $options[] = [
                    'id' => $opt->id,
                    'option_text' => $opt->option_text,
                    'option_image_url' => $opt->option_image_url,
                    'new_image' => null,
                    'is_correct' => (bool) $opt->is_correct,
                ];
            }

            $this->questionsState[] = [
                'id' => $q->id,
                'temp_id' => (string) Str::uuid(),
                'question_text' => $q->question_text,
                'question_image_url' => $q->question_image_url,
                'new_image' => null,
                'score' => $q->score_weight,
                'options' => $options,
                'is_expanded' => $index === 0 // Open first by default
            ];
        }

        if (empty($this->questionsState)) {
            $this->addQuestion();
        }
    }

    public function expandQuestion($index)
    {
        // Collapse all others
        foreach ($this->questionsState as $key => $q) {
            $this->questionsState[$key]['is_expanded'] = ($key === $index);
        }
    }

    public function addQuestion()
    {
        // Collapse existing
        foreach ($this->questionsState as $key => $q) {
            $this->questionsState[$key]['is_expanded'] = false;
        }

        $this->questionsState[] = [
            'id' => null,
            'temp_id' => (string) Str::uuid(),
            'question_text' => '',
            'question_image_url' => null,
            'new_image' => null,
            'score' => 10,
            'options' => [
                ['id' => null, 'option_text' => '', 'option_image_url' => null, 'new_image' => null, 'is_correct' => false],
                ['id' => null, 'option_text' => '', 'option_image_url' => null, 'new_image' => null, 'is_correct' => false],
            ],
            'is_expanded' => true
        ];
        $this->activeTab = 'questions';

        // Dispatch event to scroll to bottom or focus
        $this->dispatch('question-added');
    }

    public function removeQuestion($index)
    {
        if ($this->questionsState[$index]['id']) {
            $this->deletedQuestions[] = $this->questionsState[$index]['id'];
        }
        unset($this->questionsState[$index]);
        $this->questionsState = array_values($this->questionsState);
    }

    public function addOption($qIndex)
    {
        $this->questionsState[$qIndex]['options'][] = [
            'id' => null,
            'option_text' => '',
            'option_image_url' => null,
            'new_image' => null,
            'is_correct' => false
        ];
    }

    public function removeOption($qIndex, $oIndex)
    {
        if (isset($this->questionsState[$qIndex]['options'][$oIndex]['id'])) {
            $this->deletedOptions[] = $this->questionsState[$qIndex]['options'][$oIndex]['id'];
        }
        unset($this->questionsState[$qIndex]['options'][$oIndex]);
        $this->questionsState[$qIndex]['options'] = array_values($this->questionsState[$qIndex]['options']);
    }

    public function toggleCorrectOption($qIndex, $oIndex)
    {
        $current = $this->questionsState[$qIndex]['options'][$oIndex]['is_correct'];
        $this->questionsState[$qIndex]['options'][$oIndex]['is_correct'] = !$current;
    }

    public function deleteQuestionImage($qIndex)
    {
        $this->questionsState[$qIndex]['question_image_url'] = null;
        $this->questionsState[$qIndex]['new_image'] = null;
    }

    public function deleteOptionImage($qIndex, $oIndex)
    {
        $this->questionsState[$qIndex]['options'][$oIndex]['option_image_url'] = null;
        $this->questionsState[$qIndex]['options'][$oIndex]['new_image'] = null;
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|min:3',
            'duration_minutes' => 'required|integer|min:1',
            'passing_grade' => 'required|numeric|min:0|max:100',
            'questionsState.*.question_text' => 'required_without:questionsState.*.new_image',
            'questionsState.*.options' => 'array|min:2',
            'questionsState.*.options.*.option_text' => 'required_without:questionsState.*.options.*.new_image',
        ]);

        DB::transaction(function () {
            $this->quiz->update([
                'title' => $this->title,
                'duration_minutes' => $this->duration_minutes,
                'passing_grade' => $this->passing_grade,
                'is_randomized' => $this->is_randomized,
                'max_attempts' => $this->max_attempts,
            ]);

            Question::destroy($this->deletedQuestions);
            QuestionOption::destroy($this->deletedOptions);

            foreach ($this->questionsState as $index => $qData) {
                // Update Image
                $qImageUrl = $qData['question_image_url'];
                if (isset($qData['new_image']) && $qData['new_image']) {
                    $qImageUrl = $qData['new_image']->store('quiz-images', 'public');
                }

                $question = Question::updateOrCreate(
                    ['id' => $qData['id'] ?? null],
                    [
                        'quiz_id' => $this->quiz->id,
                        'question_text' => $qData['question_text'] ?? '',
                        'question_image_url' => $qImageUrl,
                        'score_weight' => 1,
                        // Ensure order is saved (using creation time trick or add order column in migration)
                        'created_at' => now()->addSeconds($index),
                    ]
                );

                foreach ($qData['options'] as $optData) {
                    $optImageUrl = $optData['option_image_url'];
                    if (isset($optData['new_image']) && $optData['new_image']) {
                        $optImageUrl = $optData['new_image']->store('quiz-images', 'public');
                    }

                    QuestionOption::updateOrCreate(
                        ['id' => $optData['id'] ?? null],
                        [
                            'question_id' => $question->id,
                            'option_text' => $optData['option_text'] ?? '',
                            'option_image_url' => $optImageUrl,
                            'is_correct' => $optData['is_correct']
                        ]
                    );
                }
            }
        });

        $this->deletedQuestions = [];
        $this->deletedOptions = [];
        $this->questionsState = [];
        $this->loadQuestions();

        session()->flash('message', 'Kuis berhasil disimpan!');
        $this->dispatch('saved-success');
    }
}; ?>

<div class="min-h-screen bg-gray-50/50 font-sans text-gray-900 pb-20">

    {{-- STICKY HEADER --}}
    <div class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">
                <div class="flex items-center gap-3">
                    <a href="{{ $quiz->course_id ? route('instructor.course.builder', $quiz->course_id) : route('instructor.programs') }}"
                       class="text-gray-400 hover:text-indigo-600 transition-colors p-1.5 rounded-lg hover:bg-gray-50">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </a>
                    <div class="flex flex-col">
                        <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Quiz Editor</span>
                        <h1 class="text-sm font-bold text-gray-800 truncate max-w-xs">{{ $this->title ?: 'Judul Kuis' }}</h1>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div x-data="{ show: false }" x-on:saved-success.window="show = true; setTimeout(() => show = false, 2500)" x-show="show" x-transition
                         class="hidden sm:flex items-center text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-md border border-emerald-100">
                        <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Tersimpan
                    </div>
                    <button wire:click="save" wire:loading.attr="disabled" class="inline-flex items-center justify-center px-4 py-1.5 text-xs font-bold text-white transition-all bg-indigo-600 border border-transparent rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 disabled:opacity-50">
                        <span wire:loading.remove wire:target="save">Simpan Perubahan</span>
                        <span wire:loading wire:target="save" class="flex items-center gap-1"><svg class="w-3 h-3 animate-spin" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Saving</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            {{-- LEFT COLUMN: Questions List (8 cols) --}}
            <div class="lg:col-span-8 space-y-4">

                {{-- QUIZ INFO CARD --}}
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div class="w-full">
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Judul Kuis</label>
                            <input type="text" wire:model.blur="title" class="block w-full border-0 border-b border-gray-200 p-0 text-lg font-bold text-gray-900 focus:ring-0 focus:border-indigo-500 placeholder-gray-300" placeholder="Masukkan judul kuis...">
                        </div>
                        <button wire:click="$set('activeTab', 'settings')" class="ml-4 p-2 text-gray-400 hover:text-indigo-600 bg-gray-50 hover:bg-indigo-50 rounded-lg transition-colors" title="Pengaturan Lanjutan">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </button>
                    </div>
                </div>

                {{-- QUESTIONS LOOP --}}
                <div class="space-y-4">
                    @foreach($questionsState as $index => $question)
                        <div id="q-{{ $index }}" wire:key="q-{{ $question['temp_id'] }}"
                             class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden scroll-mt-24 transition-all {{ $question['is_expanded'] ? 'ring-2 ring-indigo-500/20' : 'hover:border-indigo-300' }}">

                            {{-- Header (Click to Expand) --}}
                            <div class="px-4 py-3 flex items-center justify-between cursor-pointer {{ $question['is_expanded'] ? 'bg-indigo-50/30 border-b border-indigo-100' : 'hover:bg-gray-50' }}"
                                 wire:click="expandQuestion({{ $index }})">
                                <div class="flex items-center gap-3 overflow-hidden">
                                    <span class="flex-shrink-0 flex items-center justify-center w-6 h-6 rounded-md bg-gray-900 text-white text-xs font-bold">
                                        {{ $loop->iteration }}
                                    </span>
                                    <div class="truncate text-sm font-medium text-gray-700">
                                        {{ $question['question_text'] ?: 'Pertanyaan belum diisi...' }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button wire:click.stop="removeQuestion({{ $index }})" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 {{ $question['is_expanded'] ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>

                            {{-- Expanded Body --}}
                            <div x-show="$wire.questionsState[{{ $index }}]['is_expanded']" x-collapse>
                                <div class="p-5 space-y-6">

                                    {{-- Question Text & Image --}}
                                    <div class="flex flex-col md:flex-row gap-4">
                                        <div class="flex-1 space-y-2">
                                            <div class="flex justify-between">
                                                <label class="text-xs font-bold text-gray-500 uppercase">Pertanyaan</label>
                                            </div>
                                            <textarea wire:model.blur="questionsState.{{ $index }}.question_text" rows="3" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm resize-y" placeholder="Tulis pertanyaan di sini..."></textarea>
                                            @error("questionsState.{$index}.question_text") <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                        </div>

                                        {{-- Question Image --}}
                                        <div class="w-full md:w-1/3 space-y-2">
                                            <label class="text-xs font-bold text-gray-500 uppercase flex justify-between">
                                                Gambar Soal
                                                @if($question['question_image_url'] || $question['new_image'])
                                                    <span wire:click="deleteQuestionImage({{ $index }})" class="text-red-500 cursor-pointer hover:underline text-[10px]">Hapus</span>
                                                @endif
                                            </label>
                                            <div class="relative group">
                                                @if($question['new_image'])
                                                    <img src="{{ $question['new_image']->temporaryUrl() }}" class="w-full h-32 object-cover rounded-lg border border-gray-200 bg-gray-50">
                                                @elseif($question['question_image_url'])
                                                    <img src="{{ Storage::url($question['question_image_url']) }}" class="w-full h-32 object-cover rounded-lg border border-gray-200 bg-gray-50">
                                                @else
                                                    <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 hover:border-indigo-400 transition-colors">
                                                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                        <span class="text-xs text-gray-500 mt-1 font-medium">Upload</span>
                                                        <input type="file" wire:model="questionsState.{{ $index }}.new_image" accept="image/*" class="hidden">
                                                    </label>
                                                @endif

                                                {{-- Overlay Change Image --}}
                                                @if($question['question_image_url'] || $question['new_image'])
                                                    <label class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-lg cursor-pointer">
                                                        <span class="text-white text-xs font-bold">Ganti</span>
                                                        <input type="file" wire:model="questionsState.{{ $index }}.new_image" accept="image/*" class="hidden">
                                                    </label>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Options Grid --}}
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                                            <label class="text-xs font-bold text-gray-500 uppercase">Jawaban <span class="normal-case font-normal text-gray-400 ml-1">(Centang yang benar)</span></label>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            @foreach($question['options'] as $optIndex => $option)
                                                <div class="flex gap-3 p-2 rounded-lg border transition-all duration-200 {{ $option['is_correct'] ? 'border-green-400 bg-green-50/40 ring-1 ring-green-400' : 'border-gray-200 bg-white hover:border-gray-300' }}" wire:key="opt-{{ $index }}-{{ $optIndex }}">

                                                    {{-- Checkbox --}}
                                                    <div class="pt-1">
                                                        <input type="checkbox" wire:click="toggleCorrectOption({{ $index }}, {{ $optIndex }})" @if($option['is_correct']) checked @endif class="w-5 h-5 rounded border-gray-300 text-green-600 focus:ring-green-500 cursor-pointer">
                                                    </div>

                                                    <div class="flex-1 min-w-0 space-y-2">
                                                        {{-- Option Image (Small & Compact) --}}
                                                        @if($option['new_image'] || $option['option_image_url'])
                                                            <div class="relative group/optimg w-full h-24 bg-gray-100 rounded-md overflow-hidden border border-gray-100">
                                                                @if($option['new_image'])
                                                                    <img src="{{ $option['new_image']->temporaryUrl() }}" class="w-full h-full object-contain">
                                                                @else
                                                                    <img src="{{ Storage::url($option['option_image_url']) }}" class="w-full h-full object-contain">
                                                                @endif
                                                                <button wire:click="deleteOptionImage({{ $index }}, {{ $optIndex }})" class="absolute top-1 right-1 bg-white/80 text-red-500 p-0.5 rounded shadow hover:bg-white opacity-0 group-hover/optimg:opacity-100 transition-opacity">
                                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                </button>
                                                            </div>
                                                        @endif

                                                        {{-- Option Text Input --}}
                                                        <div class="flex items-center gap-2">
                                                            <input type="text" wire:model.blur="questionsState.{{ $index }}.options.{{ $optIndex }}.option_text"
                                                                   class="block w-full border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 bg-transparent text-sm p-0 placeholder-gray-400 {{ $option['is_correct'] ? 'text-green-800 font-medium' : 'text-gray-700' }}"
                                                                   placeholder="Teks jawaban...">

                                                            {{-- Upload Icon --}}
                                                            <label class="text-gray-400 hover:text-indigo-600 cursor-pointer" title="Upload Gambar">
                                                                <input type="file" wire:model="questionsState.{{ $index }}.options.{{ $optIndex }}.new_image" accept="image/*" class="hidden">
                                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                            </label>
                                                            {{-- Delete Option --}}
                                                            <button wire:click="removeOption({{ $index }}, {{ $optIndex }})" class="text-gray-300 hover:text-red-500 transition-colors">
                                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                            </button>
                                                        </div>
                                                        @error("questionsState.{$index}.options.{$optIndex}.option_text") <p class="text-[10px] text-red-500">{{ $message }}</p> @enderror
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                        @error("questionsState.{$index}.options") <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                                        <button wire:click="addOption({{ $index }})" class="mt-2 text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 transition-colors">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            Tambah Opsi
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Add Button --}}
                <button wire:click="addQuestion" class="w-full py-4 border-2 border-dashed border-gray-300 rounded-xl flex items-center justify-center text-gray-400 hover:border-indigo-500 hover:text-indigo-600 hover:bg-white transition-all text-sm font-bold gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tambah Soal Baru
                </button>
            </div>

            {{-- RIGHT COLUMN: Navigation & Settings (4 cols) --}}
            <div class="lg:col-span-4 space-y-6 lg:sticky lg:top-24 h-fit overflow-y-auto max-h-[calc(100vh-100px)] custom-scrollbar">

                {{-- NAVIGATOR --}}
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
                    <h3 class="text-xs font-bold text-gray-400 uppercase mb-3">Navigasi Soal</h3>
                    <div class="grid grid-cols-5 gap-2">
                        @foreach($questionsState as $index => $q)
                            <a href="#q-{{ $index }}"
                               wire:click.prevent="expandQuestion({{ $index }})"
                               class="flex items-center justify-center w-full aspect-square rounded-lg text-xs font-bold transition-all {{ $q['is_expanded'] ? 'bg-indigo-600 text-white shadow-md scale-105' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-indigo-600' }}">
                                {{ $loop->iteration }}
                            </a>
                        @endforeach
                        <button wire:click="addQuestion" class="flex items-center justify-center w-full aspect-square rounded-lg border-2 border-dashed border-gray-200 text-gray-400 hover:border-indigo-400 hover:text-indigo-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                    </div>
                </div>

                {{-- SETTINGS (Sidebar Version) --}}
                @if($activeTab === 'settings')
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-5 animate-fade-in-down">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Durasi (Menit)</label>
                        <input type="number" wire:model="duration_minutes" class="block w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">KKM (0-100)</label>
                        <input type="number" wire:model="passing_grade" class="block w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Kesempatan (0=Unlimited)</label>
                        <input type="number" wire:model="max_attempts" class="block w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <label class="flex items-center gap-3 p-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" wire:model="is_randomized" class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500">
                        <span class="text-xs font-bold text-gray-700">Acak Urutan Soal</span>
                    </label>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
