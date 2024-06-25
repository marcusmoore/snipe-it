<?php

namespace Tests\Feature;

class BagOfHolding
{
    public $actor;

    public $subject;

    public $statusCode;

    public function setActor($actor)
    {
        $this->actor = $actor;
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    public function setStatusCode($code)
    {
        $this->statusCode = $code;
    }
}
