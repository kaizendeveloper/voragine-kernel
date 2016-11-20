<?php
/**
 * L'engine per la costruzione del CSV
 *
 * @author: Miguel Delli Carpini
 * @author: Matteo Scirea
 * @author: Javier Jara
 *
 * 
 *
 */

namespace Voragine\Kernel\Services\ExportGeneratorService;

class RSSFeedXMLBuilder
{
    protected $field_mapping;

    //Radice contenente l'oggetto padre per il DOM
    protected $dom_document;

    //Radice per il <dataroot>
    protected $document_root;


    /**
     * RSSFeedXMLBuilder constructor.
     */
    public function __construct()
    {

        //Creiamo il documento DOM
        $this->dom_document = new \DOMDocument( '1.0', 'utf-8' );

        //Creiamo la radice del documento che avrà questo nome
        $this->document_root = $this->dom_document->createElement('dataroot');

        //Appendiamo al DOM
        $this->dom_document->appendChild($this->document_root);

    }

    /**
     * Inserisce sotto la document root i dati relativi a un utente in modalità "update"
     * <CMD V="U">
     *
     * @param array $userInfoArray
     */
    public function insertElements($userInfoArray = array()){

        if(count($userInfoArray) > 0) {
            //<CMD V="U">
            $cmd = $this->dom_document->createElement('CMD');
            $cmd->setAttribute('V', 'U');

            foreach($userInfoArray as $key => $valore) {

                $userInfo = $this->dom_document->createElement($key, $valore);
                $cmd->appendChild($userInfo);
                
            }

        }
        
        $this->document_root->appendChild($cmd);

    }

    /**
     * Restituisce l'XML finale
     * @return string
     */
    public function generateFinalDocument(){

        return $this->dom_document->saveXML();
    }


}