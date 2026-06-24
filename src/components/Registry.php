<?php

namespace framework\components;

use framework\Component;

/**
 * The registry allows for registration / resolution for string keys
 * to values
 */
class Registry extends Component {
    protected $mp = [];

    public function set(string $key, mixed $value) {
        $this->mp[$key] = $value;
    }

    public function get(string $key, mixed $default = null) {
        return $this->mp[$key] ?? $default;
    }

    public function has(string $key) {
        return isset($this->mp[$key]);
    }
}