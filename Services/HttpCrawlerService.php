<?php
/**
 * HttpCrawlerService
 *
 * Cofigura e mette a disposizione il crawler HTTP
 *
 * @authors: Miguel Delli Carpini, Matteo Scirea, Javier Jara
 */
namespace Voragine\Kernel\Services;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\BadResponseException;


class HttpCrawlerService
{
    //Indirizzo base per fare le richieste
    protected $base_url;
    //Indicatore della risorsa (URI)
    protected $slug;
    //Tempo in secondi prima di considerare fallita la connessione
    protected $timeout;
    //Numero di volte che si deve riprovare prima di dare come fallita la connessione
    protected $retries;

    //Qui salveremo il resoconto delle cose che abbiamo trovato, questo sarà il messaggio che vedrete
    //quando l'oggetto lancerà un'eccezione
    protected $error_briefing;

    //Indica se ci sono i requisiti minimi per poter lavorare
    protected $minimum_req_met = false;

    //Valore optional per avere un identificativo di siteaccess (utile per i log di console più che altro)
    protected $siteaccess;

    //Copia dell'istanza dell'oggetto base per questo servizio
    protected $http_client;


    protected $auth_vault;

    //Dato un YAML di configurazione questa sarebbe la stringa che identifica il pezzo da prelevare
    const CFG_ARRAY_KEY = 'http_crawler';



    public function __construct($array = null, $siteaccess = '')
    {
        //Inseriamo l'identificativo
        if(strlen($siteaccess) > 0) {
            $this->siteaccess = $siteaccess;
        }

        //Così facendo possiamo configurare sia alla costruzione che chiamando il metodo
        if( !is_null($this->loadConfigArray($array)) ){
            $this->http_client = new Client();
        }

    }


    private function loadConfigArray($array = null) {

        if($array !== null) {
            //Prima validazione, la configurazione dev'essere in un array
            if(is_array($array)) {
                //Tentiamo di prendere dallo YAML parsato la base che ci interessa
                $baseInfo = $array[self::CFG_ARRAY_KEY];

                //Cominciamo a caricare la configurazione impostando gli eventuali valori per default
                //e impostando la bandierina "Oggetto pronto per lavorare" quando riterremo che il caricamento
                //sia stato completato

                //Controlliamo l'url
                if(isset($baseInfo['url'])){

                    //Prendiamo i singoli pezzi dall'URL
                    $parsedURL = parse_url($baseInfo['url']);

                    //Questo è il più importante per la nostra seconda validazione
                    if(!isset($parsedURL['host'])) {
                        $this->error_briefing = "URL non presente nello YAML di configurazione per il siteaccess " . $this->siteaccess . "\r\n";

                        //Fermiamo tutto
                        throw new \Exception($this->error_briefing);
                    }

                    $this->slug = $parsedURL['path'];
                    $this->base_url = $parsedURL['scheme'] . '://' . $parsedURL['host'];

                    //Abilitiamo l'oggetto per lavorare
                    $this->minimum_req_met = true;

                }

                //Controlliamo il timeout
                if(isset($baseInfo['timeout'])){
                    $this->timeout = (int)$baseInfo['timeout'];
                } else {
                    //Valore di default
                    $this->timeout = 120;
                }

                //Controlliamo quanti tentativi
                if(isset($baseInfo['retries'])){
                    $this->retries = (int)$baseInfo['retries'];
                } else {
                    //Valore di default
                    $this->retries = 3;
                }


                //Il parametro auth_vault è parola riservata e serve per salvare una lista
                //di credenziali utili per i siti sotto password, come quelli di test
                if(isset($baseInfo['auth_vault'])) {

                    //Giriamo questi per estrapolare le credenziali
                    foreach ($baseInfo['auth_vault'] as $key => $authParams) {
                        //Salviamo solo se esistono entrambi parametri, altrimenti ignoriamo
                        if (isset($authParams['password']) && isset($authParams['username'])) {
                            //Credenziale da salvare
                            $this->auth_vault[] = $authParams;

                        }

                    }
                }
            }
        }

        return $this;
    }

    /**
     * Restituisce lo slug
     * @return mixed
     * @throws \Exception
     */
    public function getURL() {
        //Ucciderà l'esecuzione se l'oggetto non è pronto per lavorare
        $this->validateOperations();

        return $this->base_url . $this->slug;

    }

    /**
     * Restituisce il timeout
     * @return mixed
     * @throws \Exception
     */
    public function getTimeout() {
        //Ucciderà l'esecuzione se l'oggetto non è pronto per lavorare
        $this->validateOperations();

        return $this->timeout;

    }

    /**
     * Restituisce il numero di volte che deve riprovare il crawler
     * @return mixed
     * @throws \Exception
     */
    public function getRetries() {
        //Ucciderà l'esecuzione se l'oggetto non è pronto per lavorare
        $this->validateOperations();

        return $this->retries;

    }

    /**
     * Controlla se l'oggetto è stato configurato o meno
     * @throws \Exception
     */
    protected function validateOperations(){
        //Controlliamo se abbiamo i parametri minimi per lavorare
        if(!$this->minimum_req_met) {
            throw new \Exception('HttpCrawlerConfigurator non è stato configurato, controllate nel vostro YAML se esistono i parametri per il crawler.');
        }
    }

    public function readTheFeed(){

        //Tentiamo di prendere Finché non finiamo i cicli
        $maxRichieste = $this->getRetries();


        // HTTP BASIC AUTH
        //-------------------------------------------------------------------------------
        //Qui metteremo le credenziali in caso di errore per HTTP BASIC AUTH
        $authHttpCredentials = array();

        //Facciamo una copia di sicurezza per non lavorare direttamente dalla matrice
        //ci servirà poiché sputtaneremo l'array di copia
        $authVaultCopy = $this->auth_vault;

        //Codifica in base64 per la trasmissione delle credenziali
        $base64UserPwd = '';

        //Se ci viene impostata la lista delle credenziali dobbiamo fermare il ciclo while se le abbiamo provato tutte
        if(count($this->auth_vault) > 0) {
            $stopAfterTryingAllCredentials = true;
        } else {
            $stopAfterTryingAllCredentials = false;
        }
        //-------------------------------------------------------------------------------

        while($maxRichieste > 0) {
            try {


                //Prendiamo la risposta del server, prendendo l'url dalla configurazione YAML
                $response = $this->http_client->get($this->getURL(), $authHttpCredentials)->send()->getBody(true);

                //Se non ci sono errori l'esecuzione passerà di cui poiché il client Guzzle se tutto va bene
                //non lancia eccezioni, quindi adesso possiamo uscire dal loop impostando sotto lo zero


                $maxRichieste = -5;

                return $response;

            } catch (BadResponseException $e) {

                //Qui si dovrebbe loggare qualcosa e con questo metodo prendiamo quale è l'errore $e->getMessage();
                $maxRichieste--;

                //Prendiamo una delle credenziali scartando il resto (così non dobbiamo ciclare in modo intelligente)
                $oneCredential = array_pop($authVaultCopy);

                //Elaboriamo l'auth header
                if(!is_null($oneCredential))
                {
                    //Per comodità di lettura facciamo
                    $base64UserPwd = base64_encode($oneCredential['username'] . ':' . $oneCredential['password']);
                    $authHttpCredentials = array('Authorization' => 'Basic ' . $base64UserPwd);

                } elseif($stopAfterTryingAllCredentials) {

                    //Abbiamo finito le credenziali, a cosa serve ritentare fino all'esaurimento di $maxRichieste?
                    $maxRichieste = -5;

                }
            }
        }

        return null;

    }
}