<?php
/**
 * ImageHandlerService
 *
 * Se ne occupa del processamento delle immagini
 *
 * @author: Miguel Delli Carpini
 * @author: Matteo Scirea
 * @author: Javier Jara
 */
namespace Engine\Kernel\Services\ImageHandlerService;



class ImageHandler extends FileHandler
{

    //Immagine da mettere quando non ce n'è una
    const NO_IMAGE_GIF = "data:image/gif;base64,R0lGODlhSAA2AOeCALO5q7S5rLS6rLW6rbW7rba7rra8rre8r7i9sLi+sbm+sbm+srq/srq/s7vAs7vAtLzBtb3Btb3Ctr7Dt7/DuL/EuMDEucDFusHFusHGu8LGu8LHvMPHvMPHvcTIvcTIvsTJvsXJv8bKv8bKwMfLwcjMwsjMw8nNw8rNxMrOxMrOxcvOxcvPxszPxszPx8zQx83Qx83RyM7RyM7Ryc7Syc/SytDTytDTy9HUzNHUzdLVzdPWztPWz9TXz9TX0NXX0NXY0dbY0dbZ0tfZ09fa09ja09ja1Njb1Nnb1dnc1drc1trd1tvd19ve2Nze2N3f2d3f2t7g2t7g29/h29/h3ODi3eHi3eHj3uLj3+Lk3+Pk4OPl4OTl4eTm4eXm4uXn4+bn4+fo5Ofp5ejp5ejq5unq5+nr5+rr5+rr6Ovs6Ovs6ezt6u3u6+7v7O7v7e/w7fDw7vDx7/Hx7/Hy8PLy8PLz8fPz8fPz8vP08vT08vT08/T18/X19Pb29Pb29ff39ff39vj49////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////yH+EUNyZWF0ZWQgd2l0aCBHSU1QACwAAAAASAA2AAAI/gADCRxIsKDBgwgTKlzIsKHDhxAjSpxIsaLFixgzatzIsaPHjyBDihxJsqTJkyhTqlx5cYnLJk2WsKRoAYDNmzbTzIyIsyeAnQxtEvDZcygAAkANEl3aM+nAG0yj/nQaiGkfgWuWaqG6VKlPMFx9CsQ5tidYp2KrNlV700pYsmxvlr15Nmnauzi5vJUb1+Zcm3rR4vXbF0Bguz7XGBTh8zBQpgSXOt5pQipkqoUtE8aseS3mz6BD2x07V+fAn4SnVjVwmrBR1R/9op4Ke7ba1qRJAxBBUnbV05Grgk39u3jrzbEDBUAt0/jv2bQDBGCtGjXvkT8j0DYaHPWbn05oHv8NZGA57JJ1J6YXzb69+/fw48ufT7++/fv482sMCAA7";

    //Valori per default per la conversione
    public $max_width = 72;
    public $max_height = 72;

    public $alias_list;

    //Informazioni relative al file dell'immagine
    //---------------------------------------------
    //Nome del file con e senza estensione
    protected $image_filename_noext;
    protected $image_filename;
    //Dimensioni del file una volta abbiamo letto dal filesystem o da URL
    protected $image_file_size;
    //Percorso di partenza dopo la working dir dell'applicazione
    protected $image_directory;
    //Extension del file a processare
    protected $image_extension;
    //Partial path si riferisce all'unione tra la cartella di partenza e il filename originale
    protected $image_partial_dir_fn;
    //Uguale a Partial Path ma senza l'extension
    protected $image_partial_dir_fn_noext;
    //Buffer con l'immagine originale
    protected $image_original_buffer;
    //Buffer con l'immagine trasformata
    protected $image_converted_buffer;

    //File temporaneo di processamento alias
    protected $temp_proc_file;

    //Qui salviamo tutte le credenziali per HTTP BASIC AUTH
    protected $auth_vault;

