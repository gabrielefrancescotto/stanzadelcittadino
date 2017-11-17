<?php
/**
 * @copyright Copyright (C) 2017 Opencontent Società Cooperativa,  All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @package ocsdc
 */

namespace AppBundle\Protocollo\Exception;

class ParentNotRegisteredException extends BaseException
{
    protected $message = 'Parent item is not registered';

}
