<?php

namespace Voragine\Kernel\Services\Base;

//Modello per costringere l'implementazione della lettura da YAML

interface ServiceModelInterface
{
    //Ogni servizio dovrebbe avere un caricatore della configurazione parsata da YAML
    public function loadConfigArray($yamlConfigArray = null);

}