    public function __construct($array = null, $siteaccess = '')
    {

        //Definiamo la "costante" ovvero la cartella di lavoro, dove butteremo i file
        $this->WORKING_BASE_PATH = APP_BASEDIR . 'var/images';

        //Dato un YAML di configurazione questa sarebbe la stringa che identifica il pezzo da prelevare
        $this->CFG_ARRAY_KEY = 'imagealias_handler';

        //--------------------------------------------------------------------------------------------------

        //Inseriamo l'identificativo
        if(strlen($siteaccess) > 0) {
            $this->siteaccess = $siteaccess;
        }

        //Così facendo possiamo configurare sia alla costruzione che chiamando il metodo se facciamo un'istanza
        //al di fuori l'ambito di un servizio
        $this->loadConfigArray($array);


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
                $baseInfo = $yamlConfigArray[$this->CFG_ARRAY_KEY];


                foreach($baseInfo as $alias => $parametri) {
                    //Stiamo a livello ALIAS


                    //Il parametro auth_vault è parola riservata e serve per salvare una lista
                    //di credenziali utili per i siti sotto password, come quelli di test
                    if($alias === 'auth_vault') {

                        //Giriamo questi per estrapolare le credenziali
                        foreach($parametri as $key => $authParams)
                        {
                            //Salviamo solo se esistono entrambi parametri, altrimenti ignoriamo
                            if(isset($authParams['password']) && isset($authParams['username']))
                            {
                                //Credenziale da salvare
                                $this->auth_vault[] = $authParams;

                            }

                        }
                    } else {

                    foreach($parametri as $funzione => $paramFunzioni) {
                        //Qui invece a livello dei parametri di ogni funzione
                        //dobbiamo castare a stringa per evitare che prenda la valutazione
                        //con falsi positivi

                        switch((string)$funzione){
                            case 'scaledownwidth':
                                $this->alias_list[$alias][$funzione] = $paramFunzioni;
                                break;
                            case 'scaledownheight':
                                $this->alias_list[$alias][$funzione] = $paramFunzioni;
                                break;
                            case 'crop':
                                $this->alias_list[$alias][$funzione] = $paramFunzioni;
                                break;
                        }
                    }



                    }


                }

            }


        }

        return $this;
    }




    public function processaAlias($idImmagine, $URLImmagineOriginale)
    {

        //Estrazione del nome del file passato tramite URL
        $urlParsata = parse_url($URLImmagineOriginale);
        $imageFilename = basename($urlParsata['path']);



        //Non ci serve più
        unset($urlParsata);

        //Ricaviamo le informazioni possibili sul file a partire dal nome
        $fileInfo = pathinfo($imageFilename);
        $this->image_filename_noext = $fileInfo['filename'];
        $this->image_filename = $fileInfo['basename'];
        $this->image_extension = $fileInfo['extension'];
        $this->image_directory = $idImmagine;
        $this->image_partial_dir_fn = $idImmagine . '/' . $this->image_filename;
        $this->image_partial_dir_fn_noext = $idImmagine . '/' . $this->image_filename_noext;

        //Creaiamo un file univoco in base al timestamp che fungirà come
        //file temporaneo per i processamenti
        $this->temp_proc_file = 'tmp-' . md5((string) time()) . '.' . $this->image_extension;
        //$this->temp_proc_file = 'tmp-b9df2d846d024c0a11b28a56d0024ceb.jpg';

        //Via
        unset($fileInfo);


        //Verifichiamo se esiste la cartella, cui nome dipende dall'id dell'oggetto in realtà
        if($this->checkIfDirectoryExists($idImmagine)){
            //Esiste la cartella, verifichiamo l'esistenza del file
            if($this->fileExists($this->image_partial_dir_fn)){

                //Il file esiste nel filesystem, prendiamolo come riferimento di partenza
                $this->image_original_buffer = $this->readFileFromFilesystem($this->image_partial_dir_fn);

            } else {

                //La cartella esiste ma il file originale no, andiamo a scaricarla e a salvarla
                //nel nostro filesystem
                $this->image_original_buffer = $this->readImageFromURL($URLImmagineOriginale);
                if($this->image_original_buffer !== null)
                {
                    //E salviamo nel nostro filesystem
                $this->saveFileOnDisc($this->image_original_buffer, $this->image_partial_dir_fn);
            }

            }

        } else {

            //No, la cartella non esiste, allora dobbiamo creare la directory
            $this->createDirectory($this->image_directory);
            //Leggiamo da URL
            $this->image_original_buffer = $this->readImageFromURL($URLImmagineOriginale);
            if($this->image_original_buffer !== null)
            {
            //E salviamo nel nostro filesystem
            $this->saveFileOnDisc($this->image_original_buffer, $this->image_partial_dir_fn);
            }

        }


        //Ecco fatto, ora dovremmo avere un Original dal quale iniziare a lavorare


        foreach($this->alias_list as $alias => $funzione) {

            //Livello ALIAS
            //Per ogni alias prendiamo la funzione e i sui parametri

            foreach($funzione as $operazione => $parametro) {

                //Qui abbiamo il wrapper delle funzioni valide per le immagini

                //Tenete presente che tutte le operazioni si salvano in un file temporaneo
                //dopodiché alla fine di tutto stabimage salva il file destino come si deve
                //con tutte le modifiche previe
                switch($operazione) {
                    case 'scaledownwidth':
                        $this->scaleDownWidth($parametro);
                        break;
                    case 'scaledownheight':
                        $this->scaleDownHeight($parametro);
                        break;
                    case 'crop':
                        //Se un crop è impostato correttamente dovremmo poter fare
                        list($parWidth, $parHeight, $parXPos, $parYPos) = $parametro;
                        $this->crop($parWidth, $parHeight, $parXPos, $parYPos);
                        unset($parWidth, $parHeight, $parXPos, $parYPos);
                        break;

                }

            }

            //Salva il file e cancella qualsiasi file rimasto appeso
            $this->stabImage($alias);


        }


    }

    /**
     * Legge un'immagine usando un URL
     *
     * @param $imageURL
     * @return mixed|null restituisce il binario in una stringa oppure null se c'è qualche problema
     */
    public function readImageFromURL($imageURL) {
        //Abbiamo l'extension CURL attivata?
        if (in_array('curl', get_loaded_extensions())) {

            //CURL è abilitato nel nostro server, facciamone uso
            $curl = curl_init();

            //Per evitare il caricamento di tutta un'HP quando l'articolo XML non ha un'immagine facciamo:
            if(!preg_match('/jpg|jpeg|gif|bmp|png$/i',$imageURL))
            {
                return $this->getNoImage();
            }

            curl_setopt($curl, CURLOPT_URL, $imageURL);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 120); //Timeout da 2 minuti

            $maxRichieste = 5;

            // HTTP BASIC AUTH
            //-------------------------------------------------------------------------------
            //Qui metteremo le credenziali in caso di errore per HTTP BASIC AUTH
            $authHttpCredentials = array();

            //Facciamo una copia di sicurezza per non lavorare direttamente dalla matrice
            //ci servirà poiché sputtaneremo l'array di copia
            $authVaultCopy = $this->auth_vault;

            //Se ci viene impostata la lista delle credenziali dobbiamo fermare il ciclo while se le abbiamo provato tutte
            if(count($this->auth_vault) > 0) {
                $stopAfterTryingAllCredentials = true;
            } else {
                $stopAfterTryingAllCredentials = false;
            }

            //-------------------------------------------------------------------------------

            do
            {

                //Nel caso di un giro fallito per via di un URL che chiede credenziali, quest'array sarà popolato
                //al primo giro non dovrebbe entrare qui
                if(is_array($authHttpCredentials) && count($authHttpCredentials) > 0)
                {
                    //Settiamo il tipo di authorize a Basic (è importante questo)
                    curl_setopt($curl, CURLOPT_USERPWD, $authHttpCredentials['username'] . ":" . $authHttpCredentials['password']);

                }


            //Verrà letto dopo in caso il server risponda con un 200
            $datiOttenuti = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                //In caso di richiesta credenziali
                if($httpCode >= 400 && $httpCode <= 403){
                    //Prendiamo una delle credenziali scartando il resto (così non dobbiamo ciclare in modo intelligente)
                    $oneCredential = array_pop($authVaultCopy);

                    //Elaboriamo l'auth header
                    if(!is_null($oneCredential))
                    {

                        //Impostiamo la variabile da usare come credenziali per l'accesso successivo
                        $authHttpCredentials = $oneCredential;

                    //Stop a credenziali finite
                    } elseif($stopAfterTryingAllCredentials) {

                        //Abbiamo finito le credenziali, a cosa serve ritentare fino all'esaurimento di $maxRichieste?
                        $maxRichieste = -5;

                    }
                } elseif($httpCode === 200){

                    //Usciamo dal loop una volta ottenuta una risposta soddisfacente
                    $maxRichieste = -5;
                }

                $maxRichieste--;

            } while ($maxRichieste > 0);

            //Leviamo ciò che non ci serve
            unset($authHttpCredentials, $authVaultCopy, $stopAfterTryingAllCredentials, $oneCredential);

            //Salviamo risorse liberandone la connessione
            curl_close($curl);

            //Se qualcosa andrà storto CURL darà false
            if ($httpCode !== 200) {
                return null;
            } else {
                return $datiOttenuti;
            }
        } else {

        }
    }

    /**
     * Rimpicciolisce un'immagine mantenendo la proporzione in base alla larghezza
     *
     * @param $width
     * @return $this
     */
    public function scaleDownWidth($width){

        //Vediamo se qualche filtro precedentemente ha fatto il suo operato
        //cioè che lavoreremo a partire dal file temporaneo
        $immagineATrattare = $this->readFileFromFilesystem($this->image_directory . DIRECTORY_SEPARATOR  . $this->temp_proc_file);

        if( $immagineATrattare === null && $this->image_original_buffer !== null) {
            $immagineATrattare = $this->image_original_buffer;
        } else {
            return $this;
        }

        //Se il server risponde, a partire dall'informazione restituita prendiamo le dimensioni
        //Nota: Prima della versione 5.4 di PHP non viene supportata l'instruzione getimagesizefromstring
        if (!function_exists('getimagesizefromstring')) {
            $uri = 'data://application/octet-stream;base64,' . base64_encode($immagineATrattare);
            list($width_orig, $height_orig) = getimagesize($uri);
        } else {
            list($width_orig, $height_orig) = getimagesizefromstring($immagineATrattare);
        }


        //Calcoliamo aspect ratio
        $ratio_orig = $width_orig / $height_orig;

        //Agiamo solo se la larghezza supera quella stabilita dal filtro
        if ($width_orig > $width) {
            $newHeight = $width / $ratio_orig;
            $newWidth = $width;

            //Creiamo un'immagine canvas vuota
            $image_p = imagecreatetruecolor($newWidth, $newHeight);

            //A partire dall'informazione contenuta nell'immagine creiamo la risorsa image richiesta
            //dalla libreria GD (PHP 4 >= 4.0.4, PHP 5, PHP 7)
            $image = imagecreatefromstring($immagineATrattare);


            //Ecco la funzione PHP che fa il resampling
            imagecopyresampled($image_p, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width_orig, $height_orig);

            //Per poter prendere il contenuto dobbiamo catturare l'output dal buffer (che menta)
            ob_start();
            //Questo è l'unico modo per catturare il contenuto della conversione
            imagejpeg($image_p);

            //Prendiamo l'output
            $final_image = ob_get_contents();

            //E puliamo il buffer
            ob_end_clean();

        } else {
            $final_image = $immagineATrattare;
        }


        if($final_image !== null)
        {
        //Salviamo il file temporaneo
            $this->saveFileOnDisc($final_image, $this->image_directory . DIRECTORY_SEPARATOR . $this->temp_proc_file);
        }


        imagedestroy($image);
        imagedestroy($image_p);

        return $this;

    }

    /**
     * Rimpicciolisce un'immagine mantenendo la proporzione in base all'altezza
     *
     * @param $height
     * @return $this
     */
    public function scaleDownHeight($height){

        //Vediamo se qualche filtro precedentemente ha fatto il suo operato
        //cioè che lavoreremo a partire dal file temporaneo
        $immagineATrattare = $this->readFileFromFilesystem($this->image_directory . '/' . $this->temp_proc_file);

        if( $immagineATrattare === null && $this->image_original_buffer !== null) {
            $immagineATrattare = $this->image_original_buffer;
        } else {
            return $this;
        }

        //Se il server risponde, a partire dall'informazione restituita prendiamo le dimensioni
        //Nota: Prima della versione 5.4 di PHP non viene supportata l'instruzione getimagesizefromstring
        if (!function_exists('getimagesizefromstring')) {
            $uri = 'data://application/octet-stream;base64,' . base64_encode($immagineATrattare);
            list($width_orig, $height_orig) = getimagesize($uri);
        } else {
            list($width_orig, $height_orig) = getimagesizefromstring($immagineATrattare);
        }


        //Calcoliamo aspect ratio
        $ratio_orig = $width_orig / $height_orig;

        //Agiamo solo se la larghezza supera quella stabilita dal filtro
        if ($height_orig > $height) {
            $newHeight = $height;
            $newWidth = $height * $ratio_orig;

            //Creiamo un'immagine canvas vuota
            $image_p = imagecreatetruecolor($newWidth, $newHeight);

            //A partire dall'informazione contenuta nell'immagine creiamo la risorsa image richiesta
            //dalla libreria GD (PHP 4 >= 4.0.4, PHP 5, PHP 7)
            $image = imagecreatefromstring($immagineATrattare);


            //Ecco la funzione PHP che fa il resampling
            imagecopyresampled($image_p, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width_orig, $height_orig);

            //Per poter prendere il contenuto dobbiamo catturare l'output dal buffer (che menta)
            ob_start();
            //Questo è l'unico modo per catturare il contenuto della conversione
            imagejpeg($image_p);

            //Prendiamo l'output
            $final_image = ob_get_contents();

            //E puliamo il buffer
            ob_end_clean();

        } else {
            $final_image = $immagineATrattare;
        }

        if($final_image !== null)
        {
        //Salviamo il file temporaneo
            $this->saveFileOnDisc($final_image, $this->image_directory . DIRECTORY_SEPARATOR . $this->temp_proc_file);
        }



        imagedestroy($image);
        imagedestroy($image_p);

        return $this;
    }


    public function crop($width = null, $height = null, $xPos = null, $yPos = null) {
        //Vediamo se qualche filtro precedentemente ha fatto il suo operato
        //cioè che lavoreremo a partire dal file temporaneo
        $immagineATrattare = $this->readFileFromFilesystem($this->image_directory . DIRECTORY_SEPARATOR . $this->temp_proc_file);

        if( $immagineATrattare === null && $this->image_original_buffer !== null) {
            $immagineATrattare = $this->image_original_buffer;
        } else {
            return $this;
        }

        //A partire dall'informazione restituita prendiamo le dimensioni
        //Nota: Prima della versione 5.4 di PHP non viene supportata l'instruzione getimagesizefromstring
        if (!function_exists('getimagesizefromstring')) {
            $uri = 'data://application/octet-stream;base64,' . base64_encode($immagineATrattare);
            list($width_orig, $height_orig) = getimagesize($uri);
        } else {
            list($width_orig, $height_orig) = getimagesizefromstring($immagineATrattare);
        }


        //Se i parametri per il crop sono più grandi dell'immagine a trattare, reimpostiamo quelli
        if($width >= $width_orig){
            $width = $width_orig;
        }
        if($height >= $height_orig){
            $height = $height_orig;
        }

        //Se manca un valore mettiamo 0 per default
        if($width === null) {
            $width = 0;
        }
        if($height === null) {
            $height = 0;
        }
        if($xPos === null) {
            $xPos = 0;
        }
        if($yPos === null) {
            $yPos = 0;
        }





        //Creiamo un'immagine canvas vuota
        $image_p = imagecreatetruecolor($width, $height);

        //A partire dall'informazione contenuta nell'immagine creiamo la risorsa image richiesta
        //dalla libreria GD (PHP 4 >= 4.0.4, PHP 5, PHP 7)
        $image = imagecreatefromstring($immagineATrattare);

        // Lasciamo qui il pezzo della documentazione PHP per avere un riferimento
        // sui parametri da passare alla funzione imagecopyresampled
        // imagecopyresampled ( resource $dst_image , resource $src_image ,
        //  int $dst_x , int $dst_y ,
        //  int $src_x , int $src_y ,
        //  int $dst_w , int $dst_h ,
        //  int $src_w , int $src_h )

        //Ecco la funzione PHP che fa il resampling
        imagecopyresampled($image_p, $image, 0, 0, $xPos, $yPos, $width, $height, $width, $height);

        //Per poter prendere il contenuto dobbiamo catturare l'output dal buffer (che menta)
        ob_start();
        //Questo è l'unico modo per catturare il contenuto della conversione
        imagejpeg($image_p);

        //Prendiamo l'output
        $final_image = ob_get_contents();

        //E puliamo il buffer
        ob_end_clean();



        if($final_image !== null)
        {
        //Salviamo il file temporaneo
            $this->saveFileOnDisc($final_image, $this->image_directory . DIRECTORY_SEPARATOR . $this->temp_proc_file);
        }


        imagedestroy($image);
        imagedestroy($image_p);

        return $this;
    }


    /**
     * Prende il file temporaneo e lo fa diventare il file finale dell'alias
     * @param $alias
     * @return $this
     */
    public function stabImage($alias) {

        //Elaboriamo il nome in base all'alias
        $newFileName = $this->WORKING_BASE_PATH . '/' .
            $this->image_directory . '/' .
            $this->image_filename_noext . '_' . $alias . '.' . $this->image_extension;
        $tempFileName = $this->WORKING_BASE_PATH . '/' . $this->image_directory . '/' . $this->temp_proc_file;

        rename($tempFileName, $newFileName);


        //Garbage collector
        //-------------------------

        //Data la natura del processamento, se magari un processamento si blocca e poi viene riavviato successivamente
        //sarebbe naturale che rimangano dei file temporanei appesi, ogni filtro dovrebbe implementare lo stocade
        $filesTemporaneiAppesi = glob($this->WORKING_BASE_PATH . '/' . $this->image_directory . '/' . 'tmp-*');
        foreach($filesTemporaneiAppesi as $tempFile) {
            $fileInformation = stat($tempFile);

            //Prendiamo la data di ultima modifica UNIX per poi verificare se si tratta di un file
            //troppo arretrato, come facciamo a sapere se magari un file è rimasto appeso o meno?

            //Beh la nostra interpretazione sarà che un processamento così semplice non dovrebbe impiegare più di 5 minuti
            //quindi un file temporaneo arretrato più di 5 min dovrebbe essere rimasto appeso
            $timeDiff = time() - $fileInformation['mtime'];

            if($timeDiff > (60*5)){
                //File con più del tempo voluto, zappiamolo via
                unlink($tempFile);
            }
        }

        return $this;

    }




}