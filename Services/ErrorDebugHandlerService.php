<?php
/**
 * ErrorDebugHandlerService
 *
 * Configura l'handler degli errori per l'applicazione
 *
 * @authors: Miguel Delli Carpini, Matteo Scirea, Javier Jara
 */
namespace Voragine\Kernel\Services;

use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;


class ErrorDebugHandlerService
{


    //Dato un YAML di configurazione questa sarebbe la stringa che identifica il pezzo da prelevare
    const CFG_ARRAY_KEY = 'error_debug_handler';

    protected $siteaccess;

    protected $enabled;



    public function __construct($array = null, $siteaccess = '')
    {
        //Inseriamo l'identificativo
        if(strlen($siteaccess) > 0) {
            $this->siteaccess = $siteaccess;
        }

        //Così facendo possiamo configurare sia alla costruzione che chiamando il metodo
        if( !is_null($this->loadConfigArray($array)) )
        {
            if($this->enabled)
            {
                $errorHandler = new Run();

                //Configuriamo il PrettyPageHandler:
                $errorPage = new PrettyPageHandler();

                $errorPage->setPageTitle("Feed Engine Error Report"); // Set the page's title

                $errorHandler->pushHandler($errorPage);
                $errorHandler->register();

            }
        }

    }


    public function loadConfigArray($baseInfo = null) {

        if($baseInfo !== null) {
            //Prima validazione, la configurazione dev'essere in un array
            if(is_array($baseInfo)) {

                //Cominciamo a caricare la configurazione impostando gli eventuali valori per default


                //Controlliamo se è abilitato per diventare l'error handler dell'applicazione
                if(isset($baseInfo['enabled'])) {
                    //Controlliamo il parametro
                    switch (true) {
                        case (!$baseInfo['enabled']):
                            $this->enabled = false;
                            break;
                        case (strcmp('false', strtolower($baseInfo['enabled'])) === 0):
                            $this->enabled = false;
                            break;
                        case ($baseInfo['enabled'] === 0 || $baseInfo['enabled'] === false):
                            $this->enabled = false;
                            break;
                        default:
                            $this->enabled = true;

                    }
                } else {
                    //Per default sarà false
                    $this->enabled = false;
                }

            }
        }

        return $this;
    }


}