<?php

namespace Martin\Forms\Classes\Traits;

use Illuminate\Support\Facades\App;
use Martin\Forms\Classes\Mails\AutoResponse;
use Martin\Forms\Classes\Mails\Notification;

trait SendMails
{
    private function sendNotification(array $post, $record)
    {
        if (!$this->property('mail_enabled')) {
            return;
        }

        $notification = App::makeWith(Notification::class, [
            $this->getProperties(), $post, $record, $record->files
        ]);

        $notification->send();
    }

    private function sendAutoresponse(array $post, $record)
    {
        if (!$this->property('mail_resp_enabled')) {
            return;
        }

        $autoresponse = App::makeWith(AutoResponse::class, [
            $this->getProperties(), $post, $record
        ]);

        $autoresponse->send();
    }
}
