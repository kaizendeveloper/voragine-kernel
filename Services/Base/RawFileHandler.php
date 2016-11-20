<?php
/**
 * Raw File Handler
 *
 * File Handler a livello Kernel
 *
 * Cioè un file handler con la capacità di fare danni, può leggere ovunque e scrivere ovunque
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

class RawFileHandler
{

    //Visto che su PHP 5.3.3 è un casino lavorare con le costanti ne facciamo una variabile da quello che dovrebbe
    //essere una costante
    protected $WORKING_BASE_PATH;
    protected $REL_WORK_PATH;


    //Estensione per default
    protected $file_extension;


    //Qui salveremo il resoconto delle cose che abbiamo trovato, questo sarà il messaggio che vedrete
    //quando l'oggetto lancerà un'eccezione
    protected $error_briefing;

    const HANDLER_NAME = 'Raw File Handler 1.0';

    /**
     * Constructor.
     *
     */
    public function __construct()
    {

        //Sarebbe (app path) + (default relative path)
        $this->WORKING_BASE_PATH = $this->cleanDoubleSlash(APP_BASEDIR);

    }


    /**
     * Controlla l'esistenza di una cartella
     *
     * @param null $directory
     * @return bool|null
     */
    public function checkIfDirectoryExists($directory = null) {
        //Nel caso non ci venga passato il nome del file a scrivere, usciamo gracilmente
        if($directory === null) {
            return null;
        }

        //Puliamo le cache di scrittura su disco
        clearstatcache();

        $builtDir = $this->cleanDoubleSlash($this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $directory);


        //Controlliamo se sulla cartella di lavoro ci si può scrivere
        if(is_dir($builtDir) && is_writable($builtDir)){
            return true;
        } else {
            return false;
        }
    }

    /**
     * Controlla se esiste un file o meno
     *
     * @param null $filenameSlug
     * @return bool|null
     */
    public function fileExists($filenameSlug = null)
    {
        //Nel caso non ci venga passato il nome del file a scrivere, usciamo gracilmente
        if ($filenameSlug === null) {
            return null;
        }

        $reformedSlug = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $filenameSlug;
        $reformedSlug = $this->cleanDoubleSlash($reformedSlug);

        return is_file($reformedSlug) && file_exists($reformedSlug);

    }

    /**
     * Salva in un file il contenuto di $data
     * @param $data
     * @param null $filenameOverride
     * @return $this|null
     * @throws \Exception
     */
    public function saveFileOnDisc($data, $filenameOverride = null)
    {
        //Nel caso non ci venga passato il nome del file a scrivere, ci creiamo noi un nome per il file
        //a partire dalle informazioni di default
        if($filenameOverride === null) {
            return null;
        }

        //Puliamo le cache di scrittura su disco
        clearstatcache();

        //Controlliamo se sulla cartella di lavoro ci si può scrivere
        if(is_dir($this->WORKING_BASE_PATH) && is_writable($this->WORKING_BASE_PATH)){

            $filenameFullPath = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $filenameOverride;

            //Preveniamo gli eventuali doppi slash a causa di errori umani
            $filenameFullPath = $this->cleanDoubleSlash($filenameFullPath);

            //Tramite il touch possiamo capire se veramente potremo scrivere il file
            //restituirà true se il sistema riesce a creare il file con dimensione zero
            //tale quale al touch di linux
            if(touch($filenameFullPath)){
                $fhandler = fopen($filenameFullPath, 'w');
                $esitoScrittura = fwrite($fhandler, $data);

                //fwrite restituirà la quantità di bytes scritti oppure false se qualcosa va male, quindi
                if($esitoScrittura !== false){
                    //E' consigliato l'uso di octdec, così convertiamo come si deve il numero ottale che ci deve
                    //essere per impostare i permessi
                    chmod($filenameFullPath, octdec('776'));
                } else {
                    $stop = "Qui lanciamo un'eccezione oppure loghiamo qualcosa per il momento lascio vuoto";
                }
            }
        } else {
            throw new \Exception('Non ci sono permessi per scrivere su: ' . $this->WORKING_BASE_PATH );
        }

        return $this;
    }


    /**
     * Crea ricorsivamente un percorso settando nel giro di elaborazione i permessi per ogni livello del percorso
     *
     * @param null $directory
     * @return null
     */
    public function createDirectory($directory = null) {

        //Nel caso non ci venga passato il nome del file a scrivere, usciamo gracilmente
        if ($directory === null) {
            return null;
        }

        $directory = $this->cleanDoubleSlash($this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $directory);

        //Prendiamo il percorso del file, eliminando gli slash '/' e '\' di troppo
        //evitando di toglierli nel caso di http://www.elle.it, invece per i sistemi windows
        //evitiamo di togliere gli inizi delle cartelle di rete \\host\percorso
        $path = $this->cleanDoubleSlash($directory);

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
                if(DIRECTORY_SEPARATOR === "/"){
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
                        echo self::HANDLER_NAME . ' - Non posso creare la cartella ' . $assembledDirectory;
                        exit(0);
                    }
                    chmod($assembledDirectory, octdec('0775'));

                }

            }
        }

        return true;

    }

    /**
     * List files using pattern
     *
     * @param $pattern
     * @return array
     */
    public function listFiles($pattern)
    {
        //Prendiamo il nome dei file che si trovano a partire dalla nostra radice di lavoro
        return glob($this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $pattern);

    }

    /**
     * Legge relativo al percorso della cartella di lavoro
     *
     * @param $filename
     * @return null|string
     */
    public function readFileFromFilesystem($filename) {

        if($this->fileExists($filename)) {
            //Mettiamo il percorso base
            $reformedFilename = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $filename;
            //Puliamo le cazzate
            $reformedFilename = $this->cleanDoubleSlash($reformedFilename);

            $fhandle = fopen($reformedFilename, 'r');
            return fread($fhandle, filesize($reformedFilename));
        }

        return null;

    }



    /**
     * Cambia il percorso dove questo servizio effettuerà le operazioni di I/O
     *
     * @param $newRelativePath
     * @return $this
     */
    public function changeRelativeOperationsPath($newRelativePath)
    {

        //Se siamo in un sistema Windows ed eventualmente ci passano un C:\BlahBlah,
        // eliminiamo qualsiasi riferimento con due punti ":", si suppone che sono path relativi, che ci mettono a fare
        // percorsi assoluti? Cazzi loro!

        $newRelativePath = str_replace(':','', $newRelativePath);

        //Eliminiamo doppioni di slash e cazzate varie e facciamo
        //la nuova assegnazione path per elaborazioni relative

        $percorsoRelativoAssoluto = APP_BASEDIR . DIRECTORY_SEPARATOR . $newRelativePath;
        $this->WORKING_BASE_PATH = $this->cleanDoubleSlash($percorsoRelativoAssoluto);

        return $this;

    }


    /**
     * Cambia il percorso dove questo servizio effettuerà le operazioni di I/O
     *
     * @param $newBasePath
     * @return $this
     */
    public function changeBasePath($newBasePath)
    {

        //Se siamo in un sistema Windows ed eventualmente ci passano un C:\BlahBlah,
        // eliminiamo qualsiasi riferimento con due punti ":", si suppone che sono path relativi, che ci mettono a fare
        // percorsi assoluti? Cazzi loro!

        $newBasePath = str_replace(':','', $newBasePath);

        //Eliminiamo doppioni di slash e cazzate varie e facciamo
        //la nuova assegnazione path per elaborazioni relative

        $percorsoRelativoAssoluto = DIRECTORY_SEPARATOR . $newBasePath;
        $this->WORKING_BASE_PATH = $this->cleanDoubleSlash($percorsoRelativoAssoluto);

        return $this;

    }




    /**
     *
     * Aggiunge un pezzo di testo qualsiasi a un file esistente
     *
     * @param $anyText
     * @param null $targetFilename
     * @param boolean $createBackup Opzione per creare un backup del file originale prima di appendere l'informazione
     * @return null
     * @throws \Exception
     */
    public function appendToFile($anyText, $targetFilename = null, $createBackup = false) {
        //Nel caso non ci venga passato il nome del file a scrivere, usciamo gracilmente
        if($targetFilename === null) {
            return null;
        }

        //Puliamo le cache di scrittura su disco
        clearstatcache();

        //Controlliamo se sulla cartella di lavoro ci si può scrivere
        if(is_dir($this->WORKING_BASE_PATH) && is_writable($this->WORKING_BASE_PATH)){

            $filenameFullPath = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $targetFilename;

            //Preveniamo gli eventuali doppi slash a causa di errori umani
            $filenameFullPath = $this->cleanDoubleSlash($filenameFullPath);


            //Il file destino deve esistere prima di procedere
            if(file_exists($filenameFullPath)){


                //Backup del file da modificare
                //------------------------------------
                if($createBackup === true) {
                    //Generiamo il nome del file di backup
                    $backupFilenameFullPath = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $targetFilename . '.original';

                    //Dobbiamo verificare se esite già il backup così se utilizziamo l'appensione secuenziale
                    //non creeremo 50000 file di backup ogni volta che scriviamo
                    if(file_exists($backupFilenameFullPath) === false) {
                        copy($filenameFullPath, $backupFilenameFullPath);
                    }
                }
                //----Fine backup del file da modificare--

                //Apriamo il file in modalità append
                $fhandler = fopen($filenameFullPath, 'a');
                $esitoScrittura = fwrite($fhandler, $anyText);

                //fwrite restituirà la quantità di bytes scritti oppure false se qualcosa va male, quindi
                if($esitoScrittura !== false){
                    //E' consigliato l'uso di octdec, così convertiamo come si deve il numero ottale che ci deve
                    //essere per impostare i permessi
                    //chmod($filenameFullPath, octdec('766'));
                } else {
                    $stop = "Qui lanciamo un'eccezione oppure loghiamo qualcosa per il momento lascio vuoto";
                }
            } else {
                throw new \Exception('Il file: ' . $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $targetFilename . " deve esistere per poter appenderci dati.");
            }
        } else {
            throw new \Exception('Non ci sono permessi per scrivere su: ' . $this->WORKING_BASE_PATH );
        }
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

    /**
     * Imposta l'estensione da usare qualora si dovesse salvare un file
     * @param null $extension
     * @return $this
     */
    public function usingExtension($extension = null) {

        //Se non mettiamo l'extension o l'ommettiamo significa che non ne vogliamo una
        if(is_null($extension) || $extension === '') {
            $this->file_extension = '';
        } elseif(strlen($extension) > 0 ) {
            //Aggiungiamo il punto davanti all'extension
            $extension = '.' . $extension;

            $this->file_extension = preg_replace('/(?<!:)(\.{2,})/i', '.', $extension);

        }

        return $this;

    }

    /**
     * Legge data di ultima modifica di un file
     *
     * @param $filename
     * @return int|null
     */
    public function getCreationTime($filename)
    {
        if($this->fileExists($filename)) {
            $reformedFilename = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $filename;
            $reformedFilename = $this->cleanDoubleSlash($reformedFilename);


            return filectime($reformedFilename);
        }

        return null;
    }

    /**
     * Legge data di ultimo accesso a un file
     *
     * @param $filename
     * @return int|null
     */
    public function getLastAccessTime($filename)
    {
        if($this->fileExists($filename)) {
            $reformedFilename = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $filename;
            $reformedFilename = $this->cleanDoubleSlash($reformedFilename);


            return fileatime($reformedFilename);
        }

        return null;
    }

    /**
     * Legge data di ultima modifica di un file
     *
     * @param $filename
     * @return int|null
     */
    public function getModifiedTime($filename)
    {
        if($this->fileExists($filename)) {
            $reformedFilename = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $filename;
            $reformedFilename = $this->cleanDoubleSlash($reformedFilename);


            return filemtime($reformedFilename);
        }

        return null;
    }
}