<?php
/**
 * Service Loader
 *
 * Factory like model, it instanciates the classes that work as services for the whole application
 *
 * It needs an initial config YAML which specifies the global scope services and establishes the
 * SITEACCESS' configuration file location
 *
 */

namespace Voragine\Kernel\Services\Base;


//Symfony components
use Symfony\Component\Yaml\Yaml;


class ServiceLoader implements \IteratorAggregate
{

    //Default values
    const DEFAULT_CONFIG_PATH = 'Resources/config';
    const DEFAULT_MAIN_CONFIG_FILE = 'mainconfig.yml';

    //String constants used while reading the conf keys
    const SERVICE_CLASS_ID = 'class';
    const SERVICE_CONF_ALIAS_ID = 'conf_alias';
    const SERVICE_MANDATORY_ID  = 'mandatory';

    const CFGK_SERVICE_LOADER   = 'service_loader';
    const CFGK_SERV_NS          = 'services_namespace';
    const CFGK_BASE_PATH        = 'base_path';
    const CFGK_REL_TO_DPATH     = 'relative_to_default_path';
    const CFGK_MAIN_FILENAME    = 'main_filename';


    //Default base namespace for all services
    const DEFAULT_BASE_NAMESPACE = 'Voragine\\Kernel\\Services\\';


    //Siteaccess
    protected $siteaccess;
    protected $siteaccess_config_file_data;

    //For loading the config files
    protected $base_config_path;
    protected $main_config_file;


    //Services
    //-----------------------------------

    //Spazio per le istanze dei servizi
    protected $service_pool;
    protected $service_definition;
    protected $services_namespace;


    protected $special_config_array;


