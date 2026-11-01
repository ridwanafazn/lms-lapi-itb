<?php

namespace App\Livewire\Instructor\Materials;

use Livewire\Component;
use App\Models\Material;
use Illuminate\Validation\Rule;

class MaterialForm extends Component
{
    public $isOpen = false;
    public $isEdit = false;

    public $materialId;
    public $sectionId;

    public $type = 'article';
    public $title;
    public $content;

    public $content_url;

    public $zoom_url;
    public $start_time;
    public $duration;
    public $meeting_id;
    public $meeting_passcode;
    public $meeting_platform = 'zoom';

    public $start_url;

    protected function rules()
    {
        return [
            'sectionId' => 'required|exists:sections,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:article,video,google_drive_video,google_drive_file,live_session,quiz,assignment',

            'content' => 'nullable|required_if:type,article',
            'content_url' => [
                'nullable',
                'required_if:type,video,google_drive_video,google_drive_file',
                'url'
            ],

            'start_time' => 'nullable|required_if:type,live_session|date',
            'duration' => 'nullable|required_if:type,live_session|integer',
            'zoom_url' => 'nullable|url',
            'meeting_id' => 'nullable|string',
            'meeting_passcode' => 'nullable|string',
        ];
    }

    protected $listeners = ['openMaterialForm'];

    public function openMaterialForm($sectionId, $materialId = null, $type = 'article')
    {
        $this->resetValidation();
        $this->reset(['title', 'content', 'content_url', 'zoom_url', 'start_time', 'duration', 'meeting_id', 'meeting_passcode', 'start_url']);

        $this->sectionId = $sectionId;
        $this->type = $type;
        $this->materialId = $materialId;

        if ($materialId) {
            $this->isEdit = true;
            $this->loadMaterial($materialId);
        } else {
            $this->isEdit = false;
        }

        $this->isOpen = true;
    }

    public function loadMaterial($id)
    {
        $material = Material::findOrFail($id);
        $this->title = $material->title;
        $this->type = $material->type;
        $this->content = $material->body_text;
        $this->content_url = $material->content_url;

        $this->zoom_url = $material->zoom_url;
        $this->start_url = $material->content_url;

        $this->start_time = $material->start_time ? $material->start_time->format('Y-m-d\TH:i') : null;
        $this->duration = $material->duration;
        $this->meeting_id = $material->meeting_id;
        $this->meeting_passcode = $material->meeting_passcode;
        $this->meeting_platform = $material->meeting_platform ?? 'zoom';
    }

    public function close()
    {
        $this->isOpen = false;
        $this->reset(['title', 'content', 'type', 'materialId']);
    }

    public function save()
    {
        $this->validate();

        $data = [
            'course_id' => \App\Models\Section::find($this->sectionId)->course_id,
            'title' => $this->title,
            'type' => $this->type,
            'body_text' => $this->content,
            'content_url' => $this->content_url,
            'start_time' => $this->start_time,
            'duration' => $this->duration,
            'meeting_platform' => $this->meeting_platform,
        ];

        // Jika Live Session, pastikan data yang butuh generate dibiarkan utuh/null
        // Instruktur tidak lagi men-generate Zoom di sini.
        if ($this->type === 'live_session') {
             $data['meeting_id'] = $this->meeting_id;
             $data['meeting_passcode'] = $this->meeting_passcode;
             $data['zoom_url'] = $this->zoom_url;
        }

        if ($this->isEdit) {
            $material = Material::find($this->materialId);
            $material->update($data);
        } else {
            $material = \App\Models\Section::find($this->sectionId)->materials()->create($data);
            $this->dispatch('material-created', materialId: $material->id, sectionId: $this->sectionId);
        }

        $this->close();
        $this->dispatch('material-saved');
    }

    public function render()
    {
        return view('livewire.instructor.materials.material-form');
    }
}
