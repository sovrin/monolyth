<?php

declare(strict_types = 1);

namespace Nc\CoreApp\Utilities;

class IntArray extends GenericArray {

    const string TYPE = 'integer';

    /**
     * @param int[] $array
     */
    public function __construct(array $array) {
        $this->setArray($array);
    }
}
