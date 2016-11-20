<?php
/**
 * MailerService
 *
 * Configura e mette a disposizione il servizio per il mailer
 *
 * @authors: Miguel Delli Carpini, Matteo Scirea, Javier Jara
 */
namespace Voragine\Kernel\Services;


//Swift Mailer Service
//----------------------
use Swift_Message;
use Swift_Mailer;
use Swift_SmtpTransport;

class MailerService
{

    protected $smtp_server;
    protected $smtp_server_port;
    protected $smtp_server_username;
    protected $smtp_server_password;

    protected $msg_from;
    protected $msg_to;
    protected $msg_cc;
    protected $msg_bcc;
    protected $msg_subject;


    protected $swift_message_object;
    protected $swift_smtp_transport;
    protected $swift_mailer;

    private $report_every_time;
    private $report_every_dayinfo;
    protected $report_every;

    public $failed_recipients;

    protected $can_send_mail;


    //Qui salveremo il resoconto delle cose che abbiamo trovato, questo sarà il messaggio che vedrete
    //quando l'oggetto lancerà un'eccezione
    protected $error_briefing;

    //Indica se ci sono i requisiti minimi per poter lavorare
    protected $minimum_req_met = false;

    //Valore optional per avere un identificativo di siteaccess (utile per i log di console più che altro)
    protected $siteaccess;



    public function __construct($array = null, $siteaccess = '')
    {
        //Inseriamo l'identificativo
        if(strlen($siteaccess) > 0) {
            $this->siteaccess = $siteaccess;
        }

        //Così facendo possiamo configurare sia alla costruzione che chiamando il metodo
        if( !is_null($this->loadConfigArray($array)) ){

            //Layer di trasporto
            $this->swift_smtp_transport = Swift_SmtpTransport::newInstance($this->getSMTPHost(), $this->getSMTPPort());

            if(!is_null($this->getSMTPUsername())) {
                $this->swift_smtp_transport->setUsername($this->getSMTPUsername());
            }
            if(!is_null($this->getSMTPPassword())) {
                $this->swift_smtp_transport->setPassword($this->getSMTPPassword());
            }


            //Oggetto per il messaggio (swift)
            $this->swift_message_object = Swift_Message::newInstance();
            $this->swift_message_object->setTo($this->getMsgTo());


            if(!is_null($this->getMsgCC())) {
                $this->swift_message_object->setCc($this->getMsgCC());
            }
            if(!is_null($this->getMsgBCC())) {
                $this->swift_message_object->setBcc($this->getMsgBCC());
            }

            $this->swift_message_object->setSubject($this->getMsgSubject());

            //Le impostazioni di Swift vogliono che questo metodo setFrom venga impostato
            //così ($indirizzo, $nome-opzionale) quindi usiamo quest'array d'appoggio
            $infoMittente = $this->getMsgFrom();
            $this->swift_message_object->setFrom($infoMittente['address'], $infoMittente['name']);
        }

    }


