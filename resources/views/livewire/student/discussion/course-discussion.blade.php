<div class="h-full w-full bg-white overflow-hidden">

    {{--
    ====================================================================
    VIEW 1: DAFTAR DISKUSI (LIST)
    Tampil HANYA jika viewState adalah 'list'
    ====================================================================
    --}}
    @if($viewState === 'list')
        <div class="w-full h-full flex flex-col bg-white">

            {{-- Header & Search --}}
            <div class="p-4 border-b border-gray-100 bg-white z-10 shrink-0">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="text-xl font-bold text-gray-900 tracking-tight">Diskusi</h2>
                    <button wire:click="showCreateForm" class="p-2 bg-black text-white rounded-lg hover:bg-gray-800 transition shadow-sm" title="Buat Diskusi Baru">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                </div>

                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 group-focus-within:text-indigo-600 transition-colors">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="text" class="w-full bg-gray-50 border-none rounded-lg pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/20 focus:bg-white transition-all placeholder-gray-400" placeholder="Cari diskusi...">
                </div>

                {{-- Mini Filters --}}
                <div class="flex gap-2 mt-3 overflow-x-auto no-scrollbar pb-1">
                    <button wire:click="$set('filterStatus', 'all')" class="px-3 py-1 text-xs font-medium rounded-full border transition-colors whitespace-nowrap {{ $filterStatus === 'all' ? 'bg-black text-white border-black' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300' }}">Semua</button>
                    <button wire:click="$set('filterStatus', 'unsolved')" class="px-3 py-1 text-xs font-medium rounded-full border transition-colors whitespace-nowrap {{ $filterStatus === 'unsolved' ? 'bg-black text-white border-black' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300' }}">Proses</button>
                    <button wire:click="$set('filterStatus', 'solved')" class="px-3 py-1 text-xs font-medium rounded-full border transition-colors whitespace-nowrap {{ $filterStatus === 'solved' ? 'bg-black text-white border-black' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300' }}">Selesai</button>
                </div>
            </div>

            {{-- List Items --}}
            <div class="flex-1 overflow-y-auto custom-scrollbar">
                @forelse($discussions as $item)
                    <div wire:click="selectDiscussion('{{ $item->id }}')"
                         class="cursor-pointer p-4 border-b border-gray-50 hover:bg-gray-50 transition-colors relative">

                        <div class="flex justify-between items-start mb-1">
                            <div class="flex items-center gap-2 max-w-[70%]">
                                <h3 class="font-bold text-sm text-gray-900 truncate">{{ $item->title }}</h3>
                            </div>
                            <span class="text-[10px] text-gray-400 shrink-0">{{ $item->created_at->diffForHumans(null, true) }}</span>
                        </div>

                        <p class="text-xs text-gray-500 line-clamp-2 mb-2">{{ strip_tags($item->body) }}</p>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($item->user->name) }}&background=random&color=fff&size=32" class="w-5 h-5 rounded-full">
                                <span class="text-xs text-gray-600 font-medium truncate max-w-[80px]">{{ $item->user->name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($item->is_solved)
                                    <span class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-bold">SOLVED</span>
                                @endif
                                <div class="flex items-center gap-1 text-gray-400">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                    <span class="text-[10px] font-bold">{{ $item->replies_count }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center h-40 text-center px-4">
                        <p class="text-gray-400 text-sm">Tidak ada diskusi ditemukan.</p>
                    </div>
                @endforelse

                <div class="p-4">
                    {{ $discussions->links(data: ['scrollTo' => false]) }}
                </div>
            </div>
        </div>
    @endif

    {{--
    ====================================================================
    VIEW 2: FORM CREATE
    Tampil HANYA jika viewState adalah 'create'
    ====================================================================
    --}}
    @if($viewState === 'create')
        <div class="w-full h-full flex flex-col bg-white">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-white sticky top-0 z-10">
                <h3 class="font-bold text-lg">Buat Diskusi Baru</h3>
                <button wire:click="cancelCreate" class="text-gray-500 hover:text-red-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                <div class="max-w-2xl mx-auto space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Judul</label>
                        <input wire:model="newTitle" type="text" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Apa inti pertanyaanmu?">
                        @error('newTitle') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Detail</label>
                        <textarea wire:model="newBody" rows="8" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Jelaskan detail permasalahan, kode error, atau konteks..."></textarea>
                        @error('newBody') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Upload Attachment UI --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Lampiran (Opsional)</label>
                        <div class="flex items-center gap-4">
                            <label class="cursor-pointer flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-600 text-sm font-medium transition">
                                <input type="file" wire:model="newAttachments" multiple class="hidden" accept="image/*">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Pilih Gambar
                            </label>
                        </div>
                        @if($newAttachments)
                            <div class="flex gap-3 mt-3 overflow-x-auto py-2">
                                @foreach($newAttachments as $index => $img)
                                    <div class="relative shrink-0 w-24 h-24 rounded-lg overflow-hidden border border-gray-200">
                                        <img src="{{ $img->temporaryUrl() }}" class="w-full h-full object-cover">
                                        <button wire:click="removeNewAttachment({{ $index }})" class="absolute top-1 right-1 bg-black/60 text-white p-1 rounded-full hover:bg-red-600 transition">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex justify-end">
                        <button wire:click="createDiscussion" class="bg-indigo-600 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center gap-2" wire:loading.attr="disabled">
                            <span wire:loading.remove>Posting Diskusi</span>
                            <span wire:loading class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{--
    ====================================================================
    VIEW 3: DETAIL DISCUSSION
    Tampil HANYA jika viewState adalah 'detail' DAN data diskusi ada
    ====================================================================
    --}}
    @if($viewState === 'detail' && isset($discussion))
        <div class="w-full h-full flex flex-col bg-white relative">

            {{-- Header Detail --}}
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-white/95 backdrop-blur z-20 sticky top-0 shadow-sm">
                <div class="flex items-center gap-3">
                    {{-- TOMBOL KEMBALI: Selalu muncul --}}
                    <button wire:click="backToList" class="text-gray-500 hover:text-black hover:bg-gray-100 p-2 rounded-full transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </button>

                    <div>
                        <h3 class="font-bold text-gray-900 leading-none truncate max-w-[200px] sm:max-w-md">{{ $discussion->title }}</h3>
                        <div class="flex items-center gap-2 text-xs text-gray-500 mt-1">
                            <span>{{ $discussion->user->name }}</span>
                            <span>&bull;</span>
                            <span>{{ $discussion->created_at->format('d M Y, H:i') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                @if(auth()->id() === $discussion->user_id)
                    <div class="flex items-center gap-2">
                        <button wire:click="toggleSolved('{{ $discussion->id }}')" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 hover:text-green-600 transition" title="Tandai Selesai">
                            @if($discussion->is_solved)
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @else
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @endif
                        </button>
                        <button wire:click="deleteDiscussion('{{ $discussion->id }}')" wire:confirm="Hapus thread ini?" class="p-2 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-600 transition" title="Hapus">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                @endif
            </div>

            {{-- Chat Stream --}}
            <div class="flex-1 overflow-y-auto px-6 py-6 space-y-8 scroll-smooth pb-40">

                {{-- OP Post --}}
                <div class="flex gap-4">
                    <div class="shrink-0 flex flex-col items-center">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($discussion->user->name) }}&background=random&color=fff" class="w-10 h-10 rounded-full border border-gray-200">
                        {{-- Thread Line --}}
                        @if($discussion->replies->count() > 0)
                            <div class="w-0.5 bg-gray-200 flex-1 mt-2 rounded-full"></div>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="bg-gray-50 rounded-2xl rounded-tl-none p-5 border border-gray-100">
                            <div class="prose prose-indigo max-w-none text-gray-800 text-[15px] leading-relaxed whitespace-pre-wrap">{{ $discussion->body }}</div>

                            @if($discussion->attachments->count() > 0)
                                <div class="mt-4 grid {{ $discussion->attachments->count() > 1 ? 'grid-cols-2' : 'grid-cols-1' }} gap-2 rounded-xl overflow-hidden max-w-md">
                                    @foreach($discussion->attachments as $media)
                                        <img src="{{ asset('storage/discussion-attachments/'.$media->file_name) }}" class="w-full h-full object-cover cursor-zoom-in hover:opacity-95 transition">
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Replies --}}
                @foreach($discussion->replies as $reply)
                    <div class="flex gap-4 group">
                        <div class="shrink-0 flex flex-col items-center relative">
                            {{-- Connection Line Top --}}
                            <div class="w-0.5 bg-gray-200 h-4 rounded-full mb-1 -mt-4"></div>
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($reply->user->name) }}&background=random&color=fff" class="w-8 h-8 rounded-full border border-gray-200 relative z-10">
                            {{-- Connection Line Bottom --}}
                            @if(!$loop->last)
                                <div class="w-0.5 bg-gray-200 flex-1 mt-1 rounded-full group-hover:bg-gray-300 transition-colors"></div>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline gap-2 mb-1">
                                <span class="font-bold text-sm text-gray-900">{{ $reply->user->name }}</span>
                                <span class="text-xs text-gray-400">{{ $reply->created_at->diffForHumans() }}</span>
                                @if($reply->user_id === $discussion->user_id)
                                    <span class="text-[9px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded font-bold tracking-wide border border-indigo-100">OP</span>
                                @endif
                            </div>

                            <div class="text-gray-800 text-[15px] leading-relaxed whitespace-pre-wrap">{{ $reply->body }}</div>

                            @if($reply->attachments->count() > 0)
                                <div class="mt-2 flex gap-2 overflow-x-auto pb-1">
                                    @foreach($reply->attachments as $media)
                                        <img src="{{ asset('storage/discussion-attachments/'.$media->file_name) }}" class="h-28 rounded-lg border border-gray-200 object-cover">
                                    @endforeach
                                </div>
                            @endif

                            @if(auth()->id() === $reply->user_id)
                                <div class="mt-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button wire:click="deleteReply('{{ $reply->id }}')" wire:confirm="Hapus?" class="text-gray-400 hover:text-red-500 text-xs font-medium">Hapus</button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- RICH EDITOR BOX (FIXED BOTTOM) --}}
            <div class="border-t border-gray-200 bg-white p-4 sticky bottom-0 z-30">
                @if($discussion->is_solved)
                    <div class="flex items-center justify-center gap-2 p-3 bg-gray-50 rounded-xl border border-gray-100 text-gray-500">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="font-medium text-sm">Topik ini telah diselesaikan.</span>
                        @if(auth()->id() === $discussion->user_id)
                            <button wire:click="toggleSolved('{{ $discussion->id }}')" class="text-indigo-600 text-sm font-bold hover:underline">Buka kembali</button>
                        @endif
                    </div>
                @else
                    {{-- AlpineJS Controlled Input --}}
                    <div x-data="{
                            text: @entangle('replyBody'),
                            hasFile: {{ count($replyAttachments) > 0 ? 'true' : 'false' }}
                         }"
                         class="flex flex-col gap-2">

                        {{-- Preview Images --}}
                        @if($replyAttachments)
                            <div class="flex gap-2 overflow-x-auto py-1">
                                @foreach($replyAttachments as $index => $img)
                                    <div class="relative shrink-0 w-16 h-16 rounded-lg overflow-hidden border border-gray-200 group">
                                        <img src="{{ $img->temporaryUrl() }}" class="w-full h-full object-cover">
                                        <button wire:click="removeReplyAttachment({{ $index }}); hasFile = {{ count($replyAttachments) - 1 > 0 ? 'true' : 'false' }}" class="absolute inset-0 bg-black/50 flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <form wire:submit.prevent="postReply" class="border border-gray-300 rounded-xl shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 bg-white transition-all">
                            <textarea
                                x-model="text"
                                wire:model="replyBody"
                                rows="1"
                                class="w-full border-none bg-transparent p-3 text-sm focus:ring-0 resize-none max-h-32"
                                placeholder="Ketik balasan..."
                                style="min-height: 50px;"
                            ></textarea>

                            <div class="flex justify-between items-center px-2 pb-2">
                                <div class="flex items-center">
                                    <label class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg cursor-pointer transition" title="Lampirkan Gambar">
                                        <input type="file" wire:model="replyAttachments" multiple class="hidden" accept="image/*" @change="hasFile = $event.target.files.length > 0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </label>
                                </div>

                                <button type="submit"
                                        class="bg-indigo-600 text-white p-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-sm"
                                        :disabled="(!text || text.trim() === '') && !hasFile"
                                        wire:loading.attr="disabled">
                                    <svg wire:loading.remove wire:target="postReply" class="w-4 h-4 transform rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    <svg wire:loading wire:target="postReply" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
