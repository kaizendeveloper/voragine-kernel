<?php

namespace Voragine\Kernel\Services\Base\Tests;


use PHPUnit\Framework\TestCase;
use Voragine\Kernel\Services\Base\ServiceLoader;
use Voragine\Kernel\Tests\Fixtures\MockService;

class ServiceLoaderTest extends TestCase {

    //Default values
    const DEFAULT_CONFIG_PATH = 'Resources/config';
    const DEFAULT_MAIN_CONFIG_FILE = 'mainconfig.yml';

    //String constants used while reading the conf keys
    const SERVICE_CLASS_ID = 'class';
    const SERVICE_CONF_ALIAS_ID = 'conf_alias';
    const SERVICE_MANDATORY_ID = 'mandatory';

    //Default base namespace for all services
    const DEFAULT_BASE_NAMESPACE = 'Voragine\\Kernel\\Services\\';


    //Siteaccess
    protected $siteaccess;
    protected $siteaccess_config_file_data;

    //For loading the config files
    protected $base_config_path;
    protected $main_config_file;


    //Services
    //-----------------------------------

    //Spazio per le istanze dei servizi
    protected $service_pool;
    protected $service_definition;

    protected $logger;

    protected $special_config_array;



    public function testInitializeServiceLoader($environment = 'devel', $specialConfigurations = null){

        //Point configuration path to the Fixture area
        $testBasePath = '/Fixtures/config';

        //Launch the service loader, modifying its default configuration so it does initialize under Test/Fixtures/config folder
        //instead of default Resources/config/ folder
        $serviceLoader = new ServiceLoader($environment,
            array(
                ServiceLoader::CFGK_SERVICE_LOADER => array(
                    ServiceLoader::CFGK_BASE_PATH => $testBasePath, ServiceLoader::CFGK_REL_TO_DPATH => false),
                ServiceLoader::CFGK_SERV_NS => 'Voragine\\Kernel'
            )
        );

        $this->assertInstanceOf(ServiceLoader::class, $serviceLoader, 'Must be instance of ServiceLoader');

        return $serviceLoader;

    }

    /**
     * @depends testInitializeServiceLoader
     */
    public function testServiceLoaderCanLoadAService($services){

        $mockedService = $services->get('mock_service');
        $this->assertInstanceOf(MockService::class, $mockedService, 'Voragine could not load the service MOCK');
    }



}