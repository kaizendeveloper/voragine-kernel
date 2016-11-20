<?php
/**
 * Generatore dei nome per i file in base a un pattern, adeguato per questo progetto
 *
 * @author: Miguel Delli Carpini
 * @author: Matteo Scirea
 * @author: Javier Jara
 */
namespace Voragine\Kernel\Services;



use Voragine\Kernel\Services\Base\ServiceModelInterface;

class NameGeneratorService implements ServiceModelInterface
{

    //Variabile per la sostituzione dei placeholder con le informazioni della data
    protected $filename_format;


    //Qui salveremo il resoconto delle cose che abbiamo trovato, questo sarà il messaggio che vedrete
    //quando l'oggetto lancerà un'eccezione
    protected $error_briefing;


    public function __construct($configArray = null, $siteaccess = null)
    {



        //Così facendo possiamo configurare sia alla costruzione che chiamando il metodo se facciamo un'istanza
        //al di fuori l'ambito di un servizio
        $this->loadConfigArray($configArray);


    }

    /**
     * Carica l'impostazione dallo YAML in base alla chiave definita nell'oggetto
     * @param null $yamlConfigArray
     * @return $this
     * @throws \Exception
     */
    public function loadConfigArray($yamlConfigArray = null) {

        if($yamlConfigArray !== null) {
            //Prima validazione, la configurazione dev'essere in un array
            if(is_array($yamlConfigArray)) {
                //Tentiamo di prendere dallo YAML parsato la base che ci interessa
                $baseInfo = $yamlConfigArray;

                //Cominciamo a caricare la configurazione impostando gli eventuali valori per default
                //e impostando la bandierina "Oggetto pronto per lavorare" quando riterremo che il caricamento
                //sia stato completato

                //Controlliamo le impostazioni su come scriveremo i nomi dei file
                if(isset($baseInfo['filename_format'])) {

                    //Per lavorare meglio accediamo alla variabile
                    $this->filename_format = $baseInfo['filename_format'];


                } else {
                    //Se non c'è un formato lasciamo appendere tutto alla fine di tutto
                    $this->filename_format = null;
                }

            }


        }

        return $this;
    }




    /**
     * Genera un nome di file in base a una data (NON AGGIUNGE L'EXTENSION)
     *
     * @param string|DateTime|null $date Data in formato testo con i campi separati da
     *                             trattino 2016-05-01 oppure 01-05-2016 oppure un oggetto
     *                             di tipo DateTime
     * @param string $format       Il formato con i placeholder
     * @return string
     */
    public function generateFilenameUsingDate($date = null, $format = null) {

        //Per default prendiamo quello che il servizio è riusciuto ad acchiappare da file configurazione YAML
        if(is_null($format)) {
            $format = $this->filename_format;
        }

        //Prima dobbiamo controllare la natura della data che ci passano
        //noi dobbiamo lavorare con un'istanza di DateTime
        if(get_class($date) !== 'DateTime') {

            //Se non è un'istanza di DateTime può darsi che ci stanno passando una data formatata
            //del tipo '2016-06-16' per esempio
            if(is_string($date)){

                //Se casomai ci passano now, prendiamo "l'adesso"
                if(strtolower($date) === 'now') {
                    $date = new \DateTime();
                } else {
                    //Tentiamo di creare la data giusta in base alla stringa
                    $date = new \DateTime($date);
                }
            } else {
                //Se non è una stringa allora per evitare un crash prendiamo "l'adesso"
                $date = new \DateTime();
            }
        }


        //Temporaneamente ci salviamo i risultati delle comparazioni
        $matches = array();

        //---------------------------------------------------------
        //Controlliamo se esiste il nostro placeholder per l'anno
        //---------------------------------------------------------

        //RegEx per prendere A da due volte fino a quattro
        $regEx = '/A{2,4}/i';
        //preg_match restituirà 1 (intero attenzione!) se qualcosa matcha 0 sennò
        $foundSomething = preg_match($regEx, $format, $matches);

        //Se abbiamo trovato qualcosa controlliamo se l'utente vuole il formato lungo o corto
        if($foundSomething === 1) {
            //Il formato è lungo?
            if(strlen($matches[0]) > 2){
                //Sì, prendiamo la data e sostituiamo il placeholder dell'anno (usando la stessa regEx)
                $adessoFormatato = date('Y', $date->getTimestamp());
                $format = preg_replace($regEx, $adessoFormatato, $format);

            } else {
                $adessoFormatato = date('y', $date->getTimestamp());
                $format = preg_replace($regEx, $adessoFormatato, $format);
            }
        } else {
            //Se non ci viene dato un placeholder per l'anno attacchiamo l'anno
            $format .= date('Y', $date->getTimestamp());
        }

        //---------------------------------------------------------
        //Controlliamo se esiste il nostro placeholder per il mese
        //---------------------------------------------------------

        //RegEx per prendere il gruppo 'MM' o 'mm' una volta sola
        $regEx = '/(?:MM|mm){1}/i';
        $foundSomething = preg_match($regEx, $format, $matches);

        if($foundSomething === 1) {
            //Vediamo quale formato si desidera
            if($matches[0] === 'MM'){
                //Formato lungo (cioè con lo zero davanti)
                $adessoFormatato = date('m', $date->getTimestamp());
                $format = preg_replace($regEx, $adessoFormatato, $format);

            } else {
                //Formato corto
                $adessoFormatato = date('n', $date->getTimestamp());
                $format = preg_replace($regEx, $adessoFormatato, $format);
            }
        } else {
            //Se non ci viene dato un placeholder per il mese attacchiamo il mese lungo
            $format .= date('m', $date->getTimestamp());
        }

        //---------------------------------------------------------
        //Controlliamo se esiste il nostro placeholder per il giorno
        //---------------------------------------------------------

        //RegEx per prendere il gruppo 'DD' o 'dd' una volta sola
        $regEx = '/(?:DD|dd){1}/i';
        $foundSomething = preg_match($regEx, $format, $matches);

        if($foundSomething === 1) {
            //Vediamo quale formato si desidera
            if($matches[0] === 'DD') {
                //Formato lungo (cioè con lo zero davanti)
                $adessoFormatato = date('d', $date->getTimestamp());
                $format = preg_replace($regEx, $adessoFormatato, $format);
            } else {
                //Formato corto
                $adessoFormatato = date('j', $date->getTimestamp());
                $format = preg_replace($regEx, $adessoFormatato, $format);
            }
        } else {
            //Se non ci viene dato un placeholder per il giorno attacchiamo il giorno lungo
            $format .= date('d', $date->getTimestamp());
        }

        //Controlliamo se abbiamo messo l'estensione XML, se non c'è la mettiamo
        /*if(preg_match("/\\" . $this->file_extension . "$/i", $format) !== 1) {
            $format .= $this->file_extension;
        }*/
        return $format;

    }

