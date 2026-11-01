<div class="max-w-7xl mx-auto py-10 px-4 space-y-6">
    <h1 class="text-2xl font-bold">Penilaian Esai</h1>
    <p class="text-gray-600">Nilai setiap esai dari 0 sampai bobot soal dan sertakan umpan balik. Kelulusan ditentukan setelah seluruh esai dinilai.</p>
    @if(session('grading-status'))
        <p role="status" class="p-4 bg-green-50 text-green-800 rounded-lg">{{ session('grading-status') }}</p>
    @endif
    @error('score') <p role="alert" class="text-red-600">{{ $message }}</p> @enderror
    @error('feedback') <p role="alert" class="text-red-600">{{ $message }}</p> @enderror

    @if($selectedAttempt)
        <section class="bg-white rounded-xl border p-6 space-y-5">
            <h2 class="text-xl font-semibold">{{ $selectedAttempt->quiz->title }} — {{ $selectedAttempt->user?->name ?? 'Pengguna dihapus' }}</h2>
            <p>{{ $selectedAttempt->quiz->course?->title }} · {{ $selectedAttempt->isPendingGrading() ? 'Menunggu penilaian' : 'Nilai akhir: '.number_format($selectedAttempt->score, 2) }}{{ $selectedAttempt->is_timeout ? ' · Waktu habis' : '' }}</p>
            @foreach($selectedAttempt->answers->filter(fn ($answer) => $answer->question?->isEssay()) as $answer)
                <form wire:submit="saveGrade('{{ $answer->id }}')" wire:key="grade-{{ $answer->id }}" class="border rounded-lg p-4 space-y-3">
                    <h3 class="font-semibold">{{ strip_tags($answer->question->question_text) }}</h3>
                    <p class="whitespace-pre-wrap bg-gray-50 p-4 rounded">{{ $answer->essay_answer ?: '(Tidak dijawab)' }}</p>
                    <p class="text-sm text-gray-500">{{ $answer->graded_at ? 'Sudah dinilai' : 'Belum dinilai' }}</p>
                    <label for="score-{{ $answer->id }}" class="block font-medium">Nilai (0–{{ $answer->question->score_weight }})</label>
                    <input id="score-{{ $answer->id }}" wire:model="scores.{{ $answer->id }}" type="number" min="0" max="{{ $answer->question->score_weight }}" step="0.01" required class="rounded-lg border-gray-300" @disabled(!$selectedAttempt->isPendingGrading())>
                    <label for="feedback-{{ $answer->id }}" class="block font-medium">Umpan balik</label>
                    <textarea id="feedback-{{ $answer->id }}" wire:model="feedback.{{ $answer->id }}" rows="3" maxlength="10000" required class="w-full rounded-lg border-gray-300" @disabled(!$selectedAttempt->isPendingGrading())></textarea>
                    @if($selectedAttempt->isPendingGrading())
                        <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-indigo-600 text-white">Simpan Nilai</button>
                    @endif
                </form>
            @endforeach
        </section>
    @endif

    <section class="bg-white rounded-xl border overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="text-left p-4">Siswa / Course / Kuis</th><th class="text-left p-4">Dikumpulkan</th><th class="text-left p-4">Status</th><th class="p-4">Aksi</th></tr></thead>
            <tbody>
                @forelse($attempts as $attempt)
                    <tr wire:key="attempt-{{ $attempt->id }}" class="border-t">
                        <td class="p-4"><p class="font-semibold">{{ $attempt->user?->name ?? 'Pengguna dihapus' }}</p><p>{{ $attempt->quiz->course?->title }} / {{ $attempt->quiz->title }}</p></td>
                        <td class="p-4">{{ ($attempt->submitted_at ?? $attempt->end_time)?->format('d M Y H:i') }}</td>
                        <td class="p-4">{{ $attempt->isPendingGrading() ? 'Menunggu penilaian' : number_format($attempt->score, 2).' / 100' }}</td>
                        <td class="p-4"><a href="{{ url('/grading/essays/'.$attempt->id) }}" wire:navigate class="text-indigo-600 font-semibold">{{ $attempt->isPendingGrading() ? 'Nilai Esai' : 'Lihat Hasil' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-6 text-center text-gray-500">Belum ada kuis esai yang dikumpulkan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    {{ $attempts->links() }}
</div>
