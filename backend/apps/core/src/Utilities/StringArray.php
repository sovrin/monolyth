<?php

declare(strict_types = 1);

namespace Nc\CoreApp\Utilities;

class StringArray extends GenericArray {

    const string TYPE = 'string';

    /**
     * @param string[] $array
     */
    public function __construct(array $array) {
        $this->setArray($array);
    }
}
