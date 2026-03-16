<?php

namespace Modules\CMS\Services\Components;

/**
 * Form Response Component
 * Displays success/error messages from session
 * Usage: {form_response}
 */
class FormResponseComponent extends ThemeComponent
{
    public function render(array $params, $template = null): string
    {
        $html = '';

        // Check for success message
        if (session()->has('success')) {
            $message = session('success');
            $html .= '<div class="alert alert-success alert-dismissible fade show" role="alert">';
            $html .= '<i class="ri-checkbox-circle-line me-2"></i>';
            $html .= $this->escape($message);
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            $html .= '</div>';
        }

        // Check for error message
        if (session()->has('error')) {
            $message = session('error');
            $html .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
            $html .= '<i class="ri-error-warning-line me-2"></i>';
            $html .= $this->escape($message);
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            $html .= '</div>';
        }

        // Check for warning message
        if (session()->has('warning')) {
            $message = session('warning');
            $html .= '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
            $html .= '<i class="ri-alert-line me-2"></i>';
            $html .= $this->escape($message);
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            $html .= '</div>';
        }

        // Check for info message
        if (session()->has('info')) {
            $message = session('info');
            $html .= '<div class="alert alert-info alert-dismissible fade show" role="alert">';
            $html .= '<i class="ri-information-line me-2"></i>';
            $html .= $this->escape($message);
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            $html .= '</div>';
        }

        // Check for validation errors
        if (session()->has('errors')) {
            $errors = session('errors');
            if ($errors->any()) {
                $html .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                $html .= '<i class="ri-error-warning-line me-2"></i>';
                $html .= '<strong>There were some errors with your submission:</strong>';
                $html .= '<ul class="mb-0 mt-2">';
                foreach ($errors->all() as $error) {
                    $html .= '<li>'.$this->escape($error).'</li>';
                }

                $html .= '</ul>';
                $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                $html .= '</div>';
            }
        }

        return $html;
    }
}