    /**
     * Genera un nome di file in base a una data (TENETE PRESENTE CHE NON AGGIUNGE L'EXTENSION)
     *
     * @return string
     */
    public function generateTodaysFilenameBasedOnPattern() {


        //Lavoriamo sulla copia
        $format = $this->filename_format;

        //---------------------------------------------------------
        //Controlliamo se esiste il nostro placeholder per l'anno
        //---------------------------------------------------------

        //RegEx per prendere A da due volte fino a quattro
        $regEx = '/A{2,4}/i';
        //preg_match restituirà 1 (intero attenzione!) se qualcosa matcha 0 sennò
        $foundSomething = preg_match($regEx, $format, $matches);

        //Se abbiamo trovato qualcosa controlliamo se l'utente vuole il formato lungo o corto
        if($foundSomething === 1) {
            //Il formato è lungo?
            if(strlen($matches[0]) > 2){
                //Sì, prendiamo la data e sostituiamo il placeholder dell'anno (usando la stessa regEx)
                $adessoFormatato = date('Y', time());
                $format = preg_replace($regEx, $adessoFormatato, $format);

            } else {
                $adessoFormatato = date('y', time());
                $format = preg_replace($regEx, $adessoFormatato, $format);
            }
        } else {
            //Se non ci viene dato un placeholder per l'anno attacchiamo l'anno
            $this->filename_format .= date('Y', time());
        }

        //---------------------------------------------------------
        //Controlliamo se esiste il nostro placeholder per il mese
        //---------------------------------------------------------

        //RegEx per prendere il gruppo 'MM' o 'mm' una volta sola
        $regEx = '/(?:MM|mm){1}/i';
        $foundSomething = preg_match($regEx, $format, $matches);

        if($foundSomething === 1) {
            //Vediamo quale formato si desidera
            if($matches[0] === 'MM'){
                //Formato lungo (cioè con lo zero davanti)
                $adessoFormatato = date('m', time());
                $format = preg_replace($regEx, $adessoFormatato, $format);

            } else {
                //Formato corto
                $adessoFormatato = date('n', time());
                $format = preg_replace($regEx, $adessoFormatato, $format);
            }
        } else {
            //Se non ci viene dato un placeholder per il mese attacchiamo il mese lungo
            $format .= date('m', time());
        }

        //---------------------------------------------------------
        //Controlliamo se esiste il nostro placeholder per il giorno
        //---------------------------------------------------------

        //RegEx per prendere il gruppo 'DD' o 'dd' una volta sola
        $regEx = '/(?:DD|dd){1}/i';
        $foundSomething = preg_match($regEx, $format, $matches);

        if($foundSomething === 1) {
            //Vediamo quale formato si desidera
            if($matches[0] === 'DD') {
                //Formato lungo (cioè con lo zero davanti)
                $adessoFormatato = date('d', time());
                $format = preg_replace($regEx, $adessoFormatato, $format);
            } else {
                //Formato corto
                $adessoFormatato = date('j', time());
                $format = preg_replace($regEx, $adessoFormatato, $format);
            }
        } else {
            //Se non ci viene dato un placeholder per il giorno attacchiamo il giorno lungo
            $format .= date('d', time());
        }

        return $format;

    }



    /**
     * Pulisce gli slash ripetuti in una stringa
     *
     * @param $stringaDaPulire
     * @return mixed
     */
    private function cleanDoubleSlash($stringaDaPulire){
        return preg_replace('/(?<!:)(\/{2,})/i', '/', $stringaDaPulire);
    }






}