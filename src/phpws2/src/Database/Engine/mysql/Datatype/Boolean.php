<?php

namespace phpws2\Database\Engine\mysql\Datatype;

/**
 *
 * @author Matt Mcnaney <mcnaney at gmail dot com>
 */
class Boolean extends \phpws2\Database\Datatype\Int {

    protected $signed_limit_low = 0;
    protected $signed_limit_high = 1;
    protected $unsigned_limit_high = 1;

}

