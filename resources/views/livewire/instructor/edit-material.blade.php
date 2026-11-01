<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\Material;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public Material $material;

    public $type;
    public $title;
    public $body_text;
    public $content_url;
    public $status;

    // File Upload Property
    public $file_upload;

    // Live Session specific
    public $zoom_url;
    public $start_time;
    public $duration;
    public $meeting_id;
    public $meeting_passcode;
    public $meeting_platform = 'zoom';

    public function mount(Material $material)
    {
        $this->material = $material;
        $this->title = $material->title;
        $this->type = $material->type;
        $this->status = $material->status ?? 'draft';
        $this->body_text = $material->body_text;
        $this->content_url = $material->content_url;

        $this->zoom_url = $material->zoom_url;
        $this->start_time = $material->start_time ? $material->start_time->format('Y-m-d\TH:i') : null;
        $this->duration = $material->duration;
        $this->meeting_id = $material->meeting_id;
        $this->meeting_passcode = $material->meeting_passcode;
        $this->meeting_platform = $material->meeting_platform ?? 'zoom';
    }

    public function toggleStatus()
    {
        $this->status = $this->status === 'published' ? 'draft' : 'published';
        $this->material->update(['status' => $this->status]);
        $this->dispatch('message', text: 'Status diubah menjadi ' . strtoupper($this->status));
    }

    public function save()
    {
        $rules = [
            'title' => 'required|min:3|max:255',
        ];

        // Validasi Dinamis
        if ($this->type === 'video') {
            $rules['content_url'] = 'required|url';
        } elseif ($this->type === 'live_session') {
            $rules['start_time'] = 'required|date';
            $rules['duration'] = 'nullable|integer';

            // Zoom URL hanya diwajibkan jika BUKAN auto-generate zoom
            if ($this->meeting_platform !== 'zoom') {
                $rules['zoom_url'] = 'required|url';
            }
        } elseif (in_array($this->type, ['google_drive_video', 'google_drive_file'])) {
            if (!$this->content_url && !$this->file_upload) {
                $this->addError('file_upload', 'File wajib diunggah.');
                return;
            }
        }

        $this->validate($rules, [
            'title.required' => 'Judul materi wajib diisi.',
            'content_url.required' => 'Link URL wajib diisi.',
            'content_url.url' => 'Format URL tidak valid.',
            'zoom_url.required' => 'Link Zoom/Meeting wajib diisi.',
            'start_time.required' => 'Waktu mulai wajib diisi.'
        ]);

        // LOGIKA UPLOAD KE GOOGLE DRIVE
        if ($this->file_upload && in_array($this->type, ['google_drive_video', 'google_drive_file'])) {
            try {
                $path = $this->file_upload->store('/', 'google');
                $this->content_url = Storage::disk('google')->url($path);
                $this->file_upload = null;
            } catch (\Exception $e) {
                $this->addError('file_upload', 'Gagal upload ke Cloud. Pastikan API GDrive sudah disetup.');
                return;
            }
        }

        // Persiapkan data untuk disimpan
        $data = [
            'title' => $this->title,
            'body_text' => $this->body_text,
            'content_url' => $this->content_url,
            'zoom_url' => $this->zoom_url,
            'start_time' => $this->start_time,
            'duration' => $this->duration,
            'meeting_id' => $this->meeting_id,
            'meeting_passcode' => $this->meeting_passcode,
            'meeting_platform' => $this->meeting_platform,
            'status' => $this->status,
        ];

        $this->material->update($data);

        $this->dispatch('message', text: 'Materi berhasil disimpan!');
    }

    public function getTypeIcon()
    {
        return match($this->type) {
            'article', 'text' => '📄',
            'video' => '▶️',
            'google_drive_video' => '☁️',
            'google_drive_file' => '📂',
            'live_session' => '🎥',
            default => '📚'
        };
    }

    public function getTypeLabel()
    {
        return match($this->type) {
            'article', 'text' => 'Artikel / Teks',
            'video' => 'Video Youtube',
            'google_drive_video' => 'Video Google Drive',
            'google_drive_file' => 'File Google Drive',
            'live_session' => 'Sesi Live Meeting',
            default => 'Materi'
        };
    }
}; ?>

