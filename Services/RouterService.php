<?php
/**
 * RouterService
 *
 * Cofigura e mette a disposizione il crawler HTTP
 *
 * @authors: Miguel Delli Carpini, Matteo Scirea, Javier Jara
 */
namespace Voragine\Kernel\Services;

use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;


class RouterService
{

    const CONFIG_PATH = 'Resources/config/routing';
    const ROUTE_FILE = 'routes.yml';


    //Solo per avere un riferimento
    protected $siteaccess;

    //Qui ci saranno le colezioni di Router
    protected $collection;

    //Sottoservizio che ci penserà a fare il matching per noi
    protected $url_matcher;

    /**
     * RouterService constructor.
     * @param array $serviceYamlConfigInfo
     * @param string $siteaccess
     */
    public function __construct($serviceYamlConfigInfo = array(), $siteaccess = '')
    {
        //Assegniamo solo per avere un riferimento nei debug
        $this->siteaccess = $siteaccess;

        //Ci pensa il FileLocator a trovare uno o più file in base al percorso
        $locator = new FileLocator(array(APP_BASEDIR . self::CONFIG_PATH));
        $loader = new YamlFileLoader($locator);

        //Automaticamente il loader ci pensa a caricarci le collezioni
        $this->collection = $loader->load(self::ROUTE_FILE);

        //Visto che dobbiamo incorporare l'url matcher ai servizi, approfittiamo questo giro qui
        $context = new RequestContext();
        $this->url_matcher = new UrlMatcher($this->collection, $context);


    }

    /**
     * Restuisce l'informazione appartenente al route
     * @param $urlToMatch
     * @return array|\Exception|Exception|ResourceNotFoundException
     */
    public function match($urlToMatch)
    {

        //Visto che quando non c'è nessuna coincidenza il matcher lancerà un'eccezione
        try {

            $routeParameters = $this->url_matcher->match($urlToMatch);

        } catch (ResourceNotFoundException $e) {
            //In caso di errore restituiamo l'oggetto di errore per il momento
            $routeParameters = $e;
        } catch (Exception $e) {
            //In caso di errore restituiamo l'oggetto di errore per il momento
            $routeParameters = $e;
        }

        return $routeParameters;

    }

    /**
     * Restituisce la collezione già parsata
     * @return mixed
     */
    public function getRouteCollection()
    {
        return $this->collection;
    }



}