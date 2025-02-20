<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected static function booted(): void
    {
        static::created(function (Note $note) {
            $logaction = new Actionlog;
            $logaction->item()->associate($note->commentable);
            $logaction->target()->associate($note);

            // @todo: duplicate content?
            // $logaction->note = $note->content;
            $logaction->created_by = $note->created_by;
            $logaction->logaction('note added');
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function commentable()
    {
        return $this->morphTo();
    }

    public function getDisplayNameAttribute()
    {
        return 'Note';
    }
}
