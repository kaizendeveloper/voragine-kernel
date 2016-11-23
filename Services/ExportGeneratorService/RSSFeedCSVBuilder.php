<?php
/**
 * L'engine per la costruzione dell'XML
 *
 * @author: Miguel Delli Carpini
 * @author: Matteo Scirea
 * @author: Javier Jara
 *
 * 
 *
 */

namespace Voragine\Kernel\Services\ExportGeneratorService;

class RSSFeedCSVBuilder
{

    //Qui salviamo l'instestazione del CSV
    protected $intestazione;

    //E qui ogni riga del CSV
    protected $csv_accumulator;

    //Separatore per default
    protected $separator_char;

    //Delimitatore di testo
    protected $text_delimiter;



    /**
     * RSSFeedXMLBuilder constructor.
     */
    public function __construct()
    {

        $this->intestazione = '';
        $this->text_delimiter = '';
        $this->separator_char = ',';
        $this->csv_accumulator = '';

    }

    /**
     * Inserisce sotto la document root i dati relativi a un utente in modalità "update"
     * <CMD V="U">
     *
     * @param array $userInfoArray
     */
    public function insertElements($userInfoArray = array()){

        if(count($userInfoArray) > 0) {

            //Solo la prima volta prendiamo l'array associativo per creare l'intestazione
            if($this->intestazione === '') {
                //Prendiamo le chiavi dall'array associativo
                $keysArray = array_keys($userInfoArray);

                //Iniziando dal delimitatore naturalmente
                $this->intestazione = $this->text_delimiter;
                //Creiamo la stringa mettendo come glue il delimitatore + separatore + delimitatore
                //Ad Esempio: ","
                $this->intestazione .= implode($this->text_delimiter . $this->separator_char . $this->text_delimiter, $keysArray);
                //Finendo dal delimitatore naturalmente
                $this->intestazione .= $this->text_delimiter;
            }

            //Creiamo riga del CSV, mettendo i delimitatori e i separatori
            $this->csv_accumulator .= $this->text_delimiter;
            $this->csv_accumulator .= implode($this->text_delimiter . $this->separator_char . $this->text_delimiter, $userInfoArray);
            $this->csv_accumulator .= $this->text_delimiter . "\r\n";

        }

        $userInfoArray = null;
        unset($userInfoArray);
    }

    /**
     * Restituisce il CSV finale
     * @param $withoutHeader boolean Stabilisce se si vuole stampare senza l'intestazione, utile quando si
     *                               devono concatenare più CSV
     * @return string
     */
    public function generateFinalDocument($withoutHeader = false){

        if($withoutHeader === true) {
            //Senza intestazione
            return $this->csv_accumulator;
        } else {
            //Con intestazione
            return $this->intestazione . "\r\n" . $this->csv_accumulator;
        }
    }

    public function setSeparatorCharacter($char)
    {
        $this->separator_char = (string)$char;
    }

    public function setDelimiterCharacter($char)
    {
        $this->text_delimiter = (string)$char;
    }

    public function resetBuffer()
    {

        $this->csv_accumulator = null;
        $this->intestazione = null;

        return $this;
    }

}