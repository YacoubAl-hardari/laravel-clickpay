<?php 

namespace ClickPay\Facades;

use Illuminate\Support\Facades\Facade;

class ClickPay extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \ClickPay\ClickPayService::class;
    }
}
