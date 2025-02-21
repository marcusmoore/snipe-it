<div>
    <div class="modal fade" id="editNoteModal" tabindex="-1" role="dialog" aria-labelledby="editNoteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span></button>
                    <h2 class="modal-title" id="editNoteModalLabel">{{ trans('general.edit_note')  }}</h2>
                </div>
                <form
                    wire:submit="save"
                    accept-charset="UTF-8"
                >
                    <div class="modal-body">
                        <div class="alert alert-danger" id="modal_error_msg" style="display:none"></div>
                        <div class="row">
                            <div class="col-md-12">
                                <textarea class="form-control" id="note" name="note" wire:model="content"></textarea>
                                {!! $errors->first('note', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default pull-left" wire:click="hide">{{ trans('button.cancel') }}</button>
                        <button type="submit" class="btn btn-primary pull-right" id="modal-save">{{ trans('general.save') }}</button>
                    </div>
                </form>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div>
</div>
