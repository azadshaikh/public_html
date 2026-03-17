<?php

namespace Modules\Platform\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Modules\Platform\Definitions\TldDefinition;

class TldRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'tld' => ['required', 'string', 'max:255', $this->uniqueRule('tld')],
            'whois_server' => ['nullable', 'string', 'max:255'],
            'pattern' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'affiliate_link' => ['nullable', 'string', 'url'],
            'status' => ['nullable', 'boolean'],
            'is_main' => ['nullable', 'boolean'],
            'is_suggested' => ['nullable', 'boolean'],
            'tld_order' => ['nullable', 'integer'],
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new TldDefinition;
    }
}
