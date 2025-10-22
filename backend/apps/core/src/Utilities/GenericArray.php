<?php

declare(strict_types = 1);

namespace Nc\CoreApp\Utilities;

use JsonSerializable;
use ReturnTypeWillChange;

abstract class GenericArray implements JsonSerializable {

    const string TYPE = '';

    /**
     * @var int[] array
     */
    private array $array;

    public function getArray(): array {
        return $this->array;
    }

    protected function setArray(array $array): void {
        $this->array = $array;
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize() {
        return $this->array;
    }
}