    public function __construct($environment = 'devel', $specialConfigurations = null)
    {

        //Caricamento delle impostazioni
        //-------------------------------------------------

        $this->base_config_path     = self::DEFAULT_CONFIG_PATH;
        $this->main_config_file     = self::DEFAULT_MAIN_CONFIG_FILE;
        $this->services_namespace   = self::DEFAULT_BASE_NAMESPACE;




        //----------------------------------------------------------------------------
        //                          CONFIGURAZIONI SPECIALI
        //----------------------------------------------------------------------------

        //Salviamo le configurazioni speciali, che possono servire per qualsiasi altro servizio, compreso anche
        //questo caricatore
        //$this->special_config_array = $specialConfigurations;


        //Prendiamo il percorso del file iniziale
        $mainConfigFile = $this->configureThisServiceUsing($specialConfigurations);






        //Parse the global YAML configuration (if the file is not readable will throw an exception)
        try {

            $globalConfig = Yaml::parse(file_get_contents($mainConfigFile));

        } catch(\Exception $e){

            //C'è un problema con lo YAML, logghiamo e usciamo
            syslog(LOG_ERR, $e->getMessage());
            echo $e->getMessage() . "\r\n";

            //Dobbiamo loggare qui e uscire
            exit(0);
        }


        //Per la configurazione apposita secondo il siteaccess
        $localConfigFile = null;

        //---------------------------------------------------------------------------------------------
        //                  Verifichiamo se stiamo eseguendo da CLI o da BROWSER
        //---------------------------------------------------------------------------------------------


        if(php_sapi_name() === 'cli' OR defined('STDIN')){

            //--------------------------------------------------------------------
            //                  COMMAND LINE INTERFACE
            //--------------------------------------------------------------------

            //Controlliamo che nella configurazione ci sia l'impostazione del file a leggere

            //Abbiamo già la posizione nell'array dove dovrebbe esserci l'impostazione del file a
            //leggere

            if(isset($globalConfig['siteaccesses'])) {
                foreach ($globalConfig['siteaccesses'] as $key => $siteaccessConfigInfo) {
                    //Il primo livello definisce la descrizione del siteaccess, che verrà contenuta nella variabile $key

                    //Devono esserci almeno: criterio per il rilevamento e il nome di file per tale siteaccess
                    if (strtolower($key) === strtolower($environment)) {
                        $this->siteaccess = $key;
                        $localConfigFile = $siteaccessConfigInfo['file'];

                        //Abbiamo già il necessario (Usciamo dal FOREACH)
                        break;

                    }
                }
            }

            if($this->siteaccess === null) {
                //Non è stato trovato un ambiente da dove prendere le configurazioni

                syslog(LOG_ERR, "Enviroment passato da CLI ma non c'è riferimento al file YAML per \"" . $environment . "\" forse c'è un errore d'indentazione. Controllate il parametro \"file:\" sotto la configurazione del siteaccess nel mainconfig.yml e quello che passate da CLI.");

                exit(0);
            }



        } else {



            //--------------------------------------------------------------------
            //                              BROWSER
            //--------------------------------------------------------------------


            //Parte per Google Cloud
            //--------------------------------------------------------
            if(isset($globalConfig['siteaccesses']))
            {
                foreach($globalConfig['siteaccesses'] as $key => $siteaccessConfigInfo)
                {
                    //Il primo livello definisce la descrizione del siteaccess, che verrà contenuta nella variabile $key

                    //Rilevamento variabili di ambiente Google Cloud
                    //Devono esserci almeno: criterio per il rilevamento e il nome di file per tale siteaccess
                    if(isset($siteaccessConfigInfo['gcloud_env_var']))
                    {
                        if($this->doesSiteaccessMatchUsingGCloud($siteaccessConfigInfo['gcloud_env_var']) === true) {

                            //Controlliamo che nella configurazione ci sia l'impostazione del file a leggere

                            //Abbiamo già la posizione nell'array dove dovrebbe esserci l'impostazione del file a
                            //leggere
                            $this->siteaccess = $key;


                            if(isset($siteaccessConfigInfo['file']))
                            {
                                $localConfigFile = $siteaccessConfigInfo['file'];

                                //Abbiamo già il necessario (Usciamo dal FOREACH)
                                break;

                            } else {
                                syslog(LOG_ERR, "Siteaccess rilevato ma non c'è riferimento al file YAML
 per il siteaccess " . $this->siteaccess . " forse c'è un errore d'indentazione. Controllate il 
 parametro \"file:\" sotto la configurazione del siteaccess nel mainconfig.yml");
                                exit(0);
                            }
                        }
                    }

                    //Parte per web server normali
                    //--------------------------------------------------------

                    //Devono esserci almeno: criterio per il rilevamento e il nome di file per tale siteaccess
                    if(isset($siteaccessConfigInfo['host_pattern']))
                    {
                        if($this->doesSiteaccessMatch($siteaccessConfigInfo['host_pattern']) === true) {

                            //Controlliamo che nella configurazione ci sia l'impostazione del file a leggere

                            //Abbiamo già la posizione nell'array dove dovrebbe esserci l'impostazione del file a
                            //leggere
                            $this->siteaccess = $key;


                            if(isset($siteaccessConfigInfo['file']))
                            {
                                $localConfigFile = $siteaccessConfigInfo['file'];

                                //Abbiamo già il necessario (Usciamo dal FOREACH)
                                break;

                            } else {
                                syslog(LOG_ERR, "Siteaccess rilevato ma non c'è riferimento al file YAML
 per il siteaccess " . $this->siteaccess . " forse c'è un errore d'indentazione. Controllate il 
 parametro \"file:\" sotto la configurazione del siteaccess nel mainconfig.yml");
                                exit(0);
                            }
                        }
                    }
                }
            }
        }







        //------------------------------------------------------------------------------------------
        //                          LETTURA DEFINIZIONE DEI SERVIZI
        //------------------------------------------------------------------------------------------


        if(isset($globalConfig['services'])) {
            $this->service_definition = $globalConfig['services'];
        }

        //Leviamo di mezzo le cose che non ci servono
        unset($key, $siteaccessConfigInfo, $globalConfig, $mainConfigFile, $percorsoLog);



        //------------------------------------------------------------------------------------------
        //Note: At this very point the program already know under what environment is working on
        //------------------------------------------------------------------------------------------

        if($localConfigFile !== null) {

            //Assemble the full path for the siteaccess related configuration file
            $specificSiteaccessFile = APP_BASEDIR . $this->base_config_path . DIRECTORY_SEPARATOR . "siteaccess" . DIRECTORY_SEPARATOR . $localConfigFile;

            //And try to parse that YAML file
            try {

                $this->siteaccess_config_file_data = Yaml::parse(file_get_contents($specificSiteaccessFile));

            } catch(\Exception $e){

                //In case of error, log and stop execution
                $executionError = "Voragione Service Loader - Loading of siteaccess " . $this->siteaccess . " failed";
                syslog(LOG_ERR, $executionError);
                echo $executionError . "\r\n";

                exit(0);

            }


        } else {
            //Output the error and die giving as much hints as possible
            $executionError = "Voragine Service Loader - Loading of the main file " . $this->main_config_file  . " is not possible, check your siteaccess configuration set up";
            echo $executionError . "\r\n";

            //Write on log (useful under Google Cloud App Engine)
            syslog(LOG_ERR, $executionError);

            //Print additional hints
            echo "<pre>";
            $configurazioniServer = print_r($_SERVER, true);
            echo $configurazioniServer;
            echo "</pre>";

            //Put that hint also on the log
            syslog(LOG_ERR, \json_encode($configurazioniServer, 0, 10));

            exit(0);
        }
    }


