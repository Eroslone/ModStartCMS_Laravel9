<?php

namespace Sabre\DAV\Exception;

use Sabre\DAV;


class NotFound extends DAV\Exception {

    
    function getHTTPCode() {

        return 404;

    }

}
