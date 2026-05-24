<?php

namespace DGLab\Core\Exceptions;

use Psr\Container\NotFoundExceptionInterface;
use Exception;

class EntryNotFoundException extends Exception implements NotFoundExceptionInterface
{
}