    /**
     * Per il rilevamento del siteaccess
     * @param $pattern
     * @return bool
     */
    protected function doesSiteaccessMatch($pattern) {


        //Verifica del dominio tramite variabili di ambiente, SERVER_SOFTWARE è visibile sia da NGINX che da APACHE allora
        //tentiamo il rilevamento della variabile contenente il dominio
        $myServer = null;

        $http_host = '';

        preg_match('/(nginx|apache){1}/i', $_SERVER["SERVER_SOFTWARE"], $myServer);

        //Analizziamo la stringa per sapere su quale server stiamo fungendo $myServer[1] contiene la cattura dall'espressione
        //regolare
        try {
            switch (strtolower($myServer[1])) {
                //SU NGINX NON ESISTE SERVER_NAME
                case 'nginx':
                    $http_host = $_SERVER["HTTP_HOST"];

                    break;
                //SU APACHE INVECE Sì
                default:
                    $http_host = $_SERVER["SERVER_NAME"];
            }
        } catch (Exception $error) {
            $this->logger->error($error->getMessage());

        }


        // RILEVAMENTO
        //-----------------------------------------------------------------------------------------------------

        //Se matcha, preg_match resituirà 1 (intero) sennò 0|false, quindi facciamo un cast per restituire un true o false
        return (boolean)(preg_match('/^' . $pattern . '$/i', $http_host));

    }

    /**
     * Per il rilevamento del siteaccess negli ambienti Google App Engine
     * @param $pattern
     * @return bool
     */
    protected function doesSiteaccessMatchUsingGCloud($pattern) {


        // RILEVAMENTO
        //-----------------------------------------------------------------------------------------------------

        //Se matcha, preg_match resituirà 1 (intero) sennò 0|false, quindi facciamo un cast per restituire un true o false

        //In alcuni progetti le variabili d'ambiente non sono su $_ENV ma su $_SERVER
        if(!isset($_ENV['HMI_ENV'])){
            return (boolean)(preg_match('/^' . $pattern . '$/i', $_SERVER['HMI_ENV']));
        }
        return (boolean)(preg_match('/^' . $pattern . '$/i', $_ENV['HMI_ENV']));

    }

    public function getIterator() {
        return new \ArrayIterator($this->service_pool);
    }

    public function getSiteaccess() {
        return $this->siteaccess;
    }

    public function getAllServices() {
        return $this->service_pool;
    }

