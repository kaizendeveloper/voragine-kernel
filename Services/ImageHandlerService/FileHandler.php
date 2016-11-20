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
namespace Voragine\Kernel\Services\ImageHandlerService;



class FileHandler
{
    
    //Visto che su PHP 5.3.3 è un casino lavorare con le costanti ne facciamo una variabile da quello che dovrebbe
    //essere una costante
    protected $WORKING_BASE_PATH;

    
    //Variabile per la sostituzione dei placeholder con le informazioni della data
    protected $filename_format;

    //Così ci risparmiamo tante variabili anziché creare tante variabili per i flag
    protected $feed_formats_allowed;

    //Estensione per default
    protected $file_extension;




    //Qui salveremo il resoconto delle cose che abbiamo trovato, questo sarà il messaggio che vedrete
    //quando l'oggetto lancerà un'eccezione
    protected $error_briefing;

    //Indica se ci sono i requisiti minimi per poter lavorare
    protected $minimum_req_met = false;

    //Valore optional per avere un identificativo di siteaccess (utile per i log di console più che altro)
    protected $siteaccess;


    
    public function __construct($array = null, $siteaccess = '')
    {
        //Init dei flag
        $this->feed_formats_allowed = 0;

        //Definiamo la "costante" ovvero la cartella di lavoro, dove butteremo i file
        $this->WORKING_BASE_PATH = APP_BASEDIR . 'var/images';

    }

    public function checkIfDirectoryExists($directory = null) {
        //Nel caso non ci venga passato il nome del file a scrivere, usciamo gracilmente
        if($directory === null) {
            return null;
        }

        //Puliamo le cache di scrittura su disco
        clearstatcache();

        $builtDir = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $directory;


        //Controlliamo se sulla cartella di lavoro ci si può scrivere
        if(is_dir($builtDir) && is_writable($builtDir)){
            return true;
        } else {
            return false;
        }
    }

    public function fileExists($filenameSlug = null)
    {
        //Nel caso non ci venga passato il nome del file a scrivere, usciamo gracilmente
        if ($filenameSlug === null) {
            return null;
        }

        //Tutti controlli devono partire dal working base path, quindi se lo mettiamo dobbiamo pulire
        //gli eventuali doppioni
        //--------------------------------------------------------------------------------------------

        //Togliamo il percorso BASE ovunque sia (pulizia totale)
        $filenameSlug = str_replace($this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR, '', $filenameSlug);

        //Inseriamo percorso BASE + PERCORSO RELATIVO
        $reformedSlug = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $filenameSlug;

        //Puliamo doppioni di Slash
        $reformedSlug = $this->cleanDoubleSlash($reformedSlug);

        return is_file($reformedSlug) && file_exists($reformedSlug);

    }


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
                    $stop = "Qui lanciamo un'eccezione oppure loghiamo qualcosa per appendil momento lascio vuoto";
                }
            }
        } else {
            throw new \Exception('Non ci sono permessi per scrivere su: ' . $this->WORKING_BASE_PATH );
        }
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

        //Prendiamo il percorso del file
        $directory = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $directory;

        //Prendiamo il percorso del file, eliminando gli slash DIRECTORY_SEPARATOR e '\' di troppo
        //evitando di toglierli nel caso di http://www.elle.it, invece per i sistemi windows
        //evitiamo di togliere gli inizi delle cartelle di rete \\host\percorso
        $path = preg_replace('/(?<!:)(\/{2,})|(?<!^)(\\{2,})/i', DIRECTORY_SEPARATOR, $directory);

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

                //E' Linux?
                //Ed è qui dove construiamo il percorso nel modo valido per ciascun sistema
                if(DIRECTORY_SEPARATOR === '/'){
                    //Unix like: prima / e poi pezzo percorso -> /var /www
                    $assembledDirectory .=  DIRECTORY_SEPARATOR . $directoryPart;

                //E' Windows?
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

    public function canWriteInDirectory(){

    }

    public function readFileFromFilesystem($filename) {

            $reformedFilename = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $filename;
            $reformedFilename = $this->cleanDoubleSlash($reformedFilename);

        if($this->fileExists($reformedFilename)) {

            $fhandle = fopen($reformedFilename, 'r');
            return fread($fhandle, filesize($reformedFilename));
        }

        return null;

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
        return preg_replace('/(?<!:)(\/{2,})|(?<!^)(\\{2,})/i', DIRECTORY_SEPARATOR, $stringaDaPulire);
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
     * Controlla se c'è un file original (che sarebbe il backup del CSV processato dallo script di estrazione
     * della community di elle)
     *
     * Se c'è significa che questo script è stato eseguito precedentemente, allora questo pezzo si dispone a sovrascrivere
     * il file CSV di partenza con le informazioni contenute nel file con estensione .original in questo modo riparte
     * l'estrazione dal DB della registrazione e verranno appesi gli utenti solo una volta
     *
     * Restituisce il nome del file corrispondente ad oggi
     *
     * @return string
     */
    public function inizializzaCSVFileDiOggi(){

        $originalSuffix = '.original';

        //Prendiamo il file CSV più aggiornato dalla cartella condivisa, dove troveremo i file CSV
        $nomeFileDiOggi = $this->generateTodaysFilenameBasedOnPattern();

        $originalFilenameFullPath = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $nomeFileDiOggi . $originalSuffix;
        $destinationFilenameFullPath = $this->WORKING_BASE_PATH . DIRECTORY_SEPARATOR . $nomeFileDiOggi;

        //Dobbiamo prima controllare se esiste il file original, se esiste significa che lo script è stato eseguito
        //in precedenza quindi prenderemo questo file come il nostro file di partenza

        //CLI OUTPUT
        echo "\r\nCheck iniziale sui file CSV in " . $this->WORKING_BASE_PATH . "\r\n";

        //Se esiste il file di backup e non esiste l'originale creiamo il file di partenza a partire dal backup
        if(file_exists($originalFilenameFullPath)) {
            //Fermiamo tutto se la copiatura va male
            if(!copy($originalFilenameFullPath, $destinationFilenameFullPath)) {
                exit("\r\nFile .ORIGINAL trovato ma non riesco a creare il file di partenza a partire dal .ORIGINAL\r\n");
            } else{
                //Se tutto va bene cambiamo i permessi del file destino
                echo "\r\nCheck iniziale sui file CSV in " . $this->WORKING_BASE_PATH . "\r\n";
                echo "Copiato il file da: $nomeFileDiOggi$originalSuffix   a:   $nomeFileDiOggi\r\n";
                chmod($destinationFilenameFullPath, octdec('775'));
            }
        }

        //CLI OUTPUT
        echo "\033[01;32m Check dei file CSV finito.\033[0m \r\n";

        return $nomeFileDiOggi;

    }

    /**
     * Usiamo logica di flag a livello binario
     * @return bool
     */
    public function canWorkWithCSV(){
        //Usiamo logica di flag a livello binario
        // sappiamo che 2^1 = 2

        return (boolean)$this->feed_formats_allowed & 2;
    }

    /**
     * Usiamo logica di flag a livello binario
     * @return bool
     */
    public function canWorkWithXML(){
        //Usiamo logica di flag a livello binario
        // sappiamo che 2^0 = 1
        return (boolean)$this->feed_formats_allowed & 1;
    }



}