<?php

function validate($data, $rules)
{
    $errors = [];

    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';
        $rulesArray = explode('|', $rule);

        foreach ($rulesArray as $r) {

            if ($r === 'required' && empty($value)) {
                $errors[$field] =
                    ucfirst($field) . ' is required';
                break;
            }

            if (
                $r === 'email' &&
                !filter_var($value, FILTER_VALIDATE_EMAIL)
            ) {
                $errors[$field] =
                    'Invalid email format';
                break;
            }

            if (strpos($r, 'min:') === 0) {

                $min = (int) substr($r, 4);

                if (strlen($value) < $min) {
                    $errors[$field] =
                        ucfirst($field) .
                        ' must be at least ' .
                        $min .
                        ' characters';
                    break;
                }
            }

            if (strpos($r, 'max:') === 0) {

                $max = (int) substr($r, 4);

                if (strlen($value) > $max) {
                    $errors[$field] =
                        ucfirst($field) .
                        ' must not exceed ' .
                        $max .
                        ' characters';
                    break;
                }
            }
        }
    }

    return $errors;
}

function old($key, $default = '')
{

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST[$key])
    ) {
        return $_POST[$key];
    }

    if (isset($_SESSION['_old_input'][$key])) {
        return $_SESSION['_old_input'][$key];
    }

    return $default;
}

function saveFormState($errors)
{

    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_old_input'] = $_POST;
}

function restoreFormErrors()
{

    $errors = $_SESSION['_form_errors'] ?? [];

    unset($_SESSION['_form_errors']);

    return $errors;
}

function clearOldInput()
{

    unset($_SESSION['_old_input']);
}
