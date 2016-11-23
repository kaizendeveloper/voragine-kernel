<?php
/**
 * FileHandlerService
 *
 * Se ne occupa di tutte le operazioni relative al file system
 *
 * @author: Miguel Delli Carpini
 * @author: Matteo Scirea
 * @author: Javier Jara
 */
namespace Voragine\Kernel\Services\ExportGeneratorService;

use Voragine\Kernel\Services\Base\ServiceLoader;
use Voragine\Kernel\Services\Base\ServiceModelInterface;



class Main implements ServiceModelInterface
{

    //FileHandler Service
    protected $fhandler;

    //Namegenerator
    protected $name_generator;

    //Configurazione sul tipo di output a generare
    protected $feed_formats_allowed;

    //Collezione coi builders
    protected $builders;

    //Base per il nome dell'output file
    protected $base_nome_output_file;

    //Qui salveremo il resoconto delle cose che abbiamo trovato, questo sarà il messaggio che vedrete
    //quando l'oggetto lancerà un'eccezione
    protected $error_briefing;

    //Indica se ci sono i requisiti minimi per poter lavorare
    protected $minimum_req_met;

    //Valore optional per avere un identificativo di siteaccess (utile per i log di console più che altro)
    protected $siteaccess;

    const PARTIAL_FILE_EXT = '.partial';
    const PARTIAL_FILE_SLUG = '_wip';

    const INSTANCE_WRD = 'instance';
    const EXTENSION_WRD = 'extension';

    /**
     * FileHandlerService constructor.
     * @param null $array
     * @param null $siteaccess
     * @throws \ErrorException
     */
    public function __construct($array = null, $siteaccess = null)
    {

        //Init dei flag
        $this->feed_formats_allowed = 0;

        //Default mettiamo che sì
        $this->minimum_req_met = true;

        //Inseriamo l'identificativo
        if(strlen($siteaccess) > 0) {
            $this->siteaccess = $siteaccess;
        }


        //Carichiamo le impostazioni da YAML
        $this->loadConfigArray($array);


        //Dobbiamo avere un name generator per questo servizio
        if(!is_null($this->name_generator) && $this->minimum_req_met){

            //Generiamo il nome di base, usando il giorno di oggi formattando secondo il pattern
            $this->base_nome_output_file =  $this->name_generator->generateTodaysFilenameBasedOnPattern();

        } else {
            $this->minimum_req_met = false;
            $this->error_briefing = "\r\n" . __CLASS__ . " necessita del servizio NameGenerator, non c'è.\r\n";
        }




        //Dobbiamo avere un file handler
        if(!is_null($this->fhandler) && $this->minimum_req_met){

            //Per il momento: nun fa nu gass

        } else {
            $this->minimum_req_met = false;
            $this->error_briefing .= "\r\n" . __CLASS__ . " necessita del servizio FileHandler.\r\n";
        }

        //Se non possiamo lavorare per mancanza di una risorsa muoriamo lanciando un errore
        if(!$this->minimum_req_met){
            throw new \ErrorException($this->error_briefing);
        }

    }




    /**
     * Caricamento delle impostazioni da YAML
     *
     * @param null $yamlConfigArray
     * @throws \Exception
     * @return $this
     */
    public function loadConfigArray($yamlConfigArray = null) {


        if($yamlConfigArray !== null) {
            //Prima validazione, la configurazione dev'essere in un array
            if(is_array($yamlConfigArray)) {

                //Abbiamo bisogno del servizio FileHandler del sistema
                $services = new ServiceLoader();
                $this->fhandler = $services->get('file_handler');
                $this->fhandler->changeOperationsPath('extractions' . DIRECTORY_SEPARATOR . 'importa-csv');

                //Controlliamo il parametro nello YAML per la configurazione dei nomi in output
                //dato il livello in cui si trova la configurazione il servizio NameGeneratorService
                //riuscirà a beccare la sua config
                $this->name_generator = $services->get('name_generator', $yamlConfigArray);


                //Controlliamo le impostazioni su come scriveremo i nomi dei file
                if(isset($yamlConfigArray['feed_format'])) {

                    foreach($yamlConfigArray['feed_format'] as $feedFormat){
                        switch(strtolower($feedFormat)){
                            case 'xml':
                                $this->feed_formats_allowed = $this->feed_formats_allowed | 1;
                                $this->builders['xml'][self::INSTANCE_WRD] = new RSSFeedXMLBuilder();
                                $this->builders['xml'][self::EXTENSION_WRD] = 'xml';
                                break;
                            case 'csv':
                                $this->feed_formats_allowed = $this->feed_formats_allowed | 2;
                                $tmp = new RSSFeedCSVBuilder();

                                //Valori di default per questo progetto
                                $tmp->setSeparatorCharacter('|');

                                if(isset($yamlConfigArray['csv_format'])){
                                    //Cerchiamo impostazione per il delimiter
                                    if(!empty($yamlConfigArray['csv_format']['delimiter'])){
                                        $tmp->setDelimiterCharacter($yamlConfigArray['csv_format']['delimiter']);
                                    }
                                    //Cerchiamo impostazione per il separator
                                    if(!empty($yamlConfigArray['csv_format']['separator'])){
                                        $tmp->setSeparatorCharacter($yamlConfigArray['csv_format']['separator']);
                                    }
                                }

                                $this->builders['csv'][self::INSTANCE_WRD] = $tmp;
                                $this->builders['csv'][self::EXTENSION_WRD] = 'csv';
                                break;
                        }
                    }

                    //Se non abbiamo impostato bene il feed format fermiamo tutto
                    if($this->feed_formats_allowed === 0) {
                        throw new \Exception("FileHandlerService: Parte YAML relativa al 'feed_format:' sbagliata\r\nOpzioni valide\r\n\r\nfeed_format:\r\n -xml\r\n -csv\r\n");
                    }

                } else {

                    //Per default scegliamo xml come formato feed
                    $this->feed_formats_allowed = 1;

                }


            }
        }

        return $this;

    }


