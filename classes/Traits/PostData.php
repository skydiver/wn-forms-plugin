<?php

namespace Martin\Forms\Classes\Traits;

use Martin\Forms\Classes\BackendHelpers;

trait PostData
{
    /**
     * Apply required transformations to form data
     *
     * @return array
     */
    private function preparePost(): array
    {
        $allowed_fields = $this->property('allowed_fields');

        if (empty($allowed_fields)) {
            return input();
        }

        $post = [];

        foreach ($allowed_fields as $field) {
            $post[$field] = input($field);
        }

        if ($this->isReCaptchaEnabled()) {
            $post['g-recaptcha-response'] = input('g-recaptcha-response');
        }

        if ($this->property('sanitize_data') == 'htmlspecialchars') {
            $post = $this->sanitize($post);
        }

        return $post;
    }

    /**
     * Sanitize form data using PHP "htmlspecialchars" function
     *
     * @param array $post
     * @return array
     */
    private function sanitize(array $post): array
    {
        return BackendHelpers::array_map_recursive(function ($value) {
            return htmlspecialchars($value, ENT_QUOTES);
        }, $post);
    }
}
