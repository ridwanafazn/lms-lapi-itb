<div>
    @if($isOpen)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">

            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="close"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <form wire:submit.prevent="save">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 border-b border-gray-100">
                        <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">
                            {{ $isEdit ? 'Edit Material' : 'Add New Material' }}
                        </h3>
                    </div>

                    <div class="px-4 py-5 sm:p-6 space-y-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Material Type</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none" :class="$wire.type == 'article' ? 'border-indigo-600 ring-2 ring-indigo-600' : 'border-gray-300'">
                                    <input type="radio" wire:model.live="type" value="article" class="sr-only">
                                    <span class="flex flex-1">
                                        <span class="flex flex-col">
                                            <span class="block text-sm font-medium text-gray-900">📄 Article / Text</span>
                                            <span class="mt-1 flex items-center text-xs text-gray-500">Editor Teks & Gambar</span>
                                        </span>
                                    </span>
                                </label>

                                <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none" :class="$wire.type == 'video' ? 'border-indigo-600 ring-2 ring-indigo-600' : 'border-gray-300'">
                                    <input type="radio" wire:model.live="type" value="video" class="sr-only">
                                    <span class="flex flex-1">
                                        <span class="flex flex-col">
                                            <span class="block text-sm font-medium text-gray-900">▶️ Video Youtube</span>
                                            <span class="mt-1 flex items-center text-xs text-gray-500">Embed URL Youtube</span>
                                        </span>
                                    </span>
                                </label>

                                <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none" :class="$wire.type == 'google_drive_video' ? 'border-indigo-600 ring-2 ring-indigo-600' : 'border-gray-300'">
                                    <input type="radio" wire:model.live="type" value="google_drive_video" class="sr-only">
                                    <span class="flex flex-1">
                                        <span class="flex flex-col">
                                            <span class="block text-sm font-medium text-gray-900">☁️ GDrive Video</span>
                                            <span class="mt-1 flex items-center text-xs text-gray-500">Video dari Google Drive</span>
                                        </span>
                                    </span>
                                </label>

                                <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none" :class="$wire.type == 'google_drive_file' ? 'border-indigo-600 ring-2 ring-indigo-600' : 'border-gray-300'">
                                    <input type="radio" wire:model.live="type" value="google_drive_file" class="sr-only">
                                    <span class="flex flex-1">
                                        <span class="flex flex-col">
                                            <span class="block text-sm font-medium text-gray-900">📂 GDrive File</span>
                                            <span class="mt-1 flex items-center text-xs text-gray-500">Embed PDF / Document</span>
                                        </span>
                                    </span>
                                </label>

                                <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none" :class="$wire.type == 'live_session' ? 'border-indigo-600 ring-2 ring-indigo-600' : 'border-gray-300'">
                                    <input type="radio" wire:model.live="type" value="live_session" class="sr-only">
                                    <span class="flex flex-1">
                                        <span class="flex flex-col">
                                            <span class="block text-sm font-medium text-gray-900">🎥 Live Session</span>
                                            <span class="mt-1 flex items-center text-xs text-gray-500">Zoom / Google Meet</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Title</label>
                            <input type="text" wire:model="title" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Judul materi...">
                            @error('title') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        @if($type === 'article')
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Content (Markdown/Text)</label>
                                <textarea wire:model="content" rows="6" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                                @error('content') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        @if(in_array($type, ['video', 'google_drive_video', 'google_drive_file']))
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    @if($type == 'video') Youtube Video URL
                                    @elseif($type == 'google_drive_video') Google Drive Video Link (Public)
                                    @elseif($type == 'google_drive_file') Google Drive File Link (PDF/Doc)
                                    @endif
                                </label>
                                <input type="text" wire:model="content_url"
                                       placeholder="{{ $type == 'video' ? 'https://youtube.com/watch?v=...' : 'https://drive.google.com/file/d/...' }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @error('content_url') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                <p class="mt-1 text-xs text-gray-500">Pastikan link Google Drive sudah di-set "Anyone with the link" (Public).</p>
                            </div>

                            <div class="mt-3">
                                <label class="block text-sm font-medium text-gray-700">Description (Optional)</label>
                                <textarea wire:model="content" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                            </div>
                        @endif

                        @if($type === 'live_session')
                            <div class="bg-blue-50 p-4 rounded-lg border border-blue-100 space-y-4">
                                <h4 class="text-sm font-bold text-blue-800 border-b border-blue-200 pb-2">Rencana Sesi (Draf)</h4>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 uppercase">Waktu Mulai</label>
                                        <input type="datetime-local" wire:model="start_time" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm">
                                        @error('start_time') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 uppercase">Durasi (Menit)</label>
                                        <input type="number" wire:model="duration" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm" placeholder="60">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase">Platform Rapat</label>
                                    <select wire:model.live="meeting_platform" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm">
                                        <option value="zoom">Zoom (Di-generate Admin)</option>
                                        <option value="other">Platform Lain (Google Meet / Teams) - Input Manual</option>
                                    </select>
                                </div>

                                @if($meeting_platform === 'zoom')
                                    <div class="bg-indigo-50 border border-indigo-200 p-4 rounded-lg">
                                        <div class="flex items-start gap-3">
                                            <div class="flex-shrink-0 text-indigo-600 mt-0.5">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-indigo-900">Menunggu Persetujuan Admin</p>
                                                <p class="text-xs text-indigo-700 mt-1">
                                                    Link rapat, Meeting ID, dan Passcode untuk Zoom tidak dibuat saat ini. Tautan akan digenerate secara otomatis oleh sistem ketika <b>Course ini disetujui (Approved)</b> oleh Admin.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Jika sudah di-generate oleh admin dan instruktur membuka form ini lagi --}}
                                    @if($meeting_id)
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 opacity-75 mt-3">
                                            <div class="col-span-1 sm:col-span-2">
                                                <label class="block text-xs font-bold text-gray-700 uppercase">Join URL (Siswa)</label>
                                                <input type="text" wire:model="zoom_url" readonly class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm sm:text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase">Meeting ID</label>
                                                <input type="text" wire:model="meeting_id" readonly class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm sm:text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase">Passcode</label>
                                                <input type="text" wire:model="meeting_passcode" readonly class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm sm:text-sm">
                                            </div>
                                        </div>
                                    @endif

                                @else
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 uppercase">Meeting URL (Join Link)</label>
                                        <input type="text" wire:model="zoom_url" placeholder="https://meet.google.com/..." class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm">
                                        @error('zoom_url') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 uppercase">Meeting ID (Optional)</label>
                                            <input type="text" wire:model="meeting_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 uppercase">Passcode (Optional)</label>
                                            <input type="text" wire:model="meeting_passcode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm">
                                        </div>
                                    </div>
                                @endif

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase">Instructions</label>
                                    <textarea wire:model="content" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm" placeholder="Instruksi tambahan..."></textarea>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-100">
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="w-full inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm">
                            <span wire:loading.remove wire:target="save">Simpan Draf Materi</span>
                            <span wire:loading wire:target="save">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Menyimpan...
                            </span>
                        </button>
                        <button type="button" wire:click="close" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