    /**
     * Se non vogliamo usare i nomi basati nel giorno attuale possiamo mettere quello che vogliamo
     * @param $name
     */
    public function overrideOutputBaseFilename($name){
        if(count($name) > 0) {
            $this->base_nome_output_file = $name;
        }
    }

    /**
     * Se non vogliamo usare i nomi basati nel giorno attuale possiamo mettere quello che vogliamo
     * @param $builderIdentifier Nome base nella collezione (xml, csv)
     * @param $name
     */
    public function overrideBuilderOutputExtension($builderIdentifier, $name){
        if(count($name) > 0) {
            //Verifichiamo che esista il builder tramite il suo identificatore
            if(isset($this->builders[$builderIdentifier])){
                //Impostiamo l'extension per quel builder
                $this->builders[$builderIdentifier][self::EXTENSION_WRD] = $name;
            }
        }
    }


    /**
     * Per ogni builder caricato si creano i sui pezzi, uno alla volta
     *
     * @param $data
     */
    public function insertElements($data){

        $builderObject = null;

        foreach($this->builders as $builder){

            //Controlliamo se esiste l'istanza
            if(isset($builder[self::INSTANCE_WRD])){
                //Unwrappiamo per facilità d'accesso
                $builderObject = $builder[self::INSTANCE_WRD];
                //Per poi controllare se l'oggetto ha il metodo voluto, prima di chiamarlo
                if(method_exists($builderObject, 'insertElements')){
                    $builderObject->insertElements($data);
                }
            }

        }

    }

    /**
     * Salva in disco una parte del documento da un builder
     *
     * Alcuni builder possono richiedere certi parametri (CSV ad esempio)
     *
     * @param $withoutHeader boolean Stabilisce se si vuole stampare senza l'intestazione, utile quando si
     *                               devono concatenare più CSV
     * @return string
     */
    public function savePartially($withoutHeader = false) {

        $builderObject = null;

        foreach($this->builders as $builder){
            if(isset($builder[self::INSTANCE_WRD])){

                //Unwrappiamo per facilità d'accesso
                $builderObject = $builder[self::INSTANCE_WRD];

                //Per poi controllare se l'oggetto ha il metodo voluto, prima di chiamarlo
                if(method_exists($builderObject, 'generateFinalDocument')){
                    $dataBuffer = $builderObject->generateFinalDocument($withoutHeader);

                    //L'extension del file a generare sarà sempre "partial"
                    //----------------------------------------------------

                    //$completeFilename = $this->base_nome_output_file . self::PARTIAL_FILE_EXT;
                    $completeFilename = $this->base_nome_output_file . self::PARTIAL_FILE_SLUG . '.'. $builder['extension'];
                    //$originalFilename = $this->base_nome_output_file . '.'. $builder['extension'] . '.original';

                    //Se non esiste lo creiamo ex novo
                    if(!$this->fhandler->fileExists($completeFilename)){
                        //Salviamo tramite filehandler se non esiste il file
                        $this->fhandler->saveFileOnDisc($dataBuffer, $completeFilename);
                    } else {
                        //Il file esiste appendiamo questo salvataggio parziale a quello che c'è già
                        $this->fhandler->appendToFile($dataBuffer, $completeFilename);

                    }

                }

                //--------------------------------------------------------------------
                //              Dobbiamo svuotare i buffer dei Builder
                //--------------------------------------------------------------------

                //Prima controlliamo se l'oggetto ha il metodo desiderato prima di eseguirlo
                if(method_exists($builderObject, 'resetBuffer'))
                {
                    //Svuotiamo il buffer di elaborazione
                    $builderObject->resetBuffer();
                }
            }
        }
    }

