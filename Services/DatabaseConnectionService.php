<?php
/**
 * DatabaseConnectionService
 *
 * Oggetto che configura e mette a disposizione il servizio di Doctrine
 *
 * @authors: Miguel Delli Carpini, Matteo Scirea, Javier Jara
 */
namespace Voragine\Kernel\Services;

use Voragine\Kernel\Services\Base\DatabaseBaseService;


class DatabaseConnectionService extends DatabaseBaseService
{


    /**
     * DatabaseCommunityService constructor.
     * @param null $array Yaml grezzo
     * @param string $siteaccess Identificativo sul siteaccess
     */
    public function __construct($array = null, $siteaccess = null, $specialConfig = null)
    {

        //Inizializziamo la classe madre (parent class)
        //Controllate la documentazione nella classe madre per capire il costrutto
        //Primo parametro: prendere la cartella di default
        //Secondo parametro: Prende per default dal pezzo Yaml database
        parent::__construct(null, null, $siteaccess, $specialConfig);


        //Dovrebbe caricare l'instanza dell'entity manager
        $this->loadConfigArray($array);

    }

}