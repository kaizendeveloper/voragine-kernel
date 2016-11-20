<?php
/**
 * ConfiguratorWrapper
 *
 * Punto di partenza per la configurazione usata per l'applicazione
 *
 * Stabilisce la lettura dello YAML principale e a partire da lì va a leggere in base al
 * SITEACCESS il file di configurazione YAML per gli altri pezzi dell'applicazione
 *
 * Ad esempio: la configurazione per il DB, l'HTTP crawler e il mailer
 *
 */

namespace Voragine\Kernel\Services\Base;

//Entriamo nello spazio di YAML
use Voragine\Kernel\Services;
use Voragine\Kernel\Services\ErrorDebugHandlerService;
use Voragine\Kernel\Services\ImageHandlerService;
use Voragine\Kernel\Services\RouterService;
use Voragine\Kernel\Services\TemplateEngineService;
use Voragine\Kernel\Services\EnvironmentDetectorService;

//Symfony components
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

//Monolog
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

//Spazio dei configuratori
use Voragine\Kernel\Services\DatabaseConnectionService;
use Voragine\Kernel\Services\MailerService;

class ServiceLoader implements \IteratorAggregate
{

    const DEFAULT_CONFIG_PATH = 'Resources/config';

    const DEFAULT_MAIN_CONFIG_FILE = 'mainconfig.yml';

    //Stringhe constanti che si trovano nella configurazione dei servizi
    const SERVICE_CLASS_ID = 'class';
    const SERVICE_CONF_ALIAS_ID = 'conf_alias';
    const SERVICE_MANDATORY_ID = 'mandatory';

    const LOG_FILE_PATH = 'var/log';
    const LOG_FILENAME = 'all-logs.log';

    const BASE_NAMESPACE = 'Voragine\\Kernel\\Services\\';


    //Siteaccess attuale
    protected $siteaccess;
    protected $siteaccess_config_file_data;

    //Per il caricamento dei file
    protected $base_config_path;
    protected $main_config_file;


    //Servizi
    //-----------------------------------

    //Spazio per le istanze dei servizi
    protected $service_pool;
    protected $service_definition;

    protected $logger;

    protected $special_config_array;


