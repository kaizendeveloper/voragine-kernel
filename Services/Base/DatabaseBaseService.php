<?php
/**
 * DatabaseBaseService
 *
 * Oggetto che configura e mette a disposizione il servizio di Doctrine
 *
 * @authors: Miguel Delli Carpini, Matteo Scirea, Javier Jara
 */
namespace Voragine\Kernel\Services\Base;

//Accediamo al namespace di Doctrine
use Doctrine\Common;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;



abstract class DatabaseBaseService
{


    //Flags per controllare se il servizio è utilizzabile o meno
    protected $driver_loaded = false;
    protected $host_loaded   = false;
    protected $dbname_loaded = false;
    protected $user_loaded   = false;

    //Qui conserveremo una copia dell'istanza dell'entity manager
    protected $entity_manager;

    //Quest'è la configurazione per Doctrine
    protected $final_config;

    //Qui salveremo il resoconto delle cose che abbiamo trovato, questo sarà il messaggio che vedrete
    //quando l'oggetto lancerà un'eccezione
    protected $error_briefing;

    //Solo a titolo informativo
    public $siteaccess;


    //"Costanti" percorsi per Doctrine
    //--------------------------------------------

    //Percorso finale dove si trovano i file YAML
    private $DOCTRINE_ENTITIES_CFG_PATH;
    //Percorso finale dove si trovano i file compilati PHP
    private $DOCTRINE_COMPILED_ENTITY_BASE_PATH;

    //Percorsi BASE per DEFAULT sia YAML che PHP
    private $DOCTRINE_DEFAULT_ENTITIES_CFG_BASE_PATH;
    private $DOCTRINE_DEFAULT_COMPILED_ENTITY_BASE_PATH;

    //Percorso calcolato relativo ai file PHP dove verranno salvati i file compilati proxy
    private $DOCTRINE_ENTITIES_PROXY_CACHE_PATH;


    private $entity_subfolder_set = false;



    /**
     * DatabaseBaseService constructor.
     * @param null $baseEntitySubFolder Stabilisce la cartella sotto la cartella base delle entità Yaml
     *                                  questo lo si fa perché questo progetto si interfaccia a due DB diversi
     *                                  alla volta
     * @param string $siteaccess        Solo serve come informazione, non fa assolutamente nulla
     * @param null $yamlPart            Per contraddistinguere il pezzo dello Yaml rivolto a questo servizio
     */
    public function __construct($baseEntitySubFolder = null, $yamlPart = null, $siteaccess = 'SCONOCIUTO', $specialConfiguration = null) {

        //Settiamo i parametri per default, visto che dobbiamo fare calcoli è meglio se le mettiamo qui per evitare
        //problemi con le versioni vecchie di PHP (anziché creare costanti)

        //Percorso per DEFAULT base Resources/config
        $this->DOCTRINE_DEFAULT_ENTITIES_CFG_BASE_PATH = APP_BASEDIR . 'Resources' . DIRECTORY_SEPARATOR . 'config';
        //Creiamo percorso di lavoro per YAML partendo dal percorso base di default Resources/config/doctrine
        $this->DOCTRINE_ENTITIES_CFG_PATH = $this->DOCTRINE_DEFAULT_ENTITIES_CFG_BASE_PATH . DIRECTORY_SEPARATOR . 'doctrine' . DIRECTORY_SEPARATOR;

        //Fin qui abbiamo il percorso per default per la configurazione YAML delle entità (nel caso non venga messo nulla)
        //cioè /Resources/config/doctrine'


        //Compiled Entities (ora configuriamo i percorsi per le entità compilate)
        //Iniziando dalle impostazioni per default
        $this->DOCTRINE_DEFAULT_COMPILED_ENTITY_BASE_PATH = APP_BASEDIR . 'Entity';

        //Percorso base dei file compilati partendo dalle impostazioni di default
        $this->DOCTRINE_COMPILED_ENTITY_BASE_PATH = $this->DOCTRINE_DEFAULT_COMPILED_ENTITY_BASE_PATH;

        //A questo punto avremo solo 'Entity' (se non viene passata qualche configurazione speciale)

        //---------------------------------------------------------------------------------------------
        //              OVERRIDE DEI PERCORSI (Configurazione speciale)
        //--------------------------------------------------------------------------------------------
        if(isset($specialConfiguration['db_service'])){
            if(isset($specialConfiguration['db_service']['entity_yaml_base_path'])){

                //Cambiamo il percorso relativo base per i file YAML
                $this->DOCTRINE_ENTITIES_CFG_PATH = $this->DOCTRINE_DEFAULT_ENTITIES_CFG_BASE_PATH .
                    DIRECTORY_SEPARATOR . $specialConfiguration['db_service']['entity_yaml_base_path'] .
                    DIRECTORY_SEPARATOR . 'doctrine' . DIRECTORY_SEPARATOR;

                //Eliminiamo doppioni di slash
                $this->DOCTRINE_ENTITIES_CFG_PATH = $this->cleanDoubleSlash($this->DOCTRINE_ENTITIES_CFG_PATH);
            }

            //Cambiamo il percorso relativo base per i file compilati PHP
            if(isset($specialConfiguration['db_service']['entity_compiled_php_base_path'])){
                $this->DOCTRINE_COMPILED_ENTITY_BASE_PATH = $this->DOCTRINE_DEFAULT_COMPILED_ENTITY_BASE_PATH . DIRECTORY_SEPARATOR .
                    $specialConfiguration['db_service']['entity_yaml_base_path'];

                //Eliminiamo doppioni di slash
                $this->DOCTRINE_COMPILED_ENTITY_BASE_PATH = $this->cleanDoubleSlash($this->DOCTRINE_COMPILED_ENTITY_BASE_PATH);
            }

        }

        //Adesso possiamo impostare il percorso dedicato ai Proxies
        $this->DOCTRINE_ENTITIES_PROXY_CACHE_PATH = $this->cleanDoubleSlash(
            APP_BASEDIR . $this->DOCTRINE_COMPILED_ENTITY_BASE_PATH.
            DIRECTORY_SEPARATOR . 'ProxyCache' . DIRECTORY_SEPARATOR
        );


        //Se ci passano alla costruzione il percorso di default, lo cambiamo pure
        if(!is_null($baseEntitySubFolder)) {
            $this->changeDefaultEntitySubFolder((string) $baseEntitySubFolder);
        }

        //Se ci passano alla costruzione la parte yaml da dove prendere le configurazioni definiamola pure
        if(!is_null($yamlPart)) {
            $this->readConfigFrom((string) $yamlPart);
        }

    }

