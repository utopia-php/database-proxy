<?php

namespace Utopia\DatabaseProxy\Validator;

use Utopia\Http\Validator\Assoc as FrameworkAssoc;

/**
 * Extention of Assoc validator that allows greater length
 * TODO: Add length param to original validator instead
 */
class Assoc extends FrameworkAssoc
{
    /**
     * Is valid
     *
     * Validation will pass when $value is valid assoc array.
     *
     * @param  mixed  $value
     * @return bool
     */
    public function isValid($value): bool
    {
        if (! \is_array($value)) {
            return false;
        }

        $jsonString = \json_encode($value);
        $jsonStringSize = \strlen($jsonString);

        if ($jsonStringSize > MAX_STRING_SIZE) {
            return false;
        }

        return \array_keys($value) !== \range(0, \count($value) - 1);
    }
}
