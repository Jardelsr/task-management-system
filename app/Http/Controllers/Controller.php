<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use App\Traits\ErrorResponseTrait;
use App\Traits\SuccessResponseTrait;

class Controller extends BaseController
{
    use ErrorResponseTrait, SuccessResponseTrait;
}