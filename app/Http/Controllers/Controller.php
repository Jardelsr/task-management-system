<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use App\Traits\ErrorResponseTrait;

class Controller extends BaseController
{
    use ErrorResponseTrait;
}