    public function loadConfigArray($baseInfo = null) {

        if($baseInfo !== null) {
            //Prima validazione, la configurazione dev'essere in un array
            if(is_array($baseInfo)) {

                //Cominciamo a caricare la configurazione impostando gli eventuali valori per default
                //e impostando la bandierina "Oggetto pronto per lavorare" quando riterremo che il caricamento
                //sia stato completato

                //Controlliamo se è abilitato per inviare mail o meno
                if(isset($baseInfo['enabled'])) {
                    //Controlliamo il parametro debug sotto twig
                    switch (true) {
                        case (!$baseInfo['enabled']):
                            $this->can_send_mail = false;
                            break;
                        case (strcmp('false', strtolower($baseInfo['enabled'])) === 0):
                            $this->can_send_mail = false;
                            break;
                        case ($baseInfo['enabled'] === 0 || $baseInfo['enabled'] === false):
                            $this->can_send_mail = false;
                            break;
                        default:
                            $this->can_send_mail = true;

                    }
                } else {
                    //Per default inviamo le mail
                    $this->can_send_mail = true;
                }


                //Controlliamo le impostazioni per l'SMTP
                if(isset($baseInfo['server_config'])) {

                    //Per lavorare meglio accediamo alla variabile
                    $serverConfig = $baseInfo['server_config'];

                    //Ricaviamo l'indirizzo del server SMTP
                    if(isset($serverConfig['smtp_server'])){
                        $this->smtp_server = $serverConfig['smtp_server'];
                    } else {
                        //Valore di default
                        $this->smtp_server = '127.0.0.1';
                    }

                    //Ricaviamo la porta del server SMTP
                    if(isset($serverConfig['port'])){
                        $this->smtp_server_port = $serverConfig['port'];
                    } else {
                        //Valore di default
                        $this->smtp_server_port = 25;
                    }

                    //Ricaviamo lo username del server SMTP
                    if(isset($serverConfig['smtp_username'])){
                        $this->smtp_server_username = $serverConfig['smtp_username'];
                    } else {
                        //Valore di default
                        $this->smtp_server_username = null;
                    }

                    //Ricaviamo la passwrod del server SMTP
                    if(isset($serverConfig['smtp_password'])){
                        $this->smtp_server_password = $serverConfig['port'];
                    } else {
                        //Valore di default
                        $this->smtp_server_password = null;
                    }

                } else {

                    //Mancano le impostazione del server
                    //(assumiamo che il server SMTP si trova nella macchina attuale - localhost)
                    $this->smtp_server = '127.0.0.1';
                    $this->smtp_server_port = 25;
                    $this->smtp_server_username = null;
                    $this->smtp_server_password = null;

                }

                //Controlliamo il tempo per la reportistica

                //Per capire il formato usato andate su
                // http://php.net/manual/en/class.dateinterval.php

                if(isset($baseInfo['reports'])) {

                    //Per lavorare meglio accediamo alla variabile
                    $timeConfig = $baseInfo['reports'];

                    //Ricaviamo i giorni
                    if(isset($timeConfig['days'])){
                        if($timeConfig['days'] !== null && $timeConfig['days'] !== 0) {
                            $this->report_every_dayinfo .= (int)$timeConfig['days'] .'D';
                        }

                    } else {
                        //Valore di default
                        $this->report_every_dayinfo .= '0D';
                    }

                    //Ricaviamo i minuti
                    if(isset($timeConfig['minutes'])) {
                        if($timeConfig['minutes'] !== null && $timeConfig['minutes'] !== 0) {
                            $this->report_every_time .= (int)$timeConfig['minutes'] .'M';
                        }
                    } else {
                        //Valore di default
                        $this->report_every_time .= '0M';
                    }

                    //Ricaviamo i secondi
                    if(isset($timeConfig['seconds'])){
                        if($timeConfig['seconds'] !== null && $timeConfig['seconds'] !== 0){
                            $this->report_every_time .= (int)$timeConfig['seconds'] .'S';
                        }
                    } else {
                        //Valore di default
                        $this->report_every_time .= '0S';
                    }

                }

                //Per default impostiamo il valore del tempo
                //Per capire il formato usato andate su
                // http://php.net/manual/en/class.dateinterval.php

                if(!is_null($this->report_every_time)) {
                    $this->report_every_time = 'T' . $this->report_every_time;
                }

                //Creiamo la stringa per il DateInterval la quale deve iniziare per la P
                //esempio: P1DT1M
                $this->report_every = 'P' . $this->report_every_dayinfo . $this->report_every_time;

                //Se non abbiamo informazioni né di tempo né di giorni, impostiamo per un giorno per default
                //Per capire il formato usato andate su
                // http://php.net/manual/en/class.dateinterval.php
                if(is_null($this->report_every_dayinfo) && is_null($this->report_every_time)){
                    $this->report_every = 'P1D';
                }



                //Controlliamo le impostazioni per i messaggi
                if(isset($baseInfo['message_config'])) {

                    //Per lavorare meglio accediamo alla variabile
                    $messageConfig = $baseInfo['message_config'];

                    //Ricaviamo il mittente
                    if(isset($messageConfig['from'])){
                        $this->msg_from = $messageConfig['from'];
                    } else {
                        //Valore di default
                        $this->msg_from = array('noreply@localhost.io' => 'Noname');
                    }

                    //Ricaviamo i destinatari
                    if(isset($messageConfig['to'])){
                        $this->msg_to = $messageConfig['to'];
                    } else {
                        //Valore di default
                        $this->msg_to = array('127.0.0.1', 'Loopback Account');
                    }

                    //Ricaviamo le carbon copies
                    if(isset($messageConfig['cc'])){
                        $this->msg_cc = $messageConfig['cc'];
                    } else {
                        //Valore di default
                        $this->msg_cc = null;
                    }

                    //Ricaviamo le blind carbon copies (copie nascoste in italiano)
                    if(isset($messageConfig['bcc'])){
                        $this->msg_bcc = $messageConfig['bcc'];
                    } else {
                        //Valore di default
                        $this->msg_bcc = null;
                    }

                    //Ricaviamo l'oggetto del messaggio
                    if(isset($messageConfig['subject'])){
                        $this->msg_subject = $messageConfig['subject'];
                    } else {
                        //Valore di default
                        $this->msg_subject = 'No subject';
                    }

                    //Abilitiamo l'oggetto per lavorare
                    $this->minimum_req_met = true;

                }
            }
        }

        return $this;
    }

