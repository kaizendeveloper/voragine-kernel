<?php
/**
 * Modello base per i Controller
 *
 * @authors: Miguel Delli Carpini, Matteo Scirea, Javier Jara
 */

namespace Voragine\Kernel\Definitions\Controllers;



//Namespace necessari per accedere agli oggetti Symfony
//------------------------------------------------------


//Namespace necessari per avviare l'applicazione secondo il caso
use Voragine\Kernel\Services\Base\ServiceLoader;

class ControllerSkeleton
{
    //Contenitore per gli eventuali servizi
    protected $services;


    /**
     * Assegna un contenitore di servizi ai Controller dell'applicazione
     * @param ServiceLoader $servicePool
     */
    public function assignServicePool(ServiceLoader $servicePool)
    {
        $this->services = $servicePool;
    }
}