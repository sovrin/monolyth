<?php

declare(strict_types = 1);

namespace Nc\CoreApp;

class JsonContent extends Content {

    const string CONTENT_TYPE = 'application/json';


    public function serialize(): string {
        $data = parent::serialize();

        return json_encode($data);
    }
}
