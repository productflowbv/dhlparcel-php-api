<?php

namespace Mvdnbrk\DhlParcel\Resources;

use Illuminate\Support\Collection;

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

    /** @var \Illuminate\Support\Collection */
    public $pieces;

    /** @var \Illuminate\Support\Collection */
    public $return_pieces;


    public function __construct(array $attributes = [])
    {
        $this->pieces = new Collection;
        $this->return_pieces = new Collection;

        parent::__construct($attributes);
    }
}