<div class="min-h-screen bg-slate-50 pb-24 font-sans text-slate-900 selection:bg-indigo-100 selection:text-indigo-900">

    {{-- TOAST NOTIFICATION --}}
    <div x-data="{ show: false, message: '' }"
         x-show="show"
         x-transition:enter="transform ease-out duration-300 transition"
         x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
         x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @message.window="message = $event.detail.text; show = true; setTimeout(() => show = false, 3000)"
         class="fixed bottom-4 right-4 z-50 pointer-events-none" style="display: none;">
        <div class="max-w-sm w-full bg-slate-900 shadow-2xl rounded-2xl pointer-events-auto ring-1 ring-white/10 overflow-hidden">
            <div class="p-4 flex items-center gap-3">
                <div class="flex-shrink-0 text-emerald-400">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <p class="text-sm font-semibold text-white" x-text="message"></p>
            </div>
        </div>
    </div>

    {{-- STICKY HEADER --}}
    <div class="sticky top-[65px] z-40 bg-white/80 backdrop-blur-xl border-b border-slate-200/80 shadow-sm transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                {{-- Left: Breadcrumb & Title --}}
                <div class="flex items-center gap-4">
                    <a href="{{ $material->course_id ? route('instructor.course.builder', $material->course_id) : route('instructor.programs') }}"
                       class="group flex items-center justify-center w-10 h-10 rounded-full bg-slate-50 border border-slate-200 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 hover:border-indigo-200 transition-all shadow-sm">
                        <svg class="w-5 h-5 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </a>
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100 uppercase tracking-wider">
                                {{ $this->getTypeIcon() }} {{ $this->getTypeLabel() }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Right: Actions --}}
                <div class="flex items-center gap-4">
                    <button wire:click="save"
                            wire:loading.attr="disabled"
                            class="relative inline-flex items-center justify-center px-6 py-2.5 text-sm font-bold text-white transition-all bg-indigo-600 rounded-xl shadow-lg shadow-indigo-200 hover:bg-indigo-700 hover:shadow-indigo-300 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 disabled:opacity-70 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="save">Simpan Materi</span>
                        <span wire:loading wire:target="save" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Menyimpan...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN CANVAS --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            {{-- LEFT COLUMN: Main Editor --}}
            <div class="lg:col-span-8 space-y-8">

                {{-- Title Area --}}
                <div class="group relative">
                    <input type="text" wire:model="title"
                           class="block w-full text-4xl sm:text-5xl font-black text-slate-900 border-0 bg-transparent focus:ring-0 px-0 py-2 placeholder-slate-300 transition-all"
                           placeholder="Ketik Judul Materi Di Sini...">
                    <div class="h-1 w-full bg-slate-200 rounded-full mt-2 transition-all duration-300 group-focus-within:bg-indigo-500"></div>
                    @error('title') <p class="text-sm text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- DYNAMIC EDITOR CARD --}}
                <div class="bg-white border border-slate-200 shadow-sm rounded-3xl overflow-hidden relative">

                    {{-- 1. CASE: ARTICLE / TEXT --}}
                    @if(in_array($type, ['article', 'text']))
                        <div class="p-6 sm:p-10 border-b border-slate-100">
                            {{-- Container CKEditor --}}
                            <div wire:ignore x-data="{
                                    value: @entangle('body_text'),
                                    init() {
                                        let checkExist = setInterval(() => {
                                            if (typeof ClassicEditor !== 'undefined') {
                                                clearInterval(checkExist);
                                                ClassicEditor.create(this.$refs.editor, {
                                                    toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', '|', 'insertTable', 'mediaEmbed', 'undo', 'redo'],
                                                    shouldNotGroupWhenFull: true
                                                }).then(editor => {
                                                    editor.model.document.on('change:data', () => { this.value = editor.getData(); });
                                                    this.$watch('value', (newValue) => { if (newValue !== editor.getData()) editor.setData(newValue); });
                                                }).catch(err => console.error('CKEditor Error:', err));
                                            }
                                        }, 100);
                                    }
                                }">
                                <style>
                                    .ck-editor__editable_inline { min-height: 500px; padding: 2rem !important; font-size: 1.125rem; line-height: 1.75; color: #334155; }
                                    .ck-toolbar { border-radius: 1rem 1rem 0 0 !important; background: #f8fafc !important; border: 1px solid #e2e8f0 !important;}
                                    .ck-editor__main .ck-content { border-radius: 0 0 1rem 1rem !important; border: 1px solid #e2e8f0 !important; border-top: none !important;}
                                    .ck-content h2 { font-weight: 800; font-size: 1.875rem; color: #0f172a; margin-top: 2rem; margin-bottom: 1rem;}
                                    .ck-content h3 { font-weight: 700; font-size: 1.5rem; color: #1e293b; margin-top: 1.5rem; margin-bottom: 0.75rem;}
                                    .ck-content img { border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); margin: 1.5rem 0; max-width: 100%;}
                                </style>
                                <div x-ref="editor" class="prose max-w-none"></div>
                            </div>
                        </div>

                    {{-- 2. CASE: VIDEO YOUTUBE --}}
                    @elseif($type === 'video')
                        <div class="p-6 sm:p-10 border-b border-slate-100 bg-slate-900 text-white rounded-t-3xl">
                            <div class="max-w-2xl mx-auto space-y-6 text-center">
                                <div class="w-16 h-16 bg-red-500 rounded-2xl flex items-center justify-center mx-auto shadow-lg shadow-red-500/30">
                                    <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold mb-2">Sematkan Video Youtube</h3>
                                    <p class="text-slate-400 text-sm">Paste link video Youtube pada kolom di bawah ini untuk menampilkannya secara otomatis.</p>
                                </div>
                                <div class="relative max-w-xl mx-auto">
                                    <input type="url" wire:model.live.debounce.500ms="content_url"
                                           class="block w-full rounded-xl border-0 bg-white/10 text-white placeholder-slate-400 px-5 py-4 focus:ring-2 focus:ring-indigo-500 shadow-inner"
                                           placeholder="https://youtube.com/watch?v=...">
                                    @error('content_url') <span class="absolute -bottom-6 left-0 text-xs text-red-400 font-medium">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            @if($content_url && Str::contains($content_url, ['youtube.com', 'youtu.be']))
                                @php
                                    $videoId = '';
                                    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $content_url, $matches)) $videoId = $matches[1];
                                @endphp
                                @if($videoId)
                                    <div class="mt-12 aspect-video rounded-2xl overflow-hidden shadow-2xl ring-1 ring-white/20 bg-black max-w-4xl mx-auto transform hover:scale-[1.01] transition-transform">
                                        <iframe class="w-full h-full" src="https://www.youtube.com/embed/{{ $videoId }}" frameborder="0" allowfullscreen></iframe>
                                    </div>
                                @endif
                            @endif
                        </div>

                    {{-- 3. CASE: GOOGLE DRIVE VIDEO / FILE --}}
                    @elseif(in_array($type, ['google_drive_video', 'google_drive_file']))
                        <div class="p-6 sm:p-10 border-b border-slate-100 bg-gradient-to-br from-blue-50/50 to-indigo-50/50 rounded-t-3xl">
                            <div class="max-w-2xl mx-auto text-center space-y-6">
                                <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mx-auto shadow-sm text-blue-600 border border-blue-100">
                                    @if($type == 'google_drive_video')
                                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    @else
                                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    @endif
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-slate-800 mb-2">Unggah Berkas ke Cloud</h3>
                                    <p class="text-slate-500 text-sm">File akan otomatis diunggah ke Google Drive sistem dan ditautkan ke materi ini.</p>
                                </div>

                                {{-- Drag Drop Area --}}
                                <div class="relative border-2 border-dashed {{ $file_upload ? 'border-indigo-400 bg-indigo-50/50' : 'border-slate-300 hover:bg-slate-50' }} rounded-2xl p-10 transition-colors cursor-pointer group">
                                    <input type="file" wire:model="file_upload" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                           accept="{{ $type == 'google_drive_video' ? 'video/mp4,video/webm' : '.pdf,.docx,.pptx,.xlsx,.zip' }}">

                                    <div class="space-y-2 pointer-events-none">
                                        <div wire:loading.remove wire:target="file_upload">
                                            @if($file_upload)
                                                <div class="text-indigo-600 mb-2">
                                                    <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                </div>
                                                <p class="text-sm font-bold text-indigo-700">File siap diunggah!</p>
                                                <p class="text-xs text-indigo-500 mt-1">{{ $file_upload->getClientOriginalName() }}</p>
                                            @else
                                                <svg class="mx-auto h-12 w-12 text-slate-400 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-8m0 0l-4 4m4-4l4 4m-4 10a9 9 0 110-18 9 9 0 010 18z" /></svg>
                                                <p class="text-sm text-slate-600 font-semibold mt-4">Klik atau Drag & Drop file ke area ini</p>
                                                <p class="text-xs text-slate-400 mt-1">
                                                    {{ $type == 'google_drive_video' ? 'Format MP4, WebM didukung.' : 'Format PDF, Word, Excel, ZIP didukung.' }}
                                                </p>
                                            @endif
                                        </div>

                                        <div wire:loading wire:target="file_upload" class="w-full">
                                            <svg class="mx-auto h-12 w-12 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            <p class="text-sm font-bold text-indigo-600 mt-4 animate-pulse">Menyiapkan file...</p>
                                        </div>
                                    </div>
                                </div>
                                @error('file_upload') <p class="text-sm text-red-500 font-medium">{{ $message }}</p> @enderror

                                {{-- URL Tersimpan Indikator --}}
                                @if($content_url)
                                    <div class="mt-6 flex items-center justify-between p-4 bg-white border border-slate-200 rounded-xl shadow-sm">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center">
                                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </div>
                                            <div class="text-left">
                                                <p class="text-sm font-bold text-slate-900">Berkas Terhubung</p>
                                                <p class="text-xs text-slate-500 truncate max-w-[200px] sm:max-w-xs">{{ $content_url }}</p>
                                            </div>
                                        </div>
                                        <a href="{{ $content_url }}" target="_blank" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-3 py-1.5 rounded-lg">Cek Tautan</a>
                                    </div>
                                @endif
                            </div>
                        </div>

                    {{-- 4. CASE: LIVE SESSION --}}
                    @elseif($type === 'live_session')
                        <div class="p-6 sm:p-10 border-b border-slate-100 bg-white">
                            <div class="flex items-center gap-4 mb-8 pb-6 border-b border-slate-100">
                                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-slate-800">Detail Sesi Sinkron</h3>
                                    <p class="text-sm text-slate-500">Atur jadwal rencana kelas tatap muka virtual.</p>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Tanggal & Waktu Mulai</label>
                                        <input type="datetime-local" wire:model="start_time" class="block w-full rounded-xl border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 px-4 py-3 font-medium">
                                        @error('start_time') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Estimasi Durasi (Menit)</label>
                                        <input type="number" wire:model="duration" placeholder="Contoh: 120" class="block w-full rounded-xl border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 px-4 py-3 font-medium">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Platform Sesi Virtual</label>
                                    <select wire:model.live="meeting_platform" class="block w-full rounded-xl border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 px-4 py-3 font-medium">
                                        <option value="zoom">Zoom (Generate Otomatis via API Admin)</option>
                                        <option value="other">Platform Lain (Google Meet / Teams / Eksternal)</option>
                                    </select>
                                </div>

                                @if($meeting_platform === 'zoom')
                                    @if(!$meeting_id)
                                        <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-xl">
                                            <div class="flex items-start gap-3">
                                                <div class="flex-shrink-0 text-indigo-600 mt-0.5">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-bold text-indigo-900">Menunggu Persetujuan Admin</p>
                                                    <p class="text-xs text-indigo-700 mt-1">
                                                        Tautan, Meeting ID, dan Passcode untuk Zoom belum tersedia. Sistem akan membuatkannya secara otomatis saat <b>Course ini disetujui (Approved)</b> oleh Admin.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        {{-- Mode Readonly jika Zoom sudah ada --}}
                                        <div>
                                            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Zoom Join URL (Terkelola Sistem)</label>
                                            <input type="url" wire:model="zoom_url" readonly class="block w-full rounded-xl border-slate-200 bg-slate-100 text-slate-500 px-4 py-3 font-medium cursor-not-allowed">
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 bg-slate-50 rounded-2xl border border-slate-100 opacity-80">
                                            <div>
                                                <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Meeting ID</label>
                                                <input type="text" wire:model="meeting_id" readonly class="block w-full rounded-lg border-slate-200 bg-slate-100 text-slate-500 font-mono text-sm px-4 py-2 cursor-not-allowed">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Passcode</label>
                                                <input type="text" wire:model="meeting_passcode" readonly class="block w-full rounded-lg border-slate-200 bg-slate-100 text-slate-500 font-mono text-sm px-4 py-2 cursor-not-allowed">
                                            </div>
                                        </div>
                                    @endif
                                @else
                                    {{-- Mode Manual --}}
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Tautan Rapat (GMeet / Eksternal URL)</label>
                                        <input type="url" wire:model="zoom_url" placeholder="https://meet.google.com/..." class="block w-full rounded-xl border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 px-4 py-3 font-medium text-blue-600">
                                        @error('zoom_url') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 bg-slate-50 rounded-2xl border border-slate-100">
                                        <div>
                                            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Meeting ID (Opsional)</label>
                                            <input type="text" wire:model="meeting_id" placeholder="890 1234 5678" class="block w-full rounded-lg border-slate-200 font-mono text-sm px-4 py-2">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Passcode (Opsional)</label>
                                            <input type="text" wire:model="meeting_passcode" placeholder="123456" class="block w-full rounded-lg border-slate-200 font-mono text-sm px-4 py-2">
                                        </div>
                                    </div>
                                @endif

                            </div>
                        </div>
                    @endif

                    {{-- DESKRIPSI TAMBAHAN (Hanya Muncul Jika Bukan Tipe Artikel) --}}
                    @if(!in_array($type, ['article', 'text']))
                        <div class="p-6 sm:p-10 bg-white">
                            <label class="block text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                Deskripsi / Instruksi Tambahan
                            </label>

                            <div wire:ignore x-data="{
                                    val: @entangle('body_text'),
                                    init() {
                                        let checkExist = setInterval(() => {
                                            if (typeof ClassicEditor !== 'undefined') {
                                                clearInterval(checkExist);
                                                ClassicEditor.create(this.$refs.descEditor, {
                                                    toolbar: ['bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'undo', 'redo']
                                                }).then(editor => {
                                                    editor.model.document.on('change:data', () => { this.val = editor.getData(); });
                                                });
                                            }
                                        }, 100);
                                    }
                                }">
                                <style> .ck-editor__editable_inline { min-height: 200px; padding: 1.5rem !important; } </style>
                                <div x-ref="descEditor" class="prose max-w-none"></div>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            {{-- RIGHT COLUMN: Settings & Actions --}}
            <div class="lg:col-span-4 space-y-6">

                {{-- Card: Status & Visibility --}}
                <div class="bg-white border border-slate-200 shadow-sm rounded-3xl p-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-50 rounded-bl-full -mr-10 -mt-10 pointer-events-none"></div>

                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-6">Visibilitas Materi</h3>

                    <div class="flex items-center justify-between mb-6">
                        <div class="flex flex-col">
                            <span class="font-bold text-slate-800 text-lg">Status</span>
                            <span class="text-xs text-slate-500">{{ $status == 'published' ? 'Siswa dapat melihat materi ini.' : 'Hanya Anda yang dapat melihat.' }}</span>
                        </div>

                        {{-- Custom Toggle Switch --}}
                        <button wire:click="toggleStatus"
                                class="relative inline-flex h-7 w-14 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 {{ $status == 'published' ? 'bg-emerald-500' : 'bg-slate-300' }}"
                                role="switch" aria-checked="{{ $status == 'published' ? 'true' : 'false' }}">
                            <span class="sr-only">Use setting</span>
                            <span aria-hidden="true" class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $status == 'published' ? 'translate-x-7' : 'translate-x-0' }}"></span>
                        </button>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-600">Label Saat Ini:</span>
                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold uppercase tracking-wider {{ $status == 'published' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-amber-100 text-amber-700 border border-amber-200' }}">
                            @if($status == 'published')
                                <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                            {{ $status }}
                        </span>
                    </div>
                </div>

                {{-- Card: Info Meta --}}
                <div class="bg-white border border-slate-200 shadow-sm rounded-3xl p-6">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-6">Metadata Sistem</h3>

                    <ul class="space-y-4 text-sm">
                        <li class="flex items-center justify-between pb-4 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">Tipe Materi</span>
                            <span class="font-bold text-slate-800">{{ $this->getTypeLabel() }}</span>
                        </li>
                        <li class="flex items-center justify-between pb-4 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">Dibuat Pada</span>
                            <span class="font-bold text-slate-800">{{ $material->created_at->format('d M Y') }}</span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span class="text-slate-500 font-medium">Pembaruan Terakhir</span>
                            <span class="font-bold text-indigo-600">{{ $material->updated_at->diffForHumans() }}</span>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>

@assets
<script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
@endassets
