<?php

namespace App\Livewire;

use App\Models\Note;
use Livewire\Attributes\On;
use Livewire\Component;

class UpdateNoteModal extends Component
{
    public $content;
    #[On('showEditNoteModal')]
    public function showModal($id)
    {
        $this->content = Note::findOrFail($id)->content;

        $this->js("$('#editNoteModal').modal('show')");
    }

    public function hide()
    {
        $this->js("$('#editNoteModal').modal('hide')");
    }

    public function render()
    {
        return view('livewire.update-note-modal');
    }
}
