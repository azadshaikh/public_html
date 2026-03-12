<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class WebsiteDomainUnique implements Rule
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
        $domaincheck = DB::table('platform_websites')->whereNotIn('status', ['trashed', 'deleted'])->where('domain', $value)->count();

        return $domaincheck <= 0;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        $brand_name = setting('branding_application_name', 'Astero');

        return 'This domain name is already hosted on '.$brand_name.'. Please choose another one. If you think this is a mistake then please contact support.';
    }
}
