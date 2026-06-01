<?php

namespace DGLab\Core;

use DGLab\Core\Exceptions\ValidationException;

/**
 * Simple Validator class
 */
class Validator
{
    private Request $request;
    private array $errors = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Validate input against rules
     */
    public function validate(array $rules): array
    {
        $data = [];

        foreach ($rules as $field => $ruleSet) {
            $rules = explode('|', $ruleSet);
            $value = $this->request->input($field);

            foreach ($rules as $rule) {
                if (!$this->checkRule($field, $value, $rule)) {
                    break;
                }
            }

            $data[$field] = $value;
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return $data;
    }

    /**
     * Check a single validation rule
     */
    private function checkRule(string $field, mixed $value, string $rule): bool
    {
        // Parse rule with parameters
        if (strpos($rule, ':') !== false) {
            [$ruleName, $param] = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $param = null;
        }

        switch ($ruleName) {
            case 'required':
                if ($value === null || $value === '' || $value === []) {
                    $this->errors[$field] = "The {$field} field is required.";
                    return false;
                }
                break;

            case 'email':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field] = "The {$field} must be a valid email address.";
                    return false;
                }
                break;

            case 'min':
                if ($value !== null) {
                    if (is_string($value) && strlen($value) < (int) $param) {
                        $this->errors[$field] = "The {$field} must be at least {$param} characters.";
                        return false;
                    }
                    if (is_numeric($value) && $value < (int) $param) {
                        $this->errors[$field] = "The {$field} must be at least {$param}.";
                        return false;
                    }
                }
                break;

            case 'max':
                if ($value !== null) {
                    if (is_string($value) && strlen($value) > (int) $param) {
                        $this->errors[$field] = "The {$field} must not exceed {$param} characters.";
                        return false;
                    }
                    if (is_numeric($value) && $value > (int) $param) {
                        $this->errors[$field] = "The {$field} must not exceed {$param}.";
                        return false;
                    }
                }
                break;

            case 'numeric':
                if ($value !== null && !is_numeric($value)) {
                    $this->errors[$field] = "The {$field} must be a number.";
                    return false;
                }
                break;

            case 'integer':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->errors[$field] = "The {$field} must be an integer.";
                    return false;
                }
                break;

            case 'in':
                if ($value !== null) {
                    $allowed = explode(',', $param);
                    if (!in_array($value, $allowed, true)) {
                        $this->errors[$field] = "The {$field} must be one of: {$param}.";
                        return false;
                    }
                }
                break;

            case 'file':
                $file = $this->request->file($field);
                if ($file === null || !$file->isValid()) {
                    $this->errors[$field] = "The {$field} must be a valid file.";
                    return false;
                }
                break;

            case 'mimes':
                $file = $this->request->file($field);
                if ($file !== null && $file->isValid()) {
                    $allowed = explode(',', $param);
                    $ext = strtolower($file->getClientOriginalExtension());
                    if (!in_array($ext, $allowed, true)) {
                        $this->errors[$field] = "The {$field} must be a file of type: {$param}.";
                        return false;
                    }
                }
                break;
        }

        return true;
    }

    /**
     * Get validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
