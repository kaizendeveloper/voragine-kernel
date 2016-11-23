<?php
/**
 * TimeIntervalService
 *
 * Configurazione e messa in moto del servizio per il calcolo dell'intervallo di tempo
 *
 * @authors: Miguel Delli Carpini, Matteo Scirea, Javier Jara
 */
namespace Voragine\Kernel\Services;



class TimeIntervalService
{

    //Relativi all'estrazione degli utenti, le informazioni di tempo devono essere suddivise in due spazi
    //uno relativo al giorno e l'altro al tempo
    protected $estrazione_giorno_info;
    protected $estrazione_tempo_info;
    protected $estrazione_soglia_di_tempo;

    //Qui salveremo il resoconto delle cose che abbiamo trovato, questo sarà il messaggio che vedrete
    //quando l'oggetto lancerà un'eccezione
    protected $error_briefing;

    //Indica se ci sono i requisiti minimi per poter lavorare
    protected $minimum_req_met = false;

    //Valore optional per avere un identificativo di siteaccess (utile per i log di console più che altro)
    protected $siteaccess;

    //Per la configurazione da YAML
    const CFG_ARRAY_KEY     = 'time_interval';
    const CFG_YEARS         = 'years';
    const CFG_YEARS_CHAR    = 'Y';
    const CFG_MONTHS        = 'months';
    const CFG_MONTHS_CHAR   = 'M';
    const CFG_WEEKS         = 'weeks';
    const CFG_WEEKS_CHAR    = 'W';
    const CFG_DAYS          = 'days';
    const CFG_DAYS_CHAR     = 'D';
    const CFG_HOURS         = 'hours';
    const CFG_MINUTES       = 'minutes';
    const CFG_MINUTES_CHAR  = 'M';
    const CFG_SECONDS       = 'seconds';
    const CFG_SECONDS_CHAR  = 'S';



    
    public function __construct($array = null, $siteaccess = '')
    {

        //--------------------------------------------------------------------------------------------------

        //Inseriamo l'identificativo
        if(strlen($siteaccess) > 0) {
            $this->siteaccess = $siteaccess;
        }

        //Così facendo possiamo configurare sia alla costruzione che chiamando il metodo
        $this->loadConfigArray($array);
    }

