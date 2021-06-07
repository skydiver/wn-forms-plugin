<?php

namespace Martin\Forms\Classes;

use Cms\Classes\ComponentBase;
use Martin\Forms\Models\Record;
use Martin\Forms\Models\Settings;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Martin\Forms\Classes\BackendHelpers;
use Winter\Storm\Support\Facades\Config;
use Winter\Storm\Exception\AjaxException;
use Martin\Forms\Classes\FilePond\FilePond;
use Winter\Storm\Support\Facades\Validator;
use Martin\Forms\Classes\Mails\AutoResponse;
use Martin\Forms\Classes\Mails\Notification;
use Winter\Storm\Exception\ValidationException;

abstract class MagicForm extends ComponentBase
{

    use \Martin\Forms\Classes\Traits\PostData;
    use \Martin\Forms\Classes\Traits\ReCaptcha;
    use \Martin\Forms\Classes\Traits\RequestValidation;
    use \Martin\Forms\Classes\Traits\SendMails;
    use \Martin\Forms\Classes\Traits\SharedProperties;

    private $flash_partial;
    private $validator;

    public function init()
    {
        // FLASH PARTIAL
        $this->flash_partial = $this->property('messages_partial', '@flash.htm');
    }

    public function onRun()
    {
        $this->page['recaptcha_enabled']       = $this->isReCaptchaEnabled();
        $this->page['recaptcha_misconfigured'] = $this->isReCaptchaMisconfigured();

        if ($this->property('uploader_enable')) {
            $this->page['allowed_filesize'] = Settings::get('global_allowed_filesize');
        }

        if ($this->isReCaptchaEnabled()) {
            $this->loadReCaptcha();
        }

        if ($this->isReCaptchaMisconfigured()) {
            $this->page['recaptcha_warn'] = Lang::get('martin.forms::lang.components.shared.recaptcha_warn');
        }

        if ($this->property('inline_errors') == 'display') {
            $this->addJs('assets/js/inline-errors.js');
        }
    }

    public function settings()
    {
        return [
            'recaptcha_site_key'   => Settings::get('recaptcha_site_key'),
            'recaptcha_secret_key' => Settings::get('recaptcha_secret_key'),
        ];
    }

    public function onFormSubmit()
    {
        // CSRF CHECK
        $this->checkCSRF();

        // LOAD TRANSLATOR PLUGIN
        if (BackendHelpers::isTranslatePlugin()) {
            $translator = \RainLab\Translate\Classes\Translator::instance();
            $translator->loadLocaleFromSession();
            $locale = $translator->getLocale();
            \RainLab\Translate\Models\Message::setContext($locale);
        }

        /** PREPARE FORM DATA */
        $post = $this->preparePost();

        // VALIDATION PARAMETERS
        $rules = (array)$this->property('rules');
        $msgs  = (array)$this->property('rules_messages');
        $custom_attributes = (array)$this->property('custom_attributes');

        // TRANSLATE CUSTOM ERROR MESSAGES
        if (BackendHelpers::isTranslatePlugin()) {
            foreach ($msgs as $rule => $msg) {
                $msgs[$rule] = \RainLab\Translate\Models\Message::trans($msg);
            }
        }

        // ADD reCAPTCHA VALIDATION
        if ($this->isReCaptchaEnabled() && $this->property('recaptcha_size') != 'invisible') {
            $rules['g-recaptcha-response'] = 'required';
        }

        // DO FORM VALIDATION
        $this->validator = Validator::make($post, $rules, $msgs, $custom_attributes);

        // NICE reCAPTCHA FIELD NAME
        if ($this->isReCaptchaEnabled()) {
            $fields_names = ['g-recaptcha-response' => 'reCAPTCHA'];
            $this->validator->setAttributeNames(array_merge($fields_names, $custom_attributes));
        }

        // CHECK FOR VALID FORM AND THROW ERROR IF NEEDED
        $this->validateForm();

        // IF FIRST VALIDATION IS OK, VALIDATE CAPTCHA vs GOOGLE (prevents to resolve captcha after every form error)
        $this->validateReCaptcha($post);

        // REMOVE EXTRA FIELDS FROM STORED DATA
        unset($post['_token'], $post['g-recaptcha-response'], $post['_session_key'], $post['files']);

        /** FIRE BEFORE SAVE EVENT */
        Event::fire('martin.forms.beforeSaveRecord', [&$post, $this]);

        if (count($custom_attributes)) {
            $post = collect($post)->mapWithKeys(function ($val, $key) use ($custom_attributes) {
                return [array_get($custom_attributes, $key, $key) => $val];
            })->all();
        }

        $record = new Record;
        $record->ip        = $this->getIP();
        $record->created_at = date('Y-m-d H:i:s');

        // SAVE RECORD TO DATABASE
        if (!$this->property('skip_database')) {
            $record->form_data = json_encode($post, JSON_UNESCAPED_UNICODE);
            if ($this->property('group') != '') {
                $record->group = $this->property('group');
            }

            // attach files
            $this->attachFiles($record);

            $record->save(null, post('_session_key'));
        }

        /** SEND NOTIFICATION & AUTORESPONSE EMAILS */
        $this->sendEmails($post, $record);

        /** FIRE AFTER SAVE EVENT */
        Event::fire('martin.forms.afterSaveRecord', [&$post, $this, $record]);

        // CHECK FOR REDIRECT
        if ($this->property('redirect')) {
            return Redirect::to($this->property('redirect'));
        }

        // GET DEFAULT SUCCESS MESSAGE
        $message = $this->property('messages_success');

        // LOOK FOR TRANSLATION
        if (BackendHelpers::isTranslatePlugin()) {
            $message = \RainLab\Translate\Models\Message::trans($message);
        }

        // DISPLAY SUCCESS MESSAGE
        return ['#' . $this->alias . '_forms_flash' => $this->renderPartial($this->flash_partial, [
            'status'  => 'success',
            'type'    => 'success',
            'content' => $message,
            'jscript' => $this->prepareJavaScript(),
        ])];
    }

    private function prepareJavaScript()
    {
        $code = false;

        /* SUCCESS JS */
        if ($this->property('js_on_success') != '') {
            $code .= $this->property('js_on_success');
        }

        /* RECAPTCHA JS */
        if ($this->isReCaptchaEnabled()) {
            $code .= $this->renderPartial('@js/recaptcha.htm');
        }

        /* RESET FORM JS */
        if ($this->property('reset_form')) {
            $params = ['id' => '#' . $this->alias . '_forms_flash'];
            $code .= $this->renderPartial('@js/reset-form.htm', $params);
        }

        return $code;
    }

    private function getIP()
    {
        if ($this->property('anonymize_ip') == 'full') {
            return '(Not stored)';
        }

        $ip = Request::getClientIp();

        if ($this->property('anonymize_ip') == 'partial') {
            return BackendHelpers::anonymizeIPv4($ip);
        }

        return $ip;
    }

    private function attachFiles(Record $record)
    {
        $files = post('files', null);

        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            $filepond = App::make(FilePond::class);
            $filePath = $filepond->getPathFromServerId($file);

            $record->files()->create([
                'data' => $filePath
            ], post('_session_key'));
        }
    }
}
