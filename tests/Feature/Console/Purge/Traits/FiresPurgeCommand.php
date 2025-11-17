<?php

namespace Tests\Feature\Console\Purge\Traits;

trait FiresPurgeCommand
{
    private function firePurgeCommand()
    {
        $question = <<<TXT

****************************************************
THIS WILL PURGE ALL SOFT-DELETED ITEMS IN YOUR SYSTEM. 
There is NO undo. This WILL permanently destroy 
ALL of your deleted data. 
****************************************************

Do you wish to continue? No backsies! [y|N]
TXT;

        return $this->artisan('snipeit:purge')
            ->expectsConfirmation($question, 'yes');
    }
}
