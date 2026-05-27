<?php

namespace Kenal\M4api\Validator;

interface PayloadValidatorInterface
{
    public function validatePayload(array $payload): void;
}