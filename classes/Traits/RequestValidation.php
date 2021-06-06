<?php

namespace Martin\Forms\Classes\Traits;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Session;
use Martin\Forms\Classes\BackendHelpers;
use Winter\Storm\Support\Facades\Config;
use Winter\Storm\Exception\AjaxException;
use Winter\Storm\Support\Facades\Validator;
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
        if (Config::get('cms.enableCsrfProtection') && (Session::token() != input('_token'))) {
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

    /**
     * Validate reCaptcha
     *
     * @param array $post
     * @throws AjaxException
     * @throws ValidationException
     */
    private function validateReCaptcha(array $post)
    {
        /** CONTINUE IF RECAPTCHA IS DISABLED */
        if (!$this->isReCaptchaEnabled()) {
            return;
        }

        /** PREPARE RECAPTCHA VALIDATION */
        $rules   = ['g-recaptcha-response'           => 'recaptcha'];
        $err_msg = ['g-recaptcha-response.recaptcha' => Lang::get('martin.forms::lang.validation.recaptcha_error')];

        /** DO SECOND VALIDATION */
        $this->validator = Validator::make($post, $rules, $err_msg);

        /** CONTINUE IF VALIDATION PASSES */
        if ($this->validator->passes()) {
            return;
        }

        /** RETURN VALIDATOR OBJECT IF INLINE ERRORS ARE ENABLED */
        if ($this->property('inline_errors') == 'display') {
            throw new ValidationException($this->validator);
        }

        /** THROW ERROR MESSAGES */
        throw new AjaxException($this->exceptionResponse($this->validator, [
            'status'  => 'error',
            'type'    => 'danger',
            'content' => Lang::get('martin.forms::lang.validation.recaptcha_error'),
            'errors'  => json_encode($this->validator->messages()->messages()),
            'jscript' => $this->property('js_on_error'),
        ]));
    }

    /**
     * Return exception response
     *
     * @param $validator
     * @param $params
     * @return array
     */
    private function exceptionResponse($validator, $params): array
    {
        /** FLASH PARTIAL */
        $flash_partial = $this->property('messages_partial', '@flash.htm');

        /** EXCEPTION RESPONSE */
        $response = ['#' . $this->alias . '_forms_flash' => $this->renderPartial($flash_partial, $params)];

        /** INCLUDE ERROR FIELDS IF REQUIRED */
        if ($this->property('inline_errors') != 'disabled') {
            $response['error_fields'] = $validator->messages();
        }

        return $response;
    }
}
