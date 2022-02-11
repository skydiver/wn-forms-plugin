<?php

namespace Martin\Forms\Classes\Mails;

use Martin\Forms\Models\Record;
use System\Models\MailTemplate;
use Winter\Storm\Database\Collection;
use Winter\Storm\Support\Facades\Mail;
use Martin\Forms\Classes\BackendHelpers as BH;

class Notification implements Mailable
{
    private $properties;
    private $post;
    private $record;
    private $files;
    private $data;

    public function __construct(array $properties, array $post, Record $record, Collection $files)
    {
        $this->properties = $properties;
        $this->post = $post;
        $this->record = $record;
        $this->files = $files;
    }

    public function send()
    {
        // CHECK IF THERE IS AT LEAST ONE MAIL ADDRESS
        if (!isset($this->properties['mail_recipients'])) {
            $this->properties['mail_recipients'] = false;
        }

        // CHECK IF THERE IS AT LEAST ONE MAIL ADDRESS
        if (!isset($this->properties['mail_bcc'])) {
            $this->properties['mail_bcc'] = false;
        }

        // EXIT IF NO EMAIL ADDRESSES ARE SET
        if (!$this->checkEmailSettings()) {
            return;
        }

        // CUSTOM TEMPLATE
        $template = $this->getTemplate();

        // SET DEFAULT EMAIL DATA ARRAY
        $this->data = [
            'id'   => $this->record->id,
            'data' => $this->post,
            'ip'   => $this->record->ip,
            'date' => $this->record->created_at
        ];

        // CHECK FOR CUSTOM SUBJECT
        if (!empty($this->properties['mail_subject'])) {
            $this->prepareCustomSubject();
        }

        // SEND NOTIFICATION EMAIL
        Mail::sendTo($this->properties['mail_recipients'], $template, $this->data, function ($message) {
            // SEND BLIND CARBON COPY
            if (!empty($this->properties['mail_bcc']) && is_array($this->properties['mail_bcc'])) {
                $message->bcc($this->properties['mail_bcc']);
            }

            // USE CUSTOM SUBJECT
            if (!empty($this->properties['mail_subject'])) {
                $message->subject($this->properties['mail_subject']);
            }

            // ADD REPLY TO ADDRESS
            if (!empty($this->properties['mail_replyto'])) {
                $message->replyTo($this->properties['mail_replyto']);
            }

            // ADD UPLOADS
            if (!empty($this->properties['mail_uploads']) && !empty($this->files)) {
                foreach ($this->files as $file) {
                    $message->attach($file->getLocalPath(), ['as' => $file->getFilename()]);
                }
            }
        });
    }

    /**
     * Check if emails address are set
     *
     * @return boolean
     */
    private function checkEmailSettings(): bool
    {
        return (is_array($this->properties['mail_recipients']) || is_array($this->properties['mail_bcc']));
    }

    public function getTemplate(): string
    {
        return !empty($this->properties['mail_template']) && MailTemplate::findOrMakeTemplate($this->properties['mail_template']) ?
            $this->properties['mail_template'] :
            'martin.forms::mail.notification';
    }

    public function prepareCustomSubject()
    {
        // SET DATE FORMAT
        $dateFormat = $this->properties['emails_date_format'] ?? 'Y-m-d';

        // DATA TO REPLACE
        $id = $this->data['id'];
        $ip = $this->data['ip'];
        $date = date($dateFormat);

        // REPLACE RECORD TOKENS IN SUBJECT
        $this->properties['mail_subject'] = BH::replaceToken('record.id', $id, $this->properties['mail_subject']);
        $this->properties['mail_subject'] = BH::replaceToken('record.ip', $ip, $this->properties['mail_subject']);
        $this->properties['mail_subject'] = BH::replaceToken('record.date', $date, $this->properties['mail_subject']);

        // REPLACE FORM FIELDS TOKENS IN SUBJECT
        foreach ($this->data['data'] as $key => $value) {
            if (!is_array($value)) {
                $token = 'form.' . $key;
                $this->properties['mail_subject'] = BH::replaceToken($token, $value, $this->properties['mail_subject']);
            }
        }
    }
}
