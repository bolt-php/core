<?php

namespace framework\components;

use framework\Component;
use framework\validation\Validation;
use stdClass;

class Validator extends Component
{
    public function addValidator(string $name, callable|object $class): void
    {
        Validation::addValidator($name, $class);
    }

    public function validate($data, array $rules): array
    {
        return Validation::validate($data, $rules);
    }
}