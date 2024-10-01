<?php
 
namespace App\Rules;

use App\Helpers\GeneralHelper;
use Illuminate\Contracts\Validation\Rule;
use MongoDB\BSON\UTCDateTime;

class FutureDate implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return new UTCDateTime() < GeneralHelper::ToMongoDateTime($value);
    }
 
    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must be in future.';
    }

}