    public function loadConfigArray($baseInfo = null) {

        if($baseInfo !== null) {
            //Prima validazione, la configurazione dev'essere in un array
            if(is_array($baseInfo)) {
                //Cominciamo a caricare la configurazione impostando gli eventuali valori per default
                //e impostando le bandierine che ci faranno capire se l'oggetto è pronto per lavorare
                // quando riterremo che il caricamento sia stato completato


                //Controlliamo il driver PHP
                if(isset($baseInfo['driver'])){
                    $this->final_config['driver'] = $baseInfo['driver'];
                    $this->driver_loaded = true;
                } else {
                    $this->error_briefing = "- Parametro \"driver\" mancante, il valore dovrebbe essere pdo_mysql \r\n";
                }

                //Controlliamo il campo host
                if(isset($baseInfo['host'])){
                    $this->final_config['host'] = $baseInfo['host'];
                    $this->host_loaded = true;
                } else {
                    $this->error_briefing = "- Parametro \"host\" mancante, mettete l'indirizzo del server MySQL\r\n";
                }

                //Controlliamo il campo port
                if(isset($baseInfo['port'])){
                    $this->final_config['port'] = $baseInfo['port'];
                } else {
                    //Valore per default
                    $this->final_config['port'] = 3306;
                }


                //Controlliamo il campo dbname
                if(isset($baseInfo['dbname'])){
                    $this->final_config['dbname'] = $baseInfo['dbname'];
                    $this->dbname_loaded = true;
                } else {
                    $this->error_briefing = "- Parametro \"dbname\" mancante, inserite il nome del DB\r\n";
                }

                //Controlliamo il campo user
                if(isset($baseInfo['user'])){
                    $this->final_config['user'] = $baseInfo['user'];
                    $this->user_loaded = true;
                } else {
                    $this->error_briefing = "- Parametro \"user\" mancante, inserite il nome utente per accedere al DB\r\n";
                }

                //Controlliamo il campo password
                if(isset($baseInfo['password'])){
                    $this->final_config['password'] = $baseInfo['password'];
                } else {
                    $this->final_config['password'] = null;
                }



                //Controlliamo il campo charset
                if(isset($baseInfo['charset'])){
                    $this->final_config['charset'] = $baseInfo['charset'];
                } else {
                    $this->final_config['charset'] = 'utf8mb4';
                }



                //Non è documentato bene ma è necessario per il salvataggio di caratteri speciali
                if(isset($baseInfo['driver_options'])){
                    $this->final_config['driverOptions'] = $baseInfo['driver_options'];
                } else {
                    $this->final_config['driverOptions'] = array( 1002 =>"SET NAMES utf8mb4");
                }




                if(!$this->validateOperations()) {
                    throw new \Exception("Configurazione \""
                        . $this->cfg_array_key . "\" nello YAML del siteaccess \"" . $this->siteaccess
                        . "\" ha questi errori:\r\n" .  $this->error_briefing);
                }

            }


            //In questo modo possiamo prendere le configurazioni di ogni singola Entity e in più
            //approfittiamo per fare connessione al DB
            //-------------------------------------------
            $config = Setup::createYAMLMetadataConfiguration(array($this->DOCTRINE_ENTITIES_CFG_PATH), true, $this->DOCTRINE_ENTITIES_PROXY_CACHE_PATH);

            //Istanziamo l'Entity Manager
            $this->entity_manager = EntityManager::create($this->getAllConfig(), $config);



        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAllConfig() {
        return $this->final_config;
    }

    /**
     * @return mixed
     */
    public function getEntityManager() {
        return $this->entity_manager;
    }


    /**
     * Controlla se l'oggetto è stato configurato o meno
     * @return boolean
     */
    protected function validateOperations() {
        //Controlliamo se abbiamo i parametri minimi per lavorare
        return $this->driver_loaded && $this->dbname_loaded && $this->user_loaded && $this->host_loaded;
    }

    /**
     * Setta quale parametro dello YAML deve prendere
     * @param $yamlPart
     */
    private function readConfigFrom($yamlPart) {
        $this->cfg_array_key = (string)$yamlPart;
    }

    /**
     * @param $folderBaseNamespace
     * @return $this
     * @todo Implementare la creazione in automatico della cartella se non esiste
     */
    private function changeDefaultEntitySubFolder($folderBaseNamespace){

        //Deve essere stringa
        $folderBaseNamespace = (string)$folderBaseNamespace;

        //Possiamo procedere solo quando non abbiamo impostato precedentemente una subfolder
        if( (strlen($folderBaseNamespace) > 0) && ($this->entity_subfolder_set === false)) {

            //Creiamo la stringa della sottocartella
            $subFolder = '/' . $folderBaseNamespace . '/';

            //Facciamo capire che abbiamo cambiato la default folder
            $this->entity_subfolder_set = true;

            //Concateniamo la subfolder alla configurazione di base e poi eliminiamo gli slash duplicati
            $this->DOCTRINE_DEFAULT_ENTITIES_CFG_BASE_PATH .= $subFolder;
            $this->DOCTRINE_DEFAULT_ENTITIES_CFG_BASE_PATH = preg_replace('/(?<!:)(\/{2,})/i', DIRECTORY_SEPARATOR, $this->DOCTRINE_DEFAULT_ENTITIES_CFG_BASE_PATH);

            $this->DOCTRINE_ENTITIES_PROXY_CACHE_PATH .= $subFolder;
            $this->DOCTRINE_ENTITIES_PROXY_CACHE_PATH = preg_replace('/(?<!:)(\/{2,})/i', DIRECTORY_SEPARATOR, $this->DOCTRINE_ENTITIES_PROXY_CACHE_PATH);

        }

        return $this;
    }


    /**
     * Pulisce gli slash ripetuti in una stringa
     *
     * @param $stringaDaPulire
     * @return mixed
     */
    private function cleanDoubleSlash($stringaDaPulire){

        //Prendiamo il percorso del file, eliminando gli slash DIRECTORY_SEPARATOR e '\' di troppo
        //evitando di toglierli nel caso di http://www.elle.it, invece per i sistemi windows
        //evitiamo di togliere gli inizi delle cartelle di rete \\host\percorso
        return preg_replace('/(?<!:)(\/{2,})|(?<!^)(\\{2,})/i', DIRECTORY_SEPARATOR , $stringaDaPulire);

    }


}