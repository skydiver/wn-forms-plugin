<?php

namespace Martin\Forms\Classes;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Session;
use Winter\Storm\Support\Facades\Config;
use Winter\Storm\Exception\AjaxException;
use Winter\Storm\Exception\ValidationException;

trait RequestValidation
{
    /**
     * Check for CSRF and throw exception if failed
     *
     * @throws AjaxException
     */
    private function checkCSRF()
    {
        if (Config::get('cms.enableCsrfProtection') && (Session::token() != post('_token'))) {
            throw new AjaxException([
                '#' . $this->alias . '_forms_flash' => $this->renderPartial($this->flash_partial, [
                    'status'  => 'error',
                    'type'    => 'danger',
                    'content' => Lang::get('martin.forms::lang.components.shared.csrf_error'),
                ])
            ]);
        }
    }

    /**
     * Check for valid form and throw errors if needed
     *
     * @throws AjaxException
     * @throws ValidationException
     */
    private function validateForm()
    {
        /** CONTINUE IF VALIDATION PASSES */
        if ($this->validator->passes()) {
            return;
        }

        /** GET DEFAULT ERROR MESSAGE */
        $message = $this->property('messages_errors');

        /** TRANSLATE ERROR MESSAGE */
        if (BackendHelpers::isTranslatePlugin()) {
            $message = \RainLab\Translate\Models\Message::trans($message);
        }

        /** RETURN VALIDATOR OBJECT IF INLINE ERRORS ARE ENABLED */
        if ($this->property('inline_errors') == 'display') {
            throw new ValidationException($this->validator);
        }

        /** THROW ERROR MESSAGES */
        throw new AjaxException($this->exceptionResponse($this->validator, [
            'status'  => 'error',
            'type'    => 'danger',
            'title'   => $message,
            'list'    => $this->validator->messages()->all(),
            'errors'  => json_encode($this->validator->messages()->messages()),
            'jscript' => $this->property('js_on_error'),
        ]));
    }
}