    /**
     * Rinomina i file parziali mettendo l'apposita extension al posto dell'extension che identifica i file
     * parziali. Dunque "chiudendo" tali file
     *
     */
    public function finalizeFiles(){

        //Controlliamo ogni builder caricato
        foreach($this->builders as $builder) {

            //Agiamo solo se c'è l'istanza
            if (isset($builder[self::INSTANCE_WRD])) {

                //Costruiamo l'extension del file a finalizzare
                //-------------------------------------------------
                $fileExtension = null;

                if(isset($builder[self::EXTENSION_WRD])){

                    $fileExtension = '.' . $builder[self::EXTENSION_WRD];

                }

                //Partiamo dal file parziale
                $partialFilename = $this->base_nome_output_file . self::PARTIAL_FILE_SLUG . $fileExtension;

                //E generiamo il nome finale
                $completeFilename = $this->base_nome_output_file . $fileExtension;

                //Rinominiamo tramite filehandler
                $this->fhandler->renameFile($partialFilename, $completeFilename);

            }
        }
    }


    /**
     * Controlla se c'è un file original (che sarebbe il backup del CSV processato dallo script di estrazione
     * della community di elle)
     *
     * Se c'è significa che questo script è stato eseguito precedentemente, allora questo pezzo si dispone a sovrascrivere
     * il file CSV di partenza con le informazioni contenute nel file con estensione .original in questo modo riparte
     * l'estrazione dal DB della registrazione e verranno appesi gli utenti solo una volta
     *
     * Restituisce il nome del file corrispondente ad oggi
     * @param $modalita
     * @return string
     */
    public function inizializzaCSVFileDiOggi($modalita=null) {

        if ($modalita === 'append') {

            $originalSuffix = '.original';

            //Prendiamo il file CSV più aggiornato dalla cartella condivisa, dove troveremo i file CSV
            $nomeFileDiOggi = $this->name_generator->generateTodaysFilenameBasedOnPattern() . '.csv';

            $originalFilename = $nomeFileDiOggi . $originalSuffix;
            $destinationFilename = $nomeFileDiOggi;

            //Dobbiamo prima controllare se esiste il file original, se esiste significa che lo script è stato eseguito
            //in precedenza quindi prenderemo questo file come il nostro file di partenza

            //CLI OUTPUT
            echo "\r\nCheck iniziale sui file CSV in " . $this->fhandler->getWorkingBasePath() . "\r\n";

            //Se esiste il file di backup e non esiste l'originale creiamo il file di partenza a partire dal backup
            if ($this->fhandler->fileExists($originalFilename)) {
                //Fermiamo tutto se la copiatura va male
                if (!$this->fhandler->copyFile($originalFilename, $destinationFilename)) {
                    exit("\r\nFile .ORIGINAL trovato ma non riesco a creare il file di partenza a partire dal .ORIGINAL\r\n");
                } else {
                    //Se tutto va bene cambiamo i permessi del file destino
                    echo "\r\nCheck iniziale sui file CSV in " . $this->fhandler->getWorkingBasePath() . "\r\n";
                    echo "Copiato il file da: $nomeFileDiOggi$originalSuffix   a:   $nomeFileDiOggi\r\n";
                    $this->fhandler->changePermissions($nomeFileDiOggi, '775');
                }
            } elseif (!$this->fhandler->fileExists($nomeFileDiOggi)) {
                exit("\r\nNon riesco a trovare il file: " . $nomeFileDiOggi . "\r\n");
            }


            //CLI OUTPUT
            echo "\033[01;32m Check dei file CSV finito. Tutto bene con i file CSV.\033[0m \r\n";

            return $nomeFileDiOggi;


        } elseif($modalita === 'cancellaFileTemp'){
            //Prendiamo il file CSV più aggiornato dalla cartella condivisa, dove troveremo i file CSV
            $nomeFileDiOggi = $this->name_generator->generateTodaysFilenameBasedOnPattern() . self::PARTIAL_FILE_SLUG . '.csv';


            //Dobbiamo prima controllare se esiste il file temporaneo
            //in precedenza quindi prenderemo questo file come il nostro file di partenza

            //CLI OUTPUT
            echo "\r\nControllo se c'è il file temporaneo di oggi, sotto " . $this->fhandler->getWorkingBasePath() . "\r\n";

            //Se esiste il file di backup e non esiste l'originale creiamo il file di partenza a partire dal backup
            if ($this->fhandler->fileExists($nomeFileDiOggi)) {
                //Cancelliamo il file
                if (!$this->fhandler->deleteFile($nomeFileDiOggi)) {
                    exit("\r\nNon sono riuscito a cancellale il file temporaneo $nomeFileDiOggi\r\n");
                }
            }
        } else {

        }

    }





}