<?php
/**
 * LoggerService
 *
 * Wrapper per Monolog
 *
 * @authors: Miguel Delli Carpini, Matteo Scirea, Javier Jara
 */
namespace Voragine\Kernel\Services;




use Monolog\Logger;

class LoggerService extends Logger
{

    public function __construct($confParams , $siteaccess) {
        parent::__construct();
    }
}