<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NotesController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            // @todo: improve?
            'id' => 'required',
            'note' => 'required|string|max:500',
            'type' => [
                'required',
                Rule::in(['asset']),
            ],
        ]);

        $item = Asset::findOrFail($validated['id']);

        $this->authorize('update', $item);

        $note = new Note();
        $note->createdBy()->associate(Auth::user());
        $note->commentable()->associate($item);
        $note->content = $validated['note'];
        $note->save();

        return redirect()
            ->route('hardware.show', $validated['id'])
            ->withFragment('history')
            // @todo: translate
            ->with('success', 'Note Added!');
    }
}
