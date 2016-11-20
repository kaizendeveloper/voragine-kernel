<?php
/**
 * TemplateEngineService
 *
 * Configurazione e messa in moto del Template Engine, questo caso Twig
 *
 * @authors: Miguel Delli Carpini, Matteo Scirea, Javier Jara
 */
namespace Voragine\Kernel\Services;

//Twig
use Twig_Loader_Filesystem;
use Twig_Environment;
use Twig_Extension_Debug;
use Twig_SimpleFunction;


class TemplateEngineService
{

    //Consente il debug dei template
    protected $debug_enabled = true;


    //Servizio base dal quale ne abbiamo creato un altro più complesso
    protected $base_service;


    //Qui salveremo il resoconto delle cose che abbiamo trovato, questo sarà il messaggio che vedrete
    //quando l'oggetto lancerà un'eccezione
    protected $error_briefing;

    //Indica se ci sono i requisiti minimi per poter lavorare
    protected $minimum_req_met = false;

    //Valore optional per avere un identificativo di siteaccess (utile per i log di console più che altro)
    protected $siteaccess;


    //PHP-5.3.3 da problemi con la dichiarazioni di costanti, facciamo così allora
    private $TWIG_TEMPLATES_ROOT_PATH;
    private $TWIG_TEMPLATES_CACHE_PATH;

    //Percorsi per la separazione dei templates
    private $namespaces_paths;



    public function __construct($array = null, $siteaccess = '')
    {

        //Settaggio di default dei percorsi per la lavorazione (visto che PHP-5.3.3 da problemi con le costanti)
        //facciamo così
        $this->TWIG_TEMPLATES_ROOT_PATH = APP_BASEDIR . 'Resources/templates/';
        $this->TWIG_TEMPLATES_CACHE_PATH = APP_BASEDIR . 'var/twig/cache/';

        $this->TWIG_ASSETS_RELATIVE_ROOT_PATH = 'Resources/assets/';


        //--------------------------------------------------------------------------------------------------

        //Inseriamo l'identificativo
        if(strlen($siteaccess) > 0) {
            $this->siteaccess = $siteaccess;
        }

        //Così facendo possiamo configurare sia alla costruzione che chiamando il metodo
        if( !is_null($this->loadConfigArray($array)) ){

            //Attiviamo il file loader
            $twigFileLoader = new Twig_Loader_Filesystem( $this->TWIG_TEMPLATES_ROOT_PATH );

            //Impostiamo ogni namespace se dichiarati
            foreach($this->namespaces_paths as $namespace => $relativePath)
            {
                //Eliminiamo qualche '/' di troppo
                $calculatedPath = preg_replace('/(?<!:)(\/{2,})/i', '/' , $this->TWIG_TEMPLATES_ROOT_PATH . '/' . $relativePath );
                $twigFileLoader->addPath($calculatedPath, $namespace);

            }

            //Carichiamo l'engine
            $this->base_service = new Twig_Environment($twigFileLoader, array( 'cache' => $this->TWIG_TEMPLATES_CACHE_PATH ));

            //Abilitando l'auto reload ad ogni modifica TWIG controlla che la versione compilata venga anche aggiornata
            $this->base_service->enableAutoReload();

            //Abilitiamo debug o meno
            if($this->isDebugEnabled()){
                //Abilitiamo il debug
                $this->base_service->addExtension(new Twig_Extension_Debug());
                $this->base_service->enableDebug();
            }

            //Creaiamo il filtro per gli assets
            // an anonymous function
            $filter = new \Twig_SimpleFunction('asset', array($this, 'assetPathGeneration'));

            $this->base_service->addFunction($filter);

        }

    }


    public function loadConfigArray($baseInfo = null) {

        if($baseInfo !== null) {
            //Prima validazione, la configurazione dev'essere in un array
            if(is_array($baseInfo)) {

                //Cominciamo a caricare la configurazione impostando gli eventuali valori per default
                //e impostando la bandierina "Oggetto pronto per lavorare" quando riterremo che il caricamento
                //sia stato completato

                //Controlliamo le impostazioni per il debug
                if(isset($baseInfo['debug'])) {

                    $debugValue = $baseInfo['debug'];

                    //Controlliamo il parametro debug sotto twig
                    switch (true) {
                        case ($debugValue === false):
                            $this->debug_enabled = false;
                        case (strcmp('disabled', strtolower($debugValue)) === 0):
                            $this->debug_enabled = false;
                        case ($debugValue === 0):
                            $this->debug_enabled = false;
                        default:
                            $this->debug_enabled = true;

                    }
                }

                //Controlliamo le impostazioni per i namespace dei template
                if(isset($baseInfo['namespaces'])) {

                    $this->namespaces_paths = $baseInfo['namespaces'];

                }

            }
        }

        return $this;
    }

    /**
     * Restituisce lo stato del debug per i template
     * @return mixed
     * @throws \Exception
     */
    public function isDebugEnabled() {
        //Ucciderà l'esecuzione se l'oggetto non è pronto per lavorare
        $this->validateOperations();

        return $this->debug_enabled;

    }

    /**
     * Controlla se l'oggetto è stato configurato o meno
     * @throws \Exception
     */
    protected function validateOperations(){
        //Qui possiamo stoppare l'esecuzione per mancanza di dati per poter andare avanti
        //ma visto che per questo modulo non è necessario l'arresto ommettiamo questo passaggio

        //if(!$this->minimum_req_met) {
        //    throw new \Exception('MailerConfigurator non può lavorare, controllate nel vostro YAML se esistono i parametri.');
        //}
    }

    /**
     * Trapassiamo il render di twig
     * @param $name
     * @param $context
     * @return mixed
     */
    public function render($name, array $context = array())
    {
        return $this->base_service->render($name, $context);
    }

    /**
     * Funzione per stampare un asset con il percorso giusto di partenza
     *
     * @param null $assetRelativePath
     * @return mixed
     */
    public function assetPathGeneration($assetRelativePath = null)
    {
        $cleanPath = '/' . $this->TWIG_ASSETS_RELATIVE_ROOT_PATH . '/' . $assetRelativePath;
        return preg_replace('/(?<!:)(\/{2,})/i', '/' , $cleanPath);
    }
}