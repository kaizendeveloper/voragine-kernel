<?php
/**
 * ControllerResolverService
 *
 *
 * @authors: Miguel Delli Carpini, Matteo Scirea, Javier Jara
 */
namespace Voragine\Kernel\Services;


use Voragine\Kernel\Services\Base\ServiceLoader;
use Symfony\Component\Routing\RouteCollection;

class ControllerResolverService
{

    //Eventuale service pool
    protected $services;

    /**
     * Si incarica di spacciare il controller assegnato al route
     *
     * @param RouteCollection $routeCollection
     * @param $routeMatchArray
     *
     * @return Exception
     */
    public function dispatchController(RouteCollection $routeCollection, $routeMatchArray = array())
    {

        //Se non si tratta di un'eccezione, possiamo agire
        if($this->objectIsOfType('Exception',$routeMatchArray) === false) {
            //Ci sono due cose che non possono mancare nel routeMatchArray:
            // le chiavi associative _controller e _route

            if(isset($routeMatchArray['_route']) && isset($routeMatchArray['_controller'])) {

                //Dobbiamo capire come ci hanno impostato i parametri nel route
                //-----------------------------------------------------------------

                //Nota:
                //Visto che dovremo chiamare la funzione dentro il controller tramite call_user_func_array
                //dobbiamo assicurarci che l'ordinamento dei parametri impostati nello YAML combaci perfettamente
                //con l'ordinamento con cui verranno passati gli argomenti alla funzione, ogni oggetto RouteCollection
                //ha un metodo che restituisce un oggetto Route che a sua volta contiene un metodo che estrae il parametro
                //compilato, questa compilazione avviene grazie all'UrlMatcher (che è stato chiamato prima di arrivare qui)


                $ordineParametri = $routeCollection->get($routeMatchArray['_route'])->compile();

                //OrdineParametri sarà un oggetto di tipo CompiledRoute, dal quale possiamo prendere l'array ordinato tale
                //quale è stato impostato nello YAML

                $pathVars = $ordineParametri->getPathVariables();

                //Argomenti da passare al metodo della classe
                $args = array();

                //Ora possiamo elaborare i parametri da passare al metodo nel controller desiderato
                foreach($pathVars as $singlePath)
                {
                    //Proviamo a ricavare dal matching ogni singolo parametro impostato nello YAML
                    $args[] = $routeMatchArray[$singlePath];

                }


                //I namespace per i controller dovrebbe essere a partire da Voragine\Controllers, quindi evitiamo
                //agli sviluppatori di scrivere il namespace completo nel route YAML

                $controllerParsed = explode('::', $routeMatchArray['_controller']);

                if(isset($controllerParsed[0])) {
                    $controllerClass = $controllerParsed[0];
                } else {
                    return new Exception('Classe del Controller per il Route: ' . $routeMatchArray['_route'] . 'non trovato');
                }
                if(isset($controllerParsed[1])) {
                    $controllerMethod = $controllerParsed[1];
                } else {
                    //Se non viene specificato un metodo assumiamo che l'implementazione viene fatta nel constructor
                    $controllerMethod = '';
                }


                //Hanno tentato di puntare verso un namespace diverso del canonico?
                if(preg_match('/\\\\[\w]+$/i', $controllerClass) !== 1)
                {
                    //No, aggiungiamo il namespace dei controller
                    $controllerClass = 'Voragine\\Controllers\\' . $controllerClass;
                }


                unset($controllerParsed);

                //Adesso spacciamo
                //-----------------------

                //Dobbiamo prima creare un'istanza dell'oggetto
                $dummyObject = new $controllerClass;

                //Passiamo al Controller il contenitore dei servizi, se ce n'è uno
                if($this->objectIsOfType('ServiceLoader', $routeMatchArray) === false)
                {
                    $dummyObject->assignServicePool($this->services);

                }

                //Per poi passare il metodo e argomenti e chiamare il controller
                return call_user_func_array(array($dummyObject, $controllerMethod), $args);

            } else {
                //Invalido
                return new Exception('Nessun route per trovato per questa richiesta');
            }
        } else {

            //Sicuramente tratta di un'eccezione, restituiamo tale quale
            return $routeMatchArray;
        }

    }

    /**
     *
     * @param ServiceLoader $servicePool
     */
    public function makeControllersUseTheseServices(ServiceLoader $servicePool)
    {
        $this->services = $servicePool;
    }

    /**
     * Controlla se un oggetto è di un tipo X
     *
     * @param $objectTypeToCheck
     * @param $variableToCheck
     * @return bool
     */
    public function objectIsOfType($objectTypeToCheck, $variableToCheck)
    {

        //Innanzi tutto verifichiamo che sia un oggetto
        if(is_object($variableToCheck))
        {
            //Sì, allora vediamo se contiene qualche descrizione che lo identifichi
            if(preg_match('/' . (string)$objectTypeToCheck . '/i', @get_class($variableToCheck)) === 1) {

                //E' di questo tipo
                return true;

            } else {

                //E' di un altro tipo
                return false;
            }

        } else {
            //Non è manco un oggetto
            return false;

        }
    }



}