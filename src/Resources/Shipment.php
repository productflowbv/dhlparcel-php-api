<?php

namespace Mvdnbrk\DhlParcel\Resources;

use Tightenco\Collect\Support\Collection;

class Shipment extends BaseResource
{
    /** @var string */
    public $id;

    /** @var string */
    public $barcode;

    /** @var string */
    public $return_barcode;

    /** @var string */
    public $label_id;

    /** @var \Tightenco\Collect\Support\Collection */
    public $pieces;

    /** @var \Tightenco\Collect\Support\Collection */
    public $return_pieces;


    public function __construct(array $attributes = [])
    {
        $this->pieces = new Collection;

        parent::__construct($attributes);
    }
}