    /**
     * Restituisce l'indirizzo del server SMTP
     * @return mixed
     * @throws \Exception
     */
    private function getSMTPHost() {
        //Ucciderà l'esecuzione se l'oggetto non è pronto per lavorare
        $this->validateOperations();

        return $this->smtp_server;

    }

    /**
     * Restituisce la porta del server
     * @return mixed
     * @throws \Exception
     */
    private function getSMTPPort() {
        //Ucciderà l'esecuzione se l'oggetto non è pronto per lavorare
        $this->validateOperations();

        return $this->smtp_server_port;

    }

    /**
     * Restituisce lo username per l'accesso all'SMTP
     * @return mixed
     * @throws \Exception
     */
    private function getSMTPUsername() {
        //Ucciderà l'esecuzione se l'oggetto non è pronto per lavorare
        $this->validateOperations();

        return $this->smtp_server_username;

    }

    /**
     * Restituisce lo username per la password di accesso all'SMTP
     * @return mixed
     * @throws \Exception
     */
    private function getSMTPPassword() {
        //Ucciderà l'esecuzione se l'oggetto non è pronto per lavorare
        $this->validateOperations();

        return $this->smtp_server_password;

    }

    /**
     * Restituisce il mittente
     * @return mixed
     * @throws \Exception
     */
    private function getMsgFrom() {
        //Ucciderà l'esecuzione se l'oggetto non è pronto per lavorare
        $this->validateOperations();

        if(is_array($this->msg_from)) {
            //Ci serve solo un mittente, facciamo questa porcata per poter prendere la chiave del primo valore
            foreach($this->msg_from as $address => $name) {
                return array('address' => $address, 'name' => $name);
            }
        } else {
            return array('address' => $this->msg_from, 'name' => null);
        }
    }

    /**
     * Restituisce i destinatari
     * @return mixed
     * @throws \Exception
     */
    private function getMsgTo() {
        //Ucciderà l'esecuzione se l'oggetto non è pronto per lavorare
        $this->validateOperations();

        return $this->msg_to;

    }

    /**
     * Restituisce i destinatari per la carbon copy
     * @return mixed
     * @throws \Exception
     */
    private function getMsgCC() {
        //Ucciderà l'esecuzione se l'oggetto non è pronto per lavorare
        $this->validateOperations();

        return $this->msg_cc;

    }

    /**
     * Restituisce i destinatari per la blind carbon copy
     * @return mixed
     * @throws \Exception
     */
    private function getMsgBCC() {
        //Ucciderà l'esecuzione se l'oggetto non è pronto per lavorare
        $this->validateOperations();

        return $this->msg_bcc;

    }

    /**
     * Restituisce l'oggetto della mail
     * @return mixed
     * @throws \Exception
     */
    private function getMsgSubject() {
        //Ucciderà l'esecuzione se l'oggetto non è pronto per lavorare
        $this->validateOperations();

        return $this->msg_subject;

    }

    /**
     * Invia un messaggio con le impostazione precedemente messe
     * @param $any_text
     * @return $this
     */

    public function sendMessage($any_text) {

        //Se possiamo inviare mail le inviamo
        if($this->can_send_mail) {
            $this->swift_message_object->setContentType('text/html');
            $this->swift_message_object->setBody($any_text);

            $this->swift_mailer = Swift_Mailer::newInstance($this->swift_smtp_transport);
            $this->swift_mailer->send($this->swift_message_object, $this->failed_recipients);
        }


        return $this;
    }

    /**
     * Controlla se l'oggetto è stato configurato o meno
     * @throws \Exception
     */
    private function validateOperations(){
        //Qui possiamo stoppare l'esecuzione per mancanza di dati per poter andare avanti
        //ma visto che per questo modulo non è necessario l'arresto ommettiamo questo passaggio

        //if(!$this->minimum_req_met) {
        //    throw new \Exception('MailerConfigurator non può lavorare, controllate nel vostro YAML se esistono i parametri.');
        //}
    }

    /**
     * Restituisce la stringa per il DateInterval
     * @return mixed
     */
    public function getReportTimeConfig(){
        return $this->report_every;
    }
}