    /**
     * Carica dinamicamente i servizi LAZY LOAD
     * questo ci consente di creare un'istanza dei servizi SOLO quando vengono utilizzati
     *
     * @param $serviceIdentifier
     * @param $overrideConfigurazione
     * @param $configurazioniSpeciali
     * @return mixed
     * @throws \Exception
     */
    public function getService($serviceIdentifier, $overrideConfigurazione = null, $configurazioniSpeciali = null)
    {

        //Return the object if it's already loaded in memory
        if(isset($this->service_pool[$serviceIdentifier]))
        {
            return $this->service_pool[$serviceIdentifier];

        } else {

            //Check the service definition existence first
            if(isset($this->service_definition[$serviceIdentifier]))
            {
                //Esiste, vediamo se c'è la classe che si deve istanziare
                if(isset($this->service_definition[$serviceIdentifier][self::SERVICE_CLASS_ID]))
                {

                    $classe = $this->service_definition[$serviceIdentifier][self::SERVICE_CLASS_ID];

                    //Check if the service class definition (YAML side) is meant for global namespacing
                    if(!preg_match('/^\\\\{1}/im', $classe)){

                        //The intended namespacing is relative to Voragine's defined services, therefore
                        $classe = $this->cleanDoubleSlash($this->services_namespace . '\\' . $classe);
                    }

                } else {
                    throw new \Exception('The service is defined but the class has not been found, check your ' . $this->main_config_file . ' file');
                }

                //Esiste, vediamo se c'è un alias per leggere la configurazione nel file YAML di siteaccess
                if(isset($this->service_definition[$serviceIdentifier][self::SERVICE_CONF_ALIAS_ID]))
                {
                    //Prendiamo l'alias impostato
                    $alias = $this->service_definition[$serviceIdentifier][self::SERVICE_CONF_ALIAS_ID];

                } else {

                    //L'alias diventa lo stesso nome dell'identificatore se non viene specificato
                    $alias = $serviceIdentifier;

                }


                $configurazione = null;

                //Esiste adesso prendiamo la configurazione da YAML in base al siteaccess ma con l'ALIAS impostato
                //tenete presente che i file dentro i siteaccess possono avere intestazioni diverse per lo stesso
                //servizio
                if(isset($this->siteaccess_config_file_data[$alias]))
                {
                    $configurazione = $this->siteaccess_config_file_data[$alias];
                }
                if(!is_null($overrideConfigurazione)){
                    $configurazione = $overrideConfigurazione;
                }

                //Vero e proprio instaziamento della classe
                $this->service_pool[$serviceIdentifier] = new $classe($configurazione, $this->siteaccess, $configurazioniSpeciali, $this);
                //@todo: migliorare l'incorporazione del service loader all'istanza di ogni servizio


                return $this->service_pool[$serviceIdentifier];

            }
        }
    }

    /**
     * Interfaccia locale per il richiamo dei servizi, utile per avere una miglior notazione da un Executor
     *
     * @param $serviceIdentifier
     * @param $configurazione
     * @param $configurazioneSpeciale Data una chiamata di servizio, consente il passaggio di configurazioni aggiuntive
     *                                mirate per un determinato servizio
     * @return mixed
     */
    public function get($serviceIdentifier, $configurazione = null, $configurazioneSpeciale = null)
    {
        return $this->getService($serviceIdentifier, $configurazione, $configurazioneSpeciale);
    }

    /**
     *  Carica tutti servizi definiti nello YAML principale come servizi obbligatori
     */
    public function initAllMandatoryServices(){

        foreach($this->service_definition as $nomeServizio => $definizione)
        {
            if(isset($definizione[self::SERVICE_MANDATORY_ID])){

                $mandatory = $definizione[self::SERVICE_MANDATORY_ID];


                //Controlliamo il parametro debug sotto twig
                switch (true) {
                    case ($mandatory === true):
                    case (strcmp('true', strtolower($mandatory)) === 0):
                    case ($mandatory === 1):
                        $this->getService($nomeServizio);
                        break;
                    default:


                }
            }
        }
    }


    /**
     * Controlla se un oggetto è di un tipo X
     *
     * @param $objectTypeToCheck
     * @param $variableToCheck
     * @return bool
     */
    public function objectIsOfType($objectTypeToCheck, $variableToCheck)
    {

        //Innanzi tutto verifichiamo che sia un oggetto
        if(is_object($variableToCheck))
        {
            //Sì, allora vediamo se contiene qualche descrizione che lo identifichi
            if(preg_match('/' . (string)$objectTypeToCheck . '/i', @get_class($variableToCheck)) === 1) {

                //E' di questo tipo
                return true;

            } else {

                //E' di un altro tipo
                return false;
            }

        } else {
            //Non è manco un oggetto
            return false;

        }
    }