    /**
     * Carica l'impostazione dallo YAML in base alla chiave definita nell'oggetto
     * @param null $yamlConfigArray
     * @return $this
     */
    public function loadConfigArray($yamlConfigArray = null) {

        if($yamlConfigArray !== null) {
            //Prima validazione, la configurazione dev'essere in un array
            if(is_array($yamlConfigArray)) {

                //Cominciamo a caricare la configurazione impostando gli eventuali valori per default
                //e impostando la bandierina "Oggetto pronto per lavorare" quando riterremo che il caricamento
                //sia stato completato

                //Controlliamo le impostazioni per l'estrazione degli utenti lato DB
                if(isset($yamlConfigArray[self::CFG_ARRAY_KEY])) {

                    //Per lavorare meglio accediamo alla variabile
                    $users_retrieval = $yamlConfigArray[self::CFG_ARRAY_KEY];

                    //Per il calcolo da ore a minuti
                    $hoursToMinutes = 0;

                    //Ricaviamo gli anni
                    if(isset($users_retrieval[self::CFG_YEARS])){
                        if($users_retrieval[self::CFG_YEARS] !== null && $users_retrieval[self::CFG_YEARS] !== 0) {
                            $this->estrazione_giorno_info .= (int)$users_retrieval[self::CFG_YEARS] . self::CFG_YEARS_CHAR;
                        }

                    }

                    //Ricaviamo i mesi
                    if(isset($users_retrieval[self::CFG_MONTHS])){
                        if($users_retrieval[self::CFG_MONTHS] !== null && $users_retrieval[self::CFG_MONTHS] !== 0) {
                            $this->estrazione_giorno_info .= (int)$users_retrieval[self::CFG_MONTHS] . self::CFG_MONTHS_CHAR;
                        }

                    }

                    //Ricaviamo le settimane (NON SI POSSONO COMBINARE I GIORNI CON LE SETTIMANE QUINDI)
                    //diamo precedenza alle settimane
                    if(isset($users_retrieval[self::CFG_WEEKS])){
                        if($users_retrieval[self::CFG_WEEKS] !== null && $users_retrieval[self::CFG_WEEKS] !== 0) {
                            $this->estrazione_giorno_info .= (int)$users_retrieval[self::CFG_WEEKS] .'W';
                        } else {
                            //Quando le settimane sono impostate a ZERO possiamo
                            //controllare i giorni
                            if(isset($users_retrieval[self::CFG_DAYS])){
                                if($users_retrieval[self::CFG_DAYS] !== null && $users_retrieval[self::CFG_DAYS] !== 0) {
                                    $this->estrazione_giorno_info .= (int)$users_retrieval[self::CFG_DAYS] . self::CFG_DAYS_CHAR;
                                }
                            }
                        }

                    } else {

                        //Ricaviamo i giorni se non sono impostate le settimane
                        if(isset($users_retrieval[self::CFG_DAYS])){
                            if($users_retrieval[self::CFG_DAYS] !== null && $users_retrieval[self::CFG_DAYS] !== 0) {
                                $this->estrazione_giorno_info .= (int)$users_retrieval[self::CFG_DAYS] . self::CFG_DAYS_CHAR;
                            }
                        }
                    }



                    //Ricaviamo le ore
                    if(isset($users_retrieval[self::CFG_HOURS])) {
                        $hoursToMinutes = 60 * (int)$users_retrieval[self::CFG_HOURS];
                    }

                    //Ricaviamo i minuti
                    if(isset($users_retrieval[self::CFG_MINUTES])) {

                        //Aggiungiamo i minuti contenutim nelle ore da impostazione YAML
                        $hoursToMinutes += (int)$users_retrieval[self::CFG_MINUTES];

                        //Salviamo le ore insieme ai minuti
                        if($hoursToMinutes !== 0) {
                            $this->estrazione_tempo_info .= $hoursToMinutes . self::CFG_MINUTES_CHAR;
                        }
                    } else {
                        //Se abbiamo precedentemente qualche informazione riguardante le ore
                        //prendiamo quelle come i secondi, tanto sono già convertiti
                        if($hoursToMinutes !== 0) {
                            $this->estrazione_tempo_info .= (int)$users_retrieval[self::CFG_MINUTES] . self::CFG_MINUTES_CHAR;
                        }

                    }



                    //Ricaviamo i secondi
                    if(isset($users_retrieval[self::CFG_SECONDS])){
                        if($users_retrieval[self::CFG_SECONDS] !== null && $users_retrieval[self::CFG_SECONDS] !== 0){
                            $this->estrazione_tempo_info .= (int)$users_retrieval[self::CFG_SECONDS] . self::CFG_SECONDS_CHAR;
                        }
                    }


                    //Per default impostiamo il valore del tempo
                    //Per capire il formato usato andate su
                    //http://php.net/manual/en/dateinterval.construct.php

                    //Tutti i riferimenti sui giorni dovrebbero avere la "P" come intestazione
                    //questo controllo lo facciamo alla fine, per evitare casini ci limitiamo a copiare il valore e basta
                    if(!is_null($this->estrazione_giorno_info)) {
                        $this->estrazione_soglia_di_tempo = $this->estrazione_giorno_info;
                    }

                    //Tutti i riferimenti sul tempo devono avere la "T" come intestazione
                    if(!is_null($this->estrazione_tempo_info)) {
                        $this->estrazione_soglia_di_tempo .= 'T' . $this->estrazione_tempo_info;
                    }


                    // SE NON VIENE MESSO NULLA ASSUMIAMO CHE L'INTERVALLO SARA' 0

                    //Se non abbiamo informazioni né di tempo né di giorni, impostiamo per un giorno per default
                    //Per capire il formato usato andate su
                    //http://php.net/manual/en/dateinterval.construct.php
                    if(is_null($this->estrazione_giorno_info) && is_null($this->estrazione_tempo_info)){
                        $this->estrazione_soglia_di_tempo = 'PT0S';
                    }


                    //Check finale
                    //dobbiamo controllare che la stringa inizi da P, sennò aggiungiamola
                    if(preg_match('/^P/i', $this->estrazione_soglia_di_tempo) !== 1){

                        $this->estrazione_soglia_di_tempo = 'P' . $this->estrazione_soglia_di_tempo;

                    }


                } else {

                    //Se non becchiamo niente da file YAML assumiamo che la differenza sarà pari a 0
                    $this->estrazione_soglia_di_tempo = 'PT0S';


                }
            }
        }

        return $this;
    }

    /**
     * Restituisce il valore indietro secondo il dateinterval a partire da adesso
     * @return \DateInterval
     */
    public function calculateIntervalUsingConfig(){

        $adesso = new \DateTime();
        $tempoIndietro = $adesso->sub(new \DateInterval($this->estrazione_soglia_di_tempo));

        return $tempoIndietro;
    }

    public function overrideIntervalUsingDateInterval($dateInterval){


        $this->estrazione_soglia_di_tempo = $dateInterval;

    }

    public function overrideIntervalConfigUsing($date)
    {
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

        //A partire dalla data messa da riga di comando dobbiamo calcolare la differenza di tempo per poi convertire
        //tale differenza a formato DateInterval

        //Adesso
        $adesso = new \DateTime();

        //$date conterrà la data di override, tramite il metodo diff calcoliamo la differenza
        $differenza = $date->diff($adesso);

        //Modifichiamo la soglia di tempo per le successive estrazioni
        $this->estrazione_soglia_di_tempo = $differenza->format('P%yY%mM%dDT%hH%iM%sS');

        return $this;
    }


}