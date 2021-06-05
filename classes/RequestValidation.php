<?php

namespace Martin\Forms\Classes;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Session;
use Winter\Storm\Support\Facades\Config;
use Winter\Storm\Exception\AjaxException;

trait RequestValidation
{
    public function checkCSRF()
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
}