    /**
     * Le cose da configurare a livello di questo servizio sono i percorsi da dove leggere il file di
     * configurazione iniziale oppure il nome del file di configurazione iniziale
     * oppure tutti e due allo stesso tempo
     *
     * @param $config
     * @return string
     */
    protected function configureThisServiceUsing($config) {

        //Valori per default

        if(!is_null($config)) {


            //YAML files configuration loading
            //-----------------------------------------------------------
            if(is_array($config)) {
                if(isset($config[self::CFGK_SERVICE_LOADER])) {

                    //Base path override
                    if(isset($config[self::CFGK_SERVICE_LOADER][self::CFGK_BASE_PATH])) {

                        //Is it relative to the default config path?
                        if(isset($config[self::CFGK_SERVICE_LOADER][self::CFGK_REL_TO_DPATH]) && $config[self::CFGK_SERVICE_LOADER][self::CFGK_REL_TO_DPATH]){

                            //Set it up relative to the default config path
                            $this->base_config_path = self::DEFAULT_CONFIG_PATH . DIRECTORY_SEPARATOR . $config[self::CFGK_SERVICE_LOADER][self::CFGK_BASE_PATH];

                        } else {
                            //No, the base config path has been changed then
                            $this->base_config_path = DIRECTORY_SEPARATOR . $config[self::CFGK_SERVICE_LOADER][self::CFGK_BASE_PATH];
                        }
                    }

                    //Services Namespace Override
                    if(!empty($config[self::CFGK_SERV_NS])) {

                        $this->services_namespace = $config[self::CFGK_SERV_NS];
                    }

                    //Main config filename override
                    if(isset($config[self::CFGK_SERVICE_LOADER][self::CFGK_MAIN_FILENAME])) {
                        $this->main_config_file = $config[self::CFGK_SERVICE_LOADER][self::CFGK_MAIN_FILENAME];
                    }
                }
            }
        }

        //Construct the main configuration file full path
        $mainConfigFile = APP_BASEDIR . $this->base_config_path . DIRECTORY_SEPARATOR .$this->main_config_file;

        //Clean the eventually repeated double slashes
        $mainConfigFile = $this->cleanDoubleSlash($mainConfigFile);

        return $mainConfigFile;

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

    public function retrieveYamlParam($key){
        return $this->recursiveArraySeek($key, $this->siteaccess_config_file_data);
    }

    /**
     * Funzione copiata da PHP e modificata alla cazzo, mi serviva per cercare sia un valore che una key
     * ricorsivamente dentro un array
     *
     * @param $needle
     * @param $haystack
     * @return bool|int|string
     */
    public function recursive_array_search($needle,$haystack) {
        foreach($haystack as $key=>$value) {

            if($needle === $value) {
                return true;
            }
            if($needle === $key){
                return true;
            }
            if(is_array($value)){
                if($this->recursive_array_search($needle,$value)){
                    return array($key=>$value);
                }
            }
            if(is_array($value)){
                if($this->recursive_array_search($key,$value)){
                    return array($key=>$value);
                }
            }

        }
        return false;
    }
    /**
     * Funzione copiata da PHP e modificata alla cazzo, mi serviva per cercare sia un valore che una key
     * ricorsivamente dentro un array
     *
     * @param $needle
     * @param $haystack
     * @return bool|int|string
     */
    protected function recursiveArraySeek($needle,$haystack) {

        //Il modo migliore per leggere un array e prende le sue key
        //senza conoscere l'array precedentemente
        foreach($haystack as $key=>$value) {

            //Confrontiamo vs valore
            if($needle === $value)
            {
                //Sì, diamo tutto il pezzo
                return array($key => $value);
            }
            //Confrontiamo vs chiave
            if($needle === $key)
            {
                //Sì, diamo tutto il pezzo
                return array($key => $value);
            }

            //Né la chiave né il valore combaciano, ma non abbiamo esplorato tutta la struttura dell'array
            if(is_array($value))
            {
                //Tramite la ricorsione, spulciamo ulteriormente alla ricerca del valore nell'array
                $check = $this->recursiveArraySeek($needle, $value);
                if(!is_null($check))
                {
                    return $check;
                }
            }

        }
        return null;
    }
}