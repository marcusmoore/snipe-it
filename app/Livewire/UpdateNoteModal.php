<?php

namespace App\Livewire;

use App\Models\Note;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class UpdateNoteModal extends Component
{
    #[Locked]
    public $noteId;
    public $content;
    #[On('showEditNoteModal')]
    public function showModal($id)
    {
        $this->noteId = $id;
        $this->content = Note::findOrFail($this->noteId)->content;

        $this->js("$('#editNoteModal').modal('show')");
    }

    public function hide()
    {
        $this->js("$('#editNoteModal').modal('hide')");
    }

    public function save()
    {
        $note = Note::findOrFail($this->noteId);
        $note->content = $this->content;
        $note->save();

        $this->hide();

        $this->js("$('#assetNotes').bootstrapTable('refresh')");
    }

    public function render()
    {
        return view('livewire.update-note-modal');
    }
}
