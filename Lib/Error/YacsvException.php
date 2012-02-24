<?php

class YacsvException extends CakeException {

    /**
     * Constructor
     *
     * @param string $message If no message is given 'Not Found Data' will be the message
     * @param string $code Status code, defaults to 404
     */
    public function __construct($message = null, $code = 404) {
        if (empty($message)) {
            $message = __('YacsvException');
        }
        parent::__construct($message, $code);
    }

}