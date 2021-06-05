<?php

namespace Martin\Forms\Classes\Traits;

use Illuminate\Support\Facades\App;
use Martin\Forms\Classes\Mails\AutoResponse;
use Martin\Forms\Classes\Mails\Notification;

trait SendMails
{
    /**
     * Send notification & autoresponse emails
     *
     * @param array $post
     * @param $record
     */
    private function sendEmails(array $post, $record)
    {
        if ($this->property('mail_enabled')) {
            $this->sendNotification($post, $record);
        }

        if ($this->property('mail_resp_enabled')) {
            $this->sendAutoresponse($post, $record);
        }
    }

    /**
     * Send notification email
     *
     * @param array $post
     * @param $record
     */
    private function sendNotification(array $post, $record)
    {
        $notification = App::makeWith(Notification::class, [
            $this->getProperties(), $post, $record, $record->files
        ]);

        $notification->send();
    }

    /**
     * Send autoresponse email
     *
     * @param array $post
     * @param $record
     */
    private function sendAutoresponse(array $post, $record)
    {
        $autoresponse = App::makeWith(AutoResponse::class, [
            $this->getProperties(), $post, $record
        ]);

        $autoresponse->send();
    }
}