    public function __construct($environment = 'devel', $specialConfigurations = null)
    {

        //Caricamento delle impostazioni
        //-------------------------------------------------


        $this->base_config_path = self::DEFAULT_CONFIG_PATH;
        $this->main_config_file = self::DEFAULT_MAIN_CONFIG_FILE;

        //Ci sono dei servizi che devono esserci per default, ad esempio il logger e l'error handler

        //Abilitiamo il logger


        //Instanziamo prima il logger così
        //Rendiamolo un servizio per tutta l'applicazione
        $this->logger = new Logger('FeedSystem');

        //Percorso dove andrà a scrivere il nostro logger
        $percorsoLog = APP_BASEDIR . self::LOG_FILE_PATH;

        //Prima di creare lo StreamHandler controlliamo se esiste il percorso per il log, altrimenti Monolog fallirà
        if(!$this->checkIfDirectoryExists($percorsoLog))
        {

            $this->createDirectory($percorsoLog);

        }

        //configuriamo l'output del logger
        $this->logger->pushHandler(new StreamHandler($percorsoLog . DIRECTORY_SEPARATOR . self::LOG_FILENAME, Logger::DEBUG, false, 0777));



        //----------------------------------------------------------------------------
        //                          CONFIGURAZIONI SPECIALI
        //----------------------------------------------------------------------------

        //Salviamo le configurazioni speciali, che possono servire per qualsiasi altro servizio, compreso anche
        //questo caricatore
        $this->special_config_array = $specialConfigurations;


        //Prendiamo il percorso del file iniziale
        $mainConfigFile = $this->configureThisServiceUsing($specialConfigurations);






        //Parsiamo lo Yaml con la configurazione globale (se non si riesce a beccare il file il risultato sarà null)
        try {

            $globalConfig = Yaml::parse(file_get_contents($mainConfigFile));

        } catch(\Exception $e){

            //C'è un problema con lo YAML, logghiamo e usciamo
            $this->logger->error($e->getMessage());

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

                $this->logger->error("Enviroment passato da CLI ma non c'è riferimento al file YAML per \"" . $environment . "\" forse c'è un errore d'indentazione. Controllate il parametro \"file:\" sotto la configurazione del siteaccess nel mainconfig.yml e quello che passate da CLI.");

                exit(0);
            }



        } else {



            //--------------------------------------------------------------------
            //                              BROWSER
            //--------------------------------------------------------------------



            if(isset($globalConfig['siteaccesses']))
            {
                foreach($globalConfig['siteaccesses'] as $key => $siteaccessConfigInfo)
                {
                    //Il primo livello definisce la descrizione del siteaccess, che verrà contenuta nella variabile $key

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
                                $this->logger->error("Siteaccess rilevato ma non c'è riferimento al file YAML
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
        //Nota: A questo punto il programma dovrebbe sapere su quale ambiente sta lavorando
        //------------------------------------------------------------------------------------------


        if($localConfigFile !== null) {

            //Creiamo percorso di lettura e tentiamo il parsing del file
            $specificSiteaccessFile = APP_BASEDIR . $this->base_config_path . "/siteaccess/" . $localConfigFile;
            //Parsiamo il file specifico secondo l'ambiente
            //(se non si riesce a beccare il file il risultato sarà null)
            $this->siteaccess_config_file_data = Yaml::parse(file_get_contents($specificSiteaccessFile));

            //In base al siteaccess attiviamo il servizi che devono esserci per forza
            if($this->siteaccess_config_file_data === null)
            {

                $executionError = "Caricamento del file YAML per il siteaccess " . $this->siteaccess . " non riuscito";
                echo $executionError;
                $this->logger->error($executionError);
                exit(0);

            }

        } else {
            $executionError = "Impossibile caricare il file principale " . self::DEFAULT_MAIN_CONFIG_FILE . " non posso configurare niente, controllate che sia a posto.";
            echo $executionError;
            $this->logger->error($executionError);
            exit(0);
        }
    }











    // -------------------------------------------------------------------------------------------------
    // ------------------------------------------ LIBRERIE ---------------------------------------------
    // -------------------------------------------------------------------------------------------------


    /**
     * Crea ricorsivamente un percorso settando nel giro di elaborazione i permessi per ogni livello del percorso
     *
     * @param null $directory
     * @return null
     */
    protected function createDirectory($directory = null) {

        //Nel caso non ci venga passato il nome del file a scrivere, usciamo gracilmente
        if ($directory === null) {
            return null;
        }

        //Prendiamo il percorso del file, eliminando gli slash '/' e '\' di troppo
        //evitando di toglierli nel caso di http://www.elle.it, invece per i sistemi windows
        //evitiamo di togliere gli inizi delle cartelle di rete \\host\percorso
        $path = preg_replace('/(?<!:)(\/{2,})|(?<!^)(\\{2,})/i', '/', $directory);

        //Dobbiamo capire se ci troviamo in un sistema Windows o Linux compatible


        //Prendiamo i pezzi che compongono il percorso, considerando sia i sistemi Windows che quelli Linux
        //cioè /var/www/...... che C:\Apache2\htdocs\...

        $path_parts = preg_split ( '/(\\\\|\/)/i' , $path);



        //Dobbiamo definire fuori per poterlo usare bene dentro il ciclo
        $assembledDirectory = '';

        //Gira per ogni pezzo e ricomponi man mano tutto il percorso desiderato
        foreach($path_parts as $directoryPart) {


            //Dopo l'estrazione, nei sistemi Linux laddove c'è uno slash da solo l'elemento nell'array risultante
            //sarà vuoto tipo all'inizio di un percorso, ad esempio /var/www, per questo motivo dobbiamo agire
            //solo sui valori non vuoti
            if($directoryPart != '') {

                //Ed è qui dove construiamo il percorso nel modo valido per ciascun sistema
                if(DIRECTORY_SEPARATOR === '/'){
                    //Unix like: prima / e poi pezzo percorso -> /var /www
                    $assembledDirectory .=  DIRECTORY_SEPARATOR . $directoryPart;
                } elseif (DIRECTORY_SEPARATOR === '\\'){
                    //Windows like: Prima percorso e poi slash al contrario -> C:\ Apache2\
                    $assembledDirectory .=  $directoryPart . DIRECTORY_SEPARATOR;
                }

                //Controlliamo se esiste la directory
                if(is_dir($assembledDirectory) === false) {

                    //Se non esiste la creiamo e cambiamo i permessi
                    if(mkdir($assembledDirectory) === false)
                    {
                        echo 'Avvio impossibilitato poiché non posso creare la cartella ' . $assembledDirectory;
                        exit(0);
                    }
                    chmod($assembledDirectory, octdec('0775'));

                }

            }
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
     * @return mixed
     */
    public function getService($serviceIdentifier, $overrideConfigurazione = null)
    {

        //Se abbiamo già l'istanza del servizio, non c'è niente da fare, diamo quella
        if(isset($this->service_pool[$serviceIdentifier]))
        {
            return $this->service_pool[$serviceIdentifier];

        } else {

            //Vediamo se esiste la definizione del servizio
            if(isset($this->service_definition[$serviceIdentifier]))
            {
                //Esiste, vediamo se c'è la classe che si deve istanziare
                if(isset($this->service_definition[$serviceIdentifier][self::SERVICE_CLASS_ID]))
                {
                    //Prendiamo la classe ed inseriamo il namespace giusto in base
                    $classe = self::BASE_NAMESPACE . $this->service_definition[$serviceIdentifier][self::SERVICE_CLASS_ID];

                } else {
                    throw new Exception('Servizio definito ma la classe non è stata trovata, controllate il ' . self::DEFAULT_MAIN_CONFIG_FILE);
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
                $this->service_pool[$serviceIdentifier] = new $classe($configurazione, $this->siteaccess, $this->special_config_array);



                return $this->service_pool[$serviceIdentifier];

            }
        }
    }

    /**
     * Interfaccia locale per il richiamo dei servizi, utile per avere una miglior notazione da un Executor
     *
     * @param $serviceIdentifier
     * @param $configurazione
     * @return mixed
     */
    public function get($serviceIdentifier, $configurazione = null)
    {
        return $this->getService($serviceIdentifier, $configurazione);
    }

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


    protected function checkIfDirectoryExists($directory = null) {
        //Nel caso non ci venga passato il nome del file a scrivere, usciamo gracilmente
        if($directory === null) {
            return null;
        }

        //Puliamo le cache di scrittura su disco
        clearstatcache();

        //Eliminiamo eventuali '/' di troppo
        $builtDir = preg_replace('/(?<!:)(\/{2,})/i', '/' , APP_BASEDIR . '/' . $directory);




        //Controlliamo se sulla cartella di lavoro ci si può scrivere
        if(is_dir($builtDir) && is_writable($builtDir)){
            return true;
        } else {
            return false;
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

            //Vediamo se c'è una configurazione speciale rivolto al caricatore

            if(is_array($config)) {
                if(isset($config['service_loader'])) {
                    if(isset($config['service_loader']['base_path'])) {
                        $this->base_config_path = self::DEFAULT_CONFIG_PATH . DIRECTORY_SEPARATOR . $config['service_loader']['base_path'];
                    }
                    if(isset($config['service_loader']['main_filename'])) {
                        $this->main_config_file = $config['service_loader']['main_filename'];
                    }
                }
            }
        }

        $mainConfigFile = APP_BASEDIR . $this->base_config_path . DIRECTORY_SEPARATOR .$this->main_config_file;

        //Eliminiamo gli eventuali doppi slash
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
}