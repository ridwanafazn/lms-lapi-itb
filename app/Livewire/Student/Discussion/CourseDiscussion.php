<?php

namespace App\Livewire\Student\Discussion;

use App\Models\Course;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class CourseDiscussion extends Component
{
    use WithFileUploads, WithPagination;

    public Course $course;

    // --- VIEW STATE ---
    public $viewState = 'list';

    public $selectedDiscussionId = null;

    // --- FILTERS ---
    public $search = '';

    public $filterStatus = 'all';

    public $sort = 'latest';

    // --- FORM INPUTS ---
    public $newTitle = '';

    public $newBody = '';

    public $newAttachments = [];

    public $replyBody = '';

    public $replyAttachments = [];

    protected $listeners = ['refreshComponent' => '$refresh'];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
    }

    // --- ACTIONS: NAVIGATION ---

    public function showCreateForm()
    {
        $this->reset(['newTitle', 'newBody', 'newAttachments']);
        $this->viewState = 'create';
    }

    public function cancelCreate()
    {
        $this->reset(['newTitle', 'newBody', 'newAttachments']);
        $this->viewState = 'list';
    }

    public function selectDiscussion($id)
    {
        $this->selectedDiscussionId = $id;
        $this->viewState = 'detail';
        $this->reset(['replyBody', 'replyAttachments']);
    }

    public function backToList()
    {
        // Di Desktop ini mungkin tidak terlalu efek karena split view,
        // tapi di Mobile ini penting untuk kembali dari Detail ke List.
        $this->selectedDiscussionId = null;
        $this->viewState = 'list';
    }

    public function removeNewAttachment($index)
    {
        array_splice($this->newAttachments, $index, 1);
    }

    public function removeReplyAttachment($index)
    {
        array_splice($this->replyAttachments, $index, 1);
    }

    // --- CRUD ACTIONS ---

    public function createDiscussion()
    {
        $this->validate([
            'newTitle' => 'required|min:5|max:255',
            'newBody' => 'required|min:10',
            'newAttachments.*' => 'image|max:5120',
        ]);

        DB::transaction(function () {
            $discussion = Discussion::create([
                'course_id' => $this->course->id,
                'user_id' => Auth::id(),
                'title' => $this->newTitle,
                'body' => $this->newBody,
                'is_solved' => false,
            ]);

            $this->processAttachments($discussion, $this->newAttachments);
        });

        $this->reset(['newTitle', 'newBody', 'newAttachments']);
        $this->viewState = 'list';
        session()->flash('message', 'Diskusi berhasil dibuat!');
    }

    public function postReply()
    {
        // Validasi Hybrid: Teks ATAU Gambar wajib ada
        $this->validate([
            'replyBody' => 'nullable|required_without:replyAttachments|min:2',
            'replyAttachments' => 'nullable|required_without:replyBody|array',
            'replyAttachments.*' => 'image|max:5120',
        ], [
            'replyBody.required_without' => 'Tulis pesan atau lampirkan gambar.',
            'replyAttachments.required_without' => 'Tulis pesan atau lampirkan gambar.',
        ]);

        if (! $this->selectedDiscussionId) {
            return;
        }

        $discussion = Discussion::find($this->selectedDiscussionId);
        if ($discussion && $discussion->is_solved) {
            $this->addError('replyBody', 'Diskusi ini sudah ditutup.');

            return;
        }

        DB::transaction(function () {
            $reply = DiscussionReply::create([
                'discussion_id' => $this->selectedDiscussionId,
                'user_id' => Auth::id(),
                'body' => $this->replyBody ?? '',
            ]);

            $this->processAttachments($reply, $this->replyAttachments);
        });

        $this->reset(['replyBody', 'replyAttachments']);
    }

    // Helper: Simpan file fisik & record database manual
    private function processAttachments($model, $attachments)
    {
        if (empty($attachments)) {
            return;
        }

        foreach ($attachments as $file) {
            $path = $file->store('discussion-attachments', 'public');
            $filename = basename($path);
            $uuid = Str::uuid()->toString();

            DB::table('media')->insert([
                'id' => $uuid,
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'uuid' => $uuid,
                'collection_name' => 'default',
                'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_name' => $filename,
                'mime_type' => $file->getMimeType(),
                'disk' => 'public',
                'conversions_disk' => 'public',
                'size' => $file->getSize(),
                'manipulations' => '[]',
                'custom_properties' => '[]',
                'generated_conversions' => '[]',
                'responsive_images' => '[]',
                'order_column' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function toggleSolved($discussionId)
    {
        $discussion = Discussion::find($discussionId);
        if ($discussion && $discussion->user_id === Auth::id()) {
            $discussion->is_solved = ! $discussion->is_solved;
            $discussion->save();
        }
    }

    public function deleteDiscussion($id)
    {
        $discussion = Discussion::find($id);
        if ($discussion && $discussion->user_id === Auth::id()) {
            $discussion->delete();
            $this->backToList();
        }
    }

    public function deleteReply($id)
    {
        $reply = DiscussionReply::find($id);
        if ($reply && $reply->user_id === Auth::id()) {
            $reply->delete();
        }
    }

    // --- RENDER (UPDATED FOR SPLIT VIEW) ---

    public function render()
    {
        // 1. QUERY LIST (SELALU DIJALANKAN)
        // Kita butuh variabel $discussions di SEMUA view state karena layoutnya Split View.
        $query = Discussion::where('course_id', $this->course->id)
            ->with('user')
            ->withCount('replies');

        // Apply Filters
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('body', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->filterStatus === 'solved') {
            $query->where('is_solved', true);
        } elseif ($this->filterStatus === 'unsolved') {
            $query->where('is_solved', false);
        }

        if ($this->sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($this->sort === 'most_replies') {
            $query->orderBy('replies_count', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $discussions = $query->paginate(10);

        // 2. QUERY DETAIL (CONDITIONAL)
        // Hanya ambil detail jika user memilih sebuah diskusi.
        $activeDiscussion = null;
        if ($this->viewState === 'detail' && $this->selectedDiscussionId) {
            $activeDiscussion = Discussion::with(['user', 'attachments', 'replies.user', 'replies.attachments'])
                ->find($this->selectedDiscussionId);
        }

        // 3. RETURN VIEW WITH BOTH DATA
        return view('livewire.student.discussion.course-discussion', [
            'discussions' => $discussions,        // Wajib untuk Sidebar Kiri
            'discussion' => $activeDiscussion,     // Opsional untuk Panel Kanan
        ]);
    